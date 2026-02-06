import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, ChevronLeft, ChevronRight, Download, Search } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

type DebtItem = {
    id: number;
    full_name: string;
    document_number: string;
    market_name: string;
    debt_eur_minor: number;
    debt_usd_minor: number;
    debt_bs_minor: number;
    days_overdue_avg: number;
    days_overdue_max: number;
    locals_count: number;
    charges_count: number;
    severity: 'critical' | 'high' | 'medium' | 'low';
};

type DebtResponse = {
    data: DebtItem[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
    summary: {
        total_debt_eur_minor: number;
        total_debt_usd_minor: number;
        total_debt_bs_minor: number;
        total_count: number;
        avg_days_overdue: number;
    };
    fx_rate_eur: number;
    fx_rate_usd: number;
};

type LocalDebtItem = {
    id: number;
    local_code: string;
    local_name: string;
    concessionaire_name: string;
    market_name: string;
    local_type_name: string;
    debt_eur_minor: number;
    debt_usd_minor: number;
    debt_bs_minor: number;
    days_overdue_avg: number;
    charges_count: number;
    severity: 'critical' | 'high' | 'medium' | 'low';
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Análisis de Deudas', href: '/dashboard/debt-analysis' },
];

const fmtBs = (minor: number) => `Bs. ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`;
const fmtEur = (minor: number) => `€ ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`;
const fmtUsd = (minor: number) => `$ ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`;

const severityConfig = {
    critical: { label: 'Critico', class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
    high: { label: 'Alto', class: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
    medium: { label: 'Medio', class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
    low: { label: 'Bajo', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
} as const;

function SeverityBadge({ severity }: { severity: string }) {
    const cfg = severityConfig[severity as keyof typeof severityConfig] ?? severityConfig.low;
    return <Badge className={cfg.class}>{cfg.label}</Badge>;
}

function DebtAmounts({ eur, usd, bs }: { eur: number; usd: number; bs: number }) {
    return (
        <div className="space-y-0.5 text-right font-mono text-sm">
            {eur > 0 && <div>{fmtEur(eur)}</div>}
            {usd > 0 && <div>{fmtUsd(usd)}</div>}
            <div className="text-muted-foreground text-xs">{fmtBs(bs)}</div>
        </div>
    );
}

function useDebounce<T>(value: T, delay: number): T {
    const [debounced, setDebounced] = useState(value);
    useEffect(() => {
        const timer = setTimeout(() => setDebounced(value), delay);
        return () => clearTimeout(timer);
    }, [value, delay]);
    return debounced;
}

function LocalsDebtView() {
    const { data, isLoading } = useQuery({
        queryKey: ['debt-analysis', 'locals'],
        queryFn: async () => {
            const res = await fetch('/api/debt-analysis/delinquent-locals?page=1&per_page=50&sort_by=debt_bs&sort_dir=desc');
            if (!res.ok) throw new Error('Failed to fetch');
            return res.json();
        },
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle>Locales Morosos</CardTitle>
                <CardDescription>Vista detallada de deudas por local</CardDescription>
            </CardHeader>
            <CardContent>
                {isLoading && <p className="py-8 text-center">Cargando...</p>}
                {!isLoading && data && (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full">
                            <thead className="bg-muted/50">
                                <tr className="border-b">
                                    <th className="px-4 py-3 text-left text-sm font-medium">Severidad</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">Codigo</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">Cesionario</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">Mercado</th>
                                    <th className="px-4 py-3 text-right text-sm font-medium">Deuda</th>
                                    <th className="px-4 py-3 text-center text-sm font-medium">Dias</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((local: LocalDebtItem) => (
                                    <tr key={`${local.id}-${local.concessionaire_name}`} className="hover:bg-muted/50 border-b">
                                        <td className="px-4 py-3">
                                            <SeverityBadge severity={local.severity} />
                                        </td>
                                        <td className="px-4 py-3 font-mono">{local.local_code}</td>
                                        <td className="px-4 py-3">{local.concessionaire_name}</td>
                                        <td className="px-4 py-3">{local.market_name}</td>
                                        <td className="px-4 py-3">
                                            <DebtAmounts eur={local.debt_eur_minor} usd={local.debt_usd_minor} bs={local.debt_bs_minor} />
                                        </td>
                                        <td className="px-4 py-3 text-center">{local.days_overdue_avg}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

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
        <Card>
            <CardHeader>
                <CardTitle>Cesionarios Solventes</CardTitle>
                <CardDescription>Sin deuda vencida actualmente</CardDescription>
            </CardHeader>
            <CardContent>
                {isLoading && <p className="py-8 text-center">Cargando...</p>}
                {!isLoading && data && (
                    <>
                        <div className="mb-4 rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                            <p className="text-sm text-green-800 dark:text-green-200">
                                <strong>{data.meta.total}</strong> cesionarios sin deuda vencida
                            </p>
                        </div>
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full">
                                <thead className="bg-muted/50">
                                    <tr className="border-b">
                                        <th className="px-4 py-3 text-left text-sm font-medium">Cesionario</th>
                                        <th className="px-4 py-3 text-left text-sm font-medium">Documento</th>
                                        <th className="px-4 py-3 text-left text-sm font-medium">Mercado</th>
                                        <th className="px-4 py-3 text-center text-sm font-medium">Total Pagos</th>
                                        <th className="px-4 py-3 text-center text-sm font-medium">Ultimo Pago</th>
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
                                            <tr key={item.id} className="hover:bg-muted/50 border-b">
                                                <td className="px-4 py-3 font-medium">{item.full_name}</td>
                                                <td className="px-4 py-3">{item.document_number}</td>
                                                <td className="px-4 py-3">{item.market_name}</td>
                                                <td className="px-4 py-3 text-center">{item.total_payments}</td>
                                                <td className="px-4 py-3 text-center">
                                                    {item.last_payment_date ? new Date(item.last_payment_date).toLocaleDateString('es-VE') : 'N/A'}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function DistributionView() {
    const { data, isLoading } = useQuery({
        queryKey: ['debt-analysis', 'distributions'],
        queryFn: async () => {
            const res = await fetch('/api/debt-analysis/distributions');
            if (!res.ok) throw new Error('Failed to fetch');
            return res.json();
        },
    });

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Distribucion por Antiguedad (Aging)</CardTitle>
                    <CardDescription>Deuda agrupada por dias de atraso</CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading && <p className="py-8 text-center">Cargando...</p>}
                    {!isLoading && data && (
                        <div className="space-y-4">
                            {data.by_aging.map((bucket: { bucket: string; debt_eur_minor: number; debt_bs_minor: number; count: number }) => {
                                const totalBs = data.by_aging.reduce((sum: number, b: { debt_bs_minor: number }) => sum + b.debt_bs_minor, 0);
                                const percent = totalBs > 0 ? (bucket.debt_bs_minor / totalBs) * 100 : 0;
                                const colorClass =
                                    bucket.bucket === '90+'
                                        ? 'bg-red-500'
                                        : bucket.bucket === '61-90'
                                          ? 'bg-orange-500'
                                          : bucket.bucket === '31-60'
                                            ? 'bg-yellow-500'
                                            : 'bg-green-500';

                                return (
                                    <div key={bucket.bucket}>
                                        <div className="mb-2 flex items-center justify-between">
                                            <span className="text-sm font-medium">{bucket.bucket} dias</span>
                                            <span className="text-muted-foreground text-sm">
                                                {fmtBs(bucket.debt_bs_minor)} ({percent.toFixed(1)}%)
                                            </span>
                                        </div>
                                        <div className="h-4 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div className={`${colorClass} h-4 rounded-full transition-all`} style={{ width: `${percent}%` }} />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Distribucion por Mercado</CardTitle>
                    <CardDescription>Deuda agrupada por mercado</CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading && <p className="py-8 text-center">Cargando...</p>}
                    {!isLoading && data && (
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full">
                                <thead className="bg-muted/50">
                                    <tr className="border-b">
                                        <th className="px-4 py-3 text-left text-sm font-medium">Mercado</th>
                                        <th className="px-4 py-3 text-right text-sm font-medium">Deuda EUR</th>
                                        <th className="px-4 py-3 text-right text-sm font-medium">Deuda Bs</th>
                                        <th className="px-4 py-3 text-center text-sm font-medium">Cesionarios</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.by_market.map(
                                        (market: {
                                            market_id: number;
                                            market_name: string;
                                            debt_eur_minor: number;
                                            debt_bs_minor: number;
                                            count: number;
                                        }) => (
                                            <tr key={market.market_id} className="border-b">
                                                <td className="px-4 py-3 font-medium">{market.market_name}</td>
                                                <td className="px-4 py-3 text-right font-mono">{fmtEur(market.debt_eur_minor)}</td>
                                                <td className="px-4 py-3 text-right font-mono">{fmtBs(market.debt_bs_minor)}</td>
                                                <td className="px-4 py-3 text-center">{market.count}</td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function PaginationControls({
    currentPage,
    lastPage,
    onPageChange,
}: {
    currentPage: number;
    lastPage: number;
    onPageChange: (page: number) => void;
}) {
    if (lastPage <= 1) return null;

    const pages: (number | '...')[] = [];
    if (lastPage <= 7) {
        for (let i = 1; i <= lastPage; i++) pages.push(i);
    } else {
        pages.push(1);
        if (currentPage > 3) pages.push('...');
        for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) pages.push(i);
        if (currentPage < lastPage - 2) pages.push('...');
        pages.push(lastPage);
    }

    return (
        <div className="mt-4 flex items-center justify-between">
            <Button variant="outline" size="sm" onClick={() => onPageChange(currentPage - 1)} disabled={currentPage === 1}>
                <ChevronLeft className="mr-1 h-4 w-4" />
                Anterior
            </Button>
            <div className="flex items-center gap-1">
                {pages.map((p, i) =>
                    p === '...' ? (
                        <span key={`ellipsis-${i}`} className="px-2">
                            ...
                        </span>
                    ) : (
                        <Button key={p} variant={currentPage === p ? 'default' : 'outline'} size="sm" onClick={() => onPageChange(p)}>
                            {p}
                        </Button>
                    ),
                )}
            </div>
            <Button variant="outline" size="sm" onClick={() => onPageChange(currentPage + 1)} disabled={currentPage === lastPage}>
                Siguiente
                <ChevronRight className="ml-1 h-4 w-4" />
            </Button>
        </div>
    );
}

export default function DebtAnalysisPage() {
    const [searchInput, setSearchInput] = useState('');
    const debouncedSearch = useDebounce(searchInput, 400);

    const [filters, setFilters] = useState({
        page: 1,
        per_page: 25,
        sort_by: 'debt_bs',
        sort_dir: 'desc' as 'asc' | 'desc',
        market_id: '',
        min_debt_eur: '',
    });

    const prevSearchRef = useRef(debouncedSearch);
    useEffect(() => {
        if (prevSearchRef.current !== debouncedSearch) {
            prevSearchRef.current = debouncedSearch;
            setFilters((prev) => ({ ...prev, page: 1 }));
        }
    }, [debouncedSearch]);

    const queryFilters = useMemo(() => ({ ...filters, search: debouncedSearch }), [filters, debouncedSearch]);

    const { data, isLoading } = useQuery<DebtResponse>({
        queryKey: ['debt-analysis', 'concessionaires', queryFilters],
        queryFn: async () => {
            const params = new URLSearchParams();
            Object.entries(queryFilters).forEach(([key, value]) => {
                if (value) params.append(key, value.toString());
            });
            const res = await fetch(`/api/debt-analysis/delinquent-concessionaires?${params}`);
            if (!res.ok) throw new Error('Failed to fetch');
            return res.json();
        },
    });

    const handleFilterChange = useCallback((key: string, value: string | number) => {
        setFilters((prev) => ({ ...prev, [key]: value, page: key === 'page' ? (value as number) : 1 }));
    }, []);

    const handleExport = useCallback(() => {
        const params = new URLSearchParams();
        Object.entries(queryFilters).forEach(([key, value]) => {
            if (value && key !== 'page' && key !== 'per_page') params.append(key, value.toString());
        });
        params.append('scope', 'concessionaires');
        params.append('format', 'csv');
        window.open(`/api/debt-analysis/export?${params}`, '_blank');
    }, [queryFilters]);

    const resetFilters = useCallback(() => {
        setSearchInput('');
        setFilters({ page: 1, per_page: 25, sort_by: 'debt_bs', sort_dir: 'desc', market_id: '', min_debt_eur: '' });
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Analisis de Deudas" />

            <div className="container mx-auto space-y-6 py-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Analisis de Deudas</h1>
                        <p className="text-muted-foreground mt-1">Vista agregada filtrable y paginada de todas las deudas</p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/dashboard">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Volver al Dashboard
                        </Link>
                    </Button>
                </div>

                <Tabs defaultValue="concessionaires" className="space-y-4">
                    <TabsList>
                        <TabsTrigger value="concessionaires">Por Cesionario</TabsTrigger>
                        <TabsTrigger value="locals">Por Local</TabsTrigger>
                        <TabsTrigger value="solvent">Solventes</TabsTrigger>
                        <TabsTrigger value="distribution">Distribucion</TabsTrigger>
                    </TabsList>

                    <TabsContent value="concessionaires" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Filtros</CardTitle>
                                <CardDescription>Filtra y busca morosos segun criterios especificos</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Busqueda</label>
                                        <div className="relative">
                                            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                            <Input
                                                placeholder="Nombre o documento..."
                                                value={searchInput}
                                                onChange={(e) => setSearchInput(e.target.value)}
                                                className="pl-10"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Deuda minima (EUR)</label>
                                        <Input
                                            type="number"
                                            placeholder="0"
                                            value={filters.min_debt_eur}
                                            onChange={(e) => handleFilterChange('min_debt_eur', e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Registros por pagina</label>
                                        <Select
                                            value={filters.per_page.toString()}
                                            onValueChange={(v) => handleFilterChange('per_page', parseInt(v))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="25">25</SelectItem>
                                                <SelectItem value="50">50</SelectItem>
                                                <SelectItem value="100">100</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div className="mt-4 flex justify-between">
                                    <Button variant="outline" onClick={resetFilters}>
                                        Limpiar Filtros
                                    </Button>
                                    <Button onClick={handleExport} variant="default">
                                        <Download className="mr-2 h-4 w-4" />
                                        Exportar CSV
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        {data && (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardDescription>Deuda total EUR</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{fmtEur(data.summary.total_debt_eur_minor)}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardDescription>Deuda total USD</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{fmtUsd(data.summary.total_debt_usd_minor)}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardDescription>Total Morosos</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{data.summary.total_count}</div>
                                        <p className="text-muted-foreground mt-1 text-xs">Cesionarios</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardDescription>Promedio Dias Vencidos</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{data.summary.avg_days_overdue}</div>
                                        <p className="text-muted-foreground mt-1 text-xs">dias</p>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Cesionarios Morosos</CardTitle>
                                {data && (
                                    <CardDescription>
                                        Mostrando {(data.meta.current_page - 1) * data.meta.per_page + 1} a{' '}
                                        {Math.min(data.meta.current_page * data.meta.per_page, data.meta.total)} de {data.meta.total} resultados
                                    </CardDescription>
                                )}
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto rounded-md border">
                                    <table className="w-full">
                                        <thead className="bg-muted/50">
                                            <tr className="border-b">
                                                <th className="px-4 py-3 text-left text-sm font-medium">Severidad</th>
                                                <th className="px-4 py-3 text-left text-sm font-medium">Cesionario</th>
                                                <th className="px-4 py-3 text-left text-sm font-medium">Mercado</th>
                                                <th className="px-4 py-3 text-right text-sm font-medium">Deuda</th>
                                                <th className="px-4 py-3 text-center text-sm font-medium">Dias Vencidos</th>
                                                <th className="px-4 py-3 text-center text-sm font-medium">Locales</th>
                                                <th className="px-4 py-3 text-right text-sm font-medium">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {isLoading && (
                                                <tr>
                                                    <td colSpan={7} className="text-muted-foreground py-8 text-center">
                                                        Cargando...
                                                    </td>
                                                </tr>
                                            )}
                                            {!isLoading && data && data.data.length === 0 && (
                                                <tr>
                                                    <td colSpan={7} className="text-muted-foreground py-8 text-center">
                                                        No se encontraron morosos con los filtros aplicados
                                                    </td>
                                                </tr>
                                            )}
                                            {!isLoading &&
                                                data &&
                                                data.data.map((item) => (
                                                    <tr key={item.id} className="hover:bg-muted/50 cursor-pointer border-b">
                                                        <td className="px-4 py-3">
                                                            <SeverityBadge severity={item.severity} />
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div>
                                                                <div className="font-medium">{item.full_name}</div>
                                                                <div className="text-muted-foreground text-xs">{item.document_number}</div>
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">{item.market_name}</td>
                                                        <td className="px-4 py-3">
                                                            <DebtAmounts
                                                                eur={item.debt_eur_minor}
                                                                usd={item.debt_usd_minor}
                                                                bs={item.debt_bs_minor}
                                                            />
                                                        </td>
                                                        <td className="px-4 py-3 text-center">{item.days_overdue_avg} dias</td>
                                                        <td className="px-4 py-3 text-center">{item.locals_count}</td>
                                                        <td className="px-4 py-3 text-right">
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => router.visit(`/admin/economic-profile/concessionaires/${item.id}`)}
                                                            >
                                                                Ver Perfil
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                        </tbody>
                                    </table>
                                </div>

                                {data && (
                                    <PaginationControls
                                        currentPage={data.meta.current_page}
                                        lastPage={data.meta.last_page}
                                        onPageChange={(p) => handleFilterChange('page', p)}
                                    />
                                )}
                            </CardContent>
                        </Card>
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
