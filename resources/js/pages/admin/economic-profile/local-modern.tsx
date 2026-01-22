import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    CreditCard,
    DollarSign,
    Download,
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
    aging?: { '0_30': number; '31_60': number; '61_90': number; '90_plus': number };
};

type Header = {
    id: number;
    code?: string;
    name?: string;
    concessionaire?: {
        id: number;
        full_name: string;
        contract?: {
            id: number;
            number: string;
            status: string;
        };
    } | null;
};

type ChargeRow = {
    charge_id: number;
    local_id?: number;
    local_label?: string | null;
    period: string;
    due_on?: string;
    amount_bs_minor: number | null;
    allocated_bs_minor: number;
    credited_bs_minor: number;
    outstanding_bs_minor: number;
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
        condo?: { currency: 'USD'; open_minor: number; overdue_minor: number };
        rent?: { currency: 'EUR'; open_minor: number; overdue_minor: number };
    };
    by_local: Array<{
        local_id: number;
        local_label?: string | null;
        open_bs_minor: number;
        overdue_bs_minor: number;
        partial_applied_bs_minor: number;
        net_due_bs_minor: number;
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

function formatPeriod(v?: string | null): string {
    if (!v) return '—';
    try {
        return new Date(v).toLocaleDateString('es-ES', { year: 'numeric', month: 'short' });
    } catch {
        return String(v);
    }
}

function fmtPaidOnDate(d?: string | null): string {
    if (!d) return '—';
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

export default function EconomicProfileLocalModern(props: Props) {
    const { header, summary_bs, tables } = props;
    const [chargesOpen, setChargesOpen] = React.useState(true);
    const [paymentsOpen, setPaymentsOpen] = React.useState(false);
    const [creditsOpen, setCreditsOpen] = React.useState(false);

    const atParam = React.useMemo(() => {
        if (typeof window === 'undefined') return new Date().toISOString().slice(0, 10);
        const p = new URLSearchParams(window.location.search);
        return p.get('at') || new Date().toISOString().slice(0, 10);
    }, []);

    const exportUrl = (format: 'csv' | 'json') =>
        `/admin/economic-profile/export?scope=local&id=${header.id}&format=${format}&at=${encodeURIComponent(atParam)}`;

    const hasDebt = (summary_bs.open_bs_minor ?? 0) > 0;
    const hasOverdue = (summary_bs.overdue_bs_minor ?? 0) > 0;
    const hasCredits = (summary_bs.credits_open_bs_minor ?? 0) > 0;
    const hasPaymentsAvailable = (summary_bs.payments_available_bs_minor ?? 0) > 0;

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
            <div className="container mx-auto max-w-6xl px-4 py-8">
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
                                <div className="mb-3 flex items-center gap-2">
                                    <Badge variant="outline" className="font-mono text-xs">
                                        {header.code || `#${header.id}`}
                                    </Badge>
                                </div>
                                <h1 className="mb-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50">
                                    {header.name || 'Local sin nombre'}
                                </h1>

                                {header.concessionaire ? (
                                    <div className="mt-4 rounded-lg border border-blue-100 bg-gradient-to-r from-blue-50 to-blue-50/50 p-4 dark:border-blue-900/50 dark:from-blue-950/50 dark:to-blue-950/30">
                                        <div className="flex items-start gap-3">
                                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white">
                                                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        strokeWidth={2}
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                    />
                                                </svg>
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="mb-1 flex items-center gap-2">
                                                    <p className="text-xs font-semibold tracking-wider text-blue-700 uppercase dark:text-blue-400">
                                                        Contrato Activo
                                                    </p>
                                                    {header.concessionaire.contract && (
                                                        <Badge variant="secondary" className="font-mono text-xs">
                                                            {header.concessionaire.contract.number}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-lg font-semibold text-slate-900 dark:text-slate-50">
                                                    {header.concessionaire.full_name}
                                                </p>
                                                {header.concessionaire.contract && (
                                                    <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                                        Estado: {header.concessionaire.contract.status}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                                        <p className="text-sm text-slate-600 dark:text-slate-400">Este local no tiene contrato activo actualmente</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Alert */}
                {hasOverdue && (
                    <Alert variant="destructive" className="mb-6">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>Deuda vencida</AlertTitle>
                        <AlertDescription>Este local tiene {fmtBs(summary_bs.overdue_bs_minor)} en cargos vencidos.</AlertDescription>
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
                                    <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-50">{fmtBs(summary_bs.open_bs_minor)}</p>
                                    {hasOverdue && <p className="mt-1 text-xs text-red-600">⚠️ {fmtBs(summary_bs.overdue_bs_minor)} vencida</p>}
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
                                    <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-50">
                                        {fmtBs(summary_bs.net_due_after_credit_bs_minor)}
                                    </p>
                                </div>
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                    <FileText className="h-6 w-6" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Charges Collapsible */}
                <Collapsible open={chargesOpen} onOpenChange={setChargesOpen} className="mb-6">
                    <Card className="overflow-hidden shadow-lg">
                        <CollapsibleTrigger className="w-full">
                            <CardHeader className="cursor-pointer bg-gradient-to-r from-slate-50 to-slate-100 transition-colors hover:from-slate-100 hover:to-slate-200 dark:from-slate-800 dark:to-slate-700 dark:hover:from-slate-700 dark:hover:to-slate-600">
                                <div className="flex items-center justify-between text-left">
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Cargos abiertos
                                        <Badge variant="secondary">{tables.charges_open.length}</Badge>
                                    </CardTitle>
                                    {chargesOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent className="p-6">
                                <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                    <table className="min-w-full text-sm">
                                        <thead className="bg-slate-50 dark:bg-slate-800">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">Tipo</th>
                                                <th className="px-3 py-2 text-left font-medium">Periodo</th>
                                                <th className="px-3 py-2 text-left font-medium">Vence</th>
                                                <th className="px-3 py-2 text-right font-medium">Monto</th>
                                                <th className="px-3 py-2 text-right font-medium">Saldo</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white dark:bg-slate-900">
                                            {tables.charges_open.map((c) => {
                                                const isOverdue = c.due_on && new Date(c.due_on) < new Date();
                                                return (
                                                    <tr
                                                        key={c.charge_id}
                                                        className={`border-t border-slate-200 dark:border-slate-700 ${
                                                            isOverdue ? 'bg-red-50/50 dark:bg-red-950/20' : ''
                                                        }`}
                                                    >
                                                        <td className="px-3 py-2">
                                                            <Badge variant={isOverdue ? 'destructive' : 'outline'} className="text-xs">
                                                                {friendlyKind(c.kind)}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2">{formatPeriod(c.period)}</td>
                                                        <td className={`px-3 py-2 ${isOverdue ? 'text-red-600' : ''}`}>
                                                            {c.due_on ? new Date(c.due_on).toLocaleDateString() : '—'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right">{fmtBs(c.amount_bs_minor)}</td>
                                                        <td className="px-3 py-2 text-right font-semibold">{fmtBs(c.outstanding_bs_minor)}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Card>
                </Collapsible>

                {/* Payments and Credits Grid */}
                <div className="grid gap-6 sm:grid-cols-2">
                    {/* Payments */}
                    {tables.payments_partial.length > 0 && (
                        <Collapsible open={paymentsOpen} onOpenChange={setPaymentsOpen}>
                            <Card className="overflow-hidden shadow-lg">
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="cursor-pointer bg-gradient-to-r from-slate-50 to-slate-100 transition-colors hover:from-slate-100 hover:to-slate-200 dark:from-slate-800 dark:to-slate-700 dark:hover:from-slate-700 dark:hover:to-slate-600">
                                        <div className="flex items-center justify-between text-left">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <DollarSign className="h-4 w-4" />
                                                Pagos parciales
                                                <Badge variant="secondary">{tables.payments_partial.length}</Badge>
                                            </CardTitle>
                                            {paymentsOpen ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                                        </div>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="p-6">
                                        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                            <table className="min-w-full text-sm">
                                                <thead className="bg-slate-50 dark:bg-slate-800">
                                                    <tr>
                                                        <th className="px-3 py-2 text-left font-medium">Pago</th>
                                                        <th className="px-3 py-2 text-left font-medium">Fecha</th>
                                                        <th className="px-3 py-2 text-right font-medium">Disponible</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white dark:bg-slate-900">
                                                    {tables.payments_partial.map((p) => (
                                                        <tr key={p.payment_id} className="border-t border-slate-200 dark:border-slate-700">
                                                            <td className="px-3 py-2 font-medium">#{p.payment_id}</td>
                                                            <td className="px-3 py-2">{fmtPaidOnDate(p.paid_on)}</td>
                                                            <td className="px-3 py-2 text-right font-semibold">{fmtBs(p.available_bs_minor)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>
                    )}

                    {/* Credits */}
                    {tables.credits_open.length > 0 && (
                        <Collapsible open={creditsOpen} onOpenChange={setCreditsOpen}>
                            <Card className="overflow-hidden shadow-lg">
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="cursor-pointer bg-gradient-to-r from-slate-50 to-slate-100 transition-colors hover:from-slate-100 hover:to-slate-200 dark:from-slate-800 dark:to-slate-700 dark:hover:from-slate-700 dark:hover:to-slate-600">
                                        <div className="flex items-center justify-between text-left">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <TrendingUp className="h-4 w-4" />
                                                Créditos abiertos
                                                <Badge variant="secondary">{tables.credits_open.length}</Badge>
                                            </CardTitle>
                                            {creditsOpen ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                                        </div>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="p-6">
                                        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                            <table className="min-w-full text-sm">
                                                <thead className="bg-slate-50 dark:bg-slate-800">
                                                    <tr>
                                                        <th className="px-3 py-2 text-left font-medium">Crédito</th>
                                                        <th className="px-3 py-2 text-left font-medium">Origen</th>
                                                        <th className="px-3 py-2 text-right font-medium">Saldo</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white dark:bg-slate-900">
                                                    {tables.credits_open.map((c) => (
                                                        <tr key={c.credit_id} className="border-t border-slate-200 dark:border-slate-700">
                                                            <td className="px-3 py-2 font-medium">#{c.credit_id}</td>
                                                            <td className="px-3 py-2">
                                                                {c.source_payment_id ? `Pago #${c.source_payment_id}` : '—'}
                                                            </td>
                                                            <td className="px-3 py-2 text-right font-semibold">{fmtBs(c.balance_minor)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>
                    )}
                </div>
            </div>
        </div>
    );
}

EconomicProfileLocalModern.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
