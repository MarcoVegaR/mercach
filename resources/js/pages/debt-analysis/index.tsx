import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import {
    AlertOctagon,
    AlertTriangle,
    ArrowUpDown,
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    DollarSign,
    Download,
    FileJson,
    FileSpreadsheet,
    FileText,
    Info,
    LayoutDashboard,
    Search,
    ShieldAlert,
    Store,
    Users,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

type DebtItem = {
    id: number;
    full_name: string;
    document_number: string;
    market_name: string;
    debt_eur_minor: number;
    debt_usd_minor: number;
    debt_bs_minor: number;
    days_overdue_avg: number;
    locals_count: number;
    severity: string;
    charges_count?: number;
};

type DebtResponse = {
    data: DebtItem[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
    summary: {
        total_debt_eur_minor: number;
        total_debt_usd_minor: number;
        total_debt_bs_minor: number;
        total_count: number;
        avg_days_overdue: number;
    };
};

type LocalDebtItem = {
    id: number;
    local_code: string;
    local_name: string;
    concessionaire_name: string;
    market_name: string;
    debt_eur_minor: number;
    debt_usd_minor: number;
    debt_bs_minor: number;
    days_overdue_avg: number;
    charges_count: number;
    severity: string;
};

type LocalDebtResponse = {
    data: LocalDebtItem[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
    summary: {
        total_debt_eur_minor: number;
        total_debt_usd_minor: number;
        total_debt_bs_minor: number;
        total_count: number;
        avg_days_overdue: number;
    };
};

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Análisis de Deudas', href: '/dashboard/debt-analysis' },
];

const fmtBs = (minor: number) => `Bs. ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`;
const fmtEur = (minor: number) => `€ ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`;
const fmtUsd = (minor: number) => `$ ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`;
const monthOptions = [
    { value: '01', label: 'Enero' },
    { value: '02', label: 'Febrero' },
    { value: '03', label: 'Marzo' },
    { value: '04', label: 'Abril' },
    { value: '05', label: 'Mayo' },
    { value: '06', label: 'Junio' },
    { value: '07', label: 'Julio' },
    { value: '08', label: 'Agosto' },
    { value: '09', label: 'Septiembre' },
    { value: '10', label: 'Octubre' },
    { value: '11', label: 'Noviembre' },
    { value: '12', label: 'Diciembre' },
];
const yearOptions = Array.from({ length: 12 }, (_, index) => String(new Date().getFullYear() - index));

const severityConfig = {
    critical: {
        label: 'Más de 90 días',
        description: 'Mora crítica',
        icon: AlertOctagon,
        bg: 'bg-red-100 dark:bg-red-950/60',
        text: 'text-red-900 dark:text-red-100',
        border: 'border-l-red-700',
        ring: 'ring-red-700/25',
        iconColor: 'text-red-600 dark:text-red-400',
    },
    high: {
        label: '61 a 90 días',
        description: 'Mora alta',
        icon: ShieldAlert,
        bg: 'bg-orange-100 dark:bg-orange-950/60',
        text: 'text-orange-900 dark:text-orange-100',
        border: 'border-l-orange-600',
        ring: 'ring-orange-700/25',
        iconColor: 'text-orange-600 dark:text-orange-400',
    },
    medium: {
        label: '31 a 60 días',
        description: 'Mora media',
        icon: AlertTriangle,
        bg: 'bg-amber-100 dark:bg-amber-950/60',
        text: 'text-amber-900 dark:text-amber-100',
        border: 'border-l-amber-600',
        ring: 'ring-amber-700/25',
        iconColor: 'text-amber-600 dark:text-amber-400',
    },
    low: {
        label: '0 a 30 días',
        description: 'Mora inicial',
        icon: Info,
        bg: 'bg-sky-100 dark:bg-sky-950/60',
        text: 'text-sky-900 dark:text-sky-100',
        border: 'border-l-sky-600',
        ring: 'ring-sky-700/25',
        iconColor: 'text-sky-600 dark:text-sky-400',
    },
};

type TimePreset = 'all' | 'this_month' | 'last_3' | 'custom';
type DelinquencyPreset = 'all' | '31_plus' | '61_plus' | '91_plus';
type ExportFormat = 'csv' | 'xlsx' | 'json';

const buildPeriodValue = (year: string, month: string) => {
    if (!year && !month) {
        return '';
    }

    return `${year}-${month}`;
};

const splitPeriodValue = (value: string) => {
    if (!value || !value.includes('-')) {
        return { year: '', month: '' };
    }

    const [year, month] = value.split('-');
    return { year: year ?? '', month: month ?? '' };
};

const normalizePeriodRange = (from: string, to: string) => {
    if (from && to && from > to) {
        return { from: to, to: from };
    }

    return { from, to };
};

const sanitizePeriodValue = (value: string) => (/^\d{4}-(0[1-9]|1[0-2])$/.test(value) ? value : '');

const resolvePresetPeriodRange = (preset: TimePreset, customFrom: string, customTo: string) => {
    if (preset === 'this_month') {
        const now = new Date();
        const value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        return { from: value, to: value };
    }

    if (preset === 'last_3') {
        const now = new Date();
        const fromDate = new Date(now.getFullYear(), now.getMonth() - 2, 1);
        return {
            from: `${fromDate.getFullYear()}-${String(fromDate.getMonth() + 1).padStart(2, '0')}`,
            to: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`,
        };
    }

    if (preset === 'custom') {
        return normalizePeriodRange(sanitizePeriodValue(customFrom), sanitizePeriodValue(customTo));
    }

    return { from: '', to: '' };
};

const minDaysFromPreset = (preset: DelinquencyPreset) =>
    preset === '91_plus' ? '91' : preset === '61_plus' ? '61' : preset === '31_plus' ? '31' : '';

const buildDebtAnalysisExportParams = (queryFilters: Record<string, string>, scope: 'concessionaires' | 'locals', format: ExportFormat) => {
    const params = new URLSearchParams();
    Object.entries(queryFilters).forEach(([key, value]) => {
        if (value && key !== 'page' && key !== 'per_page') {
            params.append(key, String(value));
        }
    });
    params.append('scope', scope);
    params.append('format', format);

    return params;
};

/* ------------------------------------------------------------------ */
/*  Hooks                                                              */
/* ------------------------------------------------------------------ */

function useDebounce(value: string, delay: number) {
    const [debounced, setDebounced] = useState(value);
    useEffect(() => {
        const t = setTimeout(() => setDebounced(value), delay);
        return () => clearTimeout(t);
    }, [value, delay]);
    return debounced;
}

/* ------------------------------------------------------------------ */
/*  Reusable Components                                                */
/* ------------------------------------------------------------------ */

function SeverityIndicator({ severity }: { severity: string }) {
    const cfg = severityConfig[severity as keyof typeof severityConfig] ?? severityConfig.low;
    const Icon = cfg.icon;
    return (
        <span className={`inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-bold ring-1 ${cfg.bg} ${cfg.text} ${cfg.ring}`}>
            <Icon className={`h-4 w-4 ${cfg.iconColor}`} aria-hidden="true" />
            {cfg.label}
        </span>
    );
}

function InsightCard({
    value,
    phrase,
    details,
    highlight,
    icon: Icon,
}: {
    value: string | number;
    phrase: string;
    details?: string;
    highlight?: boolean;
    icon: React.ElementType;
}) {
    return (
        <div className={`rounded-xl border p-5 ${highlight ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/40' : 'bg-card'}`}>
            <div className="flex items-start gap-3">
                <div
                    className={`mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${highlight ? 'bg-red-200 dark:bg-red-900' : 'bg-muted'}`}
                >
                    <Icon className={`h-5 w-5 ${highlight ? 'text-red-700 dark:text-red-300' : 'text-muted-foreground'}`} />
                </div>
                <div>
                    <p className={`text-2xl font-bold tabular-nums ${highlight ? 'text-red-800 dark:text-red-200' : ''}`}>{value}</p>
                    <p
                        className={`mt-0.5 text-[15px] leading-snug ${highlight ? 'font-medium text-red-700 dark:text-red-300' : 'text-muted-foreground'}`}
                    >
                        {phrase}
                    </p>
                    {details && <p className="text-muted-foreground mt-1 text-xs">{details}</p>}
                </div>
            </div>
        </div>
    );
}

function LoadingRows({ cols }: { cols: number }) {
    return (
        <>
            {Array.from({ length: 8 }).map((_, i) => (
                <tr key={i} className="border-b">
                    {Array.from({ length: cols }).map((_, j) => (
                        <td key={j} className="px-5 py-5">
                            <Skeleton className="h-5 w-full" />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <Search className="text-muted-foreground/40 mb-4 h-12 w-12" />
            <p className="text-muted-foreground text-lg">{message}</p>
        </div>
    );
}

function PaginationControls({
    currentPage,
    lastPage,
    total,
    perPage,
    onPageChange,
    onPerPageChange,
}: {
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
    onPageChange: (page: number) => void;
    onPerPageChange?: (perPage: number) => void;
}) {
    if (lastPage <= 1) return null;
    const from = (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);

    const pages: (number | '...')[] = [];
    for (let i = 1; i <= lastPage; i++) {
        if (i === 1 || i === lastPage || (i >= currentPage - 1 && i <= currentPage + 1)) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== '...') {
            pages.push('...');
        }
    }

    return (
        <div className="mt-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                {onPerPageChange && (
                    <div className="flex items-center gap-2">
                        <span className="text-foreground text-sm font-medium">Resultados por página</span>
                        <Select value={String(perPage)} onValueChange={(value) => onPerPageChange(parseInt(value, 10))}>
                            <SelectTrigger className="h-9 w-[150px] text-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="25">25 por página</SelectItem>
                                <SelectItem value="50">50 por página</SelectItem>
                                <SelectItem value="100">100 por página</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                )}
                <p className="text-muted-foreground text-[15px]">
                    Mostrando <span className="text-foreground font-semibold">{from}</span> a{' '}
                    <span className="text-foreground font-semibold">{to}</span> de <span className="text-foreground font-semibold">{total}</span>{' '}
                    resultados
                </p>
            </div>
            <div className="flex flex-wrap items-center gap-1.5">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={currentPage <= 1}
                    onClick={() => onPageChange(currentPage - 1)}
                    className="gap-1 text-sm"
                >
                    <ChevronLeft className="h-4 w-4" /> Anterior
                </Button>
                {pages.map((p, i) =>
                    p === '...' ? (
                        <span key={`e${i}`} className="text-muted-foreground px-2 text-sm">
                            …
                        </span>
                    ) : (
                        <Button
                            key={p}
                            variant={p === currentPage ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => onPageChange(p)}
                            className="min-w-[36px] text-sm"
                        >
                            {p}
                        </Button>
                    ),
                )}
                <Button
                    variant="outline"
                    size="sm"
                    disabled={currentPage >= lastPage}
                    onClick={() => onPageChange(currentPage + 1)}
                    className="gap-1 text-sm"
                >
                    Siguiente <ChevronRight className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/*  Filter Controls                                                     */
/* ------------------------------------------------------------------ */

function formatPeriodLabel(period: string) {
    const { year, month } = splitPeriodValue(period);
    const monthLabel = monthOptions.find((item) => item.value === month)?.label;
    if (!year || !monthLabel) {
        return '—';
    }

    return `${monthLabel} ${year}`;
}

function PeriodFilters({
    active,
    onChange,
    customFrom,
    customTo,
    onCustomFromChange,
    onCustomToChange,
}: {
    active: TimePreset;
    onChange: (preset: TimePreset) => void;
    customFrom: string;
    customTo: string;
    onCustomFromChange: (v: string) => void;
    onCustomToChange: (v: string) => void;
}) {
    const presets: { id: TimePreset; label: string }[] = [
        { id: 'all', label: 'Todo' },
        { id: 'this_month', label: 'Este mes' },
        { id: 'last_3', label: 'Últimos 3 meses' },
        { id: 'custom', label: 'Personalizado' },
    ];
    const fromParts = splitPeriodValue(customFrom);
    const toParts = splitPeriodValue(customTo);

    const updateFrom = (next: Partial<{ year: string; month: string }>) => {
        onCustomFromChange(buildPeriodValue(next.year ?? fromParts.year, next.month ?? fromParts.month));
    };

    const updateTo = (next: Partial<{ year: string; month: string }>) => {
        onCustomToChange(buildPeriodValue(next.year ?? toParts.year, next.month ?? toParts.month));
    };

    return (
        <div className="space-y-4">
            <label className="text-foreground text-sm font-semibold">Período</label>
            <div className="flex flex-wrap gap-2">
                {presets.map((p) => (
                    <Button
                        key={p.id}
                        type="button"
                        variant={active === p.id ? 'default' : 'outline'}
                        onClick={() => onChange(p.id)}
                        className={`h-10 px-3.5 text-sm font-medium ${
                            active === p.id ? '' : 'border-border bg-background text-foreground hover:bg-muted'
                        }`}
                    >
                        {p.label}
                    </Button>
                ))}
            </div>
            {active === 'custom' && (
                <div className="bg-muted/20 rounded-xl border p-4">
                    <p className="text-muted-foreground text-sm">Seleccione mes y año desde y hasta. Este filtro trabaja por períodos mensuales.</p>
                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="bg-background space-y-3 rounded-lg border p-3">
                            <label className="text-foreground text-sm font-semibold">Mes / año desde</label>
                            <div className="grid grid-cols-[1.3fr_1fr] gap-2">
                                <Select value={fromParts.month} onValueChange={(value) => updateFrom({ month: value })}>
                                    <SelectTrigger className="h-10 text-sm">
                                        <SelectValue placeholder="Mes" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {monthOptions.map((month) => (
                                            <SelectItem key={month.value} value={month.value}>
                                                {month.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={fromParts.year} onValueChange={(value) => updateFrom({ year: value })}>
                                    <SelectTrigger className="h-10 text-sm">
                                        <SelectValue placeholder="Año" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {yearOptions.map((year) => (
                                            <SelectItem key={year} value={year}>
                                                {year}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="bg-background space-y-3 rounded-lg border p-3">
                            <label className="text-foreground text-sm font-semibold">Mes / año hasta</label>
                            <div className="grid grid-cols-[1.3fr_1fr] gap-2">
                                <Select value={toParts.month} onValueChange={(value) => updateTo({ month: value })}>
                                    <SelectTrigger className="h-10 text-sm">
                                        <SelectValue placeholder="Mes" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {monthOptions.map((month) => (
                                            <SelectItem key={month.value} value={month.value}>
                                                {month.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={toParts.year} onValueChange={(value) => updateTo({ year: value })}>
                                    <SelectTrigger className="h-10 text-sm">
                                        <SelectValue placeholder="Año" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {yearOptions.map((year) => (
                                            <SelectItem key={year} value={year}>
                                                {year}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function DelinquencyRangeFilters({ active, onChange }: { active: DelinquencyPreset; onChange: (preset: DelinquencyPreset) => void }) {
    const presets: { id: DelinquencyPreset; label: string }[] = [
        { id: 'all', label: 'Cualquier mora' },
        { id: '31_plus', label: '31 días o más' },
        { id: '61_plus', label: '61 días o más' },
        { id: '91_plus', label: '91 días o más' },
    ];

    return (
        <div className="space-y-3">
            <label className="text-foreground text-sm font-semibold">Rango de mora</label>
            <div className="flex flex-wrap gap-2">
                {presets.map((preset) => (
                    <Button
                        key={preset.id}
                        type="button"
                        variant={active === preset.id ? 'default' : 'outline'}
                        className="h-10 px-3.5 text-sm"
                        onClick={() => onChange(preset.id)}
                    >
                        {preset.label}
                    </Button>
                ))}
            </div>
        </div>
    );
}

function SortHeader({
    label,
    active,
    direction,
    onClick,
    align = 'left',
}: {
    label: string;
    active: boolean;
    direction: 'asc' | 'desc';
    onClick: () => void;
    align?: 'left' | 'right' | 'center';
}) {
    const alignment = align === 'right' ? 'justify-end' : align === 'center' ? 'justify-center' : 'justify-start';
    return (
        <button type="button" onClick={onClick} className={`flex w-full items-center gap-1 text-sm font-semibold ${alignment}`}>
            {label}
            <ArrowUpDown
                className={`h-3.5 w-3.5 ${active ? 'text-foreground' : 'text-muted-foreground'} ${active && direction === 'asc' ? 'rotate-180' : ''}`}
            />
        </button>
    );
}

/* ------------------------------------------------------------------ */
/*  Expandable Row Detail                                              */
/* ------------------------------------------------------------------ */

function RowDetail({ eur, usd, bs, charges, children }: { eur: number; usd: number; bs: number; charges?: number; children?: React.ReactNode }) {
    return (
        <div className="space-y-3 py-2">
            <div className="grid grid-cols-2 gap-x-8 gap-y-2 sm:grid-cols-4">
                <div>
                    <span className="text-muted-foreground text-xs font-medium uppercase">Bolívares</span>
                    <p className="text-sm font-semibold tabular-nums">{fmtBs(bs)}</p>
                </div>
                <div>
                    <span className="text-muted-foreground text-xs font-medium uppercase">Euros</span>
                    <p className="text-sm font-semibold tabular-nums">{eur > 0 ? fmtEur(eur) : '—'}</p>
                </div>
                <div>
                    <span className="text-muted-foreground text-xs font-medium uppercase">Dólares</span>
                    <p className="text-sm font-semibold tabular-nums">{usd > 0 ? fmtUsd(usd) : '—'}</p>
                </div>
                {charges !== undefined && (
                    <div>
                        <span className="text-muted-foreground text-xs font-medium uppercase">Cargos pendientes</span>
                        <p className="text-sm font-semibold">{charges}</p>
                    </div>
                )}
            </div>
            {children}
        </div>
    );
}

function AmountBreakdown({ bs, align = 'right' }: { bs: number; align?: 'left' | 'right' }) {
    return (
        <div className={align === 'right' ? 'text-right' : 'text-left'}>
            <p className="text-[15px] font-bold tabular-nums">{fmtBs(bs)}</p>
            <p className="text-muted-foreground mt-1 text-[11px] font-medium">Ver desglose al abrir</p>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/*  ConcessionairesView                                                */
/* ------------------------------------------------------------------ */

function ConcessionairesView() {
    const [searchInput, setSearchInput] = useState('');
    const debouncedSearch = useDebounce(searchInput, 400);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [filters, setFilters] = useState({
        page: 1,
        per_page: 25,
        sort_by: 'debt_bs',
        sort_dir: 'desc' as 'asc' | 'desc',
        min_debt_eur: '',
        min_days: '',
        period_preset: 'all' as TimePreset,
        period_from: '',
        period_to: '',
        delinquency_preset: 'all' as DelinquencyPreset,
    });
    const [draftFilters, setDraftFilters] = useState({
        per_page: 25,
        min_debt_eur: '',
        period_preset: 'all' as TimePreset,
        period_from: '',
        period_to: '',
        delinquency_preset: 'all' as DelinquencyPreset,
    });

    const prevSearchRef = useRef(debouncedSearch);
    useEffect(() => {
        if (prevSearchRef.current !== debouncedSearch) {
            prevSearchRef.current = debouncedSearch;
            setFilters((prev) => ({ ...prev, page: 1 }));
        }
    }, [debouncedSearch]);

    const queryFilters = useMemo(() => {
        const f: Record<string, string> = { ...filters, search: debouncedSearch } as unknown as Record<string, string>;
        if (!filters.period_from) delete f.period_from;
        if (!filters.period_to) delete f.period_to;
        if (!filters.min_days) delete f.min_days;
        delete f.period_preset;
        delete f.delinquency_preset;
        return f;
    }, [filters, debouncedSearch]);

    const { data, isLoading } = useQuery<DebtResponse>({
        queryKey: ['debt-analysis', 'concessionaires', queryFilters],
        queryFn: async () => {
            const params = new URLSearchParams();
            Object.entries(queryFilters).forEach(([k, v]) => {
                if (v) params.append(k, String(v));
            });
            const res = await fetch(`/api/debt-analysis/delinquent-concessionaires?${params}`);
            if (!res.ok) throw new Error('Failed to fetch');
            return res.json();
        },
    });

    const handleFilterChange = useCallback((key: string, value: string | number) => {
        setFilters((prev) => ({ ...prev, [key]: value, page: key === 'page' ? (value as number) : 1 }));
    }, []);

    const handlePerPageChange = useCallback((perPage: number) => {
        setFilters((prev) => ({ ...prev, per_page: perPage, page: 1 }));
        setDraftFilters((prev) => ({ ...prev, per_page: perPage }));
    }, []);

    const handleExport = useCallback(
        (format: ExportFormat = 'csv') => {
            const params = buildDebtAnalysisExportParams(queryFilters, 'concessionaires', format);
            window.open(`/api/debt-analysis/export?${params.toString()}`, '_blank');
        },
        [queryFilters],
    );

    const applyDrawerFilters = useCallback(() => {
        const normalizedPeriod = resolvePresetPeriodRange(draftFilters.period_preset, draftFilters.period_from, draftFilters.period_to);
        setFilters((prev) => ({
            ...prev,
            page: 1,
            min_debt_eur: draftFilters.min_debt_eur,
            min_days: minDaysFromPreset(draftFilters.delinquency_preset),
            period_preset: draftFilters.period_preset,
            period_from: normalizedPeriod.from,
            period_to: normalizedPeriod.to,
            delinquency_preset: draftFilters.delinquency_preset,
        }));
        setDraftFilters((prev) => ({
            ...prev,
            period_from: normalizedPeriod.from,
            period_to: normalizedPeriod.to,
        }));
    }, [draftFilters]);

    const resetFilters = useCallback(() => {
        setSearchInput('');
        setFilters({
            page: 1,
            per_page: 25,
            sort_by: 'debt_bs',
            sort_dir: 'desc',
            min_debt_eur: '',
            min_days: '',
            period_preset: 'all',
            period_from: '',
            period_to: '',
            delinquency_preset: 'all',
        });
        setDraftFilters({
            per_page: 25,
            min_debt_eur: '',
            period_preset: 'all',
            period_from: '',
            period_to: '',
            delinquency_preset: 'all',
        });
    }, []);

    const criticalCount = data ? data.data.filter((d) => d.days_overdue_avg > 90).length : 0;
    const activeFiltersCount = [filters.period_preset !== 'all', filters.delinquency_preset !== 'all', Boolean(filters.min_debt_eur)].filter(
        Boolean,
    ).length;
    const badges: Array<{ key: string; label: string; onRemove: () => void }> = [];

    if (filters.period_preset !== 'all') {
        const label =
            filters.period_preset === 'this_month'
                ? 'Período: Este mes'
                : filters.period_preset === 'last_3'
                  ? 'Período: Últimos 3 meses'
                  : `Período: ${formatPeriodLabel(filters.period_from)} a ${formatPeriodLabel(filters.period_to)}`;
        badges.push({
            key: 'period',
            label,
            onRemove: () => {
                setFilters((prev) => ({ ...prev, page: 1, period_preset: 'all', period_from: '', period_to: '' }));
                setDraftFilters((prev) => ({ ...prev, period_preset: 'all', period_from: '', period_to: '' }));
            },
        });
    }

    if (filters.delinquency_preset !== 'all') {
        const label =
            filters.delinquency_preset === '91_plus'
                ? 'Rango de mora: 91 días o más'
                : filters.delinquency_preset === '61_plus'
                  ? 'Rango de mora: 61 días o más'
                  : 'Rango de mora: 31 días o más';
        badges.push({
            key: 'delinquency',
            label,
            onRemove: () => {
                setFilters((prev) => ({ ...prev, page: 1, delinquency_preset: 'all', min_days: '' }));
                setDraftFilters((prev) => ({ ...prev, delinquency_preset: 'all' }));
            },
        });
    }

    if (filters.min_debt_eur) {
        badges.push({
            key: 'min_debt_eur',
            label: `Deuda mínima: € ${filters.min_debt_eur}`,
            onRemove: () => {
                setFilters((prev) => ({ ...prev, min_debt_eur: '', page: 1 }));
                setDraftFilters((prev) => ({ ...prev, min_debt_eur: '' }));
            },
        });
    }

    const toggleSort = useCallback((sortBy: 'debt_bs' | 'days_overdue' | 'name') => {
        setFilters((prev) => ({
            ...prev,
            sort_by: sortBy,
            sort_dir: prev.sort_by === sortBy && prev.sort_dir === 'desc' ? 'asc' : 'desc',
            page: 1,
        }));
    }, []);

    return (
        <div className="space-y-6">
            {data && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <InsightCard icon={Users} value={data.summary.total_count} phrase="personas tienen pagos vencidos" />
                    <InsightCard
                        icon={AlertOctagon}
                        value={criticalCount}
                        phrase="casos tienen más de 90 días de mora"
                        highlight={criticalCount > 0}
                    />
                    <InsightCard
                        icon={DollarSign}
                        value={fmtBs(data.summary.total_debt_bs_minor)}
                        phrase="total adeudado en bolívares"
                        details={`${fmtEur(data.summary.total_debt_eur_minor)} • ${fmtUsd(data.summary.total_debt_usd_minor)}`}
                    />
                    <InsightCard
                        icon={Clock}
                        value={`${data.summary.avg_days_overdue} días`}
                        phrase="de mora en promedio"
                        details="Mientras más alto sea este número, mayor es la urgencia."
                        highlight={data.summary.avg_days_overdue > 90}
                    />
                </div>
            )}

            <Card>
                <CardContent className="p-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end">
                        <div className="flex-1 space-y-1.5">
                            <label className="text-foreground text-sm font-semibold">Buscar persona, documento o local</label>
                            <div className="relative">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2" />
                                <Input
                                    placeholder="Escriba un nombre, cédula o código de local…"
                                    value={searchInput}
                                    onChange={(e) => setSearchInput(e.target.value)}
                                    className="h-11 pl-11 text-[15px]"
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <FilterSheet
                                activeFiltersCount={activeFiltersCount}
                                onApplyFilters={applyDrawerFilters}
                                onClearFilters={resetFilters}
                                title="Filtros de deuda"
                                description="Aplique filtros sin perder el foco del análisis"
                            >
                                <div className="space-y-5">
                                    <PeriodFilters
                                        active={draftFilters.period_preset}
                                        onChange={(value) => setDraftFilters((prev) => ({ ...prev, period_preset: value }))}
                                        customFrom={draftFilters.period_from}
                                        customTo={draftFilters.period_to}
                                        onCustomFromChange={(value) => setDraftFilters((prev) => ({ ...prev, period_from: value }))}
                                        onCustomToChange={(value) => setDraftFilters((prev) => ({ ...prev, period_to: value }))}
                                    />
                                    <DelinquencyRangeFilters
                                        active={draftFilters.delinquency_preset}
                                        onChange={(value) => setDraftFilters((prev) => ({ ...prev, delinquency_preset: value }))}
                                    />
                                    <div className="space-y-2">
                                        <label className="text-foreground text-sm font-semibold">Deuda mínima en euros</label>
                                        <Input
                                            type="number"
                                            placeholder="0"
                                            value={draftFilters.min_debt_eur}
                                            onChange={(e) => setDraftFilters((prev) => ({ ...prev, min_debt_eur: e.target.value }))}
                                            className="h-10 text-sm"
                                        />
                                    </div>
                                </div>
                            </FilterSheet>
                            <DropdownMenu>
                                <Button variant="outline" size="sm" className="flex items-center gap-2" asChild>
                                    <DropdownMenuTrigger>
                                        <Download className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                        Exportar
                                    </DropdownMenuTrigger>
                                </Button>
                                <DropdownMenuContent align="end" className="w-48">
                                    <DropdownMenuItem onClick={() => handleExport('csv')} className="flex cursor-pointer items-center gap-2">
                                        <FileText className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                        Exportar como CSV
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleExport('xlsx')} className="flex cursor-pointer items-center gap-2">
                                        <FileSpreadsheet className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                        Exportar como Excel
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleExport('json')} className="flex cursor-pointer items-center gap-2">
                                        <FileJson className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                        Exportar como JSON
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <FilterBadges badges={badges} />
                        {badges.length > 0 && (
                            <Button variant="ghost" size="sm" onClick={resetFilters} className="text-muted-foreground text-sm">
                                Limpiar filtros
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-0">
                    {!isLoading && data && data.data.length === 0 ? (
                        <EmptyState message="No se encontraron personas con estos filtros" />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-muted/60 sticky top-0 z-10">
                                    <tr className="border-b">
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Rango de mora</th>
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">
                                            <SortHeader
                                                label="Persona"
                                                active={filters.sort_by === 'name'}
                                                direction={filters.sort_dir}
                                                onClick={() => toggleSort('name')}
                                            />
                                        </th>
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Documento</th>
                                        <th className="px-5 py-3.5 text-right text-sm font-semibold">
                                            <SortHeader
                                                label="Monto adeudado"
                                                active={filters.sort_by === 'debt_bs'}
                                                direction={filters.sort_dir}
                                                onClick={() => toggleSort('debt_bs')}
                                                align="right"
                                            />
                                        </th>
                                        <th className="px-5 py-3.5 text-center text-sm font-semibold">
                                            <SortHeader
                                                label="Días vencidos"
                                                active={filters.sort_by === 'days_overdue'}
                                                direction={filters.sort_dir}
                                                onClick={() => toggleSort('days_overdue')}
                                                align="center"
                                            />
                                        </th>
                                        <th className="px-5 py-3.5 text-right text-sm font-semibold">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {isLoading && <LoadingRows cols={6} />}
                                    {!isLoading &&
                                        data &&
                                        data.data.map((item) => {
                                            const cfg = severityConfig[item.severity as keyof typeof severityConfig] ?? severityConfig.low;
                                            const isExpanded = expandedId === item.id;
                                            return (
                                                <Collapsible
                                                    key={item.id}
                                                    asChild
                                                    open={isExpanded}
                                                    onOpenChange={() => setExpandedId(isExpanded ? null : item.id)}
                                                >
                                                    <>
                                                        <CollapsibleTrigger asChild>
                                                            <tr
                                                                className={`border-b border-l-4 ${cfg.border} hover:bg-muted/40 cursor-pointer transition-colors ${isExpanded ? 'bg-muted/30' : ''}`}
                                                                role="button"
                                                                tabIndex={0}
                                                            >
                                                                <td className="px-5 py-4">
                                                                    <SeverityIndicator severity={item.severity} />
                                                                </td>
                                                                <td className="px-5 py-4 text-[15px] font-medium">{item.full_name}</td>
                                                                <td className="px-5 py-4 font-mono text-sm">{item.document_number}</td>
                                                                <td className="px-5 py-4">
                                                                    <AmountBreakdown bs={item.debt_bs_minor} />
                                                                </td>
                                                                <td className="px-5 py-4 text-center">
                                                                    <span className="text-[15px] font-semibold">{item.days_overdue_avg}</span>
                                                                    <span className="text-muted-foreground text-sm"> días</span>
                                                                </td>
                                                                <td className="px-5 py-4 text-right">
                                                                    {item.id > 0 ? (
                                                                        <Button
                                                                            size="sm"
                                                                            variant="ghost"
                                                                            className="h-9 px-3 text-sm font-medium"
                                                                            onClick={(e) => {
                                                                                e.stopPropagation();
                                                                                router.visit(`/admin/economic-profile/concessionaires/${item.id}`);
                                                                            }}
                                                                        >
                                                                            Ver detalle
                                                                        </Button>
                                                                    ) : (
                                                                        <span className="text-muted-foreground text-sm">No atribuible</span>
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        </CollapsibleTrigger>
                                                        <CollapsibleContent asChild>
                                                            <tr className={`border-b border-l-4 ${cfg.border} bg-muted/20`}>
                                                                <td colSpan={6} className="px-5 py-3">
                                                                    <RowDetail
                                                                        eur={item.debt_eur_minor}
                                                                        usd={item.debt_usd_minor}
                                                                        bs={item.debt_bs_minor}
                                                                        charges={item.charges_count}
                                                                    >
                                                                        <div className="text-muted-foreground flex flex-wrap items-center gap-6 text-sm">
                                                                            <span>Mercado: {item.market_name}</span>
                                                                            <span>Locales afectados: {item.locals_count}</span>
                                                                        </div>
                                                                    </RowDetail>
                                                                </td>
                                                            </tr>
                                                        </CollapsibleContent>
                                                    </>
                                                </Collapsible>
                                            );
                                        })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
                {data && (
                    <div className="border-t px-5 py-4">
                        <PaginationControls
                            currentPage={data.meta.current_page}
                            lastPage={data.meta.last_page}
                            total={data.meta.total}
                            perPage={data.meta.per_page}
                            onPageChange={(p) => handleFilterChange('page', p)}
                            onPerPageChange={handlePerPageChange}
                        />
                    </div>
                )}
            </Card>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/*  LocalsDebtView                                                     */
/* ------------------------------------------------------------------ */

function LocalsDebtView() {
    const [searchInput, setSearchInput] = useState('');
    const debouncedSearch = useDebounce(searchInput, 400);
    const [expandedId, setExpandedId] = useState<string | null>(null);
    const [filters, setFilters] = useState({
        page: 1,
        per_page: 25,
        sort_by: 'debt_bs',
        sort_dir: 'desc' as 'asc' | 'desc',
        local_code_from: '',
        local_code_to: '',
        min_days: '',
        period_preset: 'all' as TimePreset,
        period_from: '',
        period_to: '',
        delinquency_preset: 'all' as DelinquencyPreset,
    });
    const [draftFilters, setDraftFilters] = useState({
        per_page: 25,
        local_code_from: '',
        local_code_to: '',
        period_preset: 'all' as TimePreset,
        period_from: '',
        period_to: '',
        delinquency_preset: 'all' as DelinquencyPreset,
    });

    const prevSearchRef = useRef(debouncedSearch);
    useEffect(() => {
        if (prevSearchRef.current !== debouncedSearch) {
            prevSearchRef.current = debouncedSearch;
            setFilters((prev) => ({ ...prev, page: 1 }));
        }
    }, [debouncedSearch]);

    const queryFilters = useMemo(() => {
        const f: Record<string, string> = { ...filters, search: debouncedSearch } as unknown as Record<string, string>;
        if (!filters.period_from) delete f.period_from;
        if (!filters.period_to) delete f.period_to;
        if (!filters.min_days) delete f.min_days;
        delete f.period_preset;
        delete f.delinquency_preset;
        return f;
    }, [filters, debouncedSearch]);

    const { data, isLoading } = useQuery<LocalDebtResponse>({
        queryKey: ['debt-analysis', 'locals', queryFilters],
        queryFn: async () => {
            const params = new URLSearchParams();
            Object.entries(queryFilters).forEach(([k, v]) => {
                if (v) params.append(k, String(v));
            });
            const res = await fetch(`/api/debt-analysis/delinquent-locals?${params}`);
            if (!res.ok) throw new Error('Failed to fetch');
            return res.json();
        },
    });

    const handleFilterChange = useCallback((key: string, value: string | number) => {
        setFilters((prev) => ({ ...prev, [key]: value, page: key === 'page' ? (value as number) : 1 }));
    }, []);

    const handlePerPageChange = useCallback((perPage: number) => {
        setFilters((prev) => ({ ...prev, per_page: perPage, page: 1 }));
        setDraftFilters((prev) => ({ ...prev, per_page: perPage }));
    }, []);

    const handleExport = useCallback(
        (format: ExportFormat = 'csv') => {
            const params = buildDebtAnalysisExportParams(queryFilters, 'locals', format);
            window.open(`/api/debt-analysis/export?${params.toString()}`, '_blank');
        },
        [queryFilters],
    );

    const applyDrawerFilters = useCallback(() => {
        const normalizedPeriod = resolvePresetPeriodRange(draftFilters.period_preset, draftFilters.period_from, draftFilters.period_to);
        setFilters((prev) => ({
            ...prev,
            page: 1,
            local_code_from: draftFilters.local_code_from,
            local_code_to: draftFilters.local_code_to,
            min_days: minDaysFromPreset(draftFilters.delinquency_preset),
            period_preset: draftFilters.period_preset,
            period_from: normalizedPeriod.from,
            period_to: normalizedPeriod.to,
            delinquency_preset: draftFilters.delinquency_preset,
        }));
        setDraftFilters((prev) => ({
            ...prev,
            period_from: normalizedPeriod.from,
            period_to: normalizedPeriod.to,
        }));
    }, [draftFilters]);

    const resetFilters = useCallback(() => {
        setSearchInput('');
        setFilters({
            page: 1,
            per_page: 25,
            sort_by: 'debt_bs',
            sort_dir: 'desc',
            local_code_from: '',
            local_code_to: '',
            min_days: '',
            period_preset: 'all',
            period_from: '',
            period_to: '',
            delinquency_preset: 'all',
        });
        setDraftFilters({
            per_page: 25,
            local_code_from: '',
            local_code_to: '',
            period_preset: 'all',
            period_from: '',
            period_to: '',
            delinquency_preset: 'all',
        });
    }, []);

    const criticalCount = data ? data.data.filter((d) => d.days_overdue_avg > 90).length : 0;
    const activeFiltersCount = [
        filters.period_preset !== 'all',
        filters.delinquency_preset !== 'all',
        Boolean(filters.local_code_from),
        Boolean(filters.local_code_to),
    ].filter(Boolean).length;
    const badges: Array<{ key: string; label: string; onRemove: () => void }> = [];
    if (filters.period_preset !== 'all') {
        badges.push({
            key: 'period',
            label:
                filters.period_preset === 'this_month'
                    ? 'Período: Este mes'
                    : filters.period_preset === 'last_3'
                      ? 'Período: Últimos 3 meses'
                      : `Período: ${formatPeriodLabel(filters.period_from)} a ${formatPeriodLabel(filters.period_to)}`,
            onRemove: () => {
                setFilters((prev) => ({ ...prev, page: 1, period_preset: 'all', period_from: '', period_to: '' }));
                setDraftFilters((prev) => ({ ...prev, period_preset: 'all', period_from: '', period_to: '' }));
            },
        });
    }
    if (filters.delinquency_preset !== 'all') {
        badges.push({
            key: 'delinquency',
            label:
                filters.delinquency_preset === '91_plus'
                    ? 'Rango de mora: 91 días o más'
                    : filters.delinquency_preset === '61_plus'
                      ? 'Rango de mora: 61 días o más'
                      : 'Rango de mora: 31 días o más',
            onRemove: () => {
                setFilters((prev) => ({ ...prev, page: 1, delinquency_preset: 'all', min_days: '' }));
                setDraftFilters((prev) => ({ ...prev, delinquency_preset: 'all' }));
            },
        });
    }
    if (filters.local_code_from) {
        badges.push({
            key: 'local_code_from',
            label: `Local desde: ${filters.local_code_from}`,
            onRemove: () => {
                setFilters((prev) => ({ ...prev, local_code_from: '', page: 1 }));
                setDraftFilters((prev) => ({ ...prev, local_code_from: '' }));
            },
        });
    }
    if (filters.local_code_to) {
        badges.push({
            key: 'local_code_to',
            label: `Local hasta: ${filters.local_code_to}`,
            onRemove: () => {
                setFilters((prev) => ({ ...prev, local_code_to: '', page: 1 }));
                setDraftFilters((prev) => ({ ...prev, local_code_to: '' }));
            },
        });
    }
    const toggleSort = useCallback((sortBy: 'debt_bs' | 'days_overdue' | 'code') => {
        setFilters((prev) => ({
            ...prev,
            sort_by: sortBy,
            sort_dir: prev.sort_by === sortBy && prev.sort_dir === 'desc' ? 'asc' : 'desc',
            page: 1,
        }));
    }, []);

    return (
        <div className="space-y-6">
            {data && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <InsightCard icon={Store} value={data.summary.total_count} phrase="locales tienen pagos vencidos" />
                    <InsightCard
                        icon={AlertOctagon}
                        value={criticalCount}
                        phrase="locales tienen más de 90 días de mora"
                        highlight={criticalCount > 0}
                    />
                    <InsightCard
                        icon={DollarSign}
                        value={fmtBs(data.summary.total_debt_bs_minor)}
                        phrase="total adeudado en bolívares"
                        details={`${fmtEur(data.summary.total_debt_eur_minor)} • ${fmtUsd(data.summary.total_debt_usd_minor)}`}
                    />
                    <InsightCard
                        icon={Clock}
                        value={`${data.summary.avg_days_overdue} días`}
                        phrase="de mora en promedio"
                        highlight={data.summary.avg_days_overdue > 90}
                    />
                </div>
            )}

            <Card>
                <CardContent className="p-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end">
                        <div className="flex-1 space-y-1.5">
                            <label className="text-foreground text-sm font-semibold">Buscar local, código o cesionario</label>
                            <div className="relative">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2" />
                                <Input
                                    placeholder="Escriba un código, nombre o cesionario…"
                                    value={searchInput}
                                    onChange={(e) => setSearchInput(e.target.value)}
                                    className="h-11 pl-11 text-[15px]"
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <FilterSheet
                                activeFiltersCount={activeFiltersCount}
                                onApplyFilters={applyDrawerFilters}
                                onClearFilters={resetFilters}
                                title="Filtros de locales con deuda"
                                description="Use filtros laterales para afinar la búsqueda"
                            >
                                <div className="space-y-5">
                                    <PeriodFilters
                                        active={draftFilters.period_preset}
                                        onChange={(value) => setDraftFilters((prev) => ({ ...prev, period_preset: value }))}
                                        customFrom={draftFilters.period_from}
                                        customTo={draftFilters.period_to}
                                        onCustomFromChange={(value) => setDraftFilters((prev) => ({ ...prev, period_from: value }))}
                                        onCustomToChange={(value) => setDraftFilters((prev) => ({ ...prev, period_to: value }))}
                                    />
                                    <DelinquencyRangeFilters
                                        active={draftFilters.delinquency_preset}
                                        onChange={(value) => setDraftFilters((prev) => ({ ...prev, delinquency_preset: value }))}
                                    />
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <label className="text-foreground text-sm font-semibold">Local desde</label>
                                            <Input
                                                placeholder="Ej. A-01"
                                                value={draftFilters.local_code_from}
                                                onChange={(e) =>
                                                    setDraftFilters((prev) => ({ ...prev, local_code_from: e.target.value.toUpperCase() }))
                                                }
                                                className="h-10 text-sm"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <label className="text-foreground text-sm font-semibold">Local hasta</label>
                                            <Input
                                                placeholder="Ej. A-20"
                                                value={draftFilters.local_code_to}
                                                onChange={(e) =>
                                                    setDraftFilters((prev) => ({ ...prev, local_code_to: e.target.value.toUpperCase() }))
                                                }
                                                className="h-10 text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </FilterSheet>
                            <DropdownMenu>
                                <Button variant="outline" size="sm" className="gap-1.5 text-sm" asChild>
                                    <DropdownMenuTrigger>
                                        <Download className="h-4 w-4" />
                                        Exportar
                                    </DropdownMenuTrigger>
                                </Button>
                                <DropdownMenuContent align="end" className="w-48">
                                    <DropdownMenuItem onClick={() => handleExport('csv')} className="flex cursor-pointer items-center gap-2">
                                        <FileText className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                        Exportar como CSV
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleExport('xlsx')} className="flex cursor-pointer items-center gap-2">
                                        <FileSpreadsheet className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                        Exportar como Excel
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleExport('json')} className="flex cursor-pointer items-center gap-2">
                                        <FileJson className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                        Exportar como JSON
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <FilterBadges badges={badges} />
                        {badges.length > 0 && (
                            <Button variant="ghost" size="sm" onClick={resetFilters} className="text-muted-foreground text-sm">
                                Limpiar filtros
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-0">
                    {!isLoading && data && data.data.length === 0 ? (
                        <EmptyState message="No se encontraron locales con estos filtros" />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-muted/60 sticky top-0 z-10">
                                    <tr className="border-b">
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Rango de mora</th>
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">
                                            <SortHeader
                                                label="Local"
                                                active={filters.sort_by === 'code'}
                                                direction={filters.sort_dir}
                                                onClick={() => toggleSort('code')}
                                            />
                                        </th>
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Cesionario</th>
                                        <th className="px-5 py-3.5 text-right text-sm font-semibold">
                                            <SortHeader
                                                label="Monto adeudado"
                                                active={filters.sort_by === 'debt_bs'}
                                                direction={filters.sort_dir}
                                                onClick={() => toggleSort('debt_bs')}
                                                align="right"
                                            />
                                        </th>
                                        <th className="px-5 py-3.5 text-center text-sm font-semibold">
                                            <SortHeader
                                                label="Días vencidos"
                                                active={filters.sort_by === 'days_overdue'}
                                                direction={filters.sort_dir}
                                                onClick={() => toggleSort('days_overdue')}
                                                align="center"
                                            />
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {isLoading && <LoadingRows cols={5} />}
                                    {!isLoading &&
                                        data &&
                                        data.data.map((local) => {
                                            const cfg = severityConfig[local.severity as keyof typeof severityConfig] ?? severityConfig.low;
                                            const rowId = `${local.id}-${local.concessionaire_name}`;
                                            const isExpanded = expandedId === rowId;
                                            return (
                                                <Collapsible
                                                    key={rowId}
                                                    asChild
                                                    open={isExpanded}
                                                    onOpenChange={() => setExpandedId(isExpanded ? null : rowId)}
                                                >
                                                    <>
                                                        <CollapsibleTrigger asChild>
                                                            <tr
                                                                className={`border-b border-l-4 ${cfg.border} hover:bg-muted/40 cursor-pointer transition-colors ${isExpanded ? 'bg-muted/30' : ''}`}
                                                                role="button"
                                                                tabIndex={0}
                                                            >
                                                                <td className="px-5 py-4">
                                                                    <SeverityIndicator severity={local.severity} />
                                                                </td>
                                                                <td className="px-5 py-4">
                                                                    <span className="font-mono text-sm font-bold">{local.local_code}</span>
                                                                    <span className="text-muted-foreground ml-2 text-sm">{local.local_name}</span>
                                                                </td>
                                                                <td className="px-5 py-4 text-[15px]">{local.concessionaire_name}</td>
                                                                <td className="px-5 py-4">
                                                                    <AmountBreakdown bs={local.debt_bs_minor} />
                                                                </td>
                                                                <td className="px-5 py-4 text-center">
                                                                    <span className="text-[15px] font-semibold">{local.days_overdue_avg}</span>
                                                                    <span className="text-muted-foreground text-sm"> días</span>
                                                                </td>
                                                            </tr>
                                                        </CollapsibleTrigger>
                                                        <CollapsibleContent asChild>
                                                            <tr className={`border-b border-l-4 ${cfg.border} bg-muted/20`}>
                                                                <td colSpan={5} className="px-5 py-3">
                                                                    <RowDetail
                                                                        eur={local.debt_eur_minor}
                                                                        usd={local.debt_usd_minor}
                                                                        bs={local.debt_bs_minor}
                                                                        charges={local.charges_count}
                                                                    >
                                                                        <div className="text-muted-foreground flex flex-wrap items-center gap-6 text-sm">
                                                                            <span>Mercado: {local.market_name}</span>
                                                                        </div>
                                                                    </RowDetail>
                                                                </td>
                                                            </tr>
                                                        </CollapsibleContent>
                                                    </>
                                                </Collapsible>
                                            );
                                        })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
                {data && (
                    <div className="border-t px-5 py-4">
                        <PaginationControls
                            currentPage={data.meta.current_page}
                            lastPage={data.meta.last_page}
                            total={data.meta.total}
                            perPage={data.meta.per_page}
                            onPageChange={(p) => handleFilterChange('page', p)}
                            onPerPageChange={handlePerPageChange}
                        />
                    </div>
                )}
            </Card>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/*  SolventView                                                        */
/* ------------------------------------------------------------------ */

function SolventView() {
    const { data, isLoading } = useQuery({
        queryKey: ['debt-analysis', 'solvent'],
        queryFn: async () => {
            const res = await fetch('/api/debt-analysis/solvent-concessionaires?page=1&per_page=50');
            if (!res.ok) throw new Error('Failed to fetch');
            return res.json();
        },
    });

    return (
        <div className="space-y-6">
            {!isLoading && data && (
                <div className="rounded-xl border-2 border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-950/30">
                    <div className="flex items-start gap-4">
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-200 dark:bg-green-900">
                            <CheckCircle2 className="h-6 w-6 text-green-700 dark:text-green-300" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-green-800 dark:text-green-200">{data.meta.total} personas están al día</p>
                            <p className="mt-1 text-[15px] text-green-700 dark:text-green-300">
                                Estos cesionarios no tienen ningún pago vencido. No requieren acción.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-muted/60">
                                    <tr className="border-b">
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Persona</th>
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Documento</th>
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Mercado</th>
                                        <th className="px-5 py-3.5 text-center text-sm font-semibold">Pagos realizados</th>
                                        <th className="px-5 py-3.5 text-center text-sm font-semibold">Último pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <LoadingRows cols={5} />
                                </tbody>
                            </table>
                        </div>
                    ) : data && data.data.length === 0 ? (
                        <EmptyState message="No hay cesionarios al día en este momento" />
                    ) : data ? (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-muted/60 sticky top-0 z-10">
                                    <tr className="border-b">
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Persona</th>
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Documento</th>
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Mercado</th>
                                        <th className="px-5 py-3.5 text-center text-sm font-semibold">Pagos realizados</th>
                                        <th className="px-5 py-3.5 text-center text-sm font-semibold">Último pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.data.map(
                                        (item: {
                                            id: number;
                                            full_name: string;
                                            document_number: string;
                                            market_name: string;
                                            total_payments: number;
                                            last_payment_date: string | null;
                                        }) => (
                                            <tr key={item.id} className="hover:bg-muted/40 border-b border-l-4 border-l-green-500 transition-colors">
                                                <td className="px-5 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <CheckCircle2 className="h-5 w-5 shrink-0 text-green-600" aria-label="Al día" />
                                                        <span className="text-[15px] font-medium">{item.full_name}</span>
                                                    </div>
                                                </td>
                                                <td className="px-5 py-4 font-mono text-sm">{item.document_number}</td>
                                                <td className="px-5 py-4 text-sm">{item.market_name}</td>
                                                <td className="px-5 py-4 text-center text-[15px] font-medium">{item.total_payments}</td>
                                                <td className="px-5 py-4 text-center text-sm">
                                                    {item.last_payment_date
                                                        ? new Date(item.last_payment_date).toLocaleDateString('es-VE')
                                                        : 'Sin pagos registrados'}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/*  DistributionView                                                   */
/* ------------------------------------------------------------------ */

function DistributionView() {
    const { data, isLoading } = useQuery({
        queryKey: ['debt-analysis', 'distributions'],
        queryFn: async () => {
            const res = await fetch('/api/debt-analysis/distributions');
            if (!res.ok) throw new Error('Failed to fetch');
            return res.json();
        },
    });

    const agingLabels: Record<string, string> = {
        '0-30': 'Reciente (0 a 30 días)',
        '31-60': 'Moderado (31 a 60 días)',
        '61-90': 'Importante (61 a 90 días)',
        '90+': 'Crítico (más de 90 días)',
    };

    const agingColors: Record<string, { bar: string; bg: string; text: string; icon: React.ElementType }> = {
        '0-30': { bar: 'bg-green-500', bg: 'bg-green-50 dark:bg-green-950/30', text: 'text-green-800 dark:text-green-200', icon: CheckCircle2 },
        '31-60': { bar: 'bg-amber-500', bg: 'bg-amber-50 dark:bg-amber-950/30', text: 'text-amber-800 dark:text-amber-200', icon: AlertTriangle },
        '61-90': { bar: 'bg-orange-500', bg: 'bg-orange-50 dark:bg-orange-950/30', text: 'text-orange-800 dark:text-orange-200', icon: ShieldAlert },
        '90+': { bar: 'bg-red-600', bg: 'bg-red-50 dark:bg-red-950/30', text: 'text-red-800 dark:text-red-200', icon: AlertOctagon },
    };

    // Compute textual insights
    const insights: string[] = [];
    if (data?.by_aging) {
        const totalBs = data.by_aging.reduce((sum: number, b: { debt_bs_minor: number }) => sum + b.debt_bs_minor, 0);
        const critical = data.by_aging.find((b: { bucket: string }) => b.bucket === '90+');
        if (critical && totalBs > 0) {
            const pct = ((critical.debt_bs_minor / totalBs) * 100).toFixed(1);
            if (parseFloat(pct) > 50) {
                insights.push(`El ${pct}% de la deuda total está en mora crítica (+90 días). Requiere atención inmediata.`);
            }
        }
        if (data.by_market?.length === 1) {
            insights.push(`Toda la deuda mostrada se concentra en ${data.by_market[0].market_name}.`);
        }
    }

    return (
        <div className="space-y-6">
            {/* Conclusiones textuales */}
            {insights.length > 0 && (
                <div className="rounded-xl border-2 border-red-200 bg-red-50 p-5 dark:border-red-800 dark:bg-red-950/30">
                    <div className="flex items-start gap-3">
                        <AlertOctagon className="mt-0.5 h-6 w-6 shrink-0 text-red-600 dark:text-red-400" />
                        <div className="space-y-1">
                            <p className="text-base font-bold text-red-800 dark:text-red-200">Lo que debe saber</p>
                            {insights.map((text, i) => (
                                <p key={i} className="text-[15px] leading-relaxed text-red-700 dark:text-red-300">
                                    {text}
                                </p>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            <Card>
                <CardHeader className="pb-2">
                    <div className="flex items-center gap-3">
                        <Clock className="text-muted-foreground h-5 w-5" />
                        <div>
                            <h3 className="text-base font-semibold">Deuda por tiempo de atraso</h3>
                            <p className="text-muted-foreground text-sm">Cuánto más tiempo pasa, más crítica se vuelve la deuda</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="space-y-4">
                            {[1, 2, 3, 4].map((i) => (
                                <div key={i} className="space-y-2">
                                    <Skeleton className="h-5 w-48" />
                                    <Skeleton className="h-7 w-full rounded-lg" />
                                </div>
                            ))}
                        </div>
                    ) : data ? (
                        <div className="space-y-4">
                            {data.by_aging.map(
                                (bucket: {
                                    bucket: string;
                                    debt_eur_minor: number;
                                    debt_usd_minor: number;
                                    debt_bs_minor: number;
                                    count: number;
                                }) => {
                                    const totalBs = data.by_aging.reduce((sum: number, b: { debt_bs_minor: number }) => sum + b.debt_bs_minor, 0);
                                    const percent = totalBs > 0 ? (bucket.debt_bs_minor / totalBs) * 100 : 0;
                                    const colors = agingColors[bucket.bucket] ?? agingColors['0-30'];
                                    const label = agingLabels[bucket.bucket] ?? bucket.bucket;
                                    const BucketIcon = colors.icon;

                                    return (
                                        <div key={bucket.bucket} className={`rounded-xl border p-4 ${colors.bg}`}>
                                            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                                <span className={`flex items-center gap-2 text-sm font-bold ${colors.text}`}>
                                                    <BucketIcon className="h-4 w-4" aria-hidden="true" />
                                                    {label}
                                                </span>
                                                <div className="flex items-center gap-3">
                                                    <span className="text-sm font-bold tabular-nums">{fmtBs(bucket.debt_bs_minor)}</span>
                                                    <Badge variant="secondary" className="text-xs font-bold">
                                                        {percent.toFixed(1)}%
                                                    </Badge>
                                                </div>
                                            </div>
                                            <div className="h-5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                <div
                                                    className={`${colors.bar} h-5 rounded-full transition-all duration-500`}
                                                    style={{ width: `${Math.max(percent, 2)}%` }}
                                                />
                                            </div>
                                            {(bucket.debt_eur_minor > 0 || bucket.debt_usd_minor > 0) && (
                                                <div className="text-muted-foreground mt-1.5 flex gap-4 text-xs">
                                                    {bucket.debt_eur_minor > 0 && <span>{fmtEur(bucket.debt_eur_minor)}</span>}
                                                    {bucket.debt_usd_minor > 0 && <span>{fmtUsd(bucket.debt_usd_minor)}</span>}
                                                </div>
                                            )}
                                        </div>
                                    );
                                },
                            )}
                        </div>
                    ) : null}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="pb-2">
                    <div className="flex items-center gap-3">
                        <Building2 className="text-muted-foreground h-5 w-5" />
                        <div>
                            <h3 className="text-base font-semibold">Deuda por mercado</h3>
                            <p className="text-muted-foreground text-sm">Distribución de la deuda en cada mercado</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="space-y-3">
                            {[1, 2, 3].map((i) => (
                                <Skeleton key={i} className="h-14 w-full rounded-lg" />
                            ))}
                        </div>
                    ) : data ? (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full">
                                <thead className="bg-muted/60">
                                    <tr className="border-b">
                                        <th className="px-5 py-3.5 text-left text-sm font-semibold">Mercado</th>
                                        <th className="px-5 py-3.5 text-right text-sm font-semibold">Euros</th>
                                        <th className="px-5 py-3.5 text-right text-sm font-semibold">Dólares</th>
                                        <th className="px-5 py-3.5 text-right text-sm font-semibold">Bolívares</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.by_market.map(
                                        (market: {
                                            market_id: number;
                                            market_name: string;
                                            debt_eur_minor: number;
                                            debt_usd_minor: number;
                                            debt_bs_minor: number;
                                        }) => (
                                            <tr key={market.market_id} className="hover:bg-muted/40 border-b transition-colors">
                                                <td className="px-5 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <Building2 className="text-muted-foreground h-4 w-4" />
                                                        <span className="text-[15px] font-medium">{market.market_name}</span>
                                                    </div>
                                                </td>
                                                <td className="px-5 py-4 text-right font-mono text-sm tabular-nums">
                                                    {fmtEur(market.debt_eur_minor)}
                                                </td>
                                                <td className="px-5 py-4 text-right font-mono text-sm tabular-nums">
                                                    {fmtUsd(market.debt_usd_minor)}
                                                </td>
                                                <td className="px-5 py-4 text-right font-mono text-sm font-semibold tabular-nums">
                                                    {fmtBs(market.debt_bs_minor)}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/*  Main Page                                                          */
/* ------------------------------------------------------------------ */

export default function DebtAnalysisPage() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Análisis de Deudas" />

            <div className="mx-auto max-w-[1400px] space-y-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Análisis de deudas</h1>
                        <p className="text-muted-foreground mt-1 text-[15px]">
                            Consulte rápidamente quién debe, cuánto debe y qué casos requieren atención inmediata
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild className="shrink-0 gap-1.5 text-sm">
                        <Link href="/dashboard">
                            <LayoutDashboard className="h-4 w-4" />
                            Volver al inicio
                        </Link>
                    </Button>
                </div>

                {/* Tabs reales */}
                <Tabs defaultValue="concessionaires" className="space-y-6">
                    <TabsList className="bg-muted/60 h-auto w-full flex-wrap justify-start gap-1 rounded-xl p-1.5">
                        <TabsTrigger
                            value="concessionaires"
                            className="gap-2 rounded-lg px-4 py-2.5 text-sm font-medium data-[state=active]:shadow-md"
                        >
                            <Users className="h-4 w-4" />
                            Personas con deuda
                        </TabsTrigger>
                        <TabsTrigger value="locals" className="gap-2 rounded-lg px-4 py-2.5 text-sm font-medium data-[state=active]:shadow-md">
                            <Store className="h-4 w-4" />
                            Locales con deuda
                        </TabsTrigger>
                        <TabsTrigger value="solvent" className="gap-2 rounded-lg px-4 py-2.5 text-sm font-medium data-[state=active]:shadow-md">
                            <CheckCircle2 className="h-4 w-4" />
                            Personas al día
                        </TabsTrigger>
                        <TabsTrigger value="distribution" className="gap-2 rounded-lg px-4 py-2.5 text-sm font-medium data-[state=active]:shadow-md">
                            <Clock className="h-4 w-4" />
                            Resumen
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="concessionaires">
                        <ConcessionairesView />
                    </TabsContent>
                    <TabsContent value="locals">
                        <LocalsDebtView />
                    </TabsContent>
                    <TabsContent value="solvent">
                        <SolventView />
                    </TabsContent>
                    <TabsContent value="distribution">
                        <DistributionView />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
