import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { formatMonthYear } from '@/lib/date-utils';
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
    ChevronDown,
    ChevronUp,
    Clock,
    CreditCard,
    Download,
    Filter,
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
    local_code?: string | null;
    local_type_name?: string | null;
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

type LocalSummary = {
    local_id: number;
    local_label?: string | null;
    local_code?: string | null;
    local_type_name?: string | null;
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
    locals?: Array<{ id: number; code?: string | null; name?: string | null }>;
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
    by_local: LocalSummary[];
    tables: {
        charges_open: ChargeRow[];
        payments_partial: Array<{ payment_id: number; paid_on?: string; available_bs_minor: number }>;
        credits_open: Array<{ credit_id: number; balance_minor: number }>;
    };
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

// ==================== GROUP CHARGES BY LOCAL ====================
type LocalGroup = {
    local_id: number;
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
    charges: ChargeRow[];
};

function groupChargesByLocal(charges: ChargeRow[], byLocal: LocalSummary[]): LocalGroup[] {
    const groups: Record<number, LocalGroup> = {};
    const now = new Date();

    // Initialize from by_local
    byLocal.forEach((l) => {
        groups[l.local_id] = {
            local_id: l.local_id,
            local_code: l.local_code || `L-${l.local_id}`,
            local_type: l.local_type_name || '',
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
    });

    charges.forEach((c) => {
        if (typeof c.local_id !== 'number') return;
        const localId = c.local_id;
        if (!groups[localId]) {
            groups[localId] = {
                local_id: localId,
                local_code: c.local_code || `L-${localId}`,
                local_type: c.local_type_name || '',
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

        const amountBs = c.outstanding_bs_minor || 0;
        const amountOriginal = c.outstanding_minor || 0;
        const currency = (c.currency || 'VES').toUpperCase();

        groups[localId].count++;
        groups[localId].total_bs += amountBs;
        groups[localId].charges.push(c);

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

    // Sort charges within each group
    Object.values(groups).forEach((g) => {
        g.charges.sort((a, b) => {
            const dateA = a.due_on ? new Date(a.due_on).getTime() : Infinity;
            const dateB = b.due_on ? new Date(b.due_on).getTime() : Infinity;
            return dateA - dateB;
        });
    });

    return Object.values(groups)
        .filter((g) => g.count > 0)
        .sort((a, b) => b.overdue_bs - a.overdue_bs || b.total_bs - a.total_bs);
}

// ==================== MAIN COMPONENT ====================
export default function EconomicProfileConcessionaireUltra(props: Props) {
    const { header, locals = [], summary_bs, summary_fx, by_local, tables } = props;
    const charges = React.useMemo(() => tables.charges_open || [], [tables.charges_open]);

    // State
    const [expandedLocal, setExpandedLocal] = React.useState<number | null>(null);
    const [selected, setSelected] = React.useState<Record<number, boolean>>({});
    const [filterType, setFilterType] = React.useState<'all' | 'condo' | 'rent' | 'other'>('all');
    const [filterLocal, setFilterLocal] = React.useState<number | 'all'>('all');
    const [statementOpen, setStatementOpen] = React.useState(false);
    const [statementSelected, setStatementSelected] = React.useState<Record<number, boolean>>({});
    const [statementDocument, setStatementDocument] = React.useState<'statement' | 'payment_history' | 'balance'>('statement');

    // FX rates and original currency amounts
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

    // Derived values - recalculate totalDebt from FX components for consistency
    const totalDebt = condoBsMinor + rentM2BsMinor + rentFixedBsMinor + otherBsMinor;
    const overdueDebt = summary_bs.overdue_bs_minor || 0;
    const creditsAvail = summary_bs.credits_open_bs_minor || 0;
    const paymentsAvail = summary_bs.payments_available_bs_minor || 0;
    const netDue = Math.max(0, totalDebt - creditsAvail);

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
        if (filterLocal !== 'all') {
            result = result.filter((c) => c.local_id === filterLocal);
        }
        return result;
    }, [charges, filterType, filterLocal]);

    const concessionaireCharges = React.useMemo(() => {
        return filteredCharges
            .filter((c) => c.local_id == null)
            .slice()
            .sort((a, b) => {
                const dateA = a.due_on ? new Date(a.due_on).getTime() : Infinity;
                const dateB = b.due_on ? new Date(b.due_on).getTime() : Infinity;
                return dateA - dateB;
            });
    }, [filteredCharges]);

    const localCharges = React.useMemo(() => {
        return filteredCharges.filter((c) => c.local_id != null);
    }, [filteredCharges]);

    // Group by local
    const localGroups = groupChargesByLocal(localCharges, by_local);

    // Overdue info
    const now = new Date();
    const overdueCharges = charges.filter((c) => c.due_on && new Date(c.due_on) < now);
    const oldestOverdue = overdueCharges.length > 0 ? Math.max(...overdueCharges.map((c) => daysOverdue(c.due_on))) : 0;
    const overduePercentage = totalDebt > 0 ? Math.round((overdueDebt / totalDebt) * 100) : 0;
    const localsWithOverdue = localGroups.filter((l) => l.overdue_count > 0).length;

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

    const formattedDate = React.useMemo(() => {
        try {
            return formatCaracasLongDate(atParam);
        } catch {
            return atParam;
        }
    }, [atParam]);

    const statementLocals = React.useMemo(() => {
        return [...locals]
            .map((local) => ({
                local_id: local.id,
                local_code: local.code ?? '',
                local_label: local.name ?? '',
                local_type_name: '',
            }))
            .sort((a, b) => String(a.local_code || a.local_label || '').localeCompare(String(b.local_code || b.local_label || '')));
    }, [locals]);

    const statementAllChecked = React.useMemo(() => {
        return statementLocals.length > 0 && statementLocals.every((l) => statementSelected[l.local_id]);
    }, [statementLocals, statementSelected]);

    const statementSomeChecked = React.useMemo(() => {
        return statementLocals.some((l) => statementSelected[l.local_id]);
    }, [statementLocals, statementSelected]);

    const toggleStatementAll = (checked: boolean) => {
        if (!checked) {
            setStatementSelected({});
            return;
        }
        const next: Record<number, boolean> = {};
        statementLocals.forEach((l) => {
            next[l.local_id] = true;
        });
        setStatementSelected(next);
    };

    const toggleStatementLocal = (localId: number) => {
        setStatementSelected((prev) => {
            const next = { ...prev };
            if (next[localId]) delete next[localId];
            else next[localId] = true;
            return next;
        });
    };

    const statementUrl = (localIds: number[], document: 'statement' | 'payment_history' | 'balance' = 'statement') => {
        const qs = new URLSearchParams({
            scope: 'concessionaire',
            id: String(header.id),
            at: atParam,
            document,
        });
        localIds.forEach((lid) => qs.append('local_ids[]', String(lid)));
        return `/admin/economic-profile/statement?${qs.toString()}`;
    };

    const downloadStatement = () => {
        const ids = Object.keys(statementSelected)
            .filter((k) => statementSelected[Number(k)])
            .map((k) => Number(k))
            .filter((n) => Number.isFinite(n) && n > 0);
        const url = statementUrl(ids, statementDocument);
        if (typeof window !== 'undefined') {
            window.open(url, '_blank', 'noopener,noreferrer');
        } else {
            router.visit(url);
        }
        setStatementOpen(false);
        setStatementSelected({});
        setStatementDocument('statement');
    };

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

    const selectLocal = (localId: number) => {
        setSelected((prev) => {
            const next = { ...prev };
            filteredCharges.forEach((c) => {
                if (c.local_id === localId) {
                    next[c.charge_id] = true;
                }
            });
            return next;
        });
    };

    const clearSelection = () => setSelected({});

    const goToPaymentCreate = (method?: 'TRANSFER' | 'PMOV' | 'DEB') => {
        const chargeIds = selectedCharges.map((c) => c.charge_id).join(',');
        const qs = new URLSearchParams({
            debtor_type: 'CONCESSIONAIRE',
            debtor_id: String(header.id),
            amount_bs_minor: String(selectedTotalBs),
            charge_ids: chargeIds,
            paid_on: atParam,
        });
        if (method) qs.set('method', method);
        router.visit(`/payments/create?${qs.toString()}`);
    };

    const isLocalFullySelected = (localId: number) => {
        const localCharges = filteredCharges.filter((c) => c.local_id === localId);
        return localCharges.length > 0 && localCharges.every((c) => selected[c.charge_id]);
    };

    const isLocalPartiallySelected = (localId: number) => {
        const localCharges = filteredCharges.filter((c) => c.local_id === localId);
        const selectedCount = localCharges.filter((c) => selected[c.charge_id]).length;
        return selectedCount > 0 && selectedCount < localCharges.length;
    };

    const toggleLocalSelection = (localId: number) => {
        if (isLocalFullySelected(localId)) {
            // Deselect all
            setSelected((prev) => {
                const next = { ...prev };
                filteredCharges.forEach((c) => {
                    if (c.local_id === localId) delete next[c.charge_id];
                });
                return next;
            });
        } else {
            selectLocal(localId);
        }
    };

    // Local options for filter
    const localOptions = React.useMemo(() => {
        return by_local
            .filter((l) => charges.some((c) => c.local_id === l.local_id))
            .map((l) => ({
                id: l.local_id,
                label: l.local_code || `L-${l.local_id}`,
                type: l.local_type_name || '',
            }))
            .sort((a, b) => a.label.localeCompare(b.label));
    }, [by_local, charges]);

    return (
        <div className="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900">
            <div className="mx-auto w-full max-w-5xl px-4 py-6">
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
                                        <User className="h-6 w-6" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="mb-1 flex flex-wrap items-center gap-2">
                                            <Badge variant="outline" className="font-mono text-xs">
                                                {header.document?.type_code}-{header.document?.number}
                                            </Badge>
                                            <Badge variant="secondary" className="text-xs">
                                                {header.locals_count || 0} locales
                                            </Badge>
                                        </div>
                                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{header.full_name}</h1>
                                    </div>
                                </div>

                                {/* Status */}
                                {hasOverdue ? (
                                    <Badge variant="destructive" className="mb-4 gap-1.5 px-3 py-1.5 text-sm">
                                        <AlertCircle className="h-4 w-4" />
                                        {localsWithOverdue} {localsWithOverdue === 1 ? 'local con deuda vencida' : 'locales con deuda vencida'}
                                    </Badge>
                                ) : hasDebt ? (
                                    <Badge className="bg-warning/10 text-warning hover:bg-warning/10 mb-4 gap-1.5 px-3 py-1.5 text-sm">
                                        <Clock className="h-4 w-4" />
                                        Cargos pendientes
                                    </Badge>
                                ) : (
                                    <Badge className="bg-success/10 text-success hover:bg-success/10 mb-4 gap-1.5 px-3 py-1.5 text-sm">
                                        <CheckCircle2 className="h-4 w-4" />
                                        Al día
                                    </Badge>
                                )}

                                {/* Total */}
                                <div>
                                    <p className="text-muted-foreground mb-1 text-sm">Total que debe</p>
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

                                {/* Export buttons */}
                                <div className="mt-6 flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="gap-2"
                                        onClick={() => {
                                            setStatementDocument('statement');
                                            setStatementOpen(true);
                                        }}
                                    >
                                        <Download className="h-4 w-4" />
                                        Estado de cuenta
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="gap-2"
                                        onClick={() => {
                                            setStatementDocument('payment_history');
                                            setStatementOpen(true);
                                        }}
                                    >
                                        <Download className="h-4 w-4" />
                                        Histórico de pagos
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="gap-2"
                                        onClick={() => {
                                            setStatementDocument('balance');
                                            setStatementOpen(true);
                                        }}
                                    >
                                        <Download className="h-4 w-4" />
                                        Balance
                                    </Button>
                                </div>
                            </div>

                            {/* Right: Breakdown by type */}
                            <div className="border-t bg-slate-50/80 p-6 lg:w-72 lg:border-t-0 lg:border-l dark:bg-slate-900/50">
                                <h3 className="text-muted-foreground mb-4 text-xs font-semibold tracking-wider uppercase">Desglose por tipo</h3>
                                <div className="space-y-3">
                                    {rentM2Debt > 0 && (
                                        <button
                                            onClick={() => setFilterType(filterType === 'rent' ? 'all' : 'rent')}
                                            className={cn(
                                                'w-full rounded-xl p-4 text-left transition-all',
                                                filterType === 'rent'
                                                    ? 'bg-primary/10 ring-ring dark:bg-primary/10 ring-2'
                                                    : 'bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700',
                                            )}
                                        >
                                            <div className="mb-1 flex items-center gap-2">
                                                <Building2 className="text-primary h-4 w-4" />
                                                <span className="text-sm font-medium">Tasa de Uso</span>
                                            </div>
                                            <p className="text-xl font-bold">{fmtCurrency(rentM2Debt, 'EUR')}</p>
                                            <p className="text-muted-foreground text-xs">{fmtBs(rentM2BsMinor)}</p>
                                        </button>
                                    )}

                                    {rentFixedDebt > 0 && (
                                        <button
                                            onClick={() => setFilterType(filterType === 'rent' ? 'all' : 'rent')}
                                            className={cn(
                                                'w-full rounded-xl p-4 text-left transition-all',
                                                filterType === 'rent'
                                                    ? 'bg-primary/10 ring-ring dark:bg-primary/10 ring-2'
                                                    : 'bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700',
                                            )}
                                        >
                                            <div className="mb-1 flex items-center gap-2">
                                                <Wallet className="text-primary h-4 w-4" />
                                                <span className="text-sm font-medium">Alquiler fijo</span>
                                            </div>
                                            <p className="text-xl font-bold">{fmtCurrency(rentFixedDebt, 'USD')}</p>
                                            <p className="text-muted-foreground text-xs">{fmtBs(rentFixedBsMinor)}</p>
                                        </button>
                                    )}

                                    {condoDebt > 0 && (
                                        <button
                                            onClick={() => setFilterType(filterType === 'condo' ? 'all' : 'condo')}
                                            className={cn(
                                                'w-full rounded-xl p-4 text-left transition-all',
                                                filterType === 'condo'
                                                    ? 'bg-info/10 ring-ring dark:bg-info/10 ring-2'
                                                    : 'bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700',
                                            )}
                                        >
                                            <div className="mb-1 flex items-center gap-2">
                                                <Sparkles className="text-info h-4 w-4" />
                                                <span className="text-sm font-medium">Condominio</span>
                                            </div>
                                            <p className="text-xl font-bold">{fmtCurrency(condoDebt, 'USD')}</p>
                                            <p className="text-muted-foreground text-xs">{fmtBs(condoBsMinor)}</p>
                                        </button>
                                    )}

                                    {otherBsMinor > 0 && (
                                        <button
                                            onClick={() => setFilterType(filterType === 'other' ? 'all' : 'other')}
                                            className={cn(
                                                'w-full rounded-xl p-4 text-left transition-all',
                                                filterType === 'other'
                                                    ? 'ring-ring bg-amber-500/10 ring-2 dark:bg-amber-500/10'
                                                    : 'bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700',
                                            )}
                                        >
                                            <div className="mb-1 flex items-center gap-2">
                                                <AlertCircle className="h-4 w-4 text-amber-500" />
                                                <span className="text-sm font-medium">Otros cargos</span>
                                            </div>
                                            {otherFx.eurMinor > 0 && <p className="text-xl font-bold">{fmtCurrency(otherFx.eurMinor, 'EUR')}</p>}
                                            {otherFx.usdMinor > 0 && (
                                                <p className={cn('font-bold', otherFx.eurMinor > 0 ? 'text-base' : 'text-xl')}>
                                                    {fmtCurrency(otherFx.usdMinor, 'USD')}
                                                </p>
                                            )}
                                            <p className="text-muted-foreground text-xs">{fmtBs(otherBsMinor)}</p>
                                        </button>
                                    )}

                                    {/* Rates */}
                                    {(condoRate || rentM2Rate) && (
                                        <div className="rounded-lg border border-slate-200 p-3 text-xs dark:border-slate-700">
                                            <p className="mb-1 font-semibold text-slate-500 uppercase">Tasa BCV</p>
                                            <div className="flex gap-3">
                                                {rentM2Rate && <span>€ {rentM2Rate.toFixed(2)}</span>}
                                                {condoRate && <span>$ {condoRate.toFixed(2)}</span>}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Dialog
                    open={statementOpen}
                    onOpenChange={(o) => {
                        setStatementOpen(o);
                        if (!o) {
                            setStatementSelected({});
                            setStatementDocument('statement');
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {statementDocument === 'payment_history'
                                    ? 'Descargar histórico de pagos'
                                    : statementDocument === 'balance'
                                      ? 'Descargar balance'
                                      : 'Descargar estado de cuenta'}
                            </DialogTitle>
                            <DialogDescription>
                                Selecciona los locales a incluir. Si no seleccionas ninguno, se incluirán todos por defecto.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-3">
                            <div className="flex items-center gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                                <Checkbox
                                    checked={statementAllChecked ? true : statementSomeChecked ? 'indeterminate' : false}
                                    aria-label="Seleccionar todos los locales"
                                    onCheckedChange={(v) => toggleStatementAll(Boolean(v))}
                                />
                                <div className="flex-1">
                                    <p className="text-sm font-medium">Todos los locales</p>
                                    <p className="text-muted-foreground text-xs">{statementLocals.length} locales</p>
                                </div>
                                <Button variant="ghost" size="sm" onClick={() => setStatementSelected({})}>
                                    Limpiar
                                </Button>
                            </div>

                            <div className="max-h-[45vh] space-y-2 overflow-y-auto">
                                {statementLocals.map((l) => {
                                    const label = l.local_type_name
                                        ? `${l.local_type_name} ${l.local_code ?? ''}`.trim()
                                        : (l.local_code ?? '').trim();
                                    const display = label !== '' ? label : (l.local_label ?? `Local #${l.local_id}`);
                                    const checked = Boolean(statementSelected[l.local_id]);
                                    return (
                                        <div
                                            key={l.local_id}
                                            className="flex items-center gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                                        >
                                            <Checkbox checked={checked} onCheckedChange={() => toggleStatementLocal(l.local_id)} />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">{display}</p>
                                                <p className="text-muted-foreground text-xs">Local #{l.local_id}</p>
                                            </div>
                                        </div>
                                    );
                                })}
                                {statementLocals.length === 0 && (
                                    <div className="text-muted-foreground rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-800">
                                        No hay locales asociados.
                                    </div>
                                )}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                variant="secondary"
                                onClick={() => {
                                    setStatementSelected({});
                                    setStatementDocument('statement');
                                    setStatementOpen(false);
                                }}
                            >
                                Cancelar
                            </Button>
                            <Button onClick={downloadStatement}>Descargar</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* ===== QUICK INFO ===== */}
                {(hasCredits || hasPaymentsAvailable) && (
                    <div className="mb-6 grid gap-3 sm:grid-cols-2">
                        {hasCredits && (
                            <Card className="border-success/20 bg-success/10 dark:border-success/20 dark:bg-success/10">
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
                            <Card className="border-info/20 bg-info/10 dark:border-info/20 dark:bg-info/10">
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
                                        <p className="text-sm font-medium text-slate-600 dark:text-slate-400">
                                            {selectedCount} {selectedCount === 1 ? 'cargo seleccionado' : 'cargos seleccionados'}
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

                {/* ===== FILTERS & QUICK ACTIONS ===== */}
                <Card className="mb-6">
                    <CardHeader className="pb-3">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Filter className="h-4 w-4" />
                                Seleccionar cargos a cobrar
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
                    <CardContent className="pt-0">
                        <div className="flex flex-wrap gap-2">
                            {/* Filter by local */}
                            <select
                                className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-800"
                                value={filterLocal === 'all' ? 'all' : String(filterLocal)}
                                onChange={(e) => setFilterLocal(e.target.value === 'all' ? 'all' : Number(e.target.value))}
                            >
                                <option value="all">Todos los locales</option>
                                {localOptions.map((l) => (
                                    <option key={l.id} value={l.id}>
                                        {l.type ? `${l.type} ${l.label}` : l.label}
                                    </option>
                                ))}
                            </select>

                            {/* Filter badges */}
                            {filterType !== 'all' && (
                                <Badge variant="secondary" className="cursor-pointer gap-1 hover:bg-slate-200" onClick={() => setFilterType('all')}>
                                    {filterType === 'rent' ? 'Tasa de uso' : filterType === 'other' ? 'Otros cargos' : 'Condominio'}
                                    <X className="h-3 w-3" />
                                </Badge>
                            )}
                            {filterLocal !== 'all' && (
                                <Badge variant="secondary" className="cursor-pointer gap-1 hover:bg-slate-200" onClick={() => setFilterLocal('all')}>
                                    {localOptions.find((l) => l.id === filterLocal)?.label || 'Local'}
                                    <X className="h-3 w-3" />
                                </Badge>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {concessionaireCharges.length > 0 && (
                    <div className="mb-6">
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold">
                            <Building2 className="h-5 w-5 text-slate-500" />
                            Cargos del cesionario
                            <Badge variant="secondary">{concessionaireCharges.length}</Badge>
                        </h2>
                        <div className="space-y-2">
                            {concessionaireCharges.map((charge) => {
                                const days = daysOverdue(charge.due_on);
                                const isOverdue = days > 0;
                                const monthsOverdue = Math.floor(days / 30);
                                const isSelected = selected[charge.charge_id];

                                return (
                                    <div
                                        key={charge.charge_id}
                                        className={cn(
                                            'flex items-center gap-3 rounded-xl border bg-white p-4 transition-all dark:bg-slate-900',
                                            isSelected ? 'ring-ring/20 ring-1' : '',
                                            isOverdue ? 'border-orange-200 dark:border-orange-800/50' : 'border-slate-200 dark:border-slate-700',
                                        )}
                                    >
                                        <Checkbox
                                            checked={!!isSelected}
                                            aria-label="Seleccionar cargo"
                                            title="Seleccionar cargo"
                                            className={cn(
                                                'hover:border-ring size-6 cursor-pointer rounded-md border-2 border-slate-400 bg-white shadow-sm transition-colors dark:border-slate-600 dark:bg-slate-950',
                                                'data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground',
                                            )}
                                            onCheckedChange={() => toggleCharge(charge.charge_id)}
                                        />

                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-medium capitalize">{formatMonthYear(charge.period)}</span>
                                                <span className="text-muted-foreground text-xs">·</span>
                                                <span className="text-muted-foreground text-xs">{friendlyKind(charge.kind)}</span>
                                            </div>
                                        </div>

                                        {isOverdue && (
                                            <Badge className="shrink-0 gap-1 border-orange-200 bg-orange-50 px-2 py-0.5 text-xs text-orange-700 dark:border-orange-800 dark:bg-orange-900/30 dark:text-orange-400">
                                                <Clock className="h-3 w-3" />
                                                {monthsOverdue > 0 ? `${monthsOverdue} mes${monthsOverdue > 1 ? 'es' : ''}` : `${days} días`}
                                            </Badge>
                                        )}

                                        <div className="shrink-0 text-right">
                                            <p className={cn('font-semibold', isOverdue && 'text-orange-700 dark:text-orange-400')}>
                                                {fmtCurrency(charge.outstanding_minor, charge.currency)}
                                            </p>
                                            <p className="text-muted-foreground text-xs">{fmtBs(charge.outstanding_bs_minor)}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* ===== DEBT BY LOCAL ===== */}
                {localGroups.length > 0 && (
                    <div className="mb-6">
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold">
                            <Home className="h-5 w-5 text-slate-500" />
                            Deuda por local
                            <Badge variant="secondary">{localGroups.length}</Badge>
                        </h2>

                        <div className="space-y-2">
                            {localGroups.map((local) => {
                                const isExpanded = expandedLocal === local.local_id;
                                const isFullySelected = isLocalFullySelected(local.local_id);
                                const isPartial = isLocalPartiallySelected(local.local_id);
                                const displayName = local.local_type ? `${local.local_type} ${local.local_code}` : local.local_code;

                                return (
                                    <div key={local.local_id}>
                                        <div
                                            className={cn(
                                                'flex items-center gap-3 rounded-xl border bg-white p-4 transition-all dark:bg-slate-900',
                                                isExpanded && 'ring-ring/30 ring-2',
                                                local.overdue_count > 0
                                                    ? 'border-orange-200 dark:border-orange-800/50'
                                                    : 'border-slate-200 dark:border-slate-700',
                                            )}
                                        >
                                            {/* Checkbox */}
                                            <Checkbox
                                                checked={isPartial ? 'indeterminate' : isFullySelected}
                                                aria-label="Seleccionar cargos de este local"
                                                title="Seleccionar cargos de este local"
                                                className={cn(
                                                    'hover:border-ring size-6 cursor-pointer rounded-md border-2 border-slate-400 bg-white shadow-sm transition-colors dark:border-slate-600 dark:bg-slate-950',
                                                    'data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground',
                                                    'data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground',
                                                )}
                                                onCheckedChange={() => toggleLocalSelection(local.local_id)}
                                            />

                                            {/* Local info */}
                                            <button
                                                onClick={() => setExpandedLocal(isExpanded ? null : local.local_id)}
                                                className="flex flex-1 items-center justify-between text-left"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div
                                                        className={cn(
                                                            'flex h-10 w-10 items-center justify-center rounded-lg',
                                                            local.overdue_count > 0
                                                                ? 'bg-orange-100 dark:bg-orange-900/30'
                                                                : 'bg-slate-100 dark:bg-slate-800',
                                                        )}
                                                    >
                                                        <Home
                                                            className={cn('h-5 w-5', local.overdue_count > 0 ? 'text-orange-600' : 'text-slate-600')}
                                                        />
                                                    </div>
                                                    <div>
                                                        <p className="font-semibold">{displayName}</p>
                                                        <p className="text-muted-foreground text-sm">
                                                            {local.count} cargos
                                                            {local.overdue_count > 0 && (
                                                                <span className="ml-1 text-orange-600 dark:text-orange-400">
                                                                    · {local.overdue_count} vencidos
                                                                </span>
                                                            )}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="flex items-center gap-3">
                                                    <div className="text-right">
                                                        {local.total_eur > 0 && (
                                                            <p
                                                                className={cn(
                                                                    'font-semibold',
                                                                    local.overdue_count > 0 && 'text-orange-700 dark:text-orange-400',
                                                                )}
                                                            >
                                                                {fmtCurrency(local.total_eur, 'EUR')}
                                                            </p>
                                                        )}
                                                        {local.total_bs_eur > 0 && (
                                                            <p className="text-muted-foreground text-xs">{fmtBs(local.total_bs_eur)}</p>
                                                        )}
                                                        {local.total_usd > 0 && (
                                                            <p
                                                                className={cn(
                                                                    'font-semibold',
                                                                    local.overdue_count > 0 && 'text-orange-700 dark:text-orange-400',
                                                                )}
                                                            >
                                                                {fmtCurrency(local.total_usd, 'USD')}
                                                            </p>
                                                        )}
                                                        {local.total_bs_usd > 0 && (
                                                            <p className="text-muted-foreground text-xs">{fmtBs(local.total_bs_usd)}</p>
                                                        )}
                                                        <p className="text-muted-foreground text-xs">{fmtBs(local.total_bs)}</p>
                                                    </div>
                                                    {isExpanded ? (
                                                        <ChevronUp className="h-5 w-5 text-slate-400" />
                                                    ) : (
                                                        <ChevronDown className="h-5 w-5 text-slate-400" />
                                                    )}
                                                </div>
                                            </button>
                                        </div>

                                        {/* Expanded charges */}
                                        {isExpanded && (
                                            <div className="mt-2 space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/50">
                                                {local.charges.map((charge) => {
                                                    const days = daysOverdue(charge.due_on);
                                                    const isOverdue = days > 0;
                                                    const monthsOverdue = Math.floor(days / 30);
                                                    const isSelected = selected[charge.charge_id];

                                                    return (
                                                        <div
                                                            key={charge.charge_id}
                                                            className={cn(
                                                                'hover:ring-ring/20 flex items-center gap-3 rounded-lg px-3 py-2 transition-colors hover:ring-1',
                                                                isSelected
                                                                    ? 'bg-primary/10 ring-ring/20 dark:bg-primary/10 ring-1'
                                                                    : isOverdue
                                                                      ? 'bg-orange-50/50 dark:bg-orange-950/20'
                                                                      : 'bg-white dark:bg-slate-800/50',
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

                                                            <div className="min-w-0 flex-1">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="text-sm font-medium capitalize">
                                                                        {formatMonthYear(charge.period)}
                                                                    </span>
                                                                    <span className="text-muted-foreground text-xs">·</span>
                                                                    <span className="text-muted-foreground text-xs">{friendlyKind(charge.kind)}</span>
                                                                </div>
                                                            </div>

                                                            {isOverdue && (
                                                                <Badge className="shrink-0 gap-1 border-orange-200 bg-orange-50 px-2 py-0.5 text-xs text-orange-700 dark:border-orange-800 dark:bg-orange-900/30 dark:text-orange-400">
                                                                    <Clock className="h-3 w-3" />
                                                                    {monthsOverdue > 0
                                                                        ? `${monthsOverdue} mes${monthsOverdue > 1 ? 'es' : ''}`
                                                                        : `${days} días`}
                                                                </Badge>
                                                            )}

                                                            <div className="shrink-0 text-right">
                                                                <p
                                                                    className={cn(
                                                                        'font-semibold',
                                                                        isOverdue && 'text-orange-700 dark:text-orange-400',
                                                                    )}
                                                                >
                                                                    {fmtCurrency(charge.outstanding_minor, charge.currency)}
                                                                </p>
                                                                <p className="text-muted-foreground text-xs">{fmtBs(charge.outstanding_bs_minor)}</p>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
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
                                <p className="text-success">Este cesionario está al día con todos sus pagos.</p>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </div>
    );
}

EconomicProfileConcessionaireUltra.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
