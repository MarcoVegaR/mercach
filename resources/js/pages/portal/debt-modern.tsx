import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { formatDateShort, formatMonthYear } from '@/lib/date-utils';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { AlertCircle, ArrowRight, CheckCircle2, ChevronRight, Clock, CreditCard, Home, Info, Sparkles, TrendingUp, Wallet } from 'lucide-react';
import React from 'react';

type SummaryFx = {
    condo?: { currency: 'USD'; open_minor: number; overdue_minor: number; rate_to_ves?: number };
    rent?: { currency: 'EUR'; open_minor: number; overdue_minor: number; rate_to_ves?: number };
    rent_m2?: { currency: 'EUR'; open_minor: number; overdue_minor: number; rate_to_ves?: number };
    rent_fixed?: { currency: 'USD'; open_minor: number; overdue_minor: number; rate_to_ves?: number };
};

type Props = {
    header: { id: number; full_name: string };
    summary_bs: {
        open_bs_minor: number;
        overdue_bs_minor: number;
        credits_open_bs_minor: number;
        payments_available_bs_minor?: number;
        net_due_after_credit_bs_minor: number;
    };
    summary_fx?: SummaryFx;
    tables: { charges_open: Array<Record<string, any>> };
    at: string;
};

// Format minor units to currency display
function fmtBs(minor?: number | null): string {
    if (typeof minor !== 'number') return 'Bs. 0,00';
    return `Bs. ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Friendly names for charge types
function friendlyKind(kind?: string): string {
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
            return 'Cargo';
    }
}

// Calculate days overdue
function daysOverdue(dueDate?: string): number {
    if (!dueDate) return 0;
    const due = new Date(dueDate);
    const now = new Date();
    const diff = Math.floor((now.getTime() - due.getTime()) / (1000 * 60 * 60 * 24));
    return Math.max(0, diff);
}

// Human-readable time ago (for hero section)
function timeAgo(days: number): string {
    if (days === 0) return 'hoy';
    if (days === 1) return 'ayer';
    if (days < 7) return `hace ${days} días`;
    if (days < 30) return `hace ${Math.floor(days / 7)} semanas`;
    const months = Math.floor(days / 30);
    if (months === 1) return 'hace 1 mes';
    if (months < 12) return `hace ${months} meses`;
    return 'hace más de 1 año';
}

// Format amount in original currency (EUR/USD)
function fmtOriginal(minor?: number | null, currency?: string): string {
    if (typeof minor !== 'number') return '—';
    const cur = (currency || 'VES').toUpperCase();
    const symbol = cur === 'EUR' ? '€' : cur === 'USD' ? '$' : 'Bs.';
    return `${symbol} ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Group charges by LOCAL for summary
type LocalGroup = {
    local_id: string;
    local_code: string;
    local_type: string;
    count: number;
    total_bs: number;
    total_bs_eur: number;
    total_bs_usd: number;
    total_bs_ves: number;
    total_eur: number;
    total_usd: number;
    overdue_count: number;
    overdue_bs: number;
    charges: Array<Record<string, any>>;
};

function groupChargesByLocal(charges: Array<Record<string, any>>): LocalGroup[] {
    const groups: Record<string, LocalGroup> = {};
    const now = new Date();

    charges.forEach((c) => {
        const localId = String(c.local_id || c.debtor_id || 'unknown');
        // Use local_code directly from backend
        const localCode = c.local_code || `L-${localId}`;
        const localType = c.local_type_name || '';

        if (!groups[localId]) {
            groups[localId] = {
                local_id: localId,
                local_code: localCode,
                local_type: localType,
                count: 0,
                total_bs: 0,
                total_bs_eur: 0,
                total_bs_usd: 0,
                total_bs_ves: 0,
                total_eur: 0,
                total_usd: 0,
                overdue_count: 0,
                overdue_bs: 0,
                charges: [],
            };
        }

        const amountBs = Number(c.outstanding_bs_minor ?? c.amount_bs_minor) || 0;
        const amountOriginal = Number(c.outstanding_minor ?? c.amount_minor) || 0;
        const currency = (c.currency || 'VES').toUpperCase();

        groups[localId].count++;
        groups[localId].total_bs += amountBs;
        groups[localId].charges.push(c);

        // Accumulate by currency
        if (currency === 'EUR') {
            groups[localId].total_eur += amountOriginal;
            groups[localId].total_bs_eur += amountBs;
        } else if (currency === 'USD') {
            groups[localId].total_usd += amountOriginal;
            groups[localId].total_bs_usd += amountBs;
        } else {
            groups[localId].total_bs_ves += amountBs;
        }

        if (c.due_on && new Date(c.due_on) < now) {
            groups[localId].overdue_count++;
            groups[localId].overdue_bs += amountBs;
        }
    });

    // Sort charges within each group by due date (oldest first)
    Object.values(groups).forEach((g) => {
        g.charges.sort((a, b) => {
            const dateA = a.due_on ? new Date(a.due_on).getTime() : Infinity;
            const dateB = b.due_on ? new Date(b.due_on).getTime() : Infinity;
            return dateA - dateB;
        });
    });

    return Object.values(groups).sort((a, b) => b.overdue_bs - a.overdue_bs || b.total_bs - a.total_bs);
}

export default function PortalDebtModern({ header: _header, summary_bs, summary_fx, tables, at }: Props) {
    const charges = React.useMemo(() => (Array.isArray(tables?.charges_open) ? tables.charges_open : []), [tables?.charges_open]);
    const paymentsAvail = Number(summary_bs?.payments_available_bs_minor ?? 0);
    const creditsAvail = Number(summary_bs?.credits_open_bs_minor ?? 0);
    const totalDebt = Number(summary_bs?.open_bs_minor ?? 0);
    const overdueDebt = Number(summary_bs?.overdue_bs_minor ?? 0);
    const hasOverdue = overdueDebt > 0;
    const hasDebt = totalDebt > 0;

    const concessionaireCharges = React.useMemo(() => {
        return charges
            .filter((c) => String(c.debtor_type || '').toUpperCase() === 'CONCESSIONAIRE' || c.local_id === null)
            .slice()
            .sort((a, b) => {
                const dateA = a.due_on ? new Date(a.due_on).getTime() : Infinity;
                const dateB = b.due_on ? new Date(b.due_on).getTime() : Infinity;
                return dateA - dateB;
            });
    }, [charges]);

    const localCharges = React.useMemo(() => {
        return charges.filter((c) => !(String(c.debtor_type || '').toUpperCase() === 'CONCESSIONAIRE' || c.local_id === null));
    }, [charges]);

    // Group charges by local - use outstanding_bs_minor from backend directly
    // (backend already distributed rounding differences)
    const localGroups = React.useMemo(() => {
        return groupChargesByLocal(localCharges);
    }, [localCharges]);

    // Separate overdue charges for detail view
    const now = new Date();
    const overdueCharges = charges.filter((c) => c.due_on && new Date(c.due_on) < now);

    // State for detail expansion
    const [expandedGroup, setExpandedGroup] = React.useState<string | null>(null);
    const [showAllDetails, setShowAllDetails] = React.useState(false);

    // Calculate oldest overdue
    const oldestOverdue = overdueCharges.length > 0 ? Math.max(...overdueCharges.map((c) => daysOverdue(c.due_on))) : 0;

    // Calculate what percentage is overdue
    const overduePercentage = totalDebt > 0 ? Math.round((overdueDebt / totalDebt) * 100) : 0;

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-3xl px-4 py-6">
                {/* Simple header */}
                <div className="mb-6">
                    <Link href="/portal" className="text-muted-foreground hover:text-foreground mb-2 inline-flex items-center gap-1 text-sm">
                        ← Volver al portal
                    </Link>
                    <h1 className="text-2xl font-bold tracking-tight">Mi deuda</h1>
                    <p className="text-muted-foreground text-sm">Actualizado hoy</p>
                </div>

                {/* HERO: Main debt amount */}
                <Card
                    className={cn(
                        'relative mb-6 overflow-hidden',
                        hasOverdue
                            ? 'border-red-200 dark:border-red-900/50'
                            : hasDebt
                              ? 'border-amber-200 dark:border-amber-900/50'
                              : 'border-green-200 dark:border-green-900/50',
                    )}
                >
                    <CardContent className="p-6">
                        {/* Status badge */}
                        <div className="mb-4 flex items-center gap-2">
                            {hasOverdue ? (
                                <Badge variant="destructive" className="gap-1 px-3 py-1">
                                    <AlertCircle className="h-3.5 w-3.5" />
                                    {overdueCharges.length} {overdueCharges.length === 1 ? 'cargo vencido' : 'cargos vencidos'}
                                </Badge>
                            ) : hasDebt ? (
                                <Badge variant="secondary" className="gap-1 bg-amber-100 px-3 py-1 text-amber-700">
                                    <Clock className="h-3.5 w-3.5" />
                                    Tienes cargos por pagar
                                </Badge>
                            ) : (
                                <Badge variant="secondary" className="gap-1 bg-green-100 px-3 py-1 text-green-700">
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                    Estás al día
                                </Badge>
                            )}
                        </div>

                        {/* Main amount */}
                        <div className="mb-2">
                            <p className="text-muted-foreground mb-1 text-sm font-medium">
                                {hasOverdue ? 'Total que debes' : hasDebt ? 'Tienes pendiente' : 'Saldo pendiente'}
                            </p>
                            <p
                                className={cn(
                                    'text-4xl font-bold tracking-tight sm:text-5xl',
                                    hasOverdue ? 'text-red-600' : hasDebt ? 'text-amber-600' : 'text-green-600',
                                )}
                            >
                                {fmtBs(totalDebt)}
                            </p>
                        </div>

                        {/* Overdue context */}
                        {hasOverdue && (
                            <div className="mt-4 space-y-3">
                                <div className="flex items-center gap-2 text-sm text-red-700 dark:text-red-400">
                                    <Clock className="h-4 w-4" />
                                    <span>
                                        El cargo más antiguo venció <strong>{timeAgo(oldestOverdue)}</strong>
                                    </span>
                                </div>

                                {/* Visual progress of overdue */}
                                <div className="space-y-1.5">
                                    <div className="flex justify-between text-xs">
                                        <span className="text-muted-foreground">Deuda vencida</span>
                                        <span className="font-medium text-red-600">{overduePercentage}% del total</span>
                                    </div>
                                    <Progress value={overduePercentage} className="h-2 bg-red-100" />
                                </div>
                            </div>
                        )}

                        {/* CTA Button */}
                        {hasDebt && (
                            <Link href="/portal/pagos/nuevo" className="mt-6 block">
                                <Button
                                    size="lg"
                                    className={cn(
                                        'w-full gap-2 text-base font-semibold',
                                        hasOverdue
                                            ? 'bg-red-600 hover:bg-red-700'
                                            : 'bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-700 hover:to-blue-600',
                                    )}
                                >
                                    <CreditCard className="h-5 w-5" />
                                    Pagar ahora
                                    <ArrowRight className="ml-1 h-4 w-4" />
                                </Button>
                            </Link>
                        )}

                        {/* All clear state */}
                        {!hasDebt && (
                            <div className="mt-6 flex items-center gap-3 rounded-lg bg-green-100/50 p-4 dark:bg-green-900/20">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                                    <Sparkles className="h-5 w-5 text-green-600" />
                                </div>
                                <div>
                                    <p className="font-medium text-green-800 dark:text-green-200">¡Felicidades!</p>
                                    <p className="text-sm text-green-700 dark:text-green-300">No tienes deudas pendientes.</p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Quick info cards - only show if relevant */}
                {(creditsAvail > 0 || paymentsAvail > 0) && (
                    <div className="mb-6 grid gap-3 sm:grid-cols-2">
                        {creditsAvail > 0 && (
                            <Card className="border-green-200 bg-green-50/50 dark:border-green-900/50 dark:bg-green-950/20">
                                <CardContent className="flex items-center gap-3 p-4">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                                        <TrendingUp className="h-5 w-5 text-green-600" />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-green-800 dark:text-green-200">Tienes saldo a favor</p>
                                        <p className="text-lg font-bold text-green-600">{fmtBs(creditsAvail)}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {paymentsAvail > 0 && (
                            <Link href="/portal/pagos" className="block">
                                <Card className="h-full border-blue-200 bg-blue-50/50 transition-all hover:border-blue-300 hover:shadow-md dark:border-blue-900/50 dark:bg-blue-950/20">
                                    <CardContent className="flex items-center justify-between gap-3 p-4">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/50">
                                                <Wallet className="h-5 w-5 text-blue-600" />
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-blue-800 dark:text-blue-200">Pagos listos para usar</p>
                                                <p className="text-lg font-bold text-blue-600">{fmtBs(paymentsAvail)}</p>
                                            </div>
                                        </div>
                                        <ChevronRight className="h-5 w-5 shrink-0 text-blue-400" />
                                    </CardContent>
                                </Card>
                            </Link>
                        )}
                    </div>
                )}

                {/* Debt breakdown by Local */}
                {concessionaireCharges.length > 0 && (
                    <div className="mb-6">
                        <h2 className="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                            Cargos del cesionario
                        </h2>
                        <div className="space-y-2">
                            {concessionaireCharges.map((charge: Record<string, any>, idx: number) => {
                                const amountOriginal = Number(charge.outstanding_minor ?? charge.amount_minor) || 0;
                                const amountBs = Number(charge.outstanding_bs_minor ?? charge.amount_bs_minor) || 0;
                                const days = daysOverdue(charge.due_on);
                                const isOverdue = days > 0;
                                const monthsOverdue = Math.floor(days / 30);

                                return (
                                    <div
                                        key={idx}
                                        className={cn(
                                            'flex items-center justify-between gap-3 rounded-xl border bg-white p-4 dark:bg-slate-900',
                                            isOverdue ? 'border-red-200 dark:border-red-900/50' : 'border-slate-200',
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">{friendlyKind(charge.kind)}</p>
                                            <p className="text-muted-foreground text-sm">{formatMonthYear(charge.period)}</p>
                                        </div>

                                        {isOverdue && (
                                            <Badge variant="destructive" className="shrink-0 gap-1 px-2 py-0.5 text-xs whitespace-nowrap">
                                                <AlertCircle className="h-3 w-3" />
                                                {monthsOverdue > 0 ? `${monthsOverdue} ${monthsOverdue === 1 ? 'mes' : 'meses'}` : `${days} días`}
                                            </Badge>
                                        )}

                                        <div className="shrink-0 text-right">
                                            <p className={cn('font-semibold', isOverdue && 'text-red-600')}>
                                                {fmtOriginal(amountOriginal, charge.currency)}
                                            </p>
                                            <p className="text-muted-foreground text-xs">{fmtBs(amountBs)}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {localGroups.length > 0 && (
                    <div className="mb-6">
                        <h2 className="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">Deuda por local</h2>
                        <div className="space-y-2">
                            {localGroups.map((local: LocalGroup) => {
                                const isExpanded = expandedGroup === local.local_id;
                                // Build display name: "Tipo Código" or just "Código"
                                const displayName = local.local_type ? `${local.local_type} ${local.local_code}` : local.local_code;

                                return (
                                    <div key={local.local_id}>
                                        <button
                                            onClick={() => setExpandedGroup(isExpanded ? null : local.local_id)}
                                            className={cn(
                                                'group flex w-full items-center justify-between rounded-xl border bg-white p-4 text-left transition-all hover:shadow-md dark:bg-slate-900',
                                                isExpanded && 'ring-2 ring-blue-500/20',
                                                local.overdue_count > 0 ? 'border-red-200 dark:border-red-900/50' : 'border-slate-200',
                                            )}
                                        >
                                            <div className="flex items-center gap-3">
                                                <div
                                                    className={cn(
                                                        'flex h-11 w-11 items-center justify-center rounded-lg',
                                                        local.overdue_count > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-slate-100 dark:bg-slate-800',
                                                    )}
                                                >
                                                    <Home
                                                        className={cn(
                                                            'h-5 w-5',
                                                            local.overdue_count > 0 ? 'text-red-600' : 'text-slate-600 dark:text-slate-400',
                                                        )}
                                                    />
                                                </div>
                                                <div>
                                                    <p className="font-semibold">{displayName}</p>
                                                    <p className="text-muted-foreground text-sm">
                                                        {local.count} {local.count === 1 ? 'cargo' : 'cargos'}
                                                        {local.overdue_count > 0 && (
                                                            <span className="ml-1 text-red-600">
                                                                · {local.overdue_count} {local.overdue_count === 1 ? 'vencido' : 'vencidos'}
                                                            </span>
                                                        )}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Right side: amounts by currency + Bs total */}
                                            <div className="flex items-center gap-3">
                                                <div className="text-right">
                                                    {/* Show EUR total if any */}
                                                    {local.total_eur > 0 && (
                                                        <p className={cn('text-sm font-semibold', local.overdue_count > 0 && 'text-red-600')}>
                                                            {fmtOriginal(local.total_eur, 'EUR')}
                                                        </p>
                                                    )}
                                                    {local.total_bs_eur > 0 && (
                                                        <p className="text-muted-foreground text-xs">{fmtBs(local.total_bs_eur)}</p>
                                                    )}
                                                    {/* Show USD total if any */}
                                                    {local.total_usd > 0 && (
                                                        <p className={cn('text-sm font-semibold', local.overdue_count > 0 && 'text-red-600')}>
                                                            {fmtOriginal(local.total_usd, 'USD')}
                                                        </p>
                                                    )}
                                                    {local.total_bs_usd > 0 && (
                                                        <p className="text-muted-foreground text-xs">{fmtBs(local.total_bs_usd)}</p>
                                                    )}
                                                    {/* Always show Bs equivalent */}
                                                    <p className="text-muted-foreground text-xs">{fmtBs(local.total_bs)}</p>
                                                </div>
                                                <ChevronRight
                                                    className={cn('h-5 w-5 text-slate-400 transition-transform', isExpanded && 'rotate-90')}
                                                />
                                            </div>
                                        </button>

                                        {/* Expanded detail - charges for this local */}
                                        {isExpanded && (
                                            <div className="mt-2 space-y-1.5 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/50">
                                                {(showAllDetails ? local.charges : local.charges.slice(0, 5)).map(
                                                    (charge: Record<string, any>, idx: number) => {
                                                        const amountOriginal = Number(charge.outstanding_minor ?? charge.amount_minor) || 0;
                                                        const amountBs = Number(charge.outstanding_bs_minor ?? charge.amount_bs_minor) || 0;
                                                        const days = daysOverdue(charge.due_on);
                                                        const isOverdue = days > 0;
                                                        const monthsOverdue = Math.floor(days / 30);

                                                        return (
                                                            <div
                                                                key={idx}
                                                                className={cn(
                                                                    'flex items-center justify-between gap-3 rounded-lg p-3',
                                                                    isOverdue ? 'bg-red-50 dark:bg-red-950/30' : 'bg-white dark:bg-slate-800/50',
                                                                )}
                                                            >
                                                                {/* Left: Concept + Period */}
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="text-sm font-medium">{friendlyKind(charge.kind)}</p>
                                                                    <p className="text-muted-foreground text-sm">{formatMonthYear(charge.period)}</p>
                                                                </div>

                                                                {/* Center: Overdue indicator (visual badge) */}
                                                                {isOverdue && (
                                                                    <Badge
                                                                        variant="destructive"
                                                                        className="shrink-0 gap-1 px-2 py-0.5 text-xs whitespace-nowrap"
                                                                    >
                                                                        <AlertCircle className="h-3 w-3" />
                                                                        {monthsOverdue > 0
                                                                            ? `${monthsOverdue} ${monthsOverdue === 1 ? 'mes' : 'meses'}`
                                                                            : `${days} días`}
                                                                    </Badge>
                                                                )}

                                                                {/* Right: Amount in original currency + Bs equivalent */}
                                                                <div className="shrink-0 text-right">
                                                                    <p className={cn('font-semibold', isOverdue && 'text-red-600')}>
                                                                        {fmtOriginal(amountOriginal, charge.currency)}
                                                                    </p>
                                                                    <p className="text-muted-foreground text-xs">{fmtBs(amountBs)}</p>
                                                                </div>
                                                            </div>
                                                        );
                                                    },
                                                )}

                                                {/* Show more link */}
                                                {local.charges.length > 5 && (
                                                    <button
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setShowAllDetails(!showAllDetails);
                                                        }}
                                                        className="mt-2 w-full py-2 text-center text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                                    >
                                                        {showAllDetails ? 'Mostrar menos' : `Ver ${local.charges.length - 5} cargos más`}
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Exchange rates info */}
                {(summary_fx?.rent_m2?.rate_to_ves ||
                    summary_fx?.rent?.rate_to_ves ||
                    summary_fx?.rent_fixed?.rate_to_ves ||
                    summary_fx?.condo?.rate_to_ves) && (
                    <div className="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                        <div className="flex items-start gap-3">
                            <Info className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                            <div className="text-sm">
                                <p className="font-medium text-slate-700 dark:text-slate-300">Tasas de cambio aplicadas ({formatDateShort(at)})</p>
                                <div className="text-muted-foreground mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                    {(summary_fx?.rent_m2?.rate_to_ves || summary_fx?.rent?.rate_to_ves) && (
                                        <span>
                                            1 EUR = Bs.{' '}
                                            {(summary_fx.rent_m2?.rate_to_ves ?? summary_fx.rent?.rate_to_ves ?? 0).toLocaleString('es-VE', {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            })}
                                        </span>
                                    )}
                                    {(summary_fx?.rent_fixed?.rate_to_ves || summary_fx?.condo?.rate_to_ves) && (
                                        <span>
                                            1 USD = Bs.{' '}
                                            {(summary_fx.rent_fixed?.rate_to_ves ?? summary_fx.condo?.rate_to_ves ?? 0).toLocaleString('es-VE', {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            })}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Help section */}
                <Card className="border-dashed">
                    <CardContent className="p-4">
                        <div className="flex items-start gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                <span className="text-lg">💬</span>
                            </div>
                            <div>
                                <p className="font-medium">¿Tienes dudas sobre tu deuda?</p>
                                <p className="text-muted-foreground mt-0.5 text-sm">
                                    Escríbenos a{' '}
                                    <a href="mailto:mercado@chacao.gob.ve" className="text-blue-600 hover:underline">
                                        mercado@chacao.gob.ve
                                    </a>
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
