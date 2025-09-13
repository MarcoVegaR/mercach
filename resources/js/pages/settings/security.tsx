import InputError from '@/components/input-error';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogTrigger,
    AlertDialogContent as DangerDialogContent,
    AlertDialogDescription as DangerDialogDescription,
    AlertDialogFooter as DangerDialogFooter,
    AlertDialogHeader as DangerDialogHeader,
    AlertDialogTitle as DangerDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { InputOTP } from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { toast } from '@/lib/toast';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Laptop, MapPin, Smartphone } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Ajustes', href: '/settings' },
    { title: 'Seguridad', href: '/settings/security' },
];

export default function Security() {
    const [open, setOpen] = useState(false);
    const [cooldown, setCooldown] = useState<number | null>(null);
    const countdownRef = useRef<number | null>(null);
    // Password confirmation dialog (Fortify confirmPassword)
    const [confirmPwdOpen, setConfirmPwdOpen] = useState(false);
    const [confirmPwd, setConfirmPwd] = useState('');
    const [confirmPwdErr, setConfirmPwdErr] = useState<string | null>(null);
    const [confirmPending, setConfirmPending] = useState(false);
    const nextAfterConfirmRef = useRef<(() => void) | null>(null);
    const currentActionRef = useRef<string | null>(null);

    const { data, setData, post, processing, reset, errors } = useForm<{ password: string }>({ password: '' });

    // --- Two-Factor (MFA) state derived from server flags ---
    const page = usePage() as unknown as {
        props: {
            twoFactor?: { enabled?: boolean; confirmed?: boolean; confirmed_at?: string };
            can?: { manage2FA?: boolean; logoutOthers?: boolean };
            appName?: string;
        };
    };
    const initialEnabled = Boolean(page.props.twoFactor?.enabled);
    const initialConfirmed = Boolean(page.props.twoFactor?.confirmed);
    const canManage2FA = Boolean(page.props.can?.manage2FA);
    const canLogoutOthers = Boolean(page.props.can?.logoutOthers);

    const [tfEnabled, setTfEnabled] = useState<boolean>(initialEnabled);
    const [tfConfirmed, setTfConfirmed] = useState<boolean>(initialConfirmed);
    const [qrSvg, setQrSvg] = useState<string | null>(null);
    const [codes, setCodes] = useState<string[] | null>(null);
    const codesRef = useRef<HTMLDivElement | null>(null);
    const [verifyingCode, setVerifyingCode] = useState<string>('');
    const [verifyError, setVerifyError] = useState<string | null>(null);

    type SessionRow = { id: string; ip: string | null; agent: string | null; last_activity: number; current: boolean };
    const [sessions, setSessions] = useState<SessionRow[] | null>(null);
    const [sessionsLoading, setSessionsLoading] = useState(false);
    const [geoCache, setGeoCache] = useState<Record<string, string>>(() => {
        try {
            const raw = localStorage.getItem('session_geo_cache_v1');
            return raw ? (JSON.parse(raw) as Record<string, string>) : {};
        } catch {
            return {};
        }
    });

    const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
    const [regenPending, setRegenPending] = useState(false);
    const [openRegen, setOpenRegen] = useState(false);
    const [openDisable, setOpenDisable] = useState(false);

    const appName = String(page.props.appName ?? 'app');
    const confirmedAtStr = page.props.twoFactor?.confirmed_at ?? null;
    const confirmedDate = useMemo(() => (confirmedAtStr ? new Date(confirmedAtStr) : null), [confirmedAtStr]);
    const status = useMemo<'inactive' | 'pending' | 'active'>(() => {
        if (!tfEnabled) return 'inactive';
        return tfConfirmed ? 'active' : 'pending';
    }, [tfEnabled, tfConfirmed]);

    async function postWithCsrf(url: string, data?: Record<string, unknown>) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: data ? JSON.stringify(data) : undefined,
        });
        return res;
    }

    const startCooldown = (seconds: number) => {
        const s = Number.isFinite(seconds) && seconds > 0 ? Math.ceil(seconds) : 60;
        setCooldown(s);
        // clear previous
        if (countdownRef.current) window.clearInterval(countdownRef.current);
        countdownRef.current = window.setInterval(() => {
            setCooldown((prev) => {
                if (prev == null) return null;
                if (prev <= 1) {
                    if (countdownRef.current) window.clearInterval(countdownRef.current);
                    countdownRef.current = null;
                    return null;
                }
                return prev - 1;
            });
        }, 1000) as unknown as number;
    };

    useEffect(() => {
        // Listen for 429 globally for this page, set a countdown when it happens
        const remove = router.on('error', (event) => {
            const ev = event as unknown as { detail?: { response?: Response } };
            const res = ev.detail?.response;
            if (res?.status === 429) {
                const retry = Number(res.headers?.get?.('retry-after') ?? 60);
                startCooldown(retry);
                toast.error(`Demasiados intentos. Intenta de nuevo en ${retry}s`);
                return;
            }
            if (res?.status === 423) {
                // Fortify confirmPassword required
                const key = currentActionRef.current;
                let retry: (() => void) | null = null;
                switch (key) {
                    case 'enable2FA':
                        retry = enable2FA;
                        break;
                    case 'confirm2FA':
                        retry = confirm2FA;
                        break;
                    case 'regenerateCodes':
                        retry = regenerateCodes;
                        break;
                    case 'disable2FA':
                        retry = disable2FA;
                        break;
                    default:
                        retry = null;
                }
                nextAfterConfirmRef.current = retry;
                setConfirmPwdOpen(true);
                toast.info('Por favor confirma tu contraseña para continuar');
                return;
            }
        });
        return () => {
            try {
                remove?.();
            } catch {
                /* no-op */
            }
            if (countdownRef.current) window.clearInterval(countdownRef.current);
        };
        // We intentionally attach a single global error handler for this page lifecycle
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        // Fetch sessions on mount
        void fetchSessions();
        // We only want to fetch once on mount
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function fetchSessions() {
        try {
            setSessionsLoading(true);
            const res = await fetch(route('settings.sessions.index'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error('No se pudieron cargar las sesiones');
            const data = (await res.json()) as { sessions?: SessionRow[] };
            const list = Array.isArray(data.sessions) ? data.sessions : [];
            // Deduplicate same device/browser by ip + user_agent, keep latest
            const norm = (ua: string | null) => (ua ?? '').split(')')[0] + ')';
            const map = new Map<string, SessionRow>();
            for (const s of list) {
                const key = `${s.ip ?? ''}|${norm(s.agent ?? '')}`;
                const prev = map.get(key);
                if (!prev || s.last_activity > prev.last_activity) {
                    map.set(key, { ...s });
                } else if (prev && s.current) {
                    map.set(key, { ...prev, current: true });
                }
            }
            const arr = Array.from(map.values());
            setSessions(arr);
            // Preload geolocations for unique IPs
            const ips = Array.from(new Set(arr.map((x) => x.ip).filter((v): v is string => Boolean(v))));
            void preloadGeo(ips);
        } catch {
            setSessions([]);
        } finally {
            setSessionsLoading(false);
        }
    }

    function isPrivateIp(ip: string) {
        if (ip === '127.0.0.1' || ip === '::1') return true;
        const parts = ip.split('.').map((n) => parseInt(n, 10));
        if (parts.length === 4) {
            if (parts[0] === 10) return true;
            if (parts[0] === 172 && parts[1] >= 16 && parts[1] <= 31) return true;
            if (parts[0] === 192 && parts[1] === 168) return true;
            if (parts[0] === 169 && parts[1] === 254) return true; // link-local
        }
        return false;
    }

    async function resolveGeo(ip: string): Promise<string> {
        if (!ip || isPrivateIp(ip)) return ip === '127.0.0.1' ? 'Localhost' : 'Red privada';
        if (geoCache[ip]) return geoCache[ip];
        try {
            // Try ipapi.co first (supports CORS)
            const r1 = await fetch(`https://ipapi.co/${encodeURIComponent(ip)}/json/`, { mode: 'cors' });
            if (r1.ok) {
                const j = (await r1.json()) as { city?: string; region?: string; region_code?: string; country_name?: string; country?: string };
                const city = j.city ?? '';
                const region = j.region ?? j.region_code ?? '';
                const country = j.country_name ?? j.country ?? '';
                const loc = [city, region].filter(Boolean).join(', ') || country || 'Ubicación desconocida';
                return loc;
            }
        } catch {
            /* ignore network errors */
        }
        try {
            // Fallback: ip-api.com
            const r2 = await fetch(`https://ip-api.com/json/${encodeURIComponent(ip)}?fields=status,country,regionName,city`, { mode: 'cors' });
            if (r2.ok) {
                const j = (await r2.json()) as { status?: string; country?: string; regionName?: string; city?: string };
                if (j.status === 'success') {
                    const loc = [j.city, j.regionName].filter(Boolean).join(', ') || j.country || 'Ubicación desconocida';
                    return loc;
                }
            }
        } catch {
            /* ignore network errors */
        }
        return 'Ubicación desconocida';
    }

    async function preloadGeo(ips: string[]) {
        const updates: Record<string, string> = {};
        await Promise.all(
            ips.map(async (ip) => {
                if (geoCache[ip]) return;
                const loc = await resolveGeo(ip);
                updates[ip] = loc;
            }),
        );
        if (Object.keys(updates).length > 0) {
            setGeoCache((prev) => {
                const merged = { ...prev, ...updates };
                try {
                    localStorage.setItem('session_geo_cache_v1', JSON.stringify(merged));
                } catch {
                    /* ignore storage errors */
                }
                return merged;
            });
        }
    }

    function deviceFromUA(ua?: string | null): { icon: 'mobile' | 'desktop'; label: string } {
        const s = (ua || '').toLowerCase();
        if (!s) return { icon: 'desktop', label: 'Dispositivo desconocido' };
        const isMobile = /mobile|android|iphone|ipad|ipod/.test(s);
        let label = 'Desktop';
        if (s.includes('android')) label = 'Android';
        else if (s.includes('iphone') || s.includes('ipad') || s.includes('ios')) label = 'iOS';
        else if (s.includes('mac os') || s.includes('macintosh')) label = 'macOS';
        else if (s.includes('windows')) label = 'Windows';
        else if (s.includes('linux')) label = 'Linux';
        return { icon: isMobile ? 'mobile' : 'desktop', label };
    }

    async function closeSession(id: string) {
        try {
            const res = await fetch(route('settings.sessions.destroy', { id }), {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            if (res.status === 204) {
                await fetchSessions();
                toast.success('Sesión cerrada');
                return;
            }
            if (res.status === 422) {
                const body = await res.json().catch(() => ({}));
                toast.error(body?.message ?? 'No puedes cerrar la sesión actual');
                return;
            }
            const body = await res.json().catch(() => ({}));
            toast.error(body?.message ?? 'No se pudo cerrar la sesión');
        } catch {
            toast.error('No se pudo cerrar la sesión');
        }
    }

    // --- Two-Factor (MFA) helpers ---
    async function fetchQrCode() {
        try {
            const res = await fetch('/user/two-factor-qr-code', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                credentials: 'same-origin',
            });
            if (res.status === 429) {
                const retry = Number(res.headers.get('retry-after') ?? 60);
                startCooldown(retry);
                toast.error(`Demasiados intentos. Intenta de nuevo en ${retry}s`);
                return;
            }
            if (res.status === 423) {
                // Need recent password confirmation
                nextAfterConfirmRef.current = fetchQrCode;
                setConfirmPwdOpen(true);
                toast.info('Confirma tu contraseña para ver el código QR');
                return;
            }
            if (!res.ok) throw new Error('No se pudo obtener el código QR');
            const data = (await res.json()) as { svg?: string };
            setQrSvg(data.svg ?? null);
        } catch {
            toast.error('No se pudo obtener el QR de 2FA');
        }
    }

    async function fetchRecoveryCodes(): Promise<string[] | null> {
        try {
            const res = await fetch('/user/two-factor-recovery-codes', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                credentials: 'same-origin',
            });
            if (res.status === 429) {
                const retry = Number(res.headers.get('retry-after') ?? 60);
                startCooldown(retry);
                toast.error(`Demasiados intentos. Intenta de nuevo en ${retry}s`);
                return null;
            }
            if (res.status === 423) {
                nextAfterConfirmRef.current = fetchRecoveryCodes;
                setConfirmPwdOpen(true);
                toast.info('Confirma tu contraseña para ver los códigos de recuperación');
                return null;
            }
            if (!res.ok) throw new Error('No se pudieron obtener los códigos');
            let list: string[] = [];
            const ct = res.headers.get('content-type') ?? '';
            if (ct.includes('application/json')) {
                const data = (await res.json()) as { recovery_codes?: string[]; recoveryCodes?: string[] } | string[];
                if (Array.isArray(data)) {
                    list = data;
                } else if (Array.isArray((data as { recovery_codes?: string[] }).recovery_codes)) {
                    list = (data as { recovery_codes?: string[] }).recovery_codes as string[];
                } else {
                    const obj = data as { recoveryCodes?: string[] };
                    if (Array.isArray(obj.recoveryCodes)) list = obj.recoveryCodes;
                }
            } else {
                // Try to parse text as JSON array just in case
                const txt = await res.text();
                try {
                    const parsed = JSON.parse(txt);
                    if (Array.isArray(parsed)) list = parsed;
                } catch {
                    /* ignore parse errors */
                }
            }
            setCodes(list);
            if (list.length > 0) {
                toast.success('Códigos de recuperación cargados');
                // scroll to codes
                setTimeout(() => codesRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 0);
            }
            return list;
        } catch {
            toast.error('No se pudieron obtener los códigos de recuperación');
            return null;
        }
    }

    async function ensureRecoveryCodes() {
        const list = await fetchRecoveryCodes();
        if (Array.isArray(list) && list.length === 0) {
            // Generate and refetch
            currentActionRef.current = 'regenerateCodes';
            const res = await postWithCsrf('/user/two-factor-recovery-codes');
            if (res.ok) {
                const after = await fetchRecoveryCodes();
                toast.success('Códigos de recuperación generados');
                if (Array.isArray(after) && after.length === 0) {
                    toast.message?.('Tus códigos fueron generados, pulsa "Ver códigos de recuperación" para mostrarlos.');
                }
            } else if (res.status === 423) {
                nextAfterConfirmRef.current = ensureRecoveryCodes;
                setConfirmPwdOpen(true);
            } else {
                toast.error('No se pudieron generar los códigos');
            }
        }
    }

    function enable2FA() {
        if (!canManage2FA) return;
        currentActionRef.current = 'enable2FA';
        router.post(
            '/user/two-factor-authentication',
            {},
            {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                preserveScroll: true,
                onSuccess: async () => {
                    setTfEnabled(true);
                    setTfConfirmed(false);
                    await fetchQrCode();
                    await ensureRecoveryCodes();
                    toast.success('Autenticación de dos factores habilitada');
                },
            },
        );
    }

    function confirm2FA() {
        if (!canManage2FA || !verifyingCode) return;
        currentActionRef.current = 'confirm2FA';
        setVerifyError(null);
        router.post(
            '/user/confirmed-two-factor-authentication',
            { code: verifyingCode },
            {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                preserveScroll: true,
                onSuccess: () => {
                    setTfConfirmed(true);
                    toast.success('2FA confirmada correctamente');
                },
                onError: () => {
                    setVerifyError('El código de verificación TOTP es inválido.');
                },
            },
        );
    }

    function regenerateCodes() {
        if (!canManage2FA) return;
        currentActionRef.current = 'regenerateCodes';
        // Ocultamos los anteriores antes de regenerar
        if (codes && codes.length > 0) {
            setCodes([]);
            toast.info('Los códigos anteriores quedaron invalidados.');
        }
        setRegenPending(true);
        postWithCsrf('/user/two-factor-recovery-codes')
            .then(async (res) => {
                if (res.ok) {
                    await fetchRecoveryCodes();
                    toast.success('Códigos de recuperación regenerados');
                } else if (res.status === 423) {
                    nextAfterConfirmRef.current = regenerateCodes;
                    setConfirmPwdOpen(true);
                } else {
                    toast.error('No se pudieron regenerar los códigos');
                }
            })
            .catch(() => toast.error('No se pudieron regenerar los códigos'))
            .finally(() => setRegenPending(false));
    }

    function copyCodes() {
        if (!codes || codes.length === 0) return;
        const text = codes.join('\n');
        navigator.clipboard
            .writeText(text)
            .then(() => toast.success('Códigos copiados al portapapeles'))
            .catch(() => toast.error('No se pudieron copiar los códigos'));
    }

    function downloadCodes() {
        if (!codes || codes.length === 0) return;
        const text = codes.join('\n');
        const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const slug = appName
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
        const d = new Date();
        const ymd = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        a.download = `${slug}-recovery-codes-${ymd}.txt`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    function performDisable2FA() {
        currentActionRef.current = 'disable2FA';
        router.delete('/user/two-factor-authentication', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            preserveScroll: true,
            onSuccess: () => {
                setTfEnabled(false);
                setTfConfirmed(false);
                setQrSvg(null);
                setCodes(null);
                setVerifyingCode('');
                toast.success('Autenticación de dos factores deshabilitada');
            },
        });
    }

    function disable2FA() {
        if (!canManage2FA) return;
        // Proactively ask for password to avoid redirects and ensure smooth UX
        nextAfterConfirmRef.current = performDisable2FA;
        setConfirmPwdOpen(true);
    }

    // Confirm password and retry pending action
    function submitConfirmPassword() {
        setConfirmPending(true);
        setConfirmPwdErr(null);
        router.post(
            '/confirm-password',
            { password: confirmPwd },
            {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                preserveScroll: true,
                onSuccess: () => {
                    const fn = nextAfterConfirmRef.current;
                    nextAfterConfirmRef.current = null;
                    setConfirmPwd('');
                    setConfirmPwdOpen(false);
                    if (fn) {
                        // Retry the original action
                        setTimeout(fn, 0);
                    }
                },
                onError: () => {
                    setConfirmPwdErr('Contraseña incorrecta');
                },
                onFinish: () => setConfirmPending(false),
            },
        );
    }

    const onSubmit = () => {
        post(route('settings.sessions.logout-others'), {
            preserveScroll: true,
            onSuccess: () => {
                reset('password');
                setOpen(false);
                toast.success('Sesiones cerradas en otros dispositivos');
                void fetchSessions();
            },
            onError: () => {
                // Validation errors handled by errors object (e.g., current_password rule)
            },
            onFinish: () => {
                // no-op
            },
        });
    };

    // primary label not needed anymore (we show cooldown inline)

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ajustes de seguridad" />
            <SettingsLayout>
                {/* Confirm password dialog (global for 2FA actions) */}
                <Dialog open={confirmPwdOpen} onOpenChange={(v) => setConfirmPwdOpen(v)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Confirma tu contraseña</DialogTitle>
                            <DialogDescription>Por seguridad, confirma tu contraseña para continuar.</DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="confirm-password">Contraseña</Label>
                            <Input
                                id="confirm-password"
                                type="password"
                                autoComplete="current-password"
                                value={confirmPwd}
                                onChange={(e) => setConfirmPwd(e.target.value)}
                            />
                            {confirmPwdErr ? <p className="text-destructive text-xs">{confirmPwdErr}</p> : null}
                        </div>
                        <DialogFooter>
                            <Button variant="secondary" onClick={() => setConfirmPwdOpen(false)} disabled={confirmPending}>
                                Cancelar
                            </Button>
                            <Button onClick={submitConfirmPassword} disabled={!confirmPwd} isLoading={confirmPending} loadingText="Confirmando…">
                                Confirmar
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* MFA (TOTP) */}
                <Card className="rounded-xl">
                    <CardHeader className="flex flex-row items-start justify-between gap-2">
                        <div className="space-y-1">
                            <CardTitle>Autenticación en dos pasos (MFA)</CardTitle>
                            <CardDescription>Añade una capa extra de seguridad con códigos temporales (TOTP). Recomendado por OWASP.</CardDescription>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                            {status === 'active' && <Badge variant="success">2FA activa</Badge>}
                            {status === 'pending' && <Badge variant="warning">Pendiente de confirmación</Badge>}
                            {status === 'inactive' && <Badge variant="outline">Inactiva</Badge>}
                            {status === 'active' && confirmedDate && (
                                <span className="text-muted-foreground text-xs">Confirmada el {confirmedDate.toLocaleString()}</span>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {!canManage2FA ? (
                            <p className="text-muted-foreground text-sm">No tienes permisos para gestionar 2FA.</p>
                        ) : (
                            <div className="space-y-4">
                                {!tfEnabled ? (
                                    <Button type="button" onClick={enable2FA}>
                                        Habilitar 2FA
                                    </Button>
                                ) : (
                                    <div className="space-y-4">
                                        {!tfConfirmed && (
                                            <div className="grid gap-3">
                                                <div>
                                                    <p className="text-muted-foreground mb-2 text-sm">
                                                        Escanea el QR con tu app TOTP (Google Authenticator, Authy, etc.) y confirma el código.
                                                    </p>
                                                    <div className="bg-background rounded border p-3">
                                                        {qrSvg ? (
                                                            <div dangerouslySetInnerHTML={{ __html: qrSvg }} />
                                                        ) : (
                                                            <Button type="button" variant="outline" onClick={fetchQrCode}>
                                                                Mostrar código QR
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="totp-code">Código de verificación</Label>
                                                    <p className="text-muted-foreground text-xs">
                                                        Es el código de 6 dígitos de tu app TOTP (Google Authenticator, Authy, etc.). Cambia cada 30s.
                                                    </p>
                                                    <InputOTP
                                                        length={6}
                                                        value={verifyingCode}
                                                        onChange={(v) => setVerifyingCode(v.replace(/\D/g, '').slice(0, 6))}
                                                    />
                                                    <InputError message={verifyError ?? undefined} />
                                                    <div className="flex gap-2">
                                                        <Button type="button" onClick={confirm2FA} disabled={verifyingCode.length !== 6}>
                                                            Confirmar 2FA
                                                        </Button>
                                                        <Button type="button" variant="ghost" onClick={ensureRecoveryCodes}>
                                                            Ver códigos de recuperación
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {tfEnabled && (
                                            <div className="space-y-3">
                                                {codes && codes.length > 0 && (
                                                    <div ref={codesRef} className="rounded border p-3">
                                                        <p className="mb-2 text-sm font-medium">Códigos de recuperación</p>
                                                        <ul className="grid gap-1 font-mono text-sm">
                                                            {codes.map((c) => (
                                                                <li key={c}>{c}</li>
                                                            ))}
                                                        </ul>
                                                        <div className="mt-3 flex flex-wrap items-center gap-2">
                                                            <Button type="button" size="sm" variant="outline" onClick={copyCodes}>
                                                                Copiar
                                                            </Button>
                                                            <Button type="button" size="sm" variant="outline" onClick={downloadCodes}>
                                                                Descargar
                                                            </Button>
                                                            <AlertDialog open={openRegen} onOpenChange={setOpenRegen}>
                                                                <AlertDialogTrigger asChild>
                                                                    <Button type="button" size="sm" variant="destructive" disabled={regenPending}>
                                                                        {regenPending ? 'Regenerando…' : 'Regenerar códigos'}
                                                                    </Button>
                                                                </AlertDialogTrigger>
                                                                <DangerDialogContent>
                                                                    <DangerDialogHeader>
                                                                        <DangerDialogTitle>Regenerar códigos de recuperación</DangerDialogTitle>
                                                                        <DangerDialogDescription>
                                                                            Esto invalidará los códigos actuales inmediatamente. ¿Deseas continuar?
                                                                        </DangerDialogDescription>
                                                                    </DangerDialogHeader>
                                                                    <DangerDialogFooter>
                                                                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                                                        <AlertDialogAction
                                                                            onClick={regenerateCodes}
                                                                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                                        >
                                                                            Regenerar
                                                                        </AlertDialogAction>
                                                                    </DangerDialogFooter>
                                                                </DangerDialogContent>
                                                            </AlertDialog>
                                                        </div>
                                                        <p className="text-muted-foreground mt-2 text-xs">
                                                            Al regenerar, los códigos anteriores quedan invalidados.
                                                        </p>
                                                    </div>
                                                )}
                                                {codes && codes.length === 0 && (
                                                    <div className="flex items-center justify-between gap-2">
                                                        <p className="text-muted-foreground text-sm">Aún no hay códigos de recuperación generados.</p>
                                                        <Button type="button" variant="outline" onClick={regenerateCodes}>
                                                            Generar códigos
                                                        </Button>
                                                    </div>
                                                )}
                                                <div className="flex flex-wrap gap-2">
                                                    {tfConfirmed && (
                                                        <AlertDialog open={openDisable} onOpenChange={setOpenDisable}>
                                                            <AlertDialogTrigger asChild>
                                                                <Button type="button" variant="destructive">
                                                                    Deshabilitar 2FA
                                                                </Button>
                                                            </AlertDialogTrigger>
                                                            <DangerDialogContent>
                                                                <DangerDialogHeader>
                                                                    <DangerDialogTitle>Deshabilitar autenticación en dos pasos</DangerDialogTitle>
                                                                    <DangerDialogDescription>
                                                                        Esta acción reduce la seguridad de tu cuenta. Se te pedirá confirmar tu
                                                                        contraseña.
                                                                    </DangerDialogDescription>
                                                                </DangerDialogHeader>
                                                                <DangerDialogFooter>
                                                                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                                                    <AlertDialogAction
                                                                        onClick={disable2FA}
                                                                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                                    >
                                                                        Continuar
                                                                    </AlertDialogAction>
                                                                </DangerDialogFooter>
                                                            </DangerDialogContent>
                                                        </AlertDialog>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="rounded-xl">
                    <CardHeader>
                        <CardTitle>Sesiones</CardTitle>
                        <CardDescription>Cierra tu sesión en todos los demás dispositivos, manteniendo la actual.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-3">
                            {/* Sessions list */}
                            <div className="rounded border p-3">
                                <div className="mb-2 flex items-center justify-between">
                                    <p className="text-sm font-medium">Sesiones activas</p>
                                    <div className="flex gap-2">
                                        <Button type="button" size="sm" variant="outline" onClick={() => fetchSessions()} disabled={sessionsLoading}>
                                            Refrescar
                                        </Button>
                                        <AlertDialog>
                                            <AlertDialogTrigger asChild>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="secondary"
                                                    disabled={!canLogoutOthers || processing || (cooldown ?? 0) > 0}
                                                >
                                                    Cerrar todas (excepto esta)
                                                </Button>
                                            </AlertDialogTrigger>
                                            <DangerDialogContent>
                                                <DangerDialogHeader>
                                                    <DangerDialogTitle>Cerrar sesiones en otros dispositivos</DangerDialogTitle>
                                                    <DangerDialogDescription>
                                                        Se cerrarán todas tus sesiones excepto la actual. Se te pedirá tu contraseña a continuación.
                                                    </DangerDialogDescription>
                                                </DangerDialogHeader>
                                                <DangerDialogFooter>
                                                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                                    <AlertDialogAction onClick={() => setOpen(true)}>Continuar</AlertDialogAction>
                                                </DangerDialogFooter>
                                            </DangerDialogContent>
                                        </AlertDialog>
                                    </div>
                                </div>
                                {sessionsLoading ? (
                                    <p className="text-muted-foreground text-sm">Cargando…</p>
                                ) : sessions && sessions.length > 0 ? (
                                    <ul className="space-y-2">
                                        {sessions.map((s) => (
                                            <li key={s.id} className="flex items-center justify-between gap-2 text-sm">
                                                {(() => {
                                                    const dev = deviceFromUA(s.agent);
                                                    const ip = s.ip ?? '';
                                                    const loc = ip ? geoCache[ip] : undefined;
                                                    return (
                                                        <div className="flex min-w-0 items-start gap-3">
                                                            <div className="text-muted-foreground mt-0.5">
                                                                {dev.icon === 'mobile' ? (
                                                                    <Smartphone className="h-4 w-4" />
                                                                ) : (
                                                                    <Laptop className="h-4 w-4" />
                                                                )}
                                                            </div>
                                                            <div className="min-w-0">
                                                                <p className="truncate">
                                                                    {s.current ? (
                                                                        <span className="mr-1 rounded bg-green-100 px-1 text-green-700">Actual</span>
                                                                    ) : null}
                                                                    {dev.label} · {ip || 'IP desconocida'}
                                                                </p>
                                                                <p className="text-muted-foreground flex items-center gap-1 truncate text-xs">
                                                                    <MapPin className="h-3.5 w-3.5" />
                                                                    <span className="truncate">{loc ?? 'Ubicación...'}</span>
                                                                </p>
                                                                <p className="text-muted-foreground text-xs">
                                                                    Última actividad: {new Date(s.last_activity * 1000).toLocaleString()}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    );
                                                })()}
                                                {!s.current && (
                                                    <div className="shrink-0">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => closeSession(s.id)}
                                                            title={'Cerrar esta sesión'}
                                                        >
                                                            Cerrar
                                                        </Button>
                                                    </div>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-muted-foreground text-sm">No hay sesiones activas.</p>
                                )}
                            </div>
                            <Dialog open={open} onOpenChange={(v) => setOpen(v)}>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Confirmar cierre de sesiones</DialogTitle>
                                        <DialogDescription>Ingresa tu contraseña actual para cerrar sesión en otros dispositivos.</DialogDescription>
                                    </DialogHeader>

                                    <div className="grid gap-2">
                                        <Label htmlFor="logout-others-password">Contraseña actual</Label>
                                        <Input
                                            id="logout-others-password"
                                            type="password"
                                            autoComplete="current-password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder="Tu contraseña"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <DialogFooter>
                                        <Button variant="secondary" type="button" onClick={() => setOpen(false)} disabled={processing}>
                                            Cancelar
                                        </Button>
                                        <Button
                                            onClick={onSubmit}
                                            disabled={processing || !data.password}
                                            isLoading={processing}
                                            loadingText="Confirmando…"
                                        >
                                            Confirmar
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardContent>
                </Card>
            </SettingsLayout>
        </AppLayout>
    );
}
