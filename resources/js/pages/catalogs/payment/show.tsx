import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { TableToolbar } from '@/components/index/TableToolbar';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Calendar, Pencil, Trash2 } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

interface Item {
    id: number | string;
    // Dynamic shape depends on module
    [key: string]: unknown;
}

interface AllocationRow {
    charge_id: number;
    amount_bs_minor: number;
    created_at?: string;
    currency?: string;
    amount_minor?: number;
    period?: string;
    due_on?: string;
    local_label?: string | null;
    kind?: string;
}

interface ShowProps extends PageProps {
    item: Item;
    hasEditRoute?: boolean;
    customer_credit_bs_minor?: number;
    allocations?: AllocationRow[];
    receipt?: {
        id: number | string;
        receipt_number?: string;
        issued_at?: string;
        download_url?: string;
        verify_url?: string;
    };
    receipts_by_charge?: Array<{
        id: number | string;
        receipt_number: string;
        issued_at?: string;
        concept?: string;
        charge_id?: number;
        charge_period?: string;
        charge_kind?: string;
        applied_bs_minor?: number;
        download_url?: string;
        verify_url?: string;
    }>;
}

export default function ShowPage() {
    const { item, hasEditRoute, customer_credit_bs_minor, allocations = [], receipt, receipts_by_charge = [] } = usePage<ShowProps>().props;
    const { flash } = usePage<{ flash?: { success?: string; error?: string; warning?: string; info?: string } }>().props;
    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);
    const payment = item as any;
    const status = String(payment.status ?? '');
    const isConfirmed = status === 'CONFIRMED';
    const appliedMinor = Number(payment.applied_bs_minor ?? 0);
    const availableMinor = Number(payment.available_bs_minor ?? 0);
    const creditMinor = Number(customer_credit_bs_minor ?? 0);

    const formatDate = (date?: string | null) => {
        if (!date) return '—';
        try {
            return new Date(date).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
        } catch {
            return '—';
        }
    };

    const formatMinor = (v?: number | null) => {
        const n = Number(v ?? 0);
        return (n / 100).toFixed(2);
    };
    const formatShortDate = (s?: string | null) => {
        if (!s) return '—';
        try {
            const d = new Date(String(s));
            return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch {
            return String(s);
        }
    };
    const _parseMajorToMinor = (s: string) => {
        const clean = s.replace(/[^0-9.]/g, '');
        const n = Number(clean || '0');
        return Math.max(0, Math.round(n * 100));
    };

    // Tabs state (persist current tab via query param when possible)
    const initialTab = React.useMemo(() => {
        const p = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
        const t = p?.get('tab') || '';
        if (t === 'apply') {
            return isConfirmed ? 'apply' : status === 'APPLIED' ? 'allocations' : 'details';
        }
        if (t === 'allocations') return 'allocations';
        return status === 'APPLIED' ? 'allocations' : 'details';
    }, [status, isConfirmed]);
    const [tab, setTab] = React.useState<string>(initialTab);

    // Apply tab state
    const [charges, setCharges] = React.useState<Array<any>>([]);
    const [amounts, setAmounts] = React.useState<Record<number, number>>({}); // cents by charge_id
    const [rowIssues, setRowIssues] = React.useState<Record<number, string | null>>({});
    const [loading, setLoading] = React.useState<boolean>(false);
    const [errors, setErrors] = React.useState<Array<string>>([]);
    const [_applyKey, setApplyKey] = React.useState<string | null>(null);
    const [useCredit, setUseCredit] = React.useState<boolean>(false);

    const sumRequested = React.useMemo(() => Object.values(amounts).reduce((a, b) => a + (Number(b) || 0), 0), [amounts]);
    const afterAvailable = Math.max(0, availableMinor - sumRequested);
    const totalAvailable = availableMinor + (useCredit ? creditMinor : 0);
    const afterTotalAvailable = Math.max(0, totalAvailable - sumRequested);

    const getCookie = (name: string) => {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return decodeURIComponent(parts.pop()!.split(';').shift() || '');
        return '';
    };

    // Filters for open-charges (Phase 1)
    const [filters, setFilters] = React.useState<{
        currency?: string;
        kind?: string;
        period_from?: string;
        period_to?: string;
        overdue_only?: boolean;
    }>({});
    const activeFiltersCount = React.useMemo(() => {
        let c = 0;
        if (filters.currency) c++;
        if (filters.kind) c++;
        if (filters.period_from) c++;
        if (filters.period_to) c++;
        if (filters.overdue_only) c++;
        return c;
    }, [filters]);

    const filterBadges = React.useMemo(() => {
        const arr: Array<{ key: string; label: string; onRemove: () => void }> = [];
        if (filters.currency)
            arr.push({ key: 'currency', label: `Moneda: ${filters.currency}`, onRemove: () => setFilters((f) => ({ ...f, currency: undefined })) });
        if (filters.kind) arr.push({ key: 'kind', label: `Tipo: ${filters.kind}`, onRemove: () => setFilters((f) => ({ ...f, kind: undefined })) });
        if (filters.period_from)
            arr.push({ key: 'pf', label: `Desde: ${filters.period_from}`, onRemove: () => setFilters((f) => ({ ...f, period_from: undefined })) });
        if (filters.period_to)
            arr.push({ key: 'pt', label: `Hasta: ${filters.period_to}`, onRemove: () => setFilters((f) => ({ ...f, period_to: undefined })) });
        if (filters.overdue_only)
            arr.push({ key: 'overdue', label: 'Sólo vencidos', onRemove: () => setFilters((f) => ({ ...f, overdue_only: undefined })) });
        return arr;
    }, [filters]);

    const fetchOpenCharges = React.useCallback(async () => {
        if (!payment.paid_on) return;
        setLoading(true);
        setErrors([]);
        try {
            const isLocal = !!payment.local_id;
            const qs = new URLSearchParams({
                debtor_type: isLocal ? 'LOCAL' : String(payment.debtor_type ?? ''),
                debtor_id: isLocal ? String(payment.local_id ?? '') : String(payment.debtor_id ?? ''),
                paid_on: String(payment.paid_on ?? ''),
            });
            if (filters.currency) qs.set('currency', String(filters.currency));
            if (filters.kind) qs.set('kind', String(filters.kind));
            if (filters.period_from) qs.set('period_from', String(filters.period_from));
            if (filters.period_to) qs.set('period_to', String(filters.period_to));
            if (filters.overdue_only) qs.set('overdue_only', '1');
            const res = await fetch(`/payments/open-charges?${qs.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('open_charges_failed');
            const json = await res.json();
            setCharges(Array.isArray(json.items) ? json.items : []);
            setAmounts({});
            setRowIssues({});
        } catch {
            setErrors(['No se pudieron obtener los cargos abiertos.']);
        } finally {
            setLoading(false);
        }
    }, [payment, filters]);

    // Auto-refresh open charges when switching to Apply tab
    React.useEffect(() => {
        if (tab === 'apply') {
            fetchOpenCharges();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tab]);

    const suggestFifo = React.useCallback(() => {
        let remaining = availableMinor;
        const next: Record<number, number> = {};
        for (const c of charges) {
            if (remaining <= 0) break;
            const cap = Number(c.outstanding_bs_minor ?? 0);
            const take = Math.min(remaining, cap);
            if (take > 0) {
                next[Number(c.charge_id)] = take;
                remaining -= take;
            }
        }
        setAmounts(next);
    }, [charges, availableMinor]);

    const suggestProportional = React.useCallback(() => {
        const remaining = availableMinor;
        const totals = charges.reduce((acc, c) => acc + Number(c.outstanding_bs_minor ?? 0), 0);
        if (remaining <= 0 || totals <= 0) {
            setAmounts({});
            return;
        }
        const next: Record<number, number> = {};
        for (const c of charges) {
            const out = Number(c.outstanding_bs_minor ?? 0);
            if (out <= 0) continue;
            const share = Math.floor((out / totals) * remaining);
            next[Number(c.charge_id)] = Math.min(share, out);
        }
        // distribute residual cents
        const assigned = Object.values(next).reduce((a, b) => a + b, 0);
        let residual = Math.max(0, remaining - assigned);
        if (residual > 0) {
            for (const c of charges) {
                if (residual <= 0) break;
                const cid = Number(c.charge_id);
                const out = Number(c.outstanding_bs_minor ?? 0);
                const curr = next[cid] || 0;
                if (curr < out) {
                    next[cid] = curr + 1;
                    residual--;
                }
            }
        }
        setAmounts(next);
    }, [charges, availableMinor]);

    const clearAll = React.useCallback(() => setAmounts({}), []);

    const fetchSuggestFromServer = React.useCallback(
        async (strategy: 'fifo' | 'proportional') => {
            setErrors([]);
            try {
                const body: any = { strategy };
                if (filters.currency) body.currency = String(filters.currency);
                if (filters.kind) body.kind = String(filters.kind);
                if (filters.period_from) body.period_from = String(filters.period_from);
                if (filters.period_to) body.period_to = String(filters.period_to);
                if (filters.overdue_only) body.overdue_only = 1;
                const res = await fetch(`/payments/${payment.id}/allocations/suggest`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                        'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const js = await res.json();
                if (!res.ok) throw new Error('suggest_failed');
                const next: Record<number, number> = {};
                if (Array.isArray(js.items)) {
                    for (const it of js.items) {
                        if (it && typeof it.charge_id === 'number' && typeof it.amount_bs_minor === 'number') {
                            next[it.charge_id] = it.amount_bs_minor;
                        }
                    }
                }
                setAmounts(next);
            } catch {
                setErrors(['No se pudo obtener sugerencia del servidor.']);
            }
        },
        [payment, filters],
    );

    const previewAndApply = React.useCallback(async () => {
        setErrors([]);
        const items = Object.entries(amounts)
            .map(([cid, amt]) => ({ charge_id: Number(cid), amount_bs_minor: Number(amt) }))
            .filter((x) => x.amount_bs_minor > 0);
        if (items.length === 0) return;
        try {
            // Preview
            const resPrev = await fetch(`/payments/${payment.id}/allocations/preview`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ items, use_credit: useCredit ? 1 : 0 }),
            });
            const jsPrev = await resPrev.json();
            const rowMap: Record<number, string | null> = {};
            if (jsPrev.items && Array.isArray(jsPrev.items)) {
                for (const it of jsPrev.items) {
                    if (it && typeof it.charge_id === 'number') {
                        rowMap[it.charge_id] = it.valid ? null : it.message || 'Inválido';
                    }
                }
            }
            setRowIssues(rowMap);
            if (!resPrev.ok || jsPrev.ok === false) {
                setErrors(jsPrev.errors ?? ['Validación falló.']);
                return;
            }
            // Apply (use Inertia to keep flow consistent)
            const key = `pay-${payment.id}-${Date.now()}`;
            setApplyKey(key);
            router.post(
                `/payments/${payment.id}/allocations`,
                { items, idempotency_key: key, use_credit: useCredit ? 1 : 0 },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        // Reload page to reflect new status/allocations
                        router.visit(`/payments/${payment.id}?tab=allocations`, { preserveScroll: true, replace: true });
                    },
                },
            );
        } catch {
            setErrors(['Error al aplicar el pago.']);
        }
    }, [amounts, payment, useCredit]);

    // Aplicar todo: Distribuye todo el disponible FIFO y aplica en un paso
    const applyAllNow = React.useCallback(async () => {
        setErrors([]);
        let remaining = totalAvailable;
        const next: Record<number, number> = {};
        for (const c of charges) {
            if (remaining <= 0) break;
            const cap = Number(c.outstanding_bs_minor ?? 0);
            const take = Math.min(remaining, cap);
            if (take > 0) {
                next[Number(c.charge_id)] = take;
                remaining -= take;
            }
        }
        const items = Object.entries(next)
            .map(([cid, amt]) => ({ charge_id: Number(cid), amount_bs_minor: Number(amt) }))
            .filter((x) => x.amount_bs_minor > 0);
        if (items.length === 0) return;
        try {
            const resPrev = await fetch(`/payments/${payment.id}/allocations/preview`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ items, use_credit: useCredit ? 1 : 0 }),
            });
            const jsPrev = await resPrev.json();
            if (!resPrev.ok || jsPrev.ok === false) {
                setErrors(jsPrev.errors ?? ['Validación falló.']);
                return;
            }
            const key = `pay-${payment.id}-${Date.now()}`;
            setApplyKey(key);
            router.post(
                `/payments/${payment.id}/allocations`,
                { items, idempotency_key: key, use_credit: useCredit ? 1 : 0 },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        router.visit(`/payments/${payment.id}?tab=allocations`, { preserveScroll: true, replace: true });
                    },
                },
            );
        } catch {
            setErrors(['Error al aplicar el pago.']);
        }
    }, [charges, totalAvailable, payment, useCredit]);

    const breadcrumbs = [
        { title: 'Pagos', href: '/payments' },
        { title: String((item as any).id), href: '' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Pago: ${String((item as any).id)}`} />

            <ShowLayout
                header={
                    <div className="flex items-center gap-4">
                        <Link href="/payments" className="text-muted-foreground hover:text-foreground transition-colors">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{String((item as any).id)}</h1>
                        </div>
                    </div>
                }
                actions={
                    <div className="flex gap-2">
                        {hasEditRoute && (
                            <Button onClick={() => router.visit(`/payments/${item.id}/edit`)}>
                                <Pencil className="h-4 w-4" />
                                Editar
                            </Button>
                        )}
                        {String((item as any).status ?? '') === 'REGISTERED' && (
                            <Button
                                variant="secondary"
                                onClick={async () => {
                                    await new Promise<void>((resolve, reject) => {
                                        router.post(
                                            `/payments/${item.id}/verify`,
                                            {},
                                            {
                                                preserveState: false,
                                                preserveScroll: true,
                                                onSuccess: () => resolve(),
                                                onError: () => reject(new Error('verify_failed')),
                                            },
                                        );
                                    });
                                }}
                            >
                                Verificar
                            </Button>
                        )}
                        {null}
                        <ConfirmAlert
                            trigger={
                                <Button variant="destructive" type="button">
                                    <Trash2 className="h-4 w-4" />
                                    Eliminar
                                </Button>
                            }
                            title="Eliminar registro"
                            description={`¿Está seguro de eliminar "${String((item as any).id)}"? Esta acción no se puede deshacer.`}
                            confirmLabel="Eliminar"
                            onConfirm={async () => {
                                await new Promise<void>((resolve, reject) => {
                                    router.delete(`/payments/${item.id}`, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            resolve();
                                            router.visit('/payments');
                                        },
                                        onError: () => reject(new Error('delete_failed')),
                                    });
                                });
                            }}
                        />
                    </div>
                }
                aside={
                    <Card className="sticky top-4">
                        <CardHeader>
                            <CardTitle className="text-base">Resumen</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Estatus</span>
                                <div className="flex items-center gap-2">
                                    <Badge className="font-medium">{String((item as any).status ?? '—')}</Badge>
                                </div>
                            </div>
                            <div className="space-y-1">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Resp. código</span>
                                    <span className="text-sm">{String((item as any).gateway_resp_code ?? '—')}</span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block text-sm">Resp. mensaje</span>
                                    <span className="text-sm break-words">{String((item as any).gateway_message ?? '—')}</span>
                                </div>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Monto</span>
                                <span className="text-sm">Bs {formatMinor((item as any).amount_bs_minor)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Asignado</span>
                                <span className="text-sm">Bs {formatMinor(appliedMinor)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Disponible</span>
                                <span className="text-sm">Bs {formatMinor(availableMinor)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Crédito a favor</span>
                                <span className="text-sm">Bs {formatMinor(creditMinor)}</span>
                            </div>
                            {receipt && receipt.receipt_number ? (
                                <div className="space-y-2 border-t pt-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground text-sm">Recibo</span>
                                        <span className="text-sm font-medium">{String(receipt.receipt_number)}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground text-sm">Emitido</span>
                                        <span className="text-sm">{formatShortDate(receipt.issued_at)}</span>
                                    </div>
                                    <div className="flex justify-end gap-2">
                                        {receipt.download_url && (
                                            <a href={receipt.download_url} className="text-sm underline" target="_blank" rel="noreferrer">
                                                Descargar PDF
                                            </a>
                                        )}
                                        {receipt.verify_url && (
                                            <a href={receipt.verify_url} className="text-sm underline" target="_blank" rel="noreferrer">
                                                Verificar
                                            </a>
                                        )}
                                    </div>
                                </div>
                            ) : null}
                            {status === 'APPLIED' ? (
                                <div className="text-xs text-green-700">Pago APPLIED. Revise "Asignaciones".</div>
                            ) : (
                                !isConfirmed && <div className="text-xs text-amber-600">Debe estar CONFIRMED para aplicar.</div>
                            )}
                        </CardContent>
                    </Card>
                }
            >
                <Tabs value={tab} onValueChange={(v) => setTab(v)}>
                    <TabsList className="mb-3">
                        <TabsTrigger value="details">Detalle</TabsTrigger>
                        <TabsTrigger value="apply" disabled={!isConfirmed}>
                            Cruce/Aplicar
                        </TabsTrigger>
                        <TabsTrigger value="allocations">Asignaciones</TabsTrigger>
                    </TabsList>

                    <TabsContent value="details">
                        <ShowSection id="overview" title="Información Básica">
                            <Card>
                                <CardContent className="pt-6">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Tipo de deudor</dt>
                                            <dd className="mt-1 text-sm">{String(payment.debtor_type ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Deudor</dt>
                                            <dd className="mt-1 text-sm">{String(payment.debtor_name ?? payment.debtor_id ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Cuenta receptora</dt>
                                            <dd className="mt-1 text-sm">{String(payment.company_bank_account_id ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Método</dt>
                                            <dd className="mt-1 text-sm">{String(payment.method ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Banco origen</dt>
                                            <dd className="mt-1 text-sm">
                                                {String((payment as any).origin_bank_name ?? payment.origin_bank_id ?? '—')}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Documento pagador</dt>
                                            <dd className="mt-1 text-sm">
                                                {(() => {
                                                    const t = (payment as any).document_type_code || (payment.payer_document_type ?? '').toString();
                                                    const n = (payment.payer_document_number ?? '').toString();
                                                    const sep = t ? '-' : '';
                                                    return t || n ? `${t}${sep}${n}` : '—';
                                                })()}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Referencia</dt>
                                            <dd className="mt-1 text-sm">{String(payment.reference ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Pagado el</dt>
                                            <dd className="mt-1 text-sm">{formatDate(payment.paid_on)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Tasa FX</dt>
                                            <dd className="mt-1 text-sm">{String(payment.fx_rate_id ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Estado</dt>
                                            <dd className="mt-1 text-sm">{String(payment.status ?? '—')}</dd>
                                        </div>
                                    </dl>
                                </CardContent>
                            </Card>
                        </ShowSection>

                        <ShowSection id="receipts-by-charge" title="Recibos por cargo">
                            <Card>
                                <CardContent className="pt-6">
                                    {receipts_by_charge.length === 0 ? (
                                        <div className="text-muted-foreground text-sm">No hay recibos por cargo.</div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="text-left">
                                                        <th className="py-2 pr-3">Número</th>
                                                        <th className="py-2 pr-3">Concepto</th>
                                                        <th className="py-2 pr-3">Cargo</th>
                                                        <th className="py-2 pr-3">Periodo</th>
                                                        <th className="py-2 pr-3">Aplicado (Bs)</th>
                                                        <th className="py-2 pr-3 text-right">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {receipts_by_charge.map((r) => (
                                                        <tr key={String(r.id)} className="border-t">
                                                            <td className="py-2 pr-3">{r.receipt_number}</td>
                                                            <td className="py-2 pr-3">{(r.concept || '').toString()}</td>
                                                            <td className="py-2 pr-3">#{r.charge_id}</td>
                                                            <td className="py-2 pr-3">{String(r.charge_period || '')}</td>
                                                            <td className="py-2 pr-3">Bs {formatMinor(r.applied_bs_minor || 0)}</td>
                                                            <td className="py-2 pr-3 text-right">
                                                                <div className="flex items-center justify-end gap-3">
                                                                    {r.download_url && (
                                                                        <a
                                                                            href={r.download_url}
                                                                            target="_blank"
                                                                            rel="noreferrer"
                                                                            className="underline"
                                                                        >
                                                                            PDF
                                                                        </a>
                                                                    )}
                                                                    {r.verify_url && (
                                                                        <a href={r.verify_url} target="_blank" rel="noreferrer" className="underline">
                                                                            Verificar
                                                                        </a>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </ShowSection>

                        <ShowSection id="metadata" title="Metadatos">
                            <Card>
                                <CardContent className="pt-6">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                                Creado
                                            </dt>
                                            <dd className="mt-1 text-sm">{formatDate(((item as any).created_at as string) ?? null)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                                Última actualización
                                            </dt>
                                            <dd className="mt-1 text-sm">{formatDate(((item as any).updated_at as string) ?? null)}</dd>
                                        </div>
                                    </dl>
                                </CardContent>
                            </Card>
                        </ShowSection>
                    </TabsContent>

                    <TabsContent value="apply">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Cruce / Aplicación de pago</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {status === 'APPLIED' ? (
                                    <div className="text-sm text-green-700">
                                        Pago ya aplicado. Revisa la pestaña "Asignaciones" para ver lo distribuido.
                                    </div>
                                ) : (
                                    !isConfirmed && <div className="text-sm text-amber-600">Debe estar CONFIRMED para poder aplicar.</div>
                                )}
                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Monto del pago</span>
                                        <strong>Bs {formatMinor(payment.amount_bs_minor)}</strong>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Asignado actual</span>
                                        <strong>Bs {formatMinor(appliedMinor)}</strong>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Disponible</span>
                                        <strong>Bs {formatMinor(availableMinor)}</strong>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Asignar ahora</span>
                                        <strong>Bs {formatMinor(sumRequested)}</strong>
                                    </div>
                                    <div className="col-span-2 flex items-center justify-between">
                                        <span className="text-muted-foreground">Disponible tras aplicar</span>
                                        <strong>Bs {formatMinor(afterAvailable)}</strong>
                                    </div>
                                    <div className="col-span-2 flex items-center justify-between">
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                className="h-4 w-4"
                                                checked={useCredit}
                                                onChange={(e) => setUseCredit(e.target.checked)}
                                                disabled={creditMinor === 0}
                                            />
                                            <span className="text-muted-foreground">Usar crédito a favor (Bs {formatMinor(creditMinor)})</span>
                                        </label>
                                        <div className="text-right">
                                            <div className="text-muted-foreground">Disponible total</div>
                                            <strong>Bs {formatMinor(totalAvailable)}</strong>
                                            <div className="text-muted-foreground">Después de aplicar</div>
                                            <strong>Bs {formatMinor(afterTotalAvailable)}</strong>
                                        </div>
                                    </div>
                                </div>

                                <TableToolbar>
                                    <FilterSheet
                                        activeFiltersCount={activeFiltersCount}
                                        onApplyFilters={fetchOpenCharges}
                                        onClearFilters={() => setFilters({})}
                                        title="Filtros de cargos"
                                        description="Refina los cargos a cruzar"
                                    >
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Moneda</label>
                                                <Select
                                                    value={filters.currency ?? 'ALL'}
                                                    onValueChange={(v) => setFilters((f) => ({ ...f, currency: v === 'ALL' ? undefined : v }))}
                                                >
                                                    <SelectTrigger className="h-8">
                                                        <SelectValue placeholder="Todas" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="ALL">Todas</SelectItem>
                                                        <SelectItem value="USD">USD</SelectItem>
                                                        <SelectItem value="EUR">EUR</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Tipo</label>
                                                <Select
                                                    value={filters.kind ?? 'ALL'}
                                                    onValueChange={(v) => setFilters((f) => ({ ...f, kind: v === 'ALL' ? undefined : v }))}
                                                >
                                                    <SelectTrigger className="h-8">
                                                        <SelectValue placeholder="Todos" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="ALL">Todos</SelectItem>
                                                        <SelectItem value="RENT_EUR_M2">Alquiler m² (EUR)</SelectItem>
                                                        <SelectItem value="RENT_EUR_FIXED">Alquiler fijo (EUR)</SelectItem>
                                                        <SelectItem value="CONDO_USD">Condominio (USD)</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Periodo desde</label>
                                                <Input
                                                    type="month"
                                                    value={filters.period_from ?? ''}
                                                    onChange={(e) => setFilters((f) => ({ ...f, period_from: e.target.value || undefined }))}
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Periodo hasta</label>
                                                <Input
                                                    type="month"
                                                    value={filters.period_to ?? ''}
                                                    onChange={(e) => setFilters((f) => ({ ...f, period_to: e.target.value || undefined }))}
                                                />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <label className="mb-1 block text-sm font-medium">Sólo vencidos</label>
                                                <select
                                                    className="h-8 w-full rounded-md border px-2 text-sm"
                                                    value={filters.overdue_only ? '1' : ''}
                                                    onChange={(e) =>
                                                        setFilters((f) => ({ ...f, overdue_only: e.target.value === '1' ? true : undefined }))
                                                    }
                                                >
                                                    <option value="">No</option>
                                                    <option value="1">Sí</option>
                                                </select>
                                            </div>
                                        </div>
                                    </FilterSheet>

                                    <div className="flex min-w-0 items-center gap-2">
                                        <Button type="button" variant="secondary" disabled={!isConfirmed || loading} onClick={fetchOpenCharges}>
                                            Obtener cargos abiertos
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button type="button" variant="outline" disabled={!isConfirmed || loading || charges.length === 0}>
                                                    Estrategias
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="start" className="w-48">
                                                <DropdownMenuItem onClick={suggestFifo}>Sugerir por antigüedad</DropdownMenuItem>
                                                <DropdownMenuItem onClick={suggestProportional}>Sugerir proporcional</DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => fetchSuggestFromServer('fifo')}>Sugerir (BE) FIFO</DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => fetchSuggestFromServer('proportional')}>
                                                    Sugerir (BE) Proporcional
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={clearAll}>Limpiar</DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                        <Button
                                            type="button"
                                            disabled={!isConfirmed || loading || charges.length === 0 || totalAvailable === 0}
                                            onClick={applyAllNow}
                                        >
                                            Aplicar todo
                                        </Button>
                                    </div>
                                </TableToolbar>
                                {activeFiltersCount > 0 && (
                                    <div className="mt-2">
                                        <FilterBadges badges={filterBadges} />
                                    </div>
                                )}

                                {errors.length > 0 && (
                                    <div className="bg-destructive/10 text-destructive rounded-md p-3 text-sm">
                                        {errors.map((e, i) => (
                                            <div key={i}>{e}</div>
                                        ))}
                                    </div>
                                )}

                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="text-muted-foreground">
                                                <th className="p-2 text-left">Local</th>
                                                <th className="p-2 text-left">Periodo</th>
                                                <th className="p-2 text-left">Vence</th>
                                                <th className="p-2 text-left">Moneda</th>
                                                <th className="p-2 text-right">Monto (moneda)</th>
                                                <th className="p-2 text-right">Saldo (Bs)</th>
                                                <th className="p-2 text-right">A aplicar (Bs)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {charges.map((c) => {
                                                const cid = Number(c.charge_id);
                                                const outstanding = Number(c.outstanding_bs_minor ?? 0);
                                                const val = Number(amounts[cid] ?? 0);
                                                const issue = rowIssues[cid] ?? null;
                                                const over = val > outstanding || sumRequested > availableMinor || Boolean(issue);
                                                const isOverdue = Boolean(c.due_on) && new Date(String(c.due_on)) < new Date(String(payment.paid_on));
                                                return (
                                                    <tr key={cid} className={over ? 'bg-destructive/5' : ''}>
                                                        <td className="p-2">{String(c.local_label ?? c.local_id ?? '—')}</td>
                                                        <td className="p-2">{formatShortDate(c.period)}</td>
                                                        <td className={'p-2 ' + (isOverdue ? 'text-destructive font-medium' : '')}>
                                                            {formatShortDate(c.due_on)}
                                                        </td>
                                                        <td className="p-2">{String(c.currency ?? '')}</td>
                                                        <td className="p-2 text-right">{(Number(c.amount_minor ?? 0) / 100).toFixed(2)}</td>
                                                        <td className="p-2 text-right">{formatMinor(outstanding)}</td>
                                                        <td className="p-2 text-right">
                                                            <Input
                                                                value={formatMinor(val)}
                                                                onChange={(e) => {
                                                                    const raw = e.target.value ?? '';
                                                                    const digits = String(raw).replace(/[^0-9]/g, '');
                                                                    const minor = digits ? parseInt(digits, 10) : 0; // bank-style: digits are cents
                                                                    const cap = Math.max(0, Math.min(minor, outstanding));
                                                                    setAmounts((prev) => ({ ...prev, [cid]: cap }));
                                                                }}
                                                                inputMode="decimal"
                                                                placeholder="0.00"
                                                                className="text-right"
                                                            />
                                                            {issue && <div className="text-destructive mt-1 text-xs">{issue}</div>}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                            {charges.length === 0 && (
                                                <tr>
                                                    <td className="text-muted-foreground p-3 text-center" colSpan={7}>
                                                        {loading ? 'Cargando…' : 'Sin cargos cargados. Usa "Obtener cargos abiertos".'}
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                {(() => {
                                    const disabledReason =
                                        status === 'APPLIED'
                                            ? 'El pago ya está APPLIED'
                                            : !isConfirmed
                                              ? 'El pago no está CONFIRMED'
                                              : sumRequested === 0
                                                ? 'No hay montos a aplicar'
                                                : sumRequested > totalAvailable
                                                  ? 'El total a aplicar supera el disponible (pago + crédito)'
                                                  : Object.values(rowIssues).some((v) => v)
                                                    ? 'Corrige las validaciones por fila'
                                                    : '';
                                    const disabled = disabledReason !== '';
                                    return (
                                        <div className="flex justify-end">
                                            <Button
                                                type="button"
                                                disabled={disabled}
                                                title={disabled ? disabledReason : undefined}
                                                onClick={previewAndApply}
                                            >
                                                Aplicar selección
                                            </Button>
                                            <aside className="text-muted-foreground sticky top-0 right-0 p-2 text-xs">
                                                {disabledReason && <div>{disabledReason}</div>}
                                            </aside>
                                        </div>
                                    );
                                })()}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="allocations">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Asignaciones realizadas</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="text-muted-foreground">
                                                <th className="p-2 text-left">Charge</th>
                                                <th className="p-2 text-left">Periodo</th>
                                                <th className="p-2 text-left">Vence</th>
                                                <th className="p-2 text-left">Moneda</th>
                                                <th className="p-2 text-right">Monto (moneda)</th>
                                                <th className="p-2 text-right">Aplicado (Bs)</th>
                                                <th className="p-2 text-left">Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {allocations.length > 0 ? (
                                                allocations.map((a, idx) => (
                                                    <tr key={idx}>
                                                        <td className="p-2">{a.local_label ?? `#${a.charge_id}`}</td>
                                                        <td className="p-2">
                                                            {a.period
                                                                ? new Date(a.period).toLocaleDateString('es-ES', {
                                                                      month: '2-digit',
                                                                      year: 'numeric',
                                                                  })
                                                                : '—'}
                                                        </td>
                                                        <td className="p-2">{a.due_on ? new Date(a.due_on).toLocaleDateString('es-ES') : '—'}</td>
                                                        <td className="p-2">{a.currency ?? '—'}</td>
                                                        <td className="p-2 text-right">{((a.amount_minor ?? 0) / 100).toFixed(2)}</td>
                                                        <td className="p-2 text-right">{formatMinor(a.amount_bs_minor)}</td>
                                                        <td className="p-2">{a.created_at ? new Date(a.created_at).toLocaleString('es-ES') : '—'}</td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td className="text-muted-foreground p-3 text-center" colSpan={7}>
                                                        Sin asignaciones.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </ShowLayout>
        </AppLayout>
    );
}
