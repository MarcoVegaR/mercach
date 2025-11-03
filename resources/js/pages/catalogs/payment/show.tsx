import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, ArrowRight, Calendar, Check, Clock, Edit3, Grid3x3, Pencil, Trash2, TrendingUp } from 'lucide-react';
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
    can_edit?: boolean;
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
    const { item, hasEditRoute, can_edit, customer_credit_bs_minor, allocations = [], receipt, receipts_by_charge = [] } = usePage<ShowProps>().props;
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

    // Apply tab state - Progressive Disclosure: 3 steps
    const [applyStep, setApplyStep] = React.useState<1 | 2 | 3>(1);
    const [charges, setCharges] = React.useState<Array<any>>([]);
    const [amounts, setAmounts] = React.useState<Record<number, number>>({}); // cents by charge_id
    const [rowIssues, setRowIssues] = React.useState<Record<number, string | null>>({});
    const [loading, setLoading] = React.useState<boolean>(false);
    const [errors, setErrors] = React.useState<Array<string>>([]);
    const [_applyKey, setApplyKey] = React.useState<string | null>(null);
    const [useCredit, setUseCredit] = React.useState<boolean>(false);
    const [selectedStrategy, setSelectedStrategy] = React.useState<'fifo' | 'by_type' | null>(null);

    const sumRequested = React.useMemo(() => Object.values(amounts).reduce((a, b) => a + (Number(b) || 0), 0), [amounts]);
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
            if (json.items && json.items.length > 0) {
                toast.success(`${json.items.length} cargos pendientes encontrados`);
            } else {
                toast.info('No hay cargos pendientes para este deudor');
            }
        } catch {
            setErrors(['No se pudieron obtener los cargos abiertos.']);
            toast.error('Error al cargar cargos');
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

    const applyStrategyFifo = React.useCallback(() => {
        setSelectedStrategy('fifo');
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
        setAmounts(next);
        setApplyStep(3);
        toast.success('Distribución por antigüedad aplicada');
    }, [charges, totalAvailable]);

    const applyStrategyByType = React.useCallback(() => {
        setSelectedStrategy('by_type');
        let remaining = totalAvailable;
        const next: Record<number, number> = {};

        // Group charges by kind
        const byKind = charges.reduce(
            (acc, c) => {
                const kind = String(c.kind ?? 'OTHER');
                if (!acc[kind]) acc[kind] = [];
                acc[kind].push(c);
                return acc;
            },
            {} as Record<string, any[]>,
        );

        // Sort kinds by total outstanding (prioritize higher debts)
        const kindsSorted = Object.entries(byKind).sort(
            ([, a], [, b]) =>
                (b as any[]).reduce((s: number, c: any) => s + Number(c.outstanding_bs_minor ?? 0), 0) -
                (a as any[]).reduce((s: number, c: any) => s + Number(c.outstanding_bs_minor ?? 0), 0),
        );

        // Allocate by type, paying full charges when possible
        for (const [, kindCharges] of kindsSorted) {
            for (const c of (kindCharges as any[]).sort(
                (a: any, b: any) => new Date(a.period || '').getTime() - new Date(b.period || '').getTime(),
            )) {
                if (remaining <= 0) break;
                const cap = Number(c.outstanding_bs_minor ?? 0);
                const take = Math.min(remaining, cap);
                if (take > 0) {
                    next[Number(c.charge_id)] = take;
                    remaining -= take;
                }
            }
        }

        setAmounts(next);
        setApplyStep(3);
        toast.success('Distribución por tipo de cargo aplicada');
    }, [charges, totalAvailable]);

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
                        {hasEditRoute && can_edit && (
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
                        {/* Progress Bar */}
                        <div className="mb-8 flex items-center gap-4">
                            {[1, 2, 3].map((s, idx) => (
                                <React.Fragment key={s}>
                                    <div className={`flex items-center gap-2 ${applyStep >= s ? 'text-blue-600' : 'text-slate-400'}`}>
                                        <div
                                            className={`flex h-10 w-10 items-center justify-center rounded-full font-semibold ${
                                                applyStep >= s ? 'bg-blue-600 text-white' : 'bg-slate-200'
                                            }`}
                                        >
                                            {applyStep > s ? <Check className="h-5 w-5" /> : String(s)}
                                        </div>
                                        <span className="hidden text-sm font-medium sm:inline">
                                            {s === 1 ? 'Buscar cargos' : s === 2 ? 'Estrategia' : 'Confirmar'}
                                        </span>
                                    </div>
                                    {idx < 2 && <div className={`h-0.5 flex-1 ${applyStep > s ? 'bg-blue-600' : 'bg-slate-200'}`} />}
                                </React.Fragment>
                            ))}
                        </div>

                        {status === 'APPLIED' ? (
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="text-sm text-green-700">
                                        Pago ya aplicado. Revisa la pestaña "Asignaciones" para ver lo distribuido.
                                    </div>
                                </CardContent>
                            </Card>
                        ) : !isConfirmed ? (
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="text-sm text-amber-600">Debe estar CONFIRMED para poder aplicar.</div>
                                </CardContent>
                            </Card>
                        ) : (
                            <>
                                {/* Step 1: Load Charges */}
                                {applyStep === 1 && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-lg">🎯 Paso 1: Buscar cargos pendientes</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-6">
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                <div>
                                                    <label className="mb-2 block text-sm font-medium">Moneda</label>
                                                    <Select
                                                        value={filters.currency ?? 'ALL'}
                                                        onValueChange={(v) => setFilters((f) => ({ ...f, currency: v === 'ALL' ? undefined : v }))}
                                                    >
                                                        <SelectTrigger className="h-11">
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
                                                    <label className="mb-2 block text-sm font-medium">Tipo de cargo</label>
                                                    <Select
                                                        value={filters.kind ?? 'ALL'}
                                                        onValueChange={(v) => setFilters((f) => ({ ...f, kind: v === 'ALL' ? undefined : v }))}
                                                    >
                                                        <SelectTrigger className="h-11">
                                                            <SelectValue placeholder="Todos" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="ALL">Todos</SelectItem>
                                                            <SelectItem value="RENT_EUR_M2">Alquiler m²</SelectItem>
                                                            <SelectItem value="RENT_EUR_FIXED">Alquiler fijo</SelectItem>
                                                            <SelectItem value="CONDO_USD">Condominio</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <label className="mb-2 flex items-center gap-2 text-sm font-medium">
                                                        <input
                                                            type="checkbox"
                                                            className="h-4 w-4"
                                                            checked={filters.overdue_only ?? false}
                                                            onChange={(e) =>
                                                                setFilters((f) => ({ ...f, overdue_only: e.target.checked || undefined }))
                                                            }
                                                        />
                                                        Solo cargos vencidos
                                                    </label>
                                                </div>
                                            </div>

                                            <div className="bg-muted/30 grid grid-cols-2 gap-3 rounded-lg border p-4 text-sm">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">Disponible pago</span>
                                                    <strong>Bs {formatMinor(availableMinor)}</strong>
                                                </div>
                                                {creditMinor > 0 && (
                                                    <div className="flex items-center justify-between">
                                                        <label className="flex items-center gap-2">
                                                            <input
                                                                type="checkbox"
                                                                className="h-4 w-4"
                                                                checked={useCredit}
                                                                onChange={(e) => setUseCredit(e.target.checked)}
                                                            />
                                                            <span className="text-muted-foreground">+ Crédito a favor</span>
                                                        </label>
                                                        <strong className="text-amber-600">Bs {formatMinor(creditMinor)}</strong>
                                                    </div>
                                                )}
                                                <div className="col-span-2 flex items-center justify-between border-t pt-2">
                                                    <span className="text-muted-foreground">Total disponible</span>
                                                    <strong className="text-lg text-green-600">Bs {formatMinor(totalAvailable)}</strong>
                                                </div>
                                            </div>

                                            {errors.length > 0 && (
                                                <div className="bg-destructive/10 text-destructive rounded-md p-3 text-sm">
                                                    {errors.map((e, i) => (
                                                        <div key={i}>{e}</div>
                                                    ))}
                                                </div>
                                            )}

                                            <div className="flex items-center justify-between">
                                                <div className="text-muted-foreground text-sm">
                                                    {charges.length > 0 ? (
                                                        <>
                                                            Encontramos <strong>{charges.length}</strong> cargos pendientes
                                                        </>
                                                    ) : (
                                                        'Sin cargos cargados aún'
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={loading}
                                                        onClick={fetchOpenCharges}
                                                        className="gap-2"
                                                    >
                                                        {loading ? 'Buscando...' : 'Buscar cargos'}
                                                        <ArrowRight className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="lg"
                                                        disabled={charges.length === 0}
                                                        onClick={() => setApplyStep(2)}
                                                        className="gap-2"
                                                    >
                                                        Continuar a estrategia
                                                        <ArrowRight className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}

                                {/* Step 2: Choose Strategy */}
                                {applyStep === 2 && (
                                    <div className="space-y-6">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-lg">💡 Paso 2: ¿Cómo quieres distribuir el pago?</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <p className="text-muted-foreground mb-6 text-sm">
                                                    Encontramos <strong>{charges.length} cargos pendientes</strong>. Elige una estrategia para
                                                    distribuir automáticamente o ajusta manualmente.
                                                </p>
                                                <div className="grid gap-6 md:grid-cols-2">
                                                    {/* FIFO Strategy */}
                                                    <button onClick={applyStrategyFifo} className="group text-left">
                                                        <Card className="h-full cursor-pointer border-2 transition-all hover:border-blue-500 hover:shadow-xl">
                                                            <CardContent className="pt-8 pb-6">
                                                                <div className="flex flex-col items-center text-center">
                                                                    <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 transition-transform group-hover:scale-110">
                                                                        <Clock className="h-10 w-10 text-white" />
                                                                    </div>
                                                                    <h3 className="text-foreground mb-2 text-xl font-bold">Por antigüedad</h3>
                                                                    <p className="text-muted-foreground mb-4 text-sm">
                                                                        Paga primero los cargos más antiguos hasta agotar el monto disponible
                                                                    </p>
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        Automático
                                                                    </Badge>
                                                                </div>
                                                            </CardContent>
                                                        </Card>
                                                    </button>

                                                    {/* By Type Strategy */}
                                                    <button onClick={applyStrategyByType} className="group text-left">
                                                        <Card className="h-full cursor-pointer border-2 transition-all hover:border-green-500 hover:shadow-xl">
                                                            <CardContent className="pt-8 pb-6">
                                                                <div className="flex flex-col items-center text-center">
                                                                    <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-green-600 transition-transform group-hover:scale-110">
                                                                        <Grid3x3 className="h-10 w-10 text-white" />
                                                                    </div>
                                                                    <h3 className="text-foreground mb-2 text-xl font-bold">Por tipo de cargo</h3>
                                                                    <p className="text-muted-foreground mb-4 text-sm">
                                                                        Agrupa por tipo (Alquiler, Condominio) y paga cargos completos por categoría
                                                                    </p>
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        Automático
                                                                    </Badge>
                                                                </div>
                                                            </CardContent>
                                                        </Card>
                                                    </button>
                                                </div>

                                                <div className="mt-6 flex items-center justify-between">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() => {
                                                            setApplyStep(1);
                                                            setCharges([]);
                                                            setAmounts({});
                                                        }}
                                                    >
                                                        ← Cambiar filtros
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        onClick={() => {
                                                            setSelectedStrategy(null);
                                                            setApplyStep(3);
                                                        }}
                                                    >
                                                        <Edit3 className="mr-2 h-4 w-4" />
                                                        Lo haré manualmente
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                )}

                                {/* Step 3: Review and Confirm */}
                                {applyStep === 3 && (
                                    <div className="space-y-6">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-lg">✅ Paso 3: Revisa y confirma la distribución</CardTitle>
                                            </CardHeader>
                                            <CardContent className="space-y-6">
                                                {/* Strategy Badge */}
                                                {selectedStrategy && (
                                                    <div className="rounded-lg border bg-blue-50/50 p-3">
                                                        <div className="flex items-center gap-2">
                                                            {selectedStrategy === 'fifo' ? (
                                                                <>
                                                                    <Clock className="h-5 w-5 text-blue-600" />
                                                                    <span className="font-medium text-blue-900">Estrategia: Por antigüedad</span>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Grid3x3 className="h-5 w-5 text-green-600" />
                                                                    <span className="font-medium text-green-900">Estrategia: Por tipo de cargo</span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Inline Summary Card - Horizontal */}
                                                <div className="grid gap-4 rounded-lg border bg-gradient-to-r from-blue-50/50 to-green-50/50 p-4 sm:grid-cols-4">
                                                    <div className="text-center">
                                                        <div className="text-muted-foreground text-xs">A aplicar</div>
                                                        <div className="text-2xl font-bold text-blue-600">Bs {formatMinor(sumRequested)}</div>
                                                    </div>
                                                    <div className="text-center">
                                                        <div className="text-muted-foreground text-xs">Progreso</div>
                                                        <div className="text-2xl font-bold">
                                                            {totalAvailable > 0 ? Math.round((sumRequested / totalAvailable) * 100) : 0}%
                                                        </div>
                                                        <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                                            <div
                                                                className={`h-full transition-all ${
                                                                    sumRequested > totalAvailable
                                                                        ? 'bg-red-500'
                                                                        : sumRequested > totalAvailable * 0.85
                                                                          ? 'bg-amber-500'
                                                                          : 'bg-green-600'
                                                                }`}
                                                                style={{
                                                                    width: `${Math.min(100, totalAvailable > 0 ? (sumRequested / totalAvailable) * 100 : 0)}%`,
                                                                }}
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="text-center">
                                                        <div className="text-muted-foreground text-xs">Cargos</div>
                                                        <div className="text-2xl font-bold">
                                                            {Object.values(amounts).filter((v) => v > 0).length}/{charges.length}
                                                        </div>
                                                    </div>
                                                    <div className="text-center">
                                                        <div className="text-muted-foreground text-xs">Restante</div>
                                                        <div
                                                            className={`text-2xl font-bold ${afterTotalAvailable < 0 ? 'text-red-600' : 'text-green-600'}`}
                                                        >
                                                            Bs {formatMinor(afterTotalAvailable)}
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Warnings */}
                                                {sumRequested > totalAvailable && (
                                                    <div className="rounded-lg border border-red-200 bg-red-50 p-3">
                                                        <div className="flex items-start gap-2">
                                                            <AlertCircle className="h-5 w-5 text-red-600" />
                                                            <div className="text-sm text-red-900">
                                                                <strong>Excede el disponible.</strong> El monto a aplicar (Bs{' '}
                                                                {formatMinor(sumRequested)}) supera el disponible total (Bs{' '}
                                                                {formatMinor(totalAvailable)}). Reduce las asignaciones.
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}

                                                {errors.length > 0 && (
                                                    <div className="bg-destructive/10 text-destructive rounded-md p-3 text-sm">
                                                        {errors.map((e, i) => (
                                                            <div key={i}>{e}</div>
                                                        ))}
                                                    </div>
                                                )}

                                                {/* Charges grouped by type */}
                                                <div className="space-y-4">
                                                    {(() => {
                                                        // Group charges by kind
                                                        const grouped = charges.reduce(
                                                            (acc, c) => {
                                                                const kind = String(c.kind ?? 'OTHER');
                                                                if (!acc[kind]) acc[kind] = [];
                                                                acc[kind].push(c);
                                                                return acc;
                                                            },
                                                            {} as Record<string, any[]>,
                                                        );

                                                        const kindLabels: Record<string, string> = {
                                                            RENT_EUR_M2: 'Alquiler m²',
                                                            RENT_EUR_FIXED: 'Alquiler fijo',
                                                            CONDO_USD: 'Condominio',
                                                            OTHER: 'Otros',
                                                        };

                                                        return Object.entries(grouped).map(([kind, kindCharges]) => {
                                                            const charges = kindCharges as any[];
                                                            const totalKind = charges.reduce(
                                                                (s: number, c: any) => s + Number(amounts[Number(c.charge_id)] ?? 0),
                                                                0,
                                                            );
                                                            const hasAllocations = totalKind > 0;

                                                            return (
                                                                <div key={kind} className="rounded-lg border">
                                                                    <div className="bg-muted/50 flex items-center justify-between p-3">
                                                                        <div className="flex items-center gap-2">
                                                                            <TrendingUp className="text-muted-foreground h-4 w-4" />
                                                                            <span className="font-medium">{kindLabels[kind] || kind}</span>
                                                                            <Badge variant="outline">{charges.length}</Badge>
                                                                        </div>
                                                                        {hasAllocations && (
                                                                            <span className="text-sm font-medium text-blue-600">
                                                                                Bs {formatMinor(totalKind)}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                    <div className="divide-y">
                                                                        {charges.map((c: any) => {
                                                                            const cid = Number(c.charge_id);
                                                                            const outstanding = Number(c.outstanding_bs_minor ?? 0);
                                                                            const val = Number(amounts[cid] ?? 0);
                                                                            const issue = rowIssues[cid] ?? null;
                                                                            const over =
                                                                                val > outstanding || sumRequested > totalAvailable || Boolean(issue);
                                                                            const isOverdue =
                                                                                Boolean(c.due_on) &&
                                                                                new Date(String(c.due_on)) < new Date(String(payment.paid_on));

                                                                            return (
                                                                                <div
                                                                                    key={cid}
                                                                                    className={`grid grid-cols-2 gap-3 p-3 sm:grid-cols-4 ${over ? 'bg-destructive/5' : ''}`}
                                                                                >
                                                                                    <div>
                                                                                        <div className="text-muted-foreground text-xs">
                                                                                            Local / Periodo
                                                                                        </div>
                                                                                        <div className="font-medium">
                                                                                            {String(c.local_label ?? c.local_id ?? '—')}
                                                                                        </div>
                                                                                        <div className="text-muted-foreground text-sm">
                                                                                            {formatShortDate(c.period)}
                                                                                        </div>
                                                                                    </div>
                                                                                    <div>
                                                                                        <div className="text-muted-foreground text-xs">Vence</div>
                                                                                        <div
                                                                                            className={
                                                                                                isOverdue ? 'text-destructive font-medium' : ''
                                                                                            }
                                                                                        >
                                                                                            {formatShortDate(c.due_on)}
                                                                                        </div>
                                                                                        {isOverdue && (
                                                                                            <Badge variant="destructive" className="mt-1 text-xs">
                                                                                                VENCIDO
                                                                                            </Badge>
                                                                                        )}
                                                                                    </div>
                                                                                    <div>
                                                                                        <div className="text-muted-foreground text-xs">
                                                                                            Saldo pendiente
                                                                                        </div>
                                                                                        <div className="font-medium">
                                                                                            {String(c.currency ?? '')}{' '}
                                                                                            {(Number(c.amount_minor ?? 0) / 100).toFixed(2)}
                                                                                        </div>
                                                                                        <div className="text-muted-foreground text-sm">
                                                                                            Bs {formatMinor(outstanding)}
                                                                                        </div>
                                                                                    </div>
                                                                                    <div>
                                                                                        <div className="text-muted-foreground text-xs">
                                                                                            A aplicar (Bs)
                                                                                        </div>
                                                                                        <Input
                                                                                            value={formatMinor(val)}
                                                                                            onChange={(e) => {
                                                                                                const raw = e.target.value ?? '';
                                                                                                const digits = String(raw).replace(/[^0-9]/g, '');
                                                                                                const minor = digits ? parseInt(digits, 10) : 0;
                                                                                                const cap = Math.max(0, Math.min(minor, outstanding));
                                                                                                setAmounts((prev) => ({ ...prev, [cid]: cap }));
                                                                                            }}
                                                                                            inputMode="decimal"
                                                                                            placeholder="0.00"
                                                                                            className="h-9 font-semibold text-blue-600"
                                                                                        />
                                                                                        {issue && (
                                                                                            <div className="text-destructive mt-1 text-xs">
                                                                                                {issue}
                                                                                            </div>
                                                                                        )}
                                                                                    </div>
                                                                                </div>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                </div>
                                                            );
                                                        });
                                                    })()}
                                                </div>

                                                <div className="flex items-center justify-between pt-4">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() => {
                                                            setApplyStep(2);
                                                            setAmounts({});
                                                            setSelectedStrategy(null);
                                                        }}
                                                    >
                                                        ← Cambiar estrategia
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="lg"
                                                        disabled={
                                                            sumRequested === 0 ||
                                                            sumRequested > totalAvailable ||
                                                            Object.values(rowIssues).some((v) => v)
                                                        }
                                                        onClick={previewAndApply}
                                                        className="gap-2"
                                                    >
                                                        Confirmar aplicación
                                                        <ArrowRight className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                )}
                            </>
                        )}
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
