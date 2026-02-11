import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    CreditCard,
    DollarSign,
    Download,
    Euro,
    FileText,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import React from 'react';

type Summary = {
    open_bs_minor: number;
    overdue_bs_minor: number;
    payments_available_bs_minor: number;
    credits_open_bs_minor: number;
    net_due_after_credit_bs_minor: number;
    open_bs_minor_from_fx?: number;
    overdue_bs_minor_from_fx?: number;
    net_due_after_credit_bs_minor_from_fx?: number;
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

type LocalSummary = {
    local_id: number;
    local_label?: string | null;
    currency: string;
    open_bs_minor: number;
    overdue_bs_minor: number;
    partial_applied_bs_minor: number;
    net_due_bs_minor: number;
    open_minor: number;
    overdue_minor: number;
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
    by_local: LocalSummary[];
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
        case 'FINE':
            return 'Cargo por multa';
        case 'ADJ':
            return 'Gasto Fijo de Mantenimiento';
        default:
            return (kind || '').replace(/_/g, ' ');
    }
}

function nameOnly(label?: string | null): string {
    if (!label) return '—';
    const parts = label.split('•');
    return (parts[parts.length - 1] || '').trim() || label;
}

export default function EconomicProfileConcessionaireModern(props: Props) {
    const { header, summary_bs, summary_fx, by_local, tables } = props;
    const [showAllCharges, setShowAllCharges] = React.useState(false);
    const [showAllLocals, setShowAllLocals] = React.useState(false);
    const [chargesOpen, setChargesOpen] = React.useState(true);
    const [localsOpen, setLocalsOpen] = React.useState(false);
    const [selected, setSelected] = React.useState<Record<number, boolean>>({});
    const [localFilter, setLocalFilter] = React.useState<number | 'all'>('all');

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

    const openBs = summary_bs.open_bs_minor;
    const overdueBs = summary_bs.overdue_bs_minor;
    const netDueAfterCreditBs = summary_bs.net_due_after_credit_bs_minor;

    const hasDebt = openBs > 0;
    const hasOverdue = overdueBs > 0;
    const hasCredits = (summary_bs.credits_open_bs_minor ?? 0) > 0;
    const hasPaymentsAvailable = (summary_bs.payments_available_bs_minor ?? 0) > 0;

    const condoRate = summary_fx?.condo?.rate_to_ves ?? null;
    const rentM2Rate = summary_fx?.rent_m2?.rate_to_ves ?? summary_fx?.rent?.rate_to_ves ?? null;
    const rentFixedRate = summary_fx?.rent_fixed?.rate_to_ves ?? null;

    const localOptions = React.useMemo(() => {
        const opts = (by_local || [])
            .map((l) => ({ id: l.local_id, label: l.local_label || String(l.local_id) }))
            .sort((a, b) => (a.label || '').localeCompare(b.label || ''));
        return opts;
    }, [by_local]);

    const filteredCharges = React.useMemo(() => {
        if (localFilter === 'all') return tables.charges_open;
        return tables.charges_open.filter((c) => (c.local_id ?? 0) === localFilter);
    }, [tables.charges_open, localFilter]);

    const overdueCharges = React.useMemo(() => {
        return filteredCharges.filter((c) => c.due_on && new Date(c.due_on) < new Date());
    }, [filteredCharges]);

    const currentCharges = React.useMemo(() => {
        return filteredCharges.filter((c) => !c.due_on || new Date(c.due_on) >= new Date());
    }, [filteredCharges]);

    const CHARGES_LIMIT = 5;
    const LOCALS_LIMIT = 5;

    const displayedOverdueCharges = showAllCharges ? overdueCharges : overdueCharges.slice(0, CHARGES_LIMIT);
    const displayedCurrentCharges = showAllCharges ? currentCharges : currentCharges.slice(0, CHARGES_LIMIT);
    const displayedLocals = showAllLocals ? by_local : by_local.slice(0, LOCALS_LIMIT);

    const overdueMonthsByLocal = React.useMemo(() => {
        const map: Record<number, number> = {};
        overdueCharges.forEach((c) => {
            const lid = c.local_id ?? 0;
            if (lid) map[lid] = (map[lid] || 0) + 1;
        });
        return map;
    }, [overdueCharges]);

    // Selection functionality for payment estimation
    const selectedTotalBs = React.useMemo(() => {
        return filteredCharges.reduce((acc, c) => acc + (selected[c.charge_id] ? (c.outstanding_bs_minor ?? 0) : 0), 0);
    }, [filteredCharges, selected]);

    const selectedCount = React.useMemo(() => {
        return Object.values(selected).filter(Boolean).length;
    }, [selected]);

    const allOverdueSelected = React.useMemo(() => {
        return overdueCharges.length > 0 && overdueCharges.every((c) => selected[c.charge_id]);
    }, [overdueCharges, selected]);

    const allCurrentSelected = React.useMemo(() => {
        return currentCharges.length > 0 && currentCharges.every((c) => selected[c.charge_id]);
    }, [currentCharges, selected]);

    const toggleOne = (id: number) => {
        setSelected((prev) => {
            const map = { ...prev };
            if (map[id]) delete map[id];
            else map[id] = true;
            return map;
        });
    };

    const toggleAllOverdue = (checked: boolean) => {
        setSelected((prev) => {
            const map = { ...prev };
            overdueCharges.forEach((c) => {
                if (checked) map[c.charge_id] = true;
                else delete map[c.charge_id];
            });
            return map;
        });
    };

    const toggleAllCurrent = (checked: boolean) => {
        setSelected((prev) => {
            const map = { ...prev };
            currentCharges.forEach((c) => {
                if (checked) map[c.charge_id] = true;
                else delete map[c.charge_id];
            });
            return map;
        });
    };

    const clearSelection = () => setSelected({});

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
            <div className="container mx-auto max-w-7xl px-4 py-8">
                {/* Header */}
                <div className="mb-8">
                    <div className="mb-4 flex items-center justify-between">
                        <Link
                            href="/admin/economic-profile"
                            className="flex items-center gap-2 text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver a búsqueda
                        </Link>
                        <div className="flex items-center gap-2">
                            {hasDebt && (
                                <Button onClick={() => router.visit('/payments/create')} size="sm" className="gap-2 bg-blue-600 hover:bg-blue-700">
                                    <CreditCard className="h-3.5 w-3.5" />
                                    Registrar Pago
                                </Button>
                            )}
                            <a
                                href={exportUrl('csv')}
                                className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                <Download className="h-3.5 w-3.5" />
                                CSV
                            </a>
                            <a
                                href={exportUrl('json')}
                                className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                <Download className="h-3.5 w-3.5" />
                                JSON
                            </a>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex-1">
                                <div className="mb-3 flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" className="font-mono text-xs">
                                        {header.document?.type_code}
                                        {header.document?.number ? `-${header.document.number}` : ''}
                                    </Badge>
                                    <Badge variant="secondary" className="text-xs">
                                        {header.locals_count ?? 0} {(header.locals_count ?? 0) === 1 ? 'local' : 'locales'}
                                    </Badge>
                                    <Badge variant="secondary" className="text-xs">
                                        {header.contracts_count ?? 0} {(header.contracts_count ?? 0) === 1 ? 'contrato' : 'contratos'}
                                    </Badge>
                                </div>
                                <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50">{header.full_name}</h1>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Alerts */}
                {hasOverdue && (
                    <Alert variant="destructive" className="mb-6">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>Deuda vencida</AlertTitle>
                        <AlertDescription>Hay {fmtBs(overdueBs)} en cargos vencidos. Se recomienda regularizar a la brevedad.</AlertDescription>
                    </Alert>
                )}

                {/* KPI Cards */}
                <div className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card
                        className={`overflow-hidden shadow-lg transition-shadow hover:shadow-xl ${
                            hasDebt ? 'border-l-4 border-l-red-500' : 'border-l-4 border-l-green-500'
                        }`}
                    >
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <p className="text-sm text-slate-600 dark:text-slate-400">Deuda total</p>
                                    <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-50">{fmtBs(openBs)}</p>
                                    {hasOverdue && <p className="mt-1 text-xs text-red-600">⚠️ {fmtBs(overdueBs)} vencida</p>}
                                    {!hasDebt && <p className="mt-1 text-xs text-green-600">✓ Sin deuda</p>}
                                </div>
                                <div
                                    className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${
                                        hasDebt
                                            ? 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400'
                                            : 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400'
                                    }`}
                                >
                                    {hasDebt ? <TrendingDown className="h-6 w-6" /> : <CheckCircle2 className="h-6 w-6" />}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className={`overflow-hidden shadow-lg transition-shadow hover:shadow-xl ${hasCredits ? 'border-l-4 border-l-green-500' : ''}`}
                    >
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <p className="text-sm text-slate-600 dark:text-slate-400">Créditos a favor</p>
                                    <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-50">
                                        {fmtBs(summary_bs.credits_open_bs_minor)}
                                    </p>
                                    {hasCredits && <p className="mt-1 text-xs text-green-600">✓ Saldo positivo</p>}
                                </div>
                                <div
                                    className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${
                                        hasCredits
                                            ? 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                                    }`}
                                >
                                    <TrendingUp className="h-6 w-6" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className={`overflow-hidden shadow-lg transition-shadow hover:shadow-xl ${
                            hasPaymentsAvailable ? 'border-l-4 border-l-blue-500' : ''
                        }`}
                    >
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <p className="text-sm text-slate-600 dark:text-slate-400">Pagos disponibles</p>
                                    <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-50">
                                        {fmtBs(summary_bs.payments_available_bs_minor)}
                                    </p>
                                    {hasPaymentsAvailable && <p className="mt-1 text-xs text-blue-600">Por aplicar</p>}
                                </div>
                                <div
                                    className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${
                                        hasPaymentsAvailable
                                            ? 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                                    }`}
                                >
                                    <CreditCard className="h-6 w-6" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden shadow-lg transition-shadow hover:shadow-xl">
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <p className="text-sm text-slate-600 dark:text-slate-400">Neto tras crédito</p>
                                    <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-50">{fmtBs(netDueAfterCreditBs)}</p>
                                </div>
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                    <FileText className="h-6 w-6" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* FX Summary Cards */}
                {(condoDebt > 0 || rentM2Debt > 0 || rentFixedDebt > 0) && (
                    <div className="mb-8 grid gap-4 sm:grid-cols-2">
                        {condoDebt > 0 && (
                            <Card className="overflow-hidden border-l-4 border-l-blue-600 shadow-lg">
                                <CardHeader className="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-950 dark:to-blue-900">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <DollarSign className="h-5 w-5" />
                                            Condominio (USD)
                                        </CardTitle>
                                        <Badge variant="secondary">{fmt(condoDebt, 'USD')}</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-6">
                                    <dl className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <dt className="text-slate-600 dark:text-slate-400">Abierto</dt>
                                            <dd className="font-semibold">{fmt(summary_fx?.condo?.open_minor, 'USD')}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-600 dark:text-slate-400">Vencido</dt>
                                            <dd className="font-semibold text-red-600">{fmt(summary_fx?.condo?.overdue_minor, 'USD')}</dd>
                                        </div>
                                        <div className="flex justify-between border-t pt-2 dark:border-slate-700">
                                            <dt className="text-slate-600 dark:text-slate-400">Equivalente VES</dt>
                                            <dd className="font-semibold">{condoRate ? fmtBs(fxBsMinorTruncate(condoDebt, condoRate)) : '—'}</dd>
                                        </div>
                                        {condoRate && (
                                            <div className="text-xs text-slate-500">
                                                Tasa: {condoRate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}{' '}
                                                VES/USD
                                            </div>
                                        )}
                                    </dl>
                                </CardContent>
                            </Card>
                        )}

                        {rentM2Debt > 0 && (
                            <Card className="overflow-hidden border-l-4 border-l-green-600 shadow-lg">
                                <CardHeader className="bg-gradient-to-r from-green-50 to-green-100 dark:from-green-950 dark:to-green-900">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <Euro className="h-5 w-5" />
                                            Alquiler m² (EUR)
                                        </CardTitle>
                                        <Badge variant="secondary">{fmt(rentM2Debt, 'EUR')}</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-6">
                                    <dl className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <dt className="text-slate-600 dark:text-slate-400">Abierto</dt>
                                            <dd className="font-semibold">
                                                {fmt(summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor, 'EUR')}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-600 dark:text-slate-400">Vencido</dt>
                                            <dd className="font-semibold text-red-600">
                                                {fmt(summary_fx?.rent_m2?.overdue_minor ?? summary_fx?.rent?.overdue_minor, 'EUR')}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between border-t pt-2 dark:border-slate-700">
                                            <dt className="text-slate-600 dark:text-slate-400">Equivalente VES</dt>
                                            <dd className="font-semibold">{rentM2Rate ? fmtBs(fxBsMinorTruncate(rentM2Debt, rentM2Rate)) : '—'}</dd>
                                        </div>
                                        {rentM2Rate && (
                                            <div className="text-xs text-slate-500">
                                                Tasa: {rentM2Rate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}{' '}
                                                VES/EUR
                                            </div>
                                        )}
                                    </dl>
                                </CardContent>
                            </Card>
                        )}

                        {rentFixedDebt > 0 && (
                            <Card className="overflow-hidden border-l-4 border-l-emerald-600 shadow-lg">
                                <CardHeader className="bg-gradient-to-r from-emerald-50 to-emerald-100 dark:from-emerald-950 dark:to-emerald-900">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <DollarSign className="h-5 w-5" />
                                            Alquiler fijo (USD)
                                        </CardTitle>
                                        <Badge variant="secondary">{fmt(rentFixedDebt, 'USD')}</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-6">
                                    <dl className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <dt className="text-slate-600 dark:text-slate-400">Abierto</dt>
                                            <dd className="font-semibold">{fmt(summary_fx?.rent_fixed?.open_minor, 'USD')}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-600 dark:text-slate-400">Vencido</dt>
                                            <dd className="font-semibold text-red-600">{fmt(summary_fx?.rent_fixed?.overdue_minor, 'USD')}</dd>
                                        </div>
                                        <div className="flex justify-between border-t pt-2 dark:border-slate-700">
                                            <dt className="text-slate-600 dark:text-slate-400">Equivalente VES</dt>
                                            <dd className="font-semibold">
                                                {rentFixedRate ? fmtBs(fxBsMinorTruncate(rentFixedDebt, rentFixedRate)) : '—'}
                                            </dd>
                                        </div>
                                        {rentFixedRate && (
                                            <div className="text-xs text-slate-500">
                                                Tasa:{' '}
                                                {rentFixedRate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}{' '}
                                                VES/USD
                                            </div>
                                        )}
                                    </dl>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Selection Summary - Sticky */}
                {selectedCount > 0 && (
                    <Card className="sticky top-4 z-10 mb-6 overflow-hidden border-2 border-blue-500 shadow-2xl">
                        <CardHeader className="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-950 dark:to-blue-900">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <CheckCircle2 className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                        Estimación de pago
                                    </CardTitle>
                                    <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                        {selectedCount} {selectedCount === 1 ? 'cargo seleccionado' : 'cargos seleccionados'}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="text-right">
                                        <p className="text-sm text-slate-600 dark:text-slate-400">Monto estimado</p>
                                        <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">{fmtBs(selectedTotalBs)}</p>
                                    </div>
                                    <Button variant="outline" size="sm" onClick={clearSelection} className="gap-2">
                                        Limpiar
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                    </Card>
                )}

                {/* Charges Collapsible Section */}
                <Collapsible open={chargesOpen} onOpenChange={setChargesOpen} className="mb-8">
                    <Card className="overflow-hidden shadow-lg">
                        <CollapsibleTrigger className="w-full">
                            <CardHeader className="cursor-pointer bg-gradient-to-r from-slate-50 to-slate-100 transition-colors hover:from-slate-100 hover:to-slate-200 dark:from-slate-800 dark:to-slate-700 dark:hover:from-slate-700 dark:hover:to-slate-600">
                                <div className="flex items-center justify-between text-left">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            <FileText className="h-5 w-5" />
                                            Cargos abiertos
                                            <Badge variant="secondary">{filteredCharges.length}</Badge>
                                            {localFilter !== 'all' && <Badge variant="outline">Filtrado</Badge>}
                                        </CardTitle>
                                        <CardDescription className="mt-1">
                                            {overdueCharges.length > 0 && <span className="text-red-600">{overdueCharges.length} vencidos · </span>}
                                            {currentCharges.length} al día
                                            {selectedCount > 0 && <span className="ml-2 text-blue-600">· {selectedCount} seleccionados</span>}
                                        </CardDescription>
                                    </div>
                                    {chargesOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            {/* Filter by local */}
                            {localOptions.length > 1 && (
                                <div className="border-b border-slate-200 bg-slate-50/50 px-6 py-4 dark:border-slate-700 dark:bg-slate-800/50">
                                    <label htmlFor="local-filter" className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Filtrar por local
                                    </label>
                                    <select
                                        id="local-filter"
                                        className="h-10 w-full max-w-md rounded-lg border border-slate-300 bg-white px-3 text-sm shadow-sm transition-colors hover:border-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:hover:border-slate-500"
                                        value={localFilter === 'all' ? 'all' : String(localFilter)}
                                        onChange={(e) => {
                                            const v = e.target.value;
                                            setLocalFilter(v === 'all' ? 'all' : Number(v));
                                            clearSelection(); // Clear selection when changing filter
                                        }}
                                    >
                                        <option value="all">Todos los locales ({tables.charges_open.length} cargos)</option>
                                        {localOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            {overdueCharges.length > 0 && (
                                <div className="border-b border-slate-200 bg-red-50/50 p-6 dark:border-slate-700 dark:bg-red-950/20">
                                    <div className="mb-4 flex items-center justify-between">
                                        <h4 className="flex items-center gap-2 font-semibold text-red-900 dark:text-red-100">
                                            <AlertCircle className="h-4 w-4" />
                                            Cargos vencidos
                                            <Badge variant="destructive">{overdueCharges.length}</Badge>
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            {overdueCharges.length > CHARGES_LIMIT && (
                                                <Badge variant="outline">{showAllCharges ? 'Todos' : `Mostrando ${CHARGES_LIMIT}`}</Badge>
                                            )}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => toggleAllOverdue(!allOverdueSelected)}
                                                className="gap-2 text-xs"
                                            >
                                                {allOverdueSelected ? 'Deseleccionar' : 'Seleccionar'} vencidos
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="overflow-x-auto rounded-lg border border-red-200 dark:border-red-900">
                                        <table className="min-w-full text-sm">
                                            <thead className="bg-red-100/50 dark:bg-red-900/20">
                                                <tr>
                                                    <th className="px-3 py-2 text-center font-medium">
                                                        <input
                                                            type="checkbox"
                                                            checked={allOverdueSelected}
                                                            onChange={(e) => toggleAllOverdue(e.target.checked)}
                                                            className="h-4 w-4 cursor-pointer"
                                                        />
                                                    </th>
                                                    <th className="px-3 py-2 text-left font-medium">Tipo</th>
                                                    <th className="px-3 py-2 text-left font-medium">Local</th>
                                                    <th className="px-3 py-2 text-left font-medium">Periodo</th>
                                                    <th className="px-3 py-2 text-left font-medium">Vence</th>
                                                    <th className="px-3 py-2 text-right font-medium">Saldo</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white dark:bg-slate-900">
                                                {displayedOverdueCharges.map((c) => (
                                                    <tr key={c.charge_id} className="border-t border-red-200 dark:border-red-900">
                                                        <td className="px-3 py-2 text-center">
                                                            <input
                                                                type="checkbox"
                                                                checked={!!selected[c.charge_id]}
                                                                onChange={() => toggleOne(c.charge_id)}
                                                                className="h-4 w-4 cursor-pointer"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge variant="outline" className="text-xs">
                                                                {friendlyKind(c.kind)}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2 font-medium">{nameOnly(c.local_label)}</td>
                                                        <td className="px-3 py-2">{formatPeriod(c.period)}</td>
                                                        <td className="px-3 py-2 text-red-600">
                                                            {c.due_on ? new Date(c.due_on).toLocaleDateString() : '—'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-semibold">{fmtBs(c.outstanding_bs_minor)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {overdueCharges.length > CHARGES_LIMIT && (
                                        <div className="mt-4 text-center">
                                            <Button variant="outline" size="sm" onClick={() => setShowAllCharges(!showAllCharges)} className="gap-2">
                                                {showAllCharges ? (
                                                    <>
                                                        Ver menos
                                                        <ChevronUp className="h-4 w-4" />
                                                    </>
                                                ) : (
                                                    <>
                                                        Ver todos ({overdueCharges.length - CHARGES_LIMIT} más)
                                                        <ChevronDown className="h-4 w-4" />
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}
                            {currentCharges.length > 0 && (
                                <div className="p-6">
                                    <div className="mb-4 flex items-center justify-between">
                                        <h4 className="font-semibold text-slate-900 dark:text-slate-100">
                                            Cargos al día
                                            <Badge variant="secondary" className="ml-2">
                                                {currentCharges.length}
                                            </Badge>
                                        </h4>
                                        <div className="flex items-center gap-2">
                                            {currentCharges.length > CHARGES_LIMIT && (
                                                <Badge variant="outline">{showAllCharges ? 'Todos' : `Mostrando ${CHARGES_LIMIT}`}</Badge>
                                            )}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => toggleAllCurrent(!allCurrentSelected)}
                                                className="gap-2 text-xs"
                                            >
                                                {allCurrentSelected ? 'Deseleccionar' : 'Seleccionar'} al día
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                        <table className="min-w-full text-sm">
                                            <thead className="bg-slate-50 dark:bg-slate-800">
                                                <tr>
                                                    <th className="px-3 py-2 text-center font-medium">
                                                        <input
                                                            type="checkbox"
                                                            checked={allCurrentSelected}
                                                            onChange={(e) => toggleAllCurrent(e.target.checked)}
                                                            className="h-4 w-4 cursor-pointer"
                                                        />
                                                    </th>
                                                    <th className="px-3 py-2 text-left font-medium">Tipo</th>
                                                    <th className="px-3 py-2 text-left font-medium">Local</th>
                                                    <th className="px-3 py-2 text-left font-medium">Periodo</th>
                                                    <th className="px-3 py-2 text-left font-medium">Vence</th>
                                                    <th className="px-3 py-2 text-right font-medium">Saldo</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white dark:bg-slate-900">
                                                {displayedCurrentCharges.map((c) => (
                                                    <tr key={c.charge_id} className="border-t border-slate-200 dark:border-slate-700">
                                                        <td className="px-3 py-2 text-center">
                                                            <input
                                                                type="checkbox"
                                                                checked={!!selected[c.charge_id]}
                                                                onChange={() => toggleOne(c.charge_id)}
                                                                className="h-4 w-4 cursor-pointer"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge variant="outline" className="text-xs">
                                                                {friendlyKind(c.kind)}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2 font-medium">{nameOnly(c.local_label)}</td>
                                                        <td className="px-3 py-2">{formatPeriod(c.period)}</td>
                                                        <td className="px-3 py-2">{c.due_on ? new Date(c.due_on).toLocaleDateString() : '—'}</td>
                                                        <td className="px-3 py-2 text-right font-semibold">{fmtBs(c.outstanding_bs_minor)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {currentCharges.length > CHARGES_LIMIT && (
                                        <div className="mt-4 text-center">
                                            <Button variant="outline" size="sm" onClick={() => setShowAllCharges(!showAllCharges)} className="gap-2">
                                                {showAllCharges ? (
                                                    <>
                                                        Ver menos
                                                        <ChevronUp className="h-4 w-4" />
                                                    </>
                                                ) : (
                                                    <>
                                                        Ver todos ({currentCharges.length - CHARGES_LIMIT} más)
                                                        <ChevronDown className="h-4 w-4" />
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </CollapsibleContent>
                    </Card>
                </Collapsible>

                {/* Locals by Local Section */}
                {by_local.length > 0 && (
                    <Collapsible open={localsOpen} onOpenChange={setLocalsOpen} className="mb-8">
                        <Card className="overflow-hidden shadow-lg">
                            <CollapsibleTrigger className="w-full">
                                <CardHeader className="cursor-pointer bg-gradient-to-r from-slate-50 to-slate-100 transition-colors hover:from-slate-100 hover:to-slate-200 dark:from-slate-800 dark:to-slate-700 dark:hover:from-slate-700 dark:hover:to-slate-600">
                                    <div className="flex items-center justify-between text-left">
                                        <CardTitle className="flex items-center gap-2">
                                            Por Local
                                            <Badge variant="secondary">{by_local.length}</Badge>
                                        </CardTitle>
                                        {localsOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="p-6">
                                    {by_local.length > LOCALS_LIMIT && (
                                        <div className="mb-4 text-right">
                                            <Badge variant="outline">{showAllLocals ? 'Todos' : `Mostrando ${LOCALS_LIMIT}`}</Badge>
                                        </div>
                                    )}
                                    <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                        <table className="min-w-full text-sm">
                                            <thead className="bg-slate-50 dark:bg-slate-800">
                                                <tr>
                                                    <th className="px-3 py-2 text-left font-medium">Local</th>
                                                    <th className="px-3 py-2 text-left font-medium">Moneda</th>
                                                    <th className="px-3 py-2 text-right font-medium">Abierto</th>
                                                    <th className="px-3 py-2 text-right font-medium">Vencido</th>
                                                    <th className="px-3 py-2 text-right font-medium">Meses vencidos</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white dark:bg-slate-900">
                                                {displayedLocals.map((r) => {
                                                    const currency = (r.currency || 'VES').toUpperCase() as 'USD' | 'EUR' | 'VES';
                                                    return (
                                                        <tr key={r.local_id} className="border-t border-slate-200 dark:border-slate-700">
                                                            <td className="px-3 py-2 font-medium">{nameOnly(r.local_label) || String(r.local_id)}</td>
                                                            <td className="px-3 py-2">
                                                                <Badge
                                                                    variant={
                                                                        currency === 'USD' ? 'default' : currency === 'EUR' ? 'secondary' : 'outline'
                                                                    }
                                                                    className="text-xs"
                                                                >
                                                                    {currency}
                                                                </Badge>
                                                            </td>
                                                            <td className="px-3 py-2 text-right font-semibold">{fmt(r.open_minor, currency)}</td>
                                                            <td className="px-3 py-2 text-right text-red-600">{fmt(r.overdue_minor, currency)}</td>
                                                            <td className="px-3 py-2 text-right">
                                                                {overdueMonthsByLocal[r.local_id] ? (
                                                                    <Badge variant="destructive">{overdueMonthsByLocal[r.local_id]}</Badge>
                                                                ) : (
                                                                    '—'
                                                                )}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                    {by_local.length > LOCALS_LIMIT && (
                                        <div className="mt-4 text-center">
                                            <Button variant="outline" size="sm" onClick={() => setShowAllLocals(!showAllLocals)} className="gap-2">
                                                {showAllLocals ? (
                                                    <>
                                                        Ver menos
                                                        <ChevronUp className="h-4 w-4" />
                                                    </>
                                                ) : (
                                                    <>
                                                        Ver todos ({by_local.length - LOCALS_LIMIT} más)
                                                        <ChevronDown className="h-4 w-4" />
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>
                )}
            </div>
        </div>
    );
}

EconomicProfileConcessionaireModern.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
