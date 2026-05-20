import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    Building2,
    Calculator,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    Download,
    Home,
    Smartphone,
    Sparkles,
    TrendingUp,
    User,
    Wallet,
    X,
} from 'lucide-react';
import React from 'react';

// ==================== TYPES ====================
type Summary = {
    open_bs_minor: number;
    overdue_bs_minor: number;
    payments_available_bs_minor: number;
    credits_open_bs_minor: number;
    net_due_after_credit_bs_minor: number;
};

type Reconciliation = {
    summary_bs: {
        gross_debt_bs_minor: number;
        credits_open_bs_minor: number;
        payments_registered_bs_minor: number;
        payments_applied_bs_minor: number;
        payments_available_bs_minor: number;
        eligible_payments_available_bs_minor: number;
        net_due_after_credit_bs_minor: number;
        final_due_bs_minor: number;
    };
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
    currency?: string;
    amount_minor?: number;
    amount_bs_minor: number | null;
    allocated_bs_minor: number;
    credited_bs_minor: number;
    outstanding_bs_minor: number;
    outstanding_minor?: number;
    kind?: string;
};

type Props = {
    header: Header;
    summary_bs: Summary;
    summary_fx?: {
        condo?: {
            currency: 'USD';
            open_minor: number;
            overdue_minor: number;
            open_bs_minor?: number;
            overdue_bs_minor?: number;
            rate_to_ves?: number;
        };
        other?: {
            currency: 'VES';
            open_minor: number;
            overdue_minor: number;
            open_bs_minor?: number;
            overdue_bs_minor?: number;
            rate_to_ves?: number;
        };
        rent?: {
            currency: 'EUR';
            open_minor: number;
            overdue_minor: number;
            open_bs_minor?: number;
            overdue_bs_minor?: number;
            rate_to_ves?: number;
        };
        rent_m2?: {
            currency: 'EUR';
            open_minor: number;
            overdue_minor: number;
            open_bs_minor?: number;
            overdue_bs_minor?: number;
            rate_to_ves?: number;
        };
        rent_fixed?: {
            currency: 'USD';
            open_minor: number;
            overdue_minor: number;
            open_bs_minor?: number;
            overdue_bs_minor?: number;
            rate_to_ves?: number;
        };
    };
    by_local: Array<{
        local_id: number;
        local_label?: string | null;
        open_bs_minor: number;
        overdue_bs_minor: number;
    }>;
    tables: {
        charges_open: ChargeRow[];
        payments_partial: Array<{ payment_id: number; paid_on?: string; available_bs_minor: number }>;
        credits_open: Array<{ credit_id: number; balance_minor: number }>;
    };
    reconciliation?: Reconciliation;
};

// ==================== HELPERS ====================
function fmtBs(minor?: number | null): string {
    if (typeof minor !== 'number') return 'Bs. 0,00';
    return `Bs. ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fmtCurrency(minor?: number | null, currency?: string): string {
    if (typeof minor !== 'number') return '—';
    const cur = (currency || 'VES').toUpperCase();
    const symbol = cur === 'EUR' ? '€' : cur === 'USD' ? '$' : 'Bs.';
    return `${symbol} ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fxToBsMinor(amountMinor: number, rateToVes?: number): number {
    if (!rateToVes || rateToVes <= 0) return 0;
    const rateMinor = Math.round(rateToVes * 100);
    if (rateMinor <= 0) return 0;
    // Use Math.round to match backend FxConversionHelper::toVes() behavior
    return Math.round((amountMinor * rateMinor) / 100);
}

function friendlyKind(kind?: string): string {
    const k = (kind || '').toUpperCase();
    if (k.includes('CONDO')) return 'Condominio';
    if (k === 'RENT_EUR_FIXED') return 'Alquiler fijo';
    if (k.includes('RENT')) return 'Tasa de uso';
    if (k === 'FINE') return 'Cargo por multa';
    if (k === 'ADJ') return 'Gasto Fijo de Mantenimiento';
    if (k === 'CESION_DERECHOS') return 'Cesión de derechos';
    return 'Cargo';
}

function daysOverdue(dueDate?: string): number {
    if (!dueDate) return 0;
    const due = new Date(dueDate);
    const now = new Date();
    return Math.max(0, Math.floor((now.getTime() - due.getTime()) / (1000 * 60 * 60 * 24)));
}

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

function caracasTodayIso(): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Caracas',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
}

function isoDateToSafeDate(isoDate: string): Date {
    return new Date(`${isoDate}T12:00:00Z`);
}

function formatCaracasLongDate(isoDate: string): string {
    return new Intl.DateTimeFormat('es-VE', {
        timeZone: 'America/Caracas',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(isoDateToSafeDate(isoDate));
}

// ==================== MAIN COMPONENT ====================
export default function EconomicProfileLocalUltra(props: Props) {
    const { header, summary_bs, summary_fx, tables, reconciliation } = props;
    const charges = React.useMemo(() => tables.charges_open || [], [tables.charges_open]);

    // State
    const [selected, setSelected] = React.useState<Record<number, boolean>>({});
    const [filterType, setFilterType] = React.useState<'all' | 'condo' | 'rent' | 'other'>('all');

    // FX rates and original currency amounts (kept for display purposes only)
    const condoDebt = summary_fx?.condo?.open_minor ?? 0;
    const rentM2Debt = summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor ?? 0;
    const rentFixedDebt = summary_fx?.rent_fixed?.open_minor ?? 0;
    const condoRate = summary_fx?.condo?.rate_to_ves;
    const rentM2Rate = summary_fx?.rent_m2?.rate_to_ves ?? summary_fx?.rent?.rate_to_ves;
    const rentFixedRate = summary_fx?.rent_fixed?.rate_to_ves ?? condoRate;

    // Always use sum-then-convert for consistency across all totals
    const condoBsMinor = fxToBsMinor(condoDebt, condoRate);
    const rentM2BsMinor = fxToBsMinor(rentM2Debt, rentM2Rate);
    const rentFixedBsMinor = fxToBsMinor(rentFixedDebt, rentFixedRate);
    const otherBsMinor = Number(summary_fx?.other?.open_bs_minor ?? 0);

    const otherFx = React.useMemo(() => {
        const others = charges.filter((c) => {
            const k = (c.kind || '').toUpperCase();
            return !k.includes('CONDO') && !k.includes('RENT');
        });
        const eurMinor = others.filter((c) => c.currency === 'EUR').reduce((s, c) => s + (c.outstanding_minor || 0), 0);
        const usdMinor = others.filter((c) => c.currency === 'USD').reduce((s, c) => s + (c.outstanding_minor || 0), 0);
        return { eurMinor, usdMinor };
    }, [charges]);

    // Use canonical reconciliation values instead of recalculating
    const totalDebt = reconciliation?.summary_bs?.gross_debt_bs_minor ?? condoBsMinor + rentM2BsMinor + rentFixedBsMinor + otherBsMinor;
    const overdueDebt = summary_bs.overdue_bs_minor || 0;
    const creditsAvail = reconciliation?.summary_bs?.credits_open_bs_minor ?? summary_bs.credits_open_bs_minor ?? 0;
    const paymentsAvail = reconciliation?.summary_bs?.payments_available_bs_minor ?? summary_bs.payments_available_bs_minor ?? 0;
    const netDue = reconciliation?.summary_bs?.final_due_bs_minor ?? Math.max(0, totalDebt - creditsAvail);

    const hasDebt = totalDebt > 0;
    const hasOverdue = overdueDebt > 0;
    const hasCredits = creditsAvail > 0;
    const hasPaymentsAvailable = paymentsAvail > 0;

    // Filter charges
    const filteredCharges = React.useMemo(() => {
        let result = charges;
        if (filterType === 'condo') {
            result = result.filter((c) => (c.kind || '').toUpperCase().includes('CONDO'));
        } else if (filterType === 'rent') {
            result = result.filter((c) => (c.kind || '').toUpperCase().includes('RENT'));
        } else if (filterType === 'other') {
            result = result.filter((c) => {
                const k = (c.kind || '').toUpperCase();
                return !k.includes('CONDO') && !k.includes('RENT');
            });
        }
        return result.sort((a, b) => {
            const dateA = a.due_on ? new Date(a.due_on).getTime() : Infinity;
            const dateB = b.due_on ? new Date(b.due_on).getTime() : Infinity;
            return dateA - dateB;
        });
    }, [charges, filterType]);

    // Overdue info
    const now = new Date();
    const overdueCharges = charges.filter((c) => c.due_on && new Date(c.due_on) < now);
    const oldestOverdue = overdueCharges.length > 0 ? Math.max(...overdueCharges.map((c) => daysOverdue(c.due_on))) : 0;
    const overduePercentage = totalDebt > 0 ? Math.round((overdueDebt / totalDebt) * 100) : 0;

    const todayCaracas = caracasTodayIso();
    const atParam = (() => {
        if (typeof window === 'undefined') return todayCaracas;
        const p = new URLSearchParams(window.location.search);
        const fromUrl = p.get('at');
        if (!fromUrl) return todayCaracas;
        return fromUrl > todayCaracas ? todayCaracas : fromUrl;
    })();

    React.useEffect(() => {
        if (typeof window === 'undefined') return;
        const p = new URLSearchParams(window.location.search);
        const fromUrl = p.get('at');
        if (!fromUrl) return;
        if (fromUrl <= todayCaracas) return;
        p.set('at', todayCaracas);
        router.visit(`${window.location.pathname}?${p.toString()}`, {
            replace: true,
            preserveScroll: true,
        });
    }, [todayCaracas]);

    const statementUrl = (document: 'statement' | 'payment_history' | 'balance' = 'statement') =>
        `/admin/economic-profile/statement?scope=local&id=${header.id}&at=${encodeURIComponent(atParam)}&document=${document}`;

    const formattedDate = React.useMemo(() => {
        try {
            return formatCaracasLongDate(atParam);
        } catch {
            return atParam;
        }
    }, [atParam]);

    // Selection helpers
    const selectedCharges = React.useMemo(() => filteredCharges.filter((c) => selected[c.charge_id]), [filteredCharges, selected]);

    // Sum in original currency first (EUR and USD)
    const selectedTotalEur = React.useMemo(
        () => selectedCharges.filter((c) => c.currency === 'EUR').reduce((acc, c) => acc + (c.outstanding_minor || 0), 0),
        [selectedCharges],
    );

    const selectedTotalUsd = React.useMemo(
        () => selectedCharges.filter((c) => c.currency === 'USD').reduce((acc, c) => acc + (c.outstanding_minor || 0), 0),
        [selectedCharges],
    );

    // Convert totals to Bs ONCE (sum-then-convert for accuracy)
    const selectedBsEur = React.useMemo(() => fxToBsMinor(selectedTotalEur, rentM2Rate), [selectedTotalEur, rentM2Rate]);

    const selectedBsUsd = React.useMemo(() => fxToBsMinor(selectedTotalUsd, condoRate), [selectedTotalUsd, condoRate]);

    // Total Bs is sum of converted totals (not sum of individual conversions)
    const selectedTotalBs = React.useMemo(() => selectedBsEur + selectedBsUsd, [selectedBsEur, selectedBsUsd]);

    const selectedCount = selectedCharges.length;

    const toggleCharge = (id: number) => {
        setSelected((prev) => {
            const next = { ...prev };
            if (next[id]) delete next[id];
            else next[id] = true;
            return next;
        });
    };

    const selectAllFiltered = () => {
        setSelected((prev) => {
            const next = { ...prev };
            filteredCharges.forEach((c) => {
                next[c.charge_id] = true;
            });
            return next;
        });
    };

    const selectAllOverdue = () => {
        setSelected((prev) => {
            const next = { ...prev };
            filteredCharges.forEach((c) => {
                if (c.due_on && new Date(c.due_on) < now) {
                    next[c.charge_id] = true;
                }
            });
            return next;
        });
    };

    const clearSelection = () => setSelected({});

    const goToPaymentCreate = (method?: 'TRANSFER' | 'PMOV' | 'DEB') => {
        const chargeIds = selectedCharges.map((c) => c.charge_id).join(',');
        const debtorType = header.concessionaire ? 'CONCESSIONAIRE' : 'LOCAL';
        const debtorId = header.concessionaire ? header.concessionaire.id : header.id;
        const qs = new URLSearchParams({
            debtor_type: debtorType,
            debtor_id: String(debtorId),
            amount_bs_minor: String(selectedTotalBs),
            charge_ids: chargeIds,
            paid_on: atParam,
        });
        if (header.concessionaire) {
            qs.set('local_id', String(header.id));
        }
        if (method) qs.set('method', method);
        router.visit(`/payments/create?${qs.toString()}`);
    };

    const _allSelected = filteredCharges.length > 0 && filteredCharges.every((c) => selected[c.charge_id]);

    return (
        <div className="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900">
            <div className="mx-auto w-full max-w-4xl px-4 py-6">
                {/* ===== HEADER ===== */}
                <div className="mb-6 flex items-center justify-between">
                    <Link
                        href="/admin/economic-profile"
                        className="flex items-center gap-2 text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Nueva búsqueda
                    </Link>
                    <div className="flex items-center gap-2 text-sm text-slate-500">
                        <Calendar className="h-4 w-4" />
                        <span className="hidden sm:inline">Corte:</span>
                        <input
                            type="date"
                            value={atParam}
                            max={todayCaracas}
                            className="h-8 rounded-md border border-slate-200 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-800"
                            onChange={(e) => {
                                const nextAt = e.target.value || todayCaracas;
                                const safeAt = nextAt > todayCaracas ? todayCaracas : nextAt;
                                const p = new URLSearchParams(window.location.search);
                                p.set('at', safeAt);
                                router.visit(`${window.location.pathname}?${p.toString()}`, {
                                    preserveScroll: true,
                                    preserveState: true,
                                });
                            }}
                        />
                        <span className="hidden lg:inline">({formattedDate})</span>
                    </div>
                </div>

                {/* ===== HERO CARD ===== */}
                <Card className={cn('relative mb-6 overflow-hidden border-0 shadow-xl', hasOverdue && 'ring-2 ring-red-200 dark:ring-red-900/50')}>
                    <CardContent className="p-0">
                        <div className="flex flex-col lg:flex-row">
                            {/* Left: Identity + Amount */}
                            <div className="flex-1 p-6 lg:p-8">
                                {/* Identity */}
                                <div className="mb-4 flex items-start gap-4">
                                    <div className="from-primary to-primary/80 flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-lg">
                                        <Home className="h-6 w-6" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <Badge variant="outline" className="mb-1 font-mono text-sm font-semibold">
                                            {header.code || `#${header.id}`}
                                        </Badge>
                                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{header.name || 'Local'}</h1>
                                    </div>
                                </div>

                                {/* Concessionaire */}
                                {header.concessionaire && (
                                    <div className="border-ring/30 bg-primary/10 dark:border-ring/30 dark:bg-primary/10 mb-4 flex items-center gap-3 rounded-xl border p-3">
                                        <User className="text-primary h-5 w-5" />
                                        <div className="flex-1">
                                            <p className="text-primary dark:text-primary text-xs font-medium uppercase">Cesionario</p>
                                            <p className="font-semibold text-slate-900 dark:text-white">{header.concessionaire.full_name}</p>
                                        </div>
                                        {header.concessionaire.contract && (
                                            <Badge variant="secondary" className="font-mono text-xs">
                                                {header.concessionaire.contract.number}
                                            </Badge>
                                        )}
                                    </div>
                                )}

                                {/* Status */}
                                {hasOverdue ? (
                                    <Badge variant="destructive" className="mb-4 gap-1.5 px-3 py-1.5 text-sm">
                                        <AlertCircle className="h-4 w-4" />
                                        {overdueCharges.length} {overdueCharges.length === 1 ? 'cargo vencido' : 'cargos vencidos'}
                                    </Badge>
                                ) : hasDebt ? (
                                    <Badge className="bg-warning/10 text-warning hover:bg-warning/10 mb-4 gap-1.5 px-3 py-1.5 text-sm">
                                        <Clock className="h-4 w-4" />
                                        {charges.length} cargos pendientes
                                    </Badge>
                                ) : (
                                    <Badge className="bg-success/10 text-success hover:bg-success/10 mb-4 gap-1.5 px-3 py-1.5 text-sm">
                                        <CheckCircle2 className="h-4 w-4" />
                                        Al día
                                    </Badge>
                                )}

                                {/* Total */}
                                <div>
                                    <p className="text-muted-foreground mb-1 text-sm">Total que debe este local</p>
                                    <p
                                        className={cn(
                                            'text-4xl font-bold tracking-tight',
                                            hasOverdue ? 'text-red-600' : 'text-slate-900 dark:text-white',
                                        )}
                                    >
                                        {fmtBs(netDue)}
                                    </p>
                                </div>

                                {hasOverdue && (
                                    <div className="mt-4 space-y-2">
                                        <div className="flex items-center gap-2 text-sm text-red-700">
                                            <Clock className="h-4 w-4" />
                                            Cargo más antiguo venció <strong>{timeAgo(oldestOverdue)}</strong>
                                        </div>
                                        <div className="space-y-1">
                                            <div className="flex justify-between text-xs">
                                                <span className="text-muted-foreground">Vencido</span>
                                                <span className="font-medium text-red-600">
                                                    {fmtBs(overdueDebt)} ({overduePercentage}%)
                                                </span>
                                            </div>
                                            <Progress value={overduePercentage} className="h-2 bg-red-100" />
                                        </div>
                                    </div>
                                )}

                                {/* Export */}
                                <div className="mt-6 flex gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={statementUrl('statement')} className="gap-2" target="_blank" rel="noreferrer">
                                            <Download className="h-4 w-4" />
                                            Estado de cuenta
                                        </a>
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={statementUrl('payment_history')} className="gap-2" target="_blank" rel="noreferrer">
                                            <Download className="h-4 w-4" />
                                            Histórico de pagos
                                        </a>
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={statementUrl('balance')} className="gap-2" target="_blank" rel="noreferrer">
                                            <Download className="h-4 w-4" />
                                            Balance
                                        </a>
                                    </Button>
                                </div>
                            </div>

                            {/* Right: Breakdown */}
                            {(condoDebt > 0 || rentM2Debt > 0 || rentFixedDebt > 0 || otherBsMinor > 0) && (
                                <div className="border-t bg-slate-50/80 p-6 lg:w-64 lg:border-t-0 lg:border-l dark:bg-slate-900/50">
                                    <h3 className="text-muted-foreground mb-4 text-xs font-semibold tracking-wider uppercase">Desglose</h3>
                                    <div className="space-y-3">
                                        {rentM2Debt > 0 && (
                                            <button
                                                onClick={() => setFilterType(filterType === 'rent' ? 'all' : 'rent')}
                                                className={cn(
                                                    'w-full rounded-xl p-3 text-left transition-all',
                                                    filterType === 'rent'
                                                        ? 'bg-primary/10 ring-ring ring-2'
                                                        : 'bg-white hover:bg-slate-50 dark:bg-slate-800',
                                                )}
                                            >
                                                <div className="mb-1 flex items-center gap-2">
                                                    <Building2 className="text-primary h-4 w-4" />
                                                    <span className="text-sm font-medium">Tasa de Uso</span>
                                                </div>
                                                <p className="text-lg font-bold">{fmtCurrency(rentM2Debt, 'EUR')}</p>
                                                <p className="text-muted-foreground text-xs">{fmtBs(rentM2BsMinor)}</p>
                                            </button>
                                        )}

                                        {rentFixedDebt > 0 && (
                                            <button
                                                onClick={() => setFilterType(filterType === 'rent' ? 'all' : 'rent')}
                                                className={cn(
                                                    'w-full rounded-xl p-3 text-left transition-all',
                                                    filterType === 'rent'
                                                        ? 'bg-primary/10 ring-ring ring-2'
                                                        : 'bg-white hover:bg-slate-50 dark:bg-slate-800',
                                                )}
                                            >
                                                <div className="mb-1 flex items-center gap-2">
                                                    <Wallet className="text-primary h-4 w-4" />
                                                    <span className="text-sm font-medium">Alquiler fijo</span>
                                                </div>
                                                <p className="text-lg font-bold">{fmtCurrency(rentFixedDebt, 'USD')}</p>
                                                <p className="text-muted-foreground text-xs">{fmtBs(rentFixedBsMinor)}</p>
                                            </button>
                                        )}

                                        {condoDebt > 0 && (
                                            <button
                                                onClick={() => setFilterType(filterType === 'condo' ? 'all' : 'condo')}
                                                className={cn(
                                                    'w-full rounded-xl p-3 text-left transition-all',
                                                    filterType === 'condo'
                                                        ? 'bg-info/10 ring-ring ring-2'
                                                        : 'bg-white hover:bg-slate-50 dark:bg-slate-800',
                                                )}
                                            >
                                                <div className="mb-1 flex items-center gap-2">
                                                    <Sparkles className="text-info h-4 w-4" />
                                                    <span className="text-sm font-medium">Condominio</span>
                                                </div>
                                                <p className="text-lg font-bold">{fmtCurrency(condoDebt, 'USD')}</p>
                                                <p className="text-muted-foreground text-xs">{fmtBs(condoBsMinor)}</p>
                                            </button>
                                        )}

                                        {otherBsMinor > 0 && (
                                            <button
                                                onClick={() => setFilterType(filterType === 'other' ? 'all' : 'other')}
                                                className={cn(
                                                    'w-full rounded-xl p-3 text-left transition-all',
                                                    filterType === 'other'
                                                        ? 'ring-ring bg-amber-500/10 ring-2'
                                                        : 'bg-white hover:bg-slate-50 dark:bg-slate-800',
                                                )}
                                            >
                                                <div className="mb-1 flex items-center gap-2">
                                                    <AlertCircle className="h-4 w-4 text-amber-500" />
                                                    <span className="text-sm font-medium">Otros cargos</span>
                                                </div>
                                                {otherFx.eurMinor > 0 && <p className="text-lg font-bold">{fmtCurrency(otherFx.eurMinor, 'EUR')}</p>}
                                                {otherFx.usdMinor > 0 && (
                                                    <p className={cn('font-bold', otherFx.eurMinor > 0 ? 'text-base' : 'text-lg')}>
                                                        {fmtCurrency(otherFx.usdMinor, 'USD')}
                                                    </p>
                                                )}
                                                <p className="text-muted-foreground text-xs">{fmtBs(otherBsMinor)}</p>
                                            </button>
                                        )}

                                        {(condoRate || rentM2Rate) && (
                                            <div className="rounded-lg border border-slate-200 p-2 text-xs dark:border-slate-700">
                                                <p className="mb-1 font-semibold text-slate-500 uppercase">Tasa BCV</p>
                                                <div className="flex gap-2">
                                                    {rentM2Rate && <span>€ {rentM2Rate.toFixed(2)}</span>}
                                                    {condoRate && <span>$ {condoRate.toFixed(2)}</span>}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* ===== QUICK INFO ===== */}
                {(hasCredits || hasPaymentsAvailable) && (
                    <div className="mb-6 grid gap-3 sm:grid-cols-2">
                        {hasCredits && (
                            <Card className="border-success/20 bg-success/10">
                                <CardContent className="flex items-center gap-3 p-4">
                                    <TrendingUp className="text-success h-5 w-5" />
                                    <div>
                                        <p className="text-success text-sm font-medium">Saldo a favor</p>
                                        <p className="text-success text-lg font-bold">{fmtBs(creditsAvail)}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                        {hasPaymentsAvailable && (
                            <Card className="border-info/20 bg-info/10">
                                <CardContent className="flex items-center gap-3 p-4">
                                    <Wallet className="text-info h-5 w-5" />
                                    <div>
                                        <p className="text-info text-sm font-medium">Pagos por aplicar</p>
                                        <p className="text-info text-lg font-bold">{fmtBs(paymentsAvail)}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* ===== STICKY ESTIMATION CARD ===== */}
                {selectedCount > 0 && (
                    <Card className="border-ring sticky top-4 z-20 mb-6 border-2 bg-white shadow-2xl dark:bg-slate-900">
                        <CardContent className="p-4">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-4">
                                    <div className="bg-primary/10 dark:bg-primary/10 flex h-12 w-12 items-center justify-center rounded-xl">
                                        <Calculator className="text-primary h-6 w-6" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-slate-600">
                                            {selectedCount} {selectedCount === 1 ? 'cargo' : 'cargos'}
                                        </p>
                                        <p className="text-primary text-2xl font-bold">{fmtBs(selectedTotalBs)}</p>
                                        <div className="mt-1 flex flex-wrap gap-3 text-xs text-slate-500">
                                            {selectedTotalEur > 0 && (
                                                <span className="flex flex-col">
                                                    <span>{fmtCurrency(selectedTotalEur, 'EUR')}</span>
                                                    <span className="text-muted-foreground">{fmtBs(selectedBsEur)}</span>
                                                </span>
                                            )}
                                            {selectedTotalUsd > 0 && (
                                                <span className="flex flex-col">
                                                    <span>{fmtCurrency(selectedTotalUsd, 'USD')}</span>
                                                    <span className="text-muted-foreground">{fmtBs(selectedBsUsd)}</span>
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" onClick={clearSelection} className="gap-2">
                                        <X className="h-4 w-4" />
                                        Limpiar
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="gap-2"
                                        title="Transferencia"
                                        onClick={() => goToPaymentCreate('TRANSFER')}
                                    >
                                        <Building2 className="h-4 w-4" />
                                        <span className="hidden sm:inline">TRF</span>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="gap-2"
                                        title="Pago móvil"
                                        onClick={() => goToPaymentCreate('PMOV')}
                                    >
                                        <Smartphone className="h-4 w-4" />
                                        <span className="hidden sm:inline">PMOV</span>
                                    </Button>
                                    <Button variant="outline" size="sm" className="gap-2" title="Débito" onClick={() => goToPaymentCreate('DEB')}>
                                        <CreditCard className="h-4 w-4" />
                                        <span className="hidden sm:inline">DEB</span>
                                    </Button>
                                    <Button
                                        size="sm"
                                        className="bg-primary hover:bg-primary/90 gap-2"
                                        title="Registrar pago"
                                        onClick={() => goToPaymentCreate()}
                                    >
                                        <ArrowRight className="h-4 w-4" />
                                        <span className="hidden sm:inline">Registrar</span>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* ===== CHARGES LIST ===== */}
                {filteredCharges.length > 0 && (
                    <Card className="mb-6">
                        <CardHeader className="pb-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    Cargos pendientes
                                    <Badge variant="secondary">{filteredCharges.length}</Badge>
                                    {filterType !== 'all' && (
                                        <Badge variant="outline" className="cursor-pointer gap-1" onClick={() => setFilterType('all')}>
                                            {filterType === 'rent' ? 'Tasa de uso' : filterType === 'other' ? 'Otros cargos' : 'Condominio'}
                                            <X className="h-3 w-3" />
                                        </Badge>
                                    )}
                                </CardTitle>
                                <div className="flex flex-wrap gap-2">
                                    <Button variant="outline" size="sm" onClick={selectAllOverdue} className="gap-1.5 text-red-600 hover:bg-red-50">
                                        <AlertCircle className="h-3.5 w-3.5" />
                                        Vencidos
                                    </Button>
                                    <Button variant="outline" size="sm" onClick={selectAllFiltered} className="gap-1.5">
                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                        Todos
                                    </Button>
                                    {selectedCount > 0 && (
                                        <Button variant="ghost" size="sm" onClick={clearSelection} className="gap-1.5 text-slate-500">
                                            <X className="h-3.5 w-3.5" />
                                            Limpiar
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2 pt-0">
                            {filteredCharges.map((charge) => {
                                const days = daysOverdue(charge.due_on);
                                const isOverdue = days > 0;
                                const monthsOverdue = Math.floor(days / 30);
                                const isSelected = selected[charge.charge_id];

                                return (
                                    <div
                                        key={charge.charge_id}
                                        className={cn(
                                            'flex items-center gap-3 rounded-xl border p-4 transition-all',
                                            isSelected
                                                ? 'border-ring bg-primary/10 ring-ring/20 dark:bg-primary/10 ring-1'
                                                : isOverdue
                                                  ? 'border-red-200 bg-red-50/50 dark:bg-red-950/20'
                                                  : 'border-slate-200 bg-white dark:bg-slate-900',
                                        )}
                                    >
                                        <Checkbox
                                            checked={isSelected}
                                            aria-label="Seleccionar cargo para estimar"
                                            title="Seleccionar cargo para estimar"
                                            className={cn(
                                                'hover:border-ring size-6 cursor-pointer rounded-md border-2 border-slate-300 bg-white shadow-sm transition-colors dark:border-slate-600 dark:bg-slate-950',
                                                'data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground',
                                            )}
                                            onCheckedChange={() => toggleCharge(charge.charge_id)}
                                        />

                                        <div
                                            className={cn(
                                                'flex h-10 w-10 items-center justify-center rounded-lg',
                                                isOverdue ? 'bg-red-100' : 'bg-slate-100',
                                            )}
                                        >
                                            {(charge.kind || '').includes('CONDO') ? (
                                                <Sparkles className={cn('h-5 w-5', isOverdue ? 'text-red-600' : 'text-slate-600')} />
                                            ) : (
                                                <Building2 className={cn('h-5 w-5', isOverdue ? 'text-red-600' : 'text-slate-600')} />
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">{friendlyKind(charge.kind)}</p>
                                            <p className="text-muted-foreground text-sm">
                                                {new Date(charge.period).toLocaleDateString('es-VE', { month: 'long', year: 'numeric' })}
                                            </p>
                                        </div>

                                        {isOverdue && (
                                            <Badge variant="destructive" className="shrink-0 gap-1 px-2 py-0.5 text-xs">
                                                <AlertCircle className="h-3 w-3" />
                                                {monthsOverdue > 0 ? `${monthsOverdue}m` : `${days}d`}
                                            </Badge>
                                        )}

                                        <div className="shrink-0 text-right">
                                            <p className={cn('text-lg font-bold', isOverdue && 'text-red-600')}>
                                                {fmtCurrency(charge.outstanding_minor, charge.currency)}
                                            </p>
                                            <p className="text-muted-foreground text-xs">{fmtBs(charge.outstanding_bs_minor)}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}

                {/* ===== NO DEBT STATE ===== */}
                {!hasDebt && (
                    <Card className="border-success/20 bg-success/10">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="bg-success/10 flex h-12 w-12 items-center justify-center rounded-full">
                                <Sparkles className="text-success h-6 w-6" />
                            </div>
                            <div>
                                <p className="text-success text-lg font-semibold">¡Sin deudas!</p>
                                <p className="text-success">Este local está al día con todos sus pagos.</p>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </div>
    );
}

EconomicProfileLocalUltra.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
