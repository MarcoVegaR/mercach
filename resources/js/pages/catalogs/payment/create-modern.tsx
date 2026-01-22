import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    Banknote,
    Building2,
    Calendar,
    Check,
    CreditCard,
    Hash,
    Info,
    Landmark,
    Phone,
    Smartphone,
    User,
} from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

interface Bank {
    id: number;
    name: string;
}
interface CompanyAccount {
    id: number;
    label: string;
    supportsPMOV?: boolean;
    allow_transfer?: boolean;
    allow_pmov?: boolean;
    allow_debit?: boolean;
}
interface Concessionaire {
    id: number;
    name: string;
    document_number?: string;
    document_type_code?: string;
}
interface LocalOpt {
    id: number;
    label: string;
}
interface PhoneAreaCode {
    id: number;
    code: string;
}

type Props = {
    options: {
        companyBankAccounts: CompanyAccount[];
        banks: Bank[];
        phoneAreaCodes: PhoneAreaCode[];
        concessionaires: Concessionaire[];
        locals: LocalOpt[];
        paymentTypes: Array<{ value: string; label: string }>;
    };
    mode?: 'create' | 'edit';
};

export default function PaymentCreateModern({ options }: Props) {
    const page = usePage<{ flash?: { success?: string; error?: string; warning?: string; info?: string } }>();
    const flash = (page.props as any)?.flash ?? {};

    const breadcrumbs = [
        { title: 'Pagos', href: '/payments' },
        { title: 'Registrar', href: '' },
    ];

    const [step, setStep] = React.useState<0 | 1 | 2>(0);
    const [method, setMethod] = React.useState<'TRANSFER' | 'PMOV' | 'DEB' | 'EXO' | null>(null);
    const verifyToastRef = React.useRef<string | number | null>(null);
    const [fxRateUsd, setFxRateUsd] = React.useState<string | null>(null);
    const [fxRateEur, setFxRateEur] = React.useState<string | null>(null);
    const [confirmOpen, setConfirmOpen] = React.useState(false);

    const queryPrefill = React.useMemo(() => {
        if (typeof window === 'undefined') return null;
        const qs = new URLSearchParams(window.location.search);
        const debtorType = (qs.get('debtor_type') || '').toUpperCase();
        const debtorId = Number(qs.get('debtor_id') || '');
        const localId = Number(qs.get('local_id') || '');
        const amountMinorRaw = qs.get('amount_bs_minor');
        const amountMinor = amountMinorRaw !== null ? Number(amountMinorRaw) : null;
        const chargeIdsRaw = (qs.get('charge_ids') || '').trim();
        const methodRaw = (qs.get('method') || '').toUpperCase();
        const paidOnRaw = (qs.get('paid_on') || qs.get('at') || '').trim();

        const methodNormalized = methodRaw === 'TRANSFER' || methodRaw === 'PMOV' || methodRaw === 'DEB' || methodRaw === 'EXO' ? methodRaw : null;
        const debtorTypeNormalized = debtorType === 'LOCAL' || debtorType === 'CONCESSIONAIRE' ? debtorType : null;
        const paidOnNormalized = /^\d{4}-\d{2}-\d{2}$/.test(paidOnRaw) ? paidOnRaw : null;

        return {
            debtor_type: debtorTypeNormalized,
            debtor_id: Number.isFinite(debtorId) && debtorId > 0 ? debtorId : null,
            local_id: Number.isFinite(localId) && localId > 0 ? localId : null,
            amount_bs_minor: amountMinor !== null && Number.isFinite(amountMinor) && amountMinor >= 0 ? Math.floor(amountMinor) : null,
            charge_ids: chargeIdsRaw !== '' ? chargeIdsRaw : null,
            method: methodNormalized as 'TRANSFER' | 'PMOV' | 'DEB' | 'EXO' | null,
            paid_on: paidOnNormalized,
        };
    }, []);

    const todayCaracas = React.useMemo(
        () =>
            new Intl.DateTimeFormat('en-CA', {
                timeZone: 'America/Caracas',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date()),
        [],
    );

    const bankOptions = React.useMemo(() => (options?.banks ?? []).map((b) => ({ value: String(b.id), label: b.name })), [options?.banks]);
    const areaOptions = React.useMemo(
        () => (options?.phoneAreaCodes ?? []).map((a) => ({ value: a.code, label: a.code })),
        [options?.phoneAreaCodes],
    );

    const { data, setData, processing, post, errors } = useForm({
        // Debtor
        debtor_type: 'CONCESSIONAIRE' as 'CONCESSIONAIRE' | 'LOCAL',
        debtor_id: '' as any,
        local_id: '' as any,
        // Handoff from Economic Profile (optional)
        charge_ids: '' as any,
        // Payment core
        company_bank_account_id: '' as any,
        method: '' as any,
        origin_bank_id: '' as any,
        payer_document_type: '' as any,
        payer_document_number: '' as any,
        payer_account_number: '' as any,
        payer_phone_area_code: '' as any,
        payer_phone_number: '' as any,
        payer_phone_e164: '' as any,
        reference: '' as any,
        amount_bs_minor: 0,
        paid_on: todayCaracas,
        fx_rate_id: '' as any,
        exoneration_reason: '' as any,
    });

    const filteredCompanyAccounts = React.useMemo(() => {
        const list = options?.companyBankAccounts ?? [];
        if (!method) return list;
        return list.filter((acc) => {
            const allowTransfer = acc.allow_transfer ?? true;
            const allowPMov = acc.allow_pmov ?? true;
            const allowDebit = acc.allow_debit ?? true;
            if (method === 'TRANSFER') return allowTransfer;
            if (method === 'PMOV') return allowPMov && acc.supportsPMOV !== false;
            if (method === 'DEB') return allowDebit;
            return true;
        });
    }, [options?.companyBankAccounts, method]);

    // Auto-select company bank account based on method / allowed list
    React.useEffect(() => {
        if (!method) return;
        const allowed = filteredCompanyAccounts;
        const current = data.company_bank_account_id ? String(data.company_bank_account_id) : '';
        const stillAllowed = allowed.some((acc) => String(acc.id) === current);
        if (stillAllowed) return;
        const first = allowed.length === 1 ? String(allowed[0].id) : '';
        setData('company_bank_account_id', first);
    }, [method, filteredCompanyAccounts, data.company_bank_account_id, setData]);

    // Amount handling (bank-style)
    const [amountMajor, setAmountMajor] = React.useState<string>('0.00');
    const handleAmountChange = (raw: string) => {
        const digits = String(raw).replace(/\D+/g, '');
        const intVal = digits === '' ? 0 : Number(digits);
        const major = (intVal / 100).toFixed(2);
        setAmountMajor(major);
        setData('amount_bs_minor', intVal);
    };

    // Helpers: choose debtor
    const onSelectConcessionaire = React.useCallback(
        (id: number) => {
            setData('debtor_type', 'CONCESSIONAIRE');
            setData('debtor_id', id);
            setData('local_id', '');
            const c = (options?.concessionaires ?? []).find((x) => x.id === id);
            if (c) {
                setData('payer_document_type', c.document_type_code || '');
                setData('payer_document_number', c.document_number || '');
            }
        },
        [options?.concessionaires, setData],
    );
    const onSelectLocal = React.useCallback(
        (id: number) => {
            setData('debtor_type', 'LOCAL');
            setData('debtor_id', id);
            setData('local_id', id);
        },
        [setData],
    );

    // Prefill from query params (Economic Profile handoff)
    React.useEffect(() => {
        if (!queryPrefill) return;

        // 1) Debtor
        if (queryPrefill.debtor_type === 'LOCAL' && queryPrefill.debtor_id) {
            onSelectLocal(queryPrefill.debtor_id);
        }
        if (queryPrefill.debtor_type === 'CONCESSIONAIRE' && queryPrefill.debtor_id) {
            onSelectConcessionaire(queryPrefill.debtor_id);
        }

        // 1.1) Optional local scope (keep debtor as concessionaire, but restrict charges/apply to a local)
        if (queryPrefill.local_id && queryPrefill.local_id > 0) {
            setData('local_id', queryPrefill.local_id);
        }

        // 2) Amount
        if (typeof queryPrefill.amount_bs_minor === 'number') {
            const minor = Math.max(0, queryPrefill.amount_bs_minor);
            setData('amount_bs_minor', minor);
            setAmountMajor((minor / 100).toFixed(2));
        }

        // 2.1) Selected charges handoff
        if (queryPrefill.charge_ids) {
            setData('charge_ids', queryPrefill.charge_ids);
        }

        // 2.2) Paid on handoff (default date)
        if (queryPrefill.paid_on) {
            const safePaidOn = queryPrefill.paid_on > todayCaracas ? todayCaracas : queryPrefill.paid_on;
            setData('paid_on', safePaidOn);
        }

        // 3) Method & step
        if (queryPrefill.method) {
            setMethod(queryPrefill.method);
            setData('method', queryPrefill.method as any);
            setStep(2);
            return;
        }

        // If debtor is prefilled but not method, jump to method selection
        if (queryPrefill.debtor_type && queryPrefill.debtor_id) {
            setStep(1);
        }
    }, [queryPrefill, onSelectConcessionaire, onSelectLocal, setData, todayCaracas]);

    // Submit
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const m = String(data.method || '').toUpperCase();
        const refDigits = String(data.reference ?? '').replace(/\D+/g, '');
        if (m !== 'EXO' && !data.company_bank_account_id) {
            toast.error('Selecciona la cuenta receptora.');
            return;
        }
        if (m !== 'EXO' && (refDigits.length < 6 || refDigits.length > 8)) {
            toast.error('La referencia debe tener entre 6 y 8 dígitos.');
            return;
        }

        setConfirmOpen(true);
    };

    const confirmSubmit = () => {
        const m = String(data.method || '').toUpperCase();

        post(route('payments.store'), {
            onStart: () => {
                if (m !== 'DEB' && m !== 'EXO') verifyToastRef.current = toast.loading('Verificando en banco…');
            },
            onFinish: () => {
                if (verifyToastRef.current != null) {
                    toast.dismiss(verifyToastRef.current as any);
                    verifyToastRef.current = null;
                }
            },
            onError: () => {
                toast.error('Corrige los errores del formulario.');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        });
    };

    const debtorLabel = React.useMemo(() => {
        if (data.debtor_type === 'LOCAL') {
            const id = Number(data.local_id || data.debtor_id || 0);
            const l = (options?.locals ?? []).find((x) => x.id === id);
            return l?.label ?? (id ? `Local ${id}` : '—');
        }
        const cid = Number(data.debtor_id || 0);
        const c = (options?.concessionaires ?? []).find((x) => x.id === cid);
        return c?.name ?? (cid ? `Concesionario ${cid}` : '—');
    }, [data.debtor_type, data.debtor_id, data.local_id, options?.locals, options?.concessionaires]);

    const companyAccLabel = React.useMemo(() => {
        const id = Number(data.company_bank_account_id || 0);
        const acc = (options?.companyBankAccounts ?? []).find((x) => x.id === id);
        return acc?.label ?? (id ? `Cuenta ${id}` : '—');
    }, [data.company_bank_account_id, options?.companyBankAccounts]);

    const originBankLabel = React.useMemo(() => {
        const id = Number(data.origin_bank_id || 0);
        const b = (options?.banks ?? []).find((x) => x.id === id);
        return b?.name ?? (id ? `Banco ${id}` : '—');
    }, [data.origin_bank_id, options?.banks]);

    // Flash toasts
    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error('Validación fallida', { description: flash.error });
        if (flash?.warning) toast.warning?.(flash.warning);
        if (flash?.info) toast.message?.(flash.info);
    }, [flash?.success, flash?.error, flash?.warning, flash?.info]);

    // Friendly error mapping
    const parsedError = React.useMemo(() => {
        const raw: string | undefined = flash?.error;
        if (!raw) return null;
        const m = raw.match(/C[oó]digo?\s(\d{3,4}).*?–\s([^.]*)/i);
        const code = m?.[1];
        const desc = m?.[2]?.trim();
        if (!code) return { title: 'No se pudo registrar el pago', message: raw };
        const map: Record<string, string> = {
            '706': 'El número de cuenta no corresponde con el banco seleccionado. Verifica los 20 dígitos y el banco.',
            '707': 'La referencia ya fue utilizada. Ingresa una referencia diferente.',
            '708': 'La cuenta destino no es válida. Revisa la cuenta de la empresa seleccionada.',
            '709': 'No encontramos la referencia en tu banco. Verifica número y fecha del pago.',
            '710': 'El monto no coincide con el registrado en tu banco. Verifica el monto y vuelve a intentar.',
        };
        const friendly = map[code] ?? (desc ? desc : raw);
        return { title: 'No se pudo registrar el pago', message: friendly, details: `Código ${code}${desc ? ` – ${desc}` : ''}` };
    }, [flash?.error]);

    // FX resolve on date change - fetch both USD and EUR rates
    React.useEffect(() => {
        const paid = (data.paid_on || '').trim();
        if (!paid) return;
        // Fetch USD rate
        (async () => {
            try {
                const qs = new URLSearchParams({ currency: 'USD', paid_on: paid });
                const res = await fetch(`/payments/resolve-fx?${qs.toString()}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const json = await res.json();
                if (typeof json.fx_rate_id !== 'undefined') setData('fx_rate_id', json.fx_rate_id ?? '');
                const rate = typeof json.rate_to_ves !== 'undefined' && json.rate_to_ves !== null ? Number(json.rate_to_ves) : null;
                setFxRateUsd(rate !== null && !isNaN(rate) ? rate.toFixed(2) : null);
            } catch (error) {
                console.error(error);
            }
        })();
        // Fetch EUR rate
        (async () => {
            try {
                const qs = new URLSearchParams({ currency: 'EUR', paid_on: paid });
                const res = await fetch(`/payments/resolve-fx?${qs.toString()}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const json = await res.json();
                const rate = typeof json.rate_to_ves !== 'undefined' && json.rate_to_ves !== null ? Number(json.rate_to_ves) : null;
                setFxRateEur(rate !== null && !isNaN(rate) ? rate.toFixed(2) : null);
            } catch (error) {
                console.error(error);
            }
        })();
    }, [data.paid_on, setData]);

    // Derived
    const amountBs = Number(data.amount_bs_minor ?? 0) / 100;
    const equivUsd = fxRateUsd ? amountBs / Number(fxRateUsd) : null;
    const equivEur = fxRateEur ? amountBs / Number(fxRateEur) : null;
    const isTRF = String(method || '').toUpperCase() === 'TRANSFER';
    const isPMOV = String(method || '').toUpperCase() === 'PMOV';
    const isDEB = String(method || '').toUpperCase() === 'DEB';
    const isEXO = String(method || '').toUpperCase() === 'EXO';

    const methodLabel = React.useMemo(() => {
        const m = String(data.method || '').toUpperCase();
        if (m === 'TRANSFER') return 'Transferencia';
        if (m === 'PMOV') return 'Pago móvil';
        if (m === 'DEB') return 'Débito';
        if (m === 'EXO') return 'Exoneración';
        return m || '—';
    }, [data.method]);

    const payerDocLabel = React.useMemo(() => {
        const t = String(data.payer_document_type || '').trim();
        const n = String(data.payer_document_number || '').trim();
        if (!t && !n) return '—';
        return t && n ? `${t}-${n}` : `${t}${n}`;
    }, [data.payer_document_type, data.payer_document_number]);

    const payerPhoneLabel = React.useMemo(() => {
        const e164 = String(data.payer_phone_e164 || '').trim();
        if (e164) return e164;
        const ac = String(data.payer_phone_area_code || '').trim();
        const pn = String(data.payer_phone_number || '').trim();
        if (ac && pn) return `${ac}${pn}`;
        return '—';
    }, [data.payer_phone_e164, data.payer_phone_area_code, data.payer_phone_number]);

    const payerAccountLabel = React.useMemo(() => {
        const acct = String(data.payer_account_number || '').trim();
        return acct ? acct : '—';
    }, [data.payer_account_number]);

    const referenceLabel = React.useMemo(() => {
        const ref = String(data.reference || '').trim();
        return ref ? ref : '—';
    }, [data.reference]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar pago" />

            <AlertDialog open={confirmOpen} onOpenChange={(o) => !processing && setConfirmOpen(o)}>
                <AlertDialogContent className="sm:max-w-[580px]">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <Info className="h-5 w-5 text-blue-600" />
                            Confirmar registro de pago
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Confirma que los datos estén correctos. La <span className="font-medium">fecha de pago</span> afecta la tasa cambiaria y
                            los saldos aplicables.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    <div className="space-y-4">
                        <div className="rounded-lg border bg-gradient-to-br from-slate-50 to-white p-4 dark:from-slate-950/40 dark:to-slate-900/20">
                            <div className="flex items-start justify-between gap-4">
                                <div className="min-w-0">
                                    <div className="text-xs text-slate-500">Deudor</div>
                                    <div className="mt-1 truncate text-lg font-semibold">{debtorLabel}</div>
                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        <Badge variant="info" className="gap-1 px-3 py-1 text-sm font-semibold">
                                            <Calendar className="h-3.5 w-3.5" />
                                            {String(data.paid_on ?? '—')}
                                        </Badge>
                                        <Badge variant="secondary" className="gap-1">
                                            <CreditCard className="h-3.5 w-3.5" />
                                            {methodLabel}
                                        </Badge>
                                    </div>
                                </div>

                                <div className="text-right">
                                    <div className="text-xs text-slate-500">Monto</div>
                                    <div className="mt-1 font-mono text-2xl font-semibold tracking-tight">Bs {amountMajor}</div>
                                    {(fxRateUsd || fxRateEur) && (
                                        <div className="mt-1 flex flex-wrap items-center justify-end gap-1 text-[11px] leading-tight text-slate-500">
                                            <span className="whitespace-nowrap">Tasa del día:</span>
                                            {fxRateUsd && (
                                                <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono whitespace-nowrap text-slate-600 dark:bg-slate-950/40 dark:text-slate-300">
                                                    <span className="font-semibold text-slate-500 dark:text-slate-400">$</span>
                                                    <span className="text-slate-400 dark:text-slate-500">Bs</span>
                                                    {fxRateUsd}
                                                </span>
                                            )}
                                            {fxRateEur && (
                                                <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono whitespace-nowrap text-slate-600 dark:bg-slate-950/40 dark:text-slate-300">
                                                    <span className="font-semibold text-slate-500 dark:text-slate-400">€</span>
                                                    <span className="text-slate-400 dark:text-slate-500">Bs</span>
                                                    {fxRateEur}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="space-y-3 rounded-lg border bg-white/50 p-4 text-sm dark:bg-slate-900/20">
                            {!isEXO && (
                                <div className="flex items-center justify-between gap-4">
                                    <span className="flex items-center gap-2 text-slate-500">
                                        <Landmark className="h-4 w-4" /> Cuenta receptora
                                    </span>
                                    <span className="text-right font-medium">{companyAccLabel}</span>
                                </div>
                            )}

                            {(isTRF || isPMOV) && originBankLabel !== '—' && originBankLabel !== '' && (
                                <div className="flex items-center justify-between gap-4">
                                    <span className="flex items-center gap-2 text-slate-500">
                                        <Building2 className="h-4 w-4" /> Banco origen
                                    </span>
                                    <span className="text-right font-medium">{originBankLabel}</span>
                                </div>
                            )}

                            {!isEXO && (
                                <div className="flex items-center justify-between gap-4">
                                    <span className="flex items-center gap-2 text-slate-500">
                                        <User className="h-4 w-4" /> Documento pagador
                                    </span>
                                    <span className="text-right font-mono">{payerDocLabel}</span>
                                </div>
                            )}

                            {isTRF && (
                                <div className="flex items-center justify-between gap-4">
                                    <span className="flex items-center gap-2 text-slate-500">
                                        <CreditCard className="h-4 w-4" /> Cuenta pagador
                                    </span>
                                    <span className="text-right font-mono">{payerAccountLabel}</span>
                                </div>
                            )}

                            {isPMOV && (
                                <div className="flex items-center justify-between gap-4">
                                    <span className="flex items-center gap-2 text-slate-500">
                                        <Phone className="h-4 w-4" /> Teléfono pagador
                                    </span>
                                    <span className="text-right font-mono">{payerPhoneLabel}</span>
                                </div>
                            )}

                            {!isEXO && (
                                <div className="flex items-center justify-between gap-4">
                                    <span className="flex items-center gap-2 text-slate-500">
                                        <Hash className="h-4 w-4" /> Referencia
                                    </span>
                                    <span className="text-right font-mono">{referenceLabel}</span>
                                </div>
                            )}

                            {isEXO && (
                                <div className="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:bg-slate-950/40 dark:text-slate-300">
                                    <div className="mb-1 flex items-center gap-2 text-xs font-semibold text-slate-500">
                                        <Info className="h-4 w-4" /> Motivo
                                    </div>
                                    <div className="whitespace-pre-wrap">{String(data.exoneration_reason ?? '—')}</div>
                                </div>
                            )}
                        </div>
                    </div>

                    <AlertDialogFooter>
                        <AlertDialogCancel asChild>
                            <Button variant="secondary" disabled={processing}>
                                Cancelar
                            </Button>
                        </AlertDialogCancel>
                        <AlertDialogAction asChild>
                            <Button
                                disabled={processing}
                                onClick={(e) => {
                                    e.preventDefault();
                                    confirmSubmit();
                                }}
                            >
                                {processing ? 'Registrando…' : 'Confirmar y registrar'}
                            </Button>
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
            <div className="from-background to-muted/20 dark:from-background dark:to-muted/10 min-h-screen bg-gradient-to-br">
                <div className="container mx-auto max-w-5xl px-4 py-10">
                    <div className="mb-6">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="mb-3 gap-2"
                            onClick={() => (step > 0 ? setStep((s) => (s - 1) as any) : history.back())}
                        >
                            <ArrowLeft className="h-4 w-4" /> Volver
                        </Button>
                        <h1 className="text-foreground text-3xl font-bold">Registrar pago (interno)</h1>
                        <p className="text-muted-foreground mt-1">Flujo moderno con verificación automática para Transferencia y Pago Móvil</p>
                    </div>

                    {flash?.error && (
                        <Alert variant="destructive" className="mb-6">
                            <AlertCircle className="h-4 w-4" />
                            <AlertTitle>{parsedError?.title ?? 'No se pudo registrar el pago'}</AlertTitle>
                            <AlertDescription className="mt-2">
                                {parsedError?.message ?? flash.error}
                                {parsedError?.details && <div className="text-muted-foreground mt-2 text-xs">{parsedError.details}</div>}
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Progress */}
                    <div className="mb-8 flex items-center gap-4">
                        {[0, 1, 2].map((i) => (
                            <React.Fragment key={i}>
                                <div className={`flex items-center gap-2 ${step >= i ? 'text-blue-600' : 'text-slate-400'}`}>
                                    <div
                                        className={`flex h-8 w-8 items-center justify-center rounded-full font-semibold ${step >= i ? 'bg-blue-600 text-white' : 'bg-slate-200'}`}
                                    >
                                        {step > i ? <Check className="h-5 w-5" /> : String(i + 1)}
                                    </div>
                                    <span className="hidden text-sm font-medium sm:inline">{i === 0 ? 'Deudor' : i === 1 ? 'Método' : 'Datos'}</span>
                                </div>
                                {i < 2 && <div className={`h-0.5 flex-1 ${step > i ? 'bg-blue-600' : 'bg-slate-200'}`} />}
                            </React.Fragment>
                        ))}
                    </div>

                    {/* Step 0: Debtor */}
                    {step === 0 && (
                        <div className="space-y-6">
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-lg">Selecciona el deudor</CardTitle>
                                    <CardDescription>Concesionario o Local al cual se acreditará el pago</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <User className="h-4 w-4" /> Tipo de deudor
                                            </Label>
                                            <Select value={String(data.debtor_type)} onValueChange={(v) => setData('debtor_type', v as any)}>
                                                <SelectTrigger className="h-11">
                                                    <SelectValue placeholder="Seleccionar" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="CONCESSIONAIRE">Concesionario</SelectItem>
                                                    <SelectItem value="LOCAL">Local</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <Landmark className="h-4 w-4" /> {data.debtor_type === 'LOCAL' ? 'Local' : 'Concesionario'}
                                            </Label>
                                            {data.debtor_type === 'LOCAL' ? (
                                                <Combobox
                                                    options={(options?.locals ?? []).map((l) => ({ value: String(l.id), label: l.label }))}
                                                    value={data.local_id ? String(data.local_id) : ''}
                                                    onChange={(v) => onSelectLocal(Array.isArray(v) ? Number(v[0]) : Number(v))}
                                                    placeholder="Buscar local..."
                                                    searchPlaceholder="Buscar local..."
                                                />
                                            ) : (
                                                <Combobox
                                                    options={(options?.concessionaires ?? []).map((c) => ({
                                                        value: String(c.id),
                                                        label: `${c.document_type_code ? c.document_type_code + ' ' : ''}${c.document_number ? c.document_number + ' - ' : ''}${c.name}`,
                                                    }))}
                                                    value={data.debtor_id ? String(data.debtor_id) : ''}
                                                    onChange={(v) => onSelectConcessionaire(Array.isArray(v) ? Number(v[0]) : Number(v))}
                                                    placeholder="Buscar concesionario..."
                                                    searchPlaceholder="Buscar concesionario..."
                                                />
                                            )}
                                            {(errors.debtor_id || errors.local_id) && (
                                                <p className="mt-1 text-xs text-red-600">{(errors.debtor_id as any) || (errors.local_id as any)}</p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex justify-end">
                                        <Button
                                            type="button"
                                            className="gap-2"
                                            onClick={() => setStep(1)}
                                            disabled={!data.debtor_id && !data.local_id}
                                        >
                                            Continuar <ArrowRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {/* Step 1: Method */}
                    {step === 1 && (
                        <div className="space-y-6">
                            <Alert className="border-blue-200 bg-blue-50/50">
                                <Info className="h-4 w-4 text-blue-600" />
                                <AlertDescription className="text-blue-900">
                                    Selecciona el método utilizado. Verificaremos automáticamente con el banco.
                                </AlertDescription>
                            </Alert>
                            <div className="grid gap-6 md:grid-cols-4">
                                {/* Transfer */}
                                <button
                                    onClick={() => {
                                        setMethod('TRANSFER');
                                        setData('method', 'TRANSFER');
                                        setStep(2);
                                    }}
                                    className="group text-left"
                                >
                                    <Card className="h-full cursor-pointer border-2 transition-all hover:border-blue-500 hover:shadow-xl">
                                        <CardContent className="pt-8 pb-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 transition-transform group-hover:scale-110">
                                                    <Building2 className="h-10 w-10 text-white" />
                                                </div>
                                                <h3 className="text-foreground mb-2 text-xl font-bold">Transferencia</h3>
                                                <p className="text-muted-foreground mb-4 text-sm">De tu cuenta bancaria a la nuestra</p>
                                                <Badge variant="secondary" className="text-xs">
                                                    Verificación automática
                                                </Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </button>
                                {/* PMOV */}
                                <button
                                    onClick={() => {
                                        setMethod('PMOV');
                                        setData('method', 'PMOV');
                                        setStep(2);
                                    }}
                                    className="group text-left"
                                >
                                    <Card className="h-full cursor-pointer border-2 transition-all hover:border-green-500 hover:shadow-xl">
                                        <CardContent className="pt-8 pb-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-green-600 transition-transform group-hover:scale-110">
                                                    <Smartphone className="h-10 w-10 text-white" />
                                                </div>
                                                <h3 className="text-foreground mb-2 text-xl font-bold">Pago Móvil</h3>
                                                <p className="text-muted-foreground mb-4 text-sm">Pago C2P desde tu app bancaria</p>
                                                <Badge variant="secondary" className="text-xs">
                                                    Verificación automática
                                                </Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </button>
                                {/* DEB */}
                                <button
                                    onClick={() => {
                                        setMethod('DEB');
                                        setData('method', 'DEB');
                                        setData('origin_bank_id', '');
                                        setStep(2);
                                    }}
                                    className="group text-left"
                                >
                                    <Card className="h-full cursor-pointer border-2 transition-all hover:border-slate-500 hover:shadow-xl">
                                        <CardContent className="pt-8 pb-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-slate-400 to-slate-500 transition-transform group-hover:scale-110">
                                                    <CreditCard className="h-10 w-10 text-white" />
                                                </div>
                                                <h3 className="text-foreground mb-2 text-xl font-bold">Débito (POS)</h3>
                                                <p className="text-muted-foreground mb-4 text-sm">Confirmación automática (sin banco)</p>
                                                <Badge variant="secondary" className="text-xs">
                                                    Auto-confirma
                                                </Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </button>
                                <button
                                    onClick={() => {
                                        setMethod('EXO');
                                        setData('method', 'EXO');
                                        setData('origin_bank_id', '');
                                        setData('payer_account_number', '');
                                        setData('payer_phone_area_code', '');
                                        setData('payer_phone_number', '');
                                        setStep(2);
                                    }}
                                    className="group text-left"
                                >
                                    <Card className="h-full cursor-pointer border-2 transition-all hover:border-amber-500 hover:shadow-xl">
                                        <CardContent className="pt-8 pb-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-amber-600 transition-transform group-hover:scale-110">
                                                    <Banknote className="h-10 w-10 text-white" />
                                                </div>
                                                <h3 className="text-foreground mb-2 text-xl font-bold">Exoneración</h3>
                                                <p className="text-muted-foreground mb-4 text-sm">Crédito interno aplicado a deudas</p>
                                                <Badge variant="secondary" className="text-xs">
                                                    Auto-confirma
                                                </Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Form */}
                    {step === 2 && method && (
                        <form onSubmit={submit} className="space-y-6">
                            <Card>
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-3">
                                        {method === 'TRANSFER' ? (
                                            <>
                                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                                                    <Building2 className="h-5 w-5 text-blue-600" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">Datos de la transferencia</CardTitle>
                                                    <CardDescription>Completa la información</CardDescription>
                                                </div>
                                            </>
                                        ) : method === 'PMOV' ? (
                                            <>
                                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30">
                                                    <Smartphone className="h-5 w-5 text-green-600" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">Datos del pago móvil</CardTitle>
                                                    <CardDescription>Completa la información</CardDescription>
                                                </div>
                                            </>
                                        ) : (
                                            <>
                                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800/40">
                                                    <CreditCard className="h-5 w-5 text-slate-700" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">Datos del débito</CardTitle>
                                                    <CardDescription>Confirmación automática</CardDescription>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-6">
                                    {/* Cuenta receptora (oculto en EXO) */}
                                    {!isEXO && (
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <CreditCard className="text-muted-foreground h-4 w-4" /> Cuenta receptora
                                            </Label>
                                            <Select
                                                value={String(data.company_bank_account_id || '')}
                                                onValueChange={(v) => setData('company_bank_account_id', Number(v))}
                                            >
                                                <SelectTrigger className="h-12">
                                                    <SelectValue placeholder="Selecciona la cuenta" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {filteredCompanyAccounts.map((o) => (
                                                        <SelectItem key={o.id} value={String(o.id)}>
                                                            {o.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.company_bank_account_id && (
                                                <p className="mt-1 text-xs text-red-600">{errors.company_bank_account_id}</p>
                                            )}
                                        </div>
                                    )}

                                    <div className="grid gap-6 md:grid-cols-2">
                                        {/* Banco origen (no requerido ni visible en DEB) */}
                                        {!isDEB && !isEXO && (
                                            <div>
                                                <Label className="mb-2 flex items-center gap-2">
                                                    <Building2 className="text-muted-foreground h-4 w-4" /> Banco origen
                                                </Label>
                                                <Combobox
                                                    options={bankOptions}
                                                    value={String(data.origin_bank_id || '')}
                                                    onChange={(v) => setData('origin_bank_id', Array.isArray(v) ? (v[0] ?? '') : v)}
                                                    placeholder="¿Desde qué banco?"
                                                    searchPlaceholder="Buscar banco..."
                                                    leadingIcon={Building2}
                                                    className="h-12"
                                                />
                                                {errors.origin_bank_id && <p className="mt-1 text-xs text-red-600">{errors.origin_bank_id}</p>}
                                            </div>
                                        )}

                                        {/* Referencia (oculta en EXO) */}
                                        {!isEXO && (
                                            <div>
                                                <Label className="mb-2 flex items-center gap-2">
                                                    <Hash className="text-muted-foreground h-4 w-4" /> Referencia
                                                </Label>
                                                <Input
                                                    value={String(data.reference || '')}
                                                    onChange={(e) => setData('reference', e.target.value.replace(/\D+/g, '').slice(0, 8))}
                                                    placeholder="Ej: 12345678"
                                                    className="h-12"
                                                    maxLength={8}
                                                    required
                                                />
                                                <p className="mt-1 text-xs text-slate-500">6 a 8 dígitos</p>
                                                {errors.reference && <p className="mt-1 text-xs text-red-600">{errors.reference}</p>}
                                            </div>
                                        )}
                                    </div>

                                    {/* Método-condicional */}
                                    {method === 'TRANSFER' ? (
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <CreditCard className="h-4 w-4 text-slate-500" /> Cuenta del pagador (20 dígitos)
                                            </Label>
                                            <Input
                                                value={String(data.payer_account_number || '')}
                                                onChange={(e) => setData('payer_account_number', e.target.value.replace(/\D+/g, '').slice(0, 20))}
                                                placeholder="01020123456789012345"
                                                className="h-12"
                                                maxLength={20}
                                                required
                                            />
                                            {errors.payer_account_number && (
                                                <p className="mt-1 text-xs text-red-600">{errors.payer_account_number}</p>
                                            )}
                                        </div>
                                    ) : method === 'PMOV' ? (
                                        <div className="space-y-3">
                                            <Label className="flex items-center gap-2">
                                                <Phone className="text-muted-foreground h-4 w-4" /> Teléfono del pagador
                                            </Label>
                                            <div className="flex gap-3">
                                                <div className="w-32">
                                                    <Select
                                                        value={String(data.payer_phone_area_code || '')}
                                                        onValueChange={(v) => setData('payer_phone_area_code', v)}
                                                        required
                                                    >
                                                        <SelectTrigger className="h-12">
                                                            <SelectValue placeholder="Código" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {areaOptions.map((a) => (
                                                                <SelectItem key={a.value} value={a.value}>
                                                                    {a.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <p className="text-muted-foreground mt-1 text-xs">Código</p>
                                                </div>
                                                <div className="flex-1">
                                                    <Input
                                                        value={String(data.payer_phone_number || '')}
                                                        onChange={(e) =>
                                                            setData('payer_phone_number', e.target.value.replace(/\D+/g, '').slice(0, 7))
                                                        }
                                                        placeholder="1234567"
                                                        className="h-12"
                                                        maxLength={7}
                                                        required
                                                    />
                                                    <p className="text-muted-foreground mt-1 text-xs">Número (7 dígitos)</p>
                                                </div>
                                            </div>
                                            {(errors.payer_phone_area_code || errors.payer_phone_number) && (
                                                <p className="mt-1 text-xs text-red-600">
                                                    {(errors as any).payer_phone_area_code || (errors as any).payer_phone_number}
                                                </p>
                                            )}
                                        </div>
                                    ) : isEXO ? (
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <Info className="text-muted-foreground h-4 w-4" /> Motivo
                                            </Label>
                                            <Input
                                                value={String((data as any).exoneration_reason || '')}
                                                onChange={(e) => setData('exoneration_reason', e.target.value.slice(0, 500))}
                                                placeholder="Describa la razón de la exoneración"
                                                className="h-12"
                                                maxLength={500}
                                                required
                                            />
                                            {(errors as any).exoneration_reason && (
                                                <p className="mt-1 text-xs text-red-600">{(errors as any).exoneration_reason}</p>
                                            )}
                                        </div>
                                    ) : null}

                                    <div className="grid gap-6 md:grid-cols-2">
                                        {/* Monto */}
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <Banknote className="text-muted-foreground h-4 w-4" /> Monto (Bs)
                                            </Label>
                                            <Input
                                                value={amountMajor}
                                                onChange={(e) => handleAmountChange(e.target.value)}
                                                placeholder="0.00"
                                                className="h-12 text-lg font-semibold"
                                                inputMode="numeric"
                                                required
                                            />
                                            {errors.amount_bs_minor && <p className="mt-1 text-xs text-red-600">{errors.amount_bs_minor}</p>}
                                        </div>

                                        {/* Fecha */}
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <Calendar className="text-muted-foreground h-4 w-4" /> Fecha del pago
                                            </Label>
                                            <Input
                                                type="date"
                                                value={String(data.paid_on || '')}
                                                onChange={(e) => setData('paid_on', e.target.value)}
                                                className="h-12"
                                                required
                                            />
                                            {errors.paid_on && <p className="mt-1 text-xs text-red-600">{errors.paid_on}</p>}
                                        </div>
                                    </div>

                                    {/* Payer document (oculto en EXO) */}
                                    {!isEXO && (
                                        <div className="border-t pt-6">
                                            <Label className="text-muted-foreground mb-3 flex items-center gap-2">
                                                <User className="h-4 w-4" /> Datos del pagador
                                            </Label>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div>
                                                    <Label className="text-muted-foreground text-xs">Tipo de documento</Label>
                                                    <Select
                                                        value={String(data.payer_document_type || '')}
                                                        onValueChange={(v) => setData('payer_document_type', v)}
                                                    >
                                                        <SelectTrigger className="bg-muted h-10">
                                                            <SelectValue placeholder="Seleccionar" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="V">V</SelectItem>
                                                            <SelectItem value="E">E</SelectItem>
                                                            <SelectItem value="J">J</SelectItem>
                                                            <SelectItem value="G">G</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    {errors.payer_document_type && (
                                                        <p className="mt-1 text-xs text-red-600">{errors.payer_document_type}</p>
                                                    )}
                                                </div>
                                                <div>
                                                    <Label className="text-muted-foreground text-xs">Número de documento</Label>
                                                    <Input
                                                        value={String(data.payer_document_number || '')}
                                                        onChange={(e) =>
                                                            setData('payer_document_number', e.target.value.replace(/\D+/g, '').slice(0, 12))
                                                        }
                                                        maxLength={12}
                                                        className="bg-muted h-10"
                                                        required
                                                    />
                                                    {errors.payer_document_number && (
                                                        <p className="mt-1 text-xs text-red-600">{errors.payer_document_number}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* FX info */}
                                    {(fxRateUsd || fxRateEur) && (
                                        <Alert className="border-border bg-muted/50">
                                            <Info className="h-4 w-4" />
                                            <AlertDescription className="space-y-2 text-sm">
                                                {fxRateUsd && (
                                                    <div>
                                                        <div className="mb-1 font-medium">Tasa USD del día: Bs {fxRateUsd}</div>
                                                        {equivUsd !== null && (
                                                            <div className="font-medium text-blue-600">
                                                                Equivalente aproximado: ${equivUsd.toFixed(2)} USD
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                                {fxRateEur && (
                                                    <div>
                                                        <div className="mb-1 font-medium">Tasa EUR del día: Bs {fxRateEur}</div>
                                                        {equivEur !== null && (
                                                            <div className="font-medium text-emerald-600">
                                                                Equivalente aproximado: €{equivEur.toFixed(2)} EUR
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                </CardContent>
                            </Card>

                            <div className="flex items-center justify-between">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="gap-2"
                                    onClick={() => {
                                        setStep(1);
                                        setMethod(null);
                                        setData('method', '');
                                    }}
                                >
                                    <ArrowLeft className="h-4 w-4" /> Cambiar método
                                </Button>
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={processing}
                                    className="gap-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800"
                                >
                                    {processing ? 'Verificando...' : 'Registrar pago'} <ArrowRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
