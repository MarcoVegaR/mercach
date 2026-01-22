import { ShowLayout } from '@/components/show-base/ShowLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, CreditCard, DollarSign, Euro } from 'lucide-react';
import React from 'react';

type Summary = {
    open_bs_minor: number;
    overdue_bs_minor: number;
    payments_available_bs_minor: number;
    credits_open_bs_minor: number;
    net_due_after_credit_bs_minor: number;
    aging?: { '0_30': number; '31_60': number; '61_90': number; '90_plus': number };
};

type Header = {
    id: number;
    full_name: string;
    document?: { type_code?: string; number?: string };
    contracts_count?: number;
    locals_count?: number;
};

type ChargeRow = {
    charge_id: number;
    local_id?: number;
    local_label?: string | null;
    period: string;
    due_on?: string;
    currency: string;
    amount_minor: number;
    amount_bs_minor: number | null;
    allocated_bs_minor: number;
    credited_bs_minor: number;
    outstanding_bs_minor: number;
    outstanding_minor: number;
    kind?: string;
};

type PaymentPartial = {
    payment_id: number;
    paid_on?: string;
    status?: string | null;
    applied_bs_minor: number;
    available_bs_minor: number;
};

type CreditOpen = {
    credit_id: number;
    balance_minor: number;
    source_payment_id?: number;
    created_at?: string;
};

type Props = {
    header: Header;
    summary_bs: Summary;
    summary_fx?: {
        condo?: { currency: 'USD'; open_minor: number; overdue_minor: number; rate_to_ves?: number };
        rent?: { currency: 'EUR'; open_minor: number; overdue_minor: number; rate_to_ves?: number };
        rent_m2?: { currency: 'EUR'; open_minor: number; overdue_minor: number; rate_to_ves?: number };
        rent_fixed?: { currency: 'USD'; open_minor: number; overdue_minor: number; rate_to_ves?: number };
    };
    by_local: Array<{
        local_id: number;
        local_label?: string | null;
        currency: string;
        open_bs_minor: number;
        overdue_bs_minor: number;
        partial_applied_bs_minor: number;
        net_due_bs_minor: number;
        open_minor: number;
        overdue_minor: number;
    }>;
    tables: {
        charges_open: ChargeRow[];
        payments_partial: PaymentPartial[];
        credits_open: CreditOpen[];
    };
    recent?: Array<{ date: string; kind: string; description: string; amount_bs_minor: number; ref_id: number }>;
};

function fmtBs(n?: number | null) {
    if (typeof n !== 'number') return '-';
    return (n / 100).toLocaleString(undefined, { style: 'currency', currency: 'VES', minimumFractionDigits: 2 });
}

function fmtPaidOnDate(d?: string | null): string {
    if (!d) return '';
    try {
        const safe = /^\d{4}-\d{2}-\d{2}$/.test(d) ? new Date(`${d}T12:00:00Z`) : new Date(d);
        return new Intl.DateTimeFormat('es-VE', {
            timeZone: 'America/Caracas',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(safe);
    } catch {
        return String(d);
    }
}

function fmt(minor?: number | null, curr: 'USD' | 'EUR' | 'VES' = 'VES') {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: curr, minimumFractionDigits: 2 });
}

// Apply same FX policy as backend: truncate to 2 decimals (no rounding)
function fxBsMinorTruncate(amountMinor?: number | null, rate?: number | null): number {
    if (typeof amountMinor !== 'number' || amountMinor <= 0) return 0;
    if (typeof rate !== 'number' || rate <= 0) return 0;

    const rateMinor = Math.round(rate * 100); // tasa 283.50 -> 28350
    if (rateMinor <= 0) return 0;

    const prod = amountMinor * rateMinor; // 2dp * 2dp -> 4dp implícitos

    return Math.trunc(prod / 100); // truncar a 2 decimales
}

function formatPeriod(v?: string | null): string {
    if (!v) return '—';
    try {
        return new Date(v).toLocaleDateString('es-ES', { year: 'numeric', month: 'short' });
    } catch {
        return String(v);
    }
}

function friendlyKind(kind?: string | null): string {
    const k = (kind || '').toUpperCase();
    switch (k) {
        case 'RENT_EUR_M2':
            return 'Tasa de uso';
        case 'RENT_EUR_FIXED':
            return 'Alquiler fijo';
        case 'CONDO_USD':
            return 'Condominio';
        default:
            return (kind || '').replace(/_/g, ' ');
    }
}

function nameOnly(label?: string | null): string {
    if (!label) return '—';
    const parts = label.split('•');
    return (parts[parts.length - 1] || '').trim() || label;
}

export default function EconomicProfileConcessionaire(props: Props) {
    const { header, summary_bs, summary_fx, by_local, tables, recent: _recent } = props;
    const [activeTab, setActiveTab] = React.useState('resumen');
    const [kindFilter, setKindFilter] = React.useState<'all' | 'CONDO' | 'RENT'>('all');
    const [overdueFilter, setOverdueFilter] = React.useState(false);
    const [localFilter, setLocalFilter] = React.useState<number | 'all'>('all');
    const [selected, setSelected] = React.useState<Record<number, boolean>>({});

    const localOptions = React.useMemo(() => {
        const opts = (by_local || [])
            .map((l) => ({ id: l.local_id, label: l.local_label || String(l.local_id) }))
            .sort((a, b) => (a.label || '').localeCompare(b.label || ''));

        return opts;
    }, [by_local]);

    const atParam = React.useMemo(() => {
        if (typeof window === 'undefined') return new Date().toISOString().slice(0, 10);
        const p = new URLSearchParams(window.location.search);
        return p.get('at') || new Date().toISOString().slice(0, 10);
    }, []);
    const exportUrl = (format: 'csv' | 'json') =>
        `/admin/economic-profile/export?scope=concessionaire&id=${header.id}&format=${format}&at=${encodeURIComponent(atParam)}`;

    const condoDebt = summary_fx?.condo?.open_minor ?? 0;
    const rentM2Debt = summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor ?? 0;
    const rentFixedDebt = summary_fx?.rent_fixed?.open_minor ?? 0;
    const hasDebt = condoDebt > 0 || rentM2Debt > 0 || rentFixedDebt > 0;
    const condoRate = summary_fx?.condo?.rate_to_ves ?? null;
    const rentM2Rate = summary_fx?.rent_m2?.rate_to_ves ?? summary_fx?.rent?.rate_to_ves ?? null;
    const rentFixedRate = summary_fx?.rent_fixed?.rate_to_ves ?? null;
    const condoOpenBs = fxBsMinorTruncate(condoDebt, condoRate);
    const condoOverdueBs = fxBsMinorTruncate(summary_fx?.condo?.overdue_minor ?? 0, condoRate);
    const rentOpenBs = fxBsMinorTruncate(rentM2Debt, rentM2Rate);
    const rentOverdueBs = fxBsMinorTruncate(summary_fx?.rent_m2?.overdue_minor ?? summary_fx?.rent?.overdue_minor ?? 0, rentM2Rate);
    const rentFixedOpenBs = fxBsMinorTruncate(rentFixedDebt, rentFixedRate);
    const rentFixedOverdueBs = fxBsMinorTruncate(summary_fx?.rent_fixed?.overdue_minor ?? 0, rentFixedRate);

    const filteredCharges = React.useMemo(() => {
        let filtered = tables.charges_open;
        if (kindFilter !== 'all') {
            filtered = filtered.filter((c) => (c.kind ?? '').toUpperCase().startsWith(kindFilter));
        }
        if (overdueFilter) {
            filtered = filtered.filter((c) => c.due_on && new Date(c.due_on) < new Date());
        }
        if (localFilter !== 'all') {
            filtered = filtered.filter((c) => (c.local_id ?? 0) === localFilter);
        }
        return filtered;
    }, [tables.charges_open, kindFilter, overdueFilter, localFilter]);

    const totalFilteredBs = React.useMemo(() => {
        return filteredCharges.reduce((acc, c) => acc + (Number((c as any).outstanding_bs_minor ?? 0) || 0), 0);
    }, [filteredCharges]);

    const selectedTotalBs = React.useMemo(() => {
        return filteredCharges.reduce((acc, c) => acc + (selected[(c as any).charge_id] ? Number((c as any).outstanding_bs_minor ?? 0) : 0), 0);
    }, [filteredCharges, selected]);

    const selectedCount = React.useMemo(() => {
        let cnt = 0;
        filteredCharges.forEach((c) => {
            if (selected[(c as any).charge_id]) cnt++;
        });
        return cnt;
    }, [filteredCharges, selected]);

    const allSelected = React.useMemo(
        () => filteredCharges.length > 0 && filteredCharges.every((c) => !!selected[(c as any).charge_id]),
        [filteredCharges, selected],
    );
    const toggleAll = (checked: boolean) => {
        setSelected((prev) => {
            const map: Record<number, boolean> = { ...prev };
            if (checked) {
                filteredCharges.forEach((c) => {
                    map[(c as any).charge_id] = true;
                });
            } else {
                filteredCharges.forEach((c) => {
                    delete map[(c as any).charge_id];
                });
            }
            return map;
        });
    };
    const toggleOne = (id: number) => {
        setSelected((prev) => {
            const map = { ...prev } as Record<number, boolean>;
            if (map[id]) delete map[id];
            else map[id] = true;
            return map;
        });
    };
    const clearSelection = () => setSelected({});

    const atDate = React.useMemo(() => new Date(atParam), [atParam]);
    const overdueMonthsByLocal = React.useMemo(() => {
        const map: Record<number, number> = {};
        tables.charges_open.forEach((c) => {
            const lid = c.local_id ?? 0;
            const outstanding = (c as any).outstanding_bs_minor ?? 0;
            if (c.due_on && new Date(c.due_on) < atDate && outstanding > 0) {
                map[lid] = (map[lid] || 0) + 1;
            }
        });
        return map;
    }, [tables.charges_open, atDate]);

    return (
        <ShowLayout
            header={
                <div className="flex items-center gap-4">
                    <Link href="/admin/economic-profile" className="text-muted-foreground hover:text-foreground transition-colors">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Perfil Económico</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {header.full_name} · {header.document?.type_code}
                            {header.document?.number ? header.document.number : ''}
                        </p>
                    </div>
                </div>
            }
            actions={
                <div className="flex gap-2">
                    {hasDebt && (
                        <Button onClick={() => router.visit('/payments/create')} className="gap-2">
                            <CreditCard className="h-4 w-4" />
                            Registrar Pago
                        </Button>
                    )}
                    <a
                        href={exportUrl('csv')}
                        className="bg-background hover:bg-muted/40 inline-flex items-center rounded-md border px-3 py-2 text-sm"
                    >
                        CSV
                    </a>
                    <a
                        href={exportUrl('json')}
                        className="bg-background hover:bg-muted/40 inline-flex items-center rounded-md border px-3 py-2 text-sm"
                    >
                        JSON
                    </a>
                </div>
            }
            aside={
                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Deuda Total</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {summary_fx?.condo && summary_fx.condo.open_minor > 0 && (
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <DollarSign className="text-muted-foreground h-4 w-4" />
                                        <span className="text-muted-foreground text-sm">Condominio</span>
                                    </div>
                                    <span className="text-lg font-semibold">{fmt(summary_fx.condo.open_minor, 'USD')}</span>
                                </div>
                            )}
                            {(summary_fx?.rent_m2 || summary_fx?.rent) &&
                                (summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor ?? 0) > 0 && (
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Euro className="text-muted-foreground h-4 w-4" />
                                            <span className="text-muted-foreground text-sm">Alquiler m²</span>
                                        </div>
                                        <span className="text-lg font-semibold">
                                            {fmt(summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor, 'EUR')}
                                        </span>
                                    </div>
                                )}
                            {summary_fx?.rent_fixed && summary_fx.rent_fixed.open_minor > 0 && (
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <DollarSign className="text-muted-foreground h-4 w-4" />
                                        <span className="text-muted-foreground text-sm">Alquiler fijo</span>
                                    </div>
                                    <span className="text-lg font-semibold">{fmt(summary_fx.rent_fixed.open_minor, 'USD')}</span>
                                </div>
                            )}
                            {!hasDebt && <p className="text-muted-foreground text-sm">Sin deuda abierta</p>}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Info</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">Locales</span>
                                <span>{header.locals_count ?? '—'}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">Contratos</span>
                                <span>{header.contracts_count ?? '—'}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">Créditos</span>
                                <span>{fmtBs(summary_bs.credits_open_bs_minor)}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            }
        >
            <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
                <TabsList className="grid w-full grid-cols-4">
                    <TabsTrigger value="resumen">Resumen</TabsTrigger>
                    <TabsTrigger value="cargos">Cargos</TabsTrigger>
                    <TabsTrigger value="pagos">Pagos</TabsTrigger>
                    <TabsTrigger value="locales">Por Local</TabsTrigger>
                </TabsList>

                <TabsContent value="resumen" className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {summary_fx?.condo && summary_fx.condo.open_minor > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Condominio</CardTitle>
                                    <DollarSign className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{fmt(summary_fx.condo.open_minor, 'USD')}</div>
                                    <p className="text-muted-foreground text-xs">Vencido: {fmt(summary_fx.condo.overdue_minor, 'USD')}</p>
                                </CardContent>
                            </Card>
                        )}
                        {summary_fx?.condo && summary_fx.condo.open_minor > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Condominio — Equivalente VES</CardTitle>
                                    <DollarSign className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{fmtBs(condoOpenBs)}</div>
                                    <p className="text-muted-foreground text-xs">Vencido: {fmtBs(condoOverdueBs)}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Tasa:{' '}
                                        {typeof condoRate === 'number'
                                            ? condoRate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                            : '—'}{' '}
                                        VES/USD
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                        {(summary_fx?.rent_m2 || summary_fx?.rent) && (summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor ?? 0) > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Alquiler m²</CardTitle>
                                    <Euro className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {fmt(summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor, 'EUR')}
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        Vencido: {fmt(summary_fx?.rent_m2?.overdue_minor ?? summary_fx?.rent?.overdue_minor, 'EUR')}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                        {(summary_fx?.rent_m2 || summary_fx?.rent) && (summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor ?? 0) > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Alquiler m² — Equivalente VES</CardTitle>
                                    <DollarSign className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{fmtBs(rentOpenBs)}</div>
                                    <p className="text-muted-foreground text-xs">Vencido: {fmtBs(rentOverdueBs)}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Tasa:{' '}
                                        {typeof rentM2Rate === 'number'
                                            ? rentM2Rate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                            : '—'}{' '}
                                        VES/EUR
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                        {summary_fx?.rent_fixed && summary_fx.rent_fixed.open_minor > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Alquiler fijo</CardTitle>
                                    <DollarSign className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{fmt(summary_fx.rent_fixed.open_minor, 'USD')}</div>
                                    <p className="text-muted-foreground text-xs">Vencido: {fmt(summary_fx.rent_fixed.overdue_minor, 'USD')}</p>
                                </CardContent>
                            </Card>
                        )}
                        {summary_fx?.rent_fixed && summary_fx.rent_fixed.open_minor > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Alquiler fijo — Equivalente VES</CardTitle>
                                    <DollarSign className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{fmtBs(rentFixedOpenBs)}</div>
                                    <p className="text-muted-foreground text-xs">Vencido: {fmtBs(rentFixedOverdueBs)}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Tasa:{' '}
                                        {typeof rentFixedRate === 'number'
                                            ? rentFixedRate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                            : '—'}{' '}
                                        VES/USD
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Métricas Adicionales (VES)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">Pagos disponibles</dt>
                                    <dd className="font-semibold">{fmtBs(summary_bs.payments_available_bs_minor)}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Créditos</dt>
                                    <dd className="font-semibold">{fmtBs(summary_bs.credits_open_bs_minor)}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="locales">
                    {(summary_fx?.condo?.open_minor ||
                        summary_fx?.rent_m2?.open_minor ||
                        summary_fx?.rent_fixed?.open_minor ||
                        summary_fx?.rent?.open_minor) && (
                        <Card className="mb-4">
                            <CardHeader>
                                <CardTitle className="text-base">Totales (suma de locales)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {summary_fx?.condo && summary_fx.condo.open_minor > 0 && (
                                        <div>
                                            <div className="text-muted-foreground text-xs">Condominio</div>
                                            <div className="text-lg font-semibold">
                                                {fmt(summary_fx.condo.open_minor, 'USD')}{' '}
                                                <span className="text-muted-foreground text-xs">
                                                    (Vencido: {fmt(summary_fx.condo.overdue_minor, 'USD')})
                                                </span>
                                            </div>
                                            <div className="text-sm">
                                                {fmtBs(condoOpenBs)}{' '}
                                                <span className="text-muted-foreground text-xs">(Vencido: {fmtBs(condoOverdueBs)})</span>
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                Tasa:{' '}
                                                {typeof condoRate === 'number'
                                                    ? condoRate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                                    : '—'}{' '}
                                                VES/USD
                                            </div>
                                        </div>
                                    )}
                                    {(summary_fx?.rent_m2 || summary_fx?.rent) &&
                                        (summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor ?? 0) > 0 && (
                                            <div>
                                                <div className="text-muted-foreground text-xs">Alquiler m²</div>
                                                <div className="text-lg font-semibold">
                                                    {fmt(summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor, 'EUR')}{' '}
                                                    <span className="text-muted-foreground text-xs">
                                                        (Vencido: {fmt(summary_fx?.rent_m2?.overdue_minor ?? summary_fx?.rent?.overdue_minor, 'EUR')})
                                                    </span>
                                                </div>
                                                <div className="text-sm">
                                                    {fmtBs(rentOpenBs)}{' '}
                                                    <span className="text-muted-foreground text-xs">(Vencido: {fmtBs(rentOverdueBs)})</span>
                                                </div>
                                                <div className="text-muted-foreground text-xs">
                                                    Tasa:{' '}
                                                    {typeof rentM2Rate === 'number'
                                                        ? rentM2Rate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                                        : '—'}{' '}
                                                    VES/EUR
                                                </div>
                                            </div>
                                        )}
                                    {summary_fx?.rent_fixed && summary_fx.rent_fixed.open_minor > 0 && (
                                        <div>
                                            <div className="text-muted-foreground text-xs">Alquiler fijo</div>
                                            <div className="text-lg font-semibold">
                                                {fmt(summary_fx.rent_fixed.open_minor, 'USD')}{' '}
                                                <span className="text-muted-foreground text-xs">
                                                    (Vencido: {fmt(summary_fx.rent_fixed.overdue_minor, 'USD')})
                                                </span>
                                            </div>
                                            <div className="text-sm">
                                                {fmtBs(rentFixedOpenBs)}{' '}
                                                <span className="text-muted-foreground text-xs">(Vencido: {fmtBs(rentFixedOverdueBs)})</span>
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                Tasa:{' '}
                                                {typeof rentFixedRate === 'number'
                                                    ? rentFixedRate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                                    : '—'}{' '}
                                                VES/USD
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Totales por Local</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-3 py-2 text-left">Local</th>
                                        <th className="px-3 py-2 text-left">Moneda</th>
                                        <th className="px-3 py-2 text-right">Abierto</th>
                                        <th className="px-3 py-2 text-right">Vencido</th>
                                        <th className="px-3 py-2 text-right">Meses vencidos</th>
                                        <th className="px-3 py-2 text-right">Ref. VES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {by_local.map((r) => {
                                        const currency = (r.currency || 'VES').toUpperCase() as 'USD' | 'EUR' | 'VES';
                                        return (
                                            <tr key={r.local_id} className="border-t">
                                                <td className="px-3 py-2 font-medium">{nameOnly(r.local_label) || String(r.local_id)}</td>
                                                <td className="px-3 py-2">
                                                    <span
                                                        className={`rounded px-2 py-1 text-xs ${currency === 'USD' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : currency === 'EUR' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-700'}`}
                                                    >
                                                        {currency}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold">{fmt(r.open_minor, currency)}</td>
                                                <td className="px-3 py-2 text-right">{fmt(r.overdue_minor, currency)}</td>
                                                <td className="px-3 py-2 text-right">{overdueMonthsByLocal[r.local_id] ?? 0}</td>
                                                <td className="text-muted-foreground px-3 py-2 text-right text-xs">{fmtBs(r.open_bs_minor)}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="cargos">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base">Cargos abiertos</CardTitle>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button size="sm" variant={kindFilter === 'all' ? 'default' : 'outline'} onClick={() => setKindFilter('all')}>
                                        Todos
                                    </Button>
                                    <Button size="sm" variant={kindFilter === 'CONDO' ? 'default' : 'outline'} onClick={() => setKindFilter('CONDO')}>
                                        Condominio
                                    </Button>
                                    <Button size="sm" variant={kindFilter === 'RENT' ? 'default' : 'outline'} onClick={() => setKindFilter('RENT')}>
                                        Alquiler
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant={overdueFilter ? 'destructive' : 'outline'}
                                        onClick={() => setOverdueFilter(!overdueFilter)}
                                    >
                                        Solo vencidos
                                    </Button>
                                    <div className="ml-2 flex items-center gap-2">
                                        <label className="text-muted-foreground text-sm" htmlFor="local-filter">
                                            Local
                                        </label>
                                        <select
                                            id="local-filter"
                                            className="border-input bg-background h-8 rounded-md border px-2 text-sm"
                                            value={localFilter === 'all' ? 'all' : String(localFilter)}
                                            onChange={(e) => {
                                                const v = e.target.value;
                                                setLocalFilter(v === 'all' ? 'all' : Number(v));
                                            }}
                                        >
                                            <option value="all">Todos</option>
                                            {localOptions.map((o) => (
                                                <option key={o.id} value={o.id}>
                                                    {o.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm">
                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground">Total filtrado</span>
                                    <span className="font-semibold">{fmtBs(totalFilteredBs)}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground">Seleccionado</span>
                                    <span className="font-semibold">
                                        {fmtBs(selectedTotalBs)} ({selectedCount})
                                    </span>
                                </div>
                                <div className="ml-auto flex items-center gap-2">
                                    <Button size="sm" variant="outline" onClick={() => toggleAll(true)}>
                                        Seleccionar visibles
                                    </Button>
                                    <Button size="sm" variant="outline" onClick={clearSelection}>
                                        Limpiar selección
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-3 py-2 text-left">
                                            <input type="checkbox" checked={allSelected} onChange={(e) => toggleAll(e.target.checked)} />
                                        </th>
                                        <th className="px-3 py-2 text-left">Tipo</th>
                                        <th className="px-3 py-2 text-left">Local</th>
                                        <th className="px-3 py-2 text-left">Periodo</th>
                                        <th className="px-3 py-2 text-left">Vence</th>
                                        <th className="px-3 py-2 text-right">Monto</th>
                                        <th className="px-3 py-2 text-right">Ref. VES</th>
                                        <th className="px-3 py-2 text-right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredCharges.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="text-muted-foreground px-3 py-8 text-center">
                                                No hay cargos con los filtros seleccionados
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredCharges.map((c) => {
                                            const kind = (c.kind ?? '').toUpperCase();
                                            const isCondo = kind.startsWith('CONDO');
                                            const isRent = kind.startsWith('RENT');
                                            const currency = (c.currency || 'VES').toUpperCase() as 'USD' | 'EUR' | 'VES';
                                            const isOverdue = c.due_on && new Date(c.due_on) < new Date();
                                            return (
                                                <tr key={c.charge_id} className={`border-t ${isOverdue ? 'bg-red-50 dark:bg-red-950/10' : ''}`}>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="checkbox"
                                                            checked={!!selected[(c as any).charge_id]}
                                                            onChange={() => toggleOne((c as any).charge_id)}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <span
                                                            className={`rounded px-2 py-1 text-xs ${isCondo ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : isRent ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-700'}`}
                                                        >
                                                            {friendlyKind(kind)}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2">{(c as any).local_label || (c.local_id ?? '')}</td>
                                                    <td className="px-3 py-2">{formatPeriod(c.period)}</td>
                                                    <td className="px-3 py-2">{c.due_on ? new Date(c.due_on).toLocaleDateString() : ''}</td>
                                                    <td className="px-3 py-2 text-right font-medium">{fmt(c.outstanding_minor, currency)}</td>
                                                    <td className="text-muted-foreground px-3 py-2 text-right text-xs">
                                                        {fmtBs(c.outstanding_bs_minor)}
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-semibold">{fmt(c.outstanding_minor, currency)}</td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="pagos" className="space-y-4">
                    <Card>
                        <CardContent className="overflow-x-auto pt-6">
                            <table className="min-w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-3 py-2 text-left">Pago</th>
                                        <th className="px-3 py-2 text-left">Fecha</th>
                                        <th className="px-3 py-2 text-left">Status</th>
                                        <th className="px-3 py-2 text-right">Aplicado</th>
                                        <th className="px-3 py-2 text-right">Disponible</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tables.payments_partial.map((p) => (
                                        <tr key={p.payment_id} className="border-t">
                                            <td className="px-3 py-2">#{p.payment_id}</td>
                                            <td className="px-3 py-2">{fmtPaidOnDate(p.paid_on)}</td>
                                            <td className="px-3 py-2">{p.status}</td>
                                            <td className="px-3 py-2 text-right">{fmtBs(p.applied_bs_minor)}</td>
                                            <td className="px-3 py-2 text-right">{fmtBs(p.available_bs_minor)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Créditos abiertos</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-3 py-2 text-left">Crédito</th>
                                        <th className="px-3 py-2 text-left">Origen</th>
                                        <th className="px-3 py-2 text-right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tables.credits_open.map((c) => (
                                        <tr key={c.credit_id} className="border-t">
                                            <td className="px-3 py-2">#{c.credit_id}</td>
                                            <td className="px-3 py-2">{c.source_payment_id ? `Pago #${c.source_payment_id}` : ''}</td>
                                            <td className="px-3 py-2 text-right">{fmtBs(c.balance_minor)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </ShowLayout>
    );
}

EconomicProfileConcessionaire.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
