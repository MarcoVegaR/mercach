import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import React from 'react';

function fmtMinor(minor?: number | null) {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: 'VES', minimumFractionDigits: 2 });
}

type PaymentVM = {
    id: number;
    status: string;
    paid_on: string;
    amount_bs_minor: number;
    applied_bs_minor: number;
    available_bs_minor: number;
};

type Props = {
    payment: PaymentVM;
    customer_credit_bs_minor?: number;
};

export default function PortalPaymentsApply() {
    const { payment, customer_credit_bs_minor = 0 } = usePage<Props>().props;
    const [charges, setCharges] = React.useState<Array<any>>([]);
    const [amounts, setAmounts] = React.useState<Record<number, number>>({});
    const [errors, setErrors] = React.useState<Array<string>>([]);
    const [rowIssues, setRowIssues] = React.useState<Record<number, string | null>>({});
    const [_loading, setLoading] = React.useState<boolean>(false);
    const [useCredit, setUseCredit] = React.useState<boolean>(false);
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

    const sumRequested = React.useMemo(() => Object.values(amounts).reduce((a, b) => a + (Number(b) || 0), 0), [amounts]);
    const totalAvailable = payment.available_bs_minor + (useCredit ? Number(customer_credit_bs_minor || 0) : 0);
    const afterTotal = Math.max(0, totalAvailable - sumRequested);

    const getCookie = (name: string) => {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return decodeURIComponent(parts.pop()!.split(';').shift() || '');
        return '';
    };

    const fetchOpenCharges = React.useCallback(async () => {
        setLoading(true);
        setErrors([]);
        try {
            const qs = new URLSearchParams();
            if (filters.currency) qs.set('currency', String(filters.currency));
            if (filters.kind) qs.set('kind', String(filters.kind));
            if (filters.period_from) qs.set('period_from', String(filters.period_from));
            if (filters.period_to) qs.set('period_to', String(filters.period_to));
            if (filters.overdue_only) qs.set('overdue_only', '1');
            const res = await fetch(`/portal/pagos/${payment.id}/open-charges?${qs.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('open_charges_failed');
            const js = await res.json();
            setCharges(Array.isArray(js.items) ? js.items : []);
            setAmounts({});
            setRowIssues({});
        } catch {
            setErrors(['No se pudieron obtener los cargos abiertos.']);
        } finally {
            setLoading(false);
        }
    }, [payment, filters]);

    React.useEffect(() => {
        fetchOpenCharges();
    }, [fetchOpenCharges]);

    const suggest = React.useCallback(
        async (strategy: 'fifo' | 'proportional') => {
            setErrors([]);
            try {
                const res = await fetch(`/portal/pagos/${payment.id}/allocations/suggest`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                        'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ strategy, ...filters }),
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
                setErrors(['No se pudo obtener sugerencia.']);
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
            const resPrev = await fetch(`/portal/pagos/${payment.id}/allocations/preview`, {
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
            const key = `portal-pay-${payment.id}-${Date.now()}`;
            router.post(
                `/portal/pagos/${payment.id}/allocations`,
                { items, idempotency_key: key, use_credit: useCredit ? 1 : 0 },
                {
                    preserveScroll: true,
                    onSuccess: () => router.visit(`/portal/pagos/${payment.id}/cruzar`, { preserveScroll: true, replace: true }),
                },
            );
        } catch {
            setErrors(['Error al aplicar el pago.']);
        }
    }, [amounts, payment, useCredit]);

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

    return (
        <AppLayout>
            <Head title={`Cruzar pago #${payment.id}`} />
            <div className="container mx-auto max-w-5xl px-4 py-8">
                <div className="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Cruzar pago</h1>
                        <p className="text-muted-foreground mt-2">
                            Pago #{payment.id} • Pagado el {payment.paid_on}
                        </p>
                    </div>
                    <Link href="/portal/pagos">
                        <Button variant="outline" size="sm">
                            Volver a mis pagos
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-4 md:grid-cols-12">
                    <div className="space-y-4 md:col-span-8">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Filtros</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <FilterSheet
                                    activeFiltersCount={activeFiltersCount}
                                    onApplyFilters={() => fetchOpenCharges()}
                                    onClearFilters={() => {
                                        setFilters({});
                                        fetchOpenCharges();
                                    }}
                                    title="Filtros"
                                    description="Refina la lista de cargos abiertos"
                                >
                                    <div className="space-y-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="text-muted-foreground mb-1 block text-sm">Moneda</label>
                                                <input
                                                    className="w-full rounded border px-2 py-1"
                                                    placeholder="USD/EUR"
                                                    value={filters.currency || ''}
                                                    onChange={(e) => setFilters((f) => ({ ...f, currency: e.target.value || undefined }))}
                                                />
                                            </div>
                                            <div>
                                                <label className="text-muted-foreground mb-1 block text-sm">Tipo</label>
                                                <input
                                                    className="w-full rounded border px-2 py-1"
                                                    placeholder="CONDO/RENT/..."
                                                    value={filters.kind || ''}
                                                    onChange={(e) => setFilters((f) => ({ ...f, kind: e.target.value || undefined }))}
                                                />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="text-muted-foreground mb-1 block text-sm">Desde (período)</label>
                                                <input
                                                    type="month"
                                                    className="w-full rounded border px-2 py-1"
                                                    value={filters.period_from || ''}
                                                    onChange={(e) => setFilters((f) => ({ ...f, period_from: e.target.value || undefined }))}
                                                />
                                            </div>
                                            <div>
                                                <label className="text-muted-foreground mb-1 block text-sm">Hasta (período)</label>
                                                <input
                                                    type="month"
                                                    className="w-full rounded border px-2 py-1"
                                                    value={filters.period_to || ''}
                                                    onChange={(e) => setFilters((f) => ({ ...f, period_to: e.target.value || undefined }))}
                                                />
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <input
                                                id="overdue_only"
                                                type="checkbox"
                                                checked={!!filters.overdue_only}
                                                onChange={(e) => setFilters((f) => ({ ...f, overdue_only: e.target.checked || undefined }))}
                                            />
                                            <label htmlFor="overdue_only" className="text-sm">
                                                Sólo vencidos
                                            </label>
                                        </div>
                                    </div>
                                </FilterSheet>
                                <div className="mt-3">
                                    <FilterBadges badges={filterBadges} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Cargos abiertos</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-muted-foreground border-b">
                                                <th className="py-2 pr-4 text-left">Periodo</th>
                                                <th className="py-2 pr-4 text-left">Vence</th>
                                                <th className="py-2 pr-4 text-left">Moneda</th>
                                                <th className="py-2 pr-4 text-right">Pendiente (moneda)</th>
                                                <th className="py-2 pr-4 text-right">Pendiente (VES)</th>
                                                <th className="py-2 pr-0 text-right">Aplicar (VES)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {Array.isArray(charges) && charges.length > 0 ? (
                                                charges.map((c: any, i: number) => {
                                                    const cid = Number(c.charge_id);
                                                    const val = Number(amounts[cid] || 0);
                                                    const issue = rowIssues[cid] || null;
                                                    return (
                                                        <tr key={i} className="border-b/50">
                                                            <td className="py-2 pr-4">{String(c.period ?? '')}</td>
                                                            <td className="py-2 pr-4">{String(c.due_on ?? '')}</td>
                                                            <td className="py-2 pr-4">{String(c.currency ?? '')}</td>
                                                            <td className="py-2 pr-4 text-right">
                                                                {fmtMinor(Number(c.outstanding_minor || c.amount_minor || 0))}
                                                            </td>
                                                            <td className="py-2 pr-4 text-right">
                                                                {fmtMinor(Number(c.outstanding_bs_minor || c.amount_bs_minor || 0))}
                                                            </td>
                                                            <td className="py-2 pr-0 text-right">
                                                                <input
                                                                    className={`w-32 rounded border px-2 py-1 text-right ${issue ? 'border-red-500' : ''}`}
                                                                    type="number"
                                                                    min={0}
                                                                    step={1}
                                                                    value={Number.isFinite(val) ? val : 0}
                                                                    onChange={(e) =>
                                                                        setAmounts((prev) => ({
                                                                            ...prev,
                                                                            [cid]: Math.max(0, Math.floor(Number(e.target.value || '0'))),
                                                                        }))
                                                                    }
                                                                />
                                                                {issue && <div className="mt-1 text-xs text-red-600">{issue}</div>}
                                                            </td>
                                                        </tr>
                                                    );
                                                })
                                            ) : (
                                                <tr>
                                                    <td className="text-muted-foreground py-4" colSpan={6}>
                                                        Sin cargos abiertos
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>

                        {errors.length > 0 && (
                            <Card>
                                <CardContent className="space-y-2 pt-6">
                                    {errors.map((e, i) => (
                                        <div className="text-sm text-red-600" key={i}>
                                            {e}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="md:col-span-4">
                        <Card className="sticky top-4">
                            <CardHeader>
                                <CardTitle className="text-base">Resumen</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Estado</span>
                                    <span className="text-sm">{String(payment.status || '')}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Monto</span>
                                    <span className="text-sm">{fmtMinor(payment.amount_bs_minor)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Asignado</span>
                                    <span className="text-sm">{fmtMinor(payment.applied_bs_minor)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Disponible</span>
                                    <span className="text-sm">{fmtMinor(payment.available_bs_minor)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Crédito a favor</span>
                                    <span className="text-sm">{fmtMinor(Number(customer_credit_bs_minor || 0))}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Aplicar ahora</span>
                                    <span className="text-sm">{fmtMinor(sumRequested)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Neto tras aplicar</span>
                                    <span className="text-sm">{fmtMinor(afterTotal)}</span>
                                </div>

                                <div className="space-y-2 pt-2">
                                    <div className="flex items-center gap-2">
                                        <input id="useCredit" type="checkbox" checked={useCredit} onChange={(e) => setUseCredit(e.target.checked)} />
                                        <label htmlFor="useCredit" className="text-sm">
                                            Usar crédito a favor
                                        </label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button variant="secondary" size="sm" onClick={() => suggest('fifo')}>
                                            Sugerir FIFO
                                        </Button>
                                        <Button variant="secondary" size="sm" onClick={() => suggest('proportional')}>
                                            Sugerir proporcional
                                        </Button>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button size="sm" onClick={previewAndApply} disabled={sumRequested <= 0}>
                                            Aplicar
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={() => setAmounts({})}>
                                            Limpiar
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
