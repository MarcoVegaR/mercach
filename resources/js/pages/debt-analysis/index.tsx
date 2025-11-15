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
import { useState } from 'react';
// Table components will be added later or use HTML tables
import { ArrowLeft, ChevronLeft, ChevronRight, Download, Search } from 'lucide-react';

type DebtItem = {
    id: number;
    full_name: string;
    document_number: string;
    market_name: string;
    debt_eur_minor: number;
    debt_bs_minor: number;
    days_overdue_avg: number;
    days_overdue_max: number;
    locals_count: number;
    charges_count: number;
    severity: 'critical' | 'high' | 'medium' | 'low';
};

type DebtResponse = {
    data: DebtItem[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    summary: {
        total_debt_eur_minor: number;
        total_debt_bs_minor: number;
        total_count: number;
        avg_debt_eur_minor: number;
        avg_days_overdue: number;
    };
    fx_rate: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Análisis de Deudas', href: '/dashboard/debt-analysis' },
];

// Componente: Tab Locales Morosos
function LocalsDebtView() {
    const { data, isLoading } = useQuery({
        queryKey: ['debt-analysis', 'locals'],
        queryFn: async () => {
            const res = await fetch('/api/debt-analysis/delinquent-locals?page=1&per_page=50');
            if (!res.ok) throw new Error('Failed to fetch');
            return res.json();
        },
    });

    const getSeverityBadge = (severity: string) => {
        const variants = {
            critical: { label: '🔴 Crítico', class: 'bg-red-100 text-red-800' },
            high: { label: '🟠 Alto', class: 'bg-orange-100 text-orange-800' },
            medium: { label: '🟡 Medio', class: 'bg-yellow-100 text-yellow-800' },
            low: { label: '🟢 Bajo', class: 'bg-green-100 text-green-800' },
        };
        const variant = variants[severity as keyof typeof variants] || variants.low;
        return <Badge className={variant.class}>{variant.label}</Badge>;
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>🏪 Locales Morosos</CardTitle>
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
                                    <th className="px-4 py-3 text-left text-sm font-medium">Código</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">Cesionario</th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">Mercado</th>
                                    <th className="px-4 py-3 text-right text-sm font-medium">Deuda EUR</th>
                                    <th className="px-4 py-3 text-center text-sm font-medium">Días</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((local: any) => (
                                    <tr key={local.id} className="hover:bg-muted/50 border-b">
                                        <td className="px-4 py-3">{getSeverityBadge(local.severity)}</td>
                                        <td className="px-4 py-3 font-mono">{local.local_code}</td>
                                        <td className="px-4 py-3">{local.concessionaire_name}</td>
                                        <td className="px-4 py-3">{local.market_name}</td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            € {(local.debt_eur_minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}
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

// Componente: Tab Solventes
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
                <CardTitle>✅ Cesionarios Solventes</CardTitle>
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
                                        <th className="px-4 py-3 text-center text-sm font-medium">Último Pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.data.map((item: any) => (
                                        <tr key={item.id} className="hover:bg-muted/50 border-b">
                                            <td className="px-4 py-3 font-medium">{item.full_name}</td>
                                            <td className="px-4 py-3">{item.document_number}</td>
                                            <td className="px-4 py-3">{item.market_name}</td>
                                            <td className="px-4 py-3 text-center">{item.total_payments}</td>
                                            <td className="px-4 py-3 text-center">
                                                {item.last_payment_date ? new Date(item.last_payment_date).toLocaleDateString('es-VE') : 'N/A'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

// Componente: Tab Distribución
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
            {/* Distribución por Aging */}
            <Card>
                <CardHeader>
                    <CardTitle>📊 Distribución por Antigüedad (Aging)</CardTitle>
                    <CardDescription>Deuda agrupada por días de atraso</CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading && <p className="py-8 text-center">Cargando...</p>}
                    {!isLoading && data && (
                        <div className="space-y-4">
                            {data.by_aging.map((bucket: any) => {
                                const percent =
                                    (bucket.debt_eur_minor / data.by_aging.reduce((sum: number, b: any) => sum + b.debt_eur_minor, 0)) * 100;
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
                                            <span className="text-sm font-medium">{bucket.bucket} días</span>
                                            <span className="text-muted-foreground text-sm">
                                                € {(bucket.debt_eur_minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })} (
                                                {percent.toFixed(1)}%)
                                            </span>
                                        </div>
                                        <div className="h-4 w-full rounded-full bg-gray-200">
                                            <div className={`${colorClass} h-4 rounded-full transition-all`} style={{ width: `${percent}%` }} />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Distribución por Mercado */}
            <Card>
                <CardHeader>
                    <CardTitle>🗺️ Distribución por Mercado</CardTitle>
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
                                    {data.by_market.map((market: any) => (
                                        <tr key={market.market_id} className="border-b">
                                            <td className="px-4 py-3 font-medium">{market.market_name}</td>
                                            <td className="px-4 py-3 text-right font-mono">
                                                € {(market.debt_eur_minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono">
                                                Bs. {(market.debt_bs_minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-4 py-3 text-center">{market.count}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default function DebtAnalysisPage() {
    const [filters, setFilters] = useState({
        page: 1,
        per_page: 25,
        sort_by: 'debt_eur',
        sort_dir: 'desc' as 'asc' | 'desc',
        search: '',
        market_id: '',
        min_debt_eur: '',
    });

    const { data, isLoading } = useQuery<DebtResponse>({
        queryKey: ['debt-analysis', 'concessionaires', filters],
        queryFn: async () => {
            const params = new URLSearchParams();
            Object.entries(filters).forEach(([key, value]) => {
                if (value) params.append(key, value.toString());
            });
            const res = await fetch(`/api/debt-analysis/delinquent-concessionaires?${params}`);
            if (!res.ok) throw new Error('Failed to fetch');
            return res.json();
        },
    });

    const handleFilterChange = (key: string, value: string | number) => {
        setFilters((prev) => ({ ...prev, [key]: value, page: 1 }));
    };

    const handleExport = () => {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value && key !== 'page' && key !== 'per_page') {
                params.append(key, value.toString());
            }
        });
        params.append('scope', 'concessionaires');
        params.append('format', 'csv');
        window.open(`/api/debt-analysis/export?${params}`, '_blank');
    };

    const getSeverityBadge = (severity: string) => {
        const variants = {
            critical: { label: '🔴 Crítico', class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
            high: { label: '🟠 Alto', class: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
            medium: { label: '🟡 Medio', class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
            low: { label: '🟢 Bajo', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
        };
        const variant = variants[severity as keyof typeof variants] || variants.low;
        return <Badge className={variant.class}>{variant.label}</Badge>;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Análisis de Deudas" />

            <div className="container mx-auto space-y-6 py-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">📊 Análisis de Deudas</h1>
                        <p className="text-muted-foreground mt-1">Vista agregada filtrable y paginada de todas las deudas</p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/dashboard">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Volver al Dashboard
                        </Link>
                    </Button>
                </div>

                {/* Tabs */}
                <Tabs defaultValue="concessionaires" className="space-y-4">
                    <TabsList>
                        <TabsTrigger value="concessionaires">Por Cesionario</TabsTrigger>
                        <TabsTrigger value="locals">Por Local</TabsTrigger>
                        <TabsTrigger value="solvent">Solventes</TabsTrigger>
                        <TabsTrigger value="distribution">Distribución</TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Por Cesionario */}
                    <TabsContent value="concessionaires" className="space-y-4">
                        {/* Filtros */}
                        <Card>
                            <CardHeader>
                                <CardTitle>🔍 Filtros</CardTitle>
                                <CardDescription>Filtra y busca morosos según criterios específicos</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Búsqueda</label>
                                        <div className="relative">
                                            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                            <Input
                                                placeholder="Nombre o documento..."
                                                value={filters.search}
                                                onChange={(e) => handleFilterChange('search', e.target.value)}
                                                className="pl-10"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Deuda mínima (EUR)</label>
                                        <Input
                                            type="number"
                                            placeholder="0"
                                            value={filters.min_debt_eur}
                                            onChange={(e) => handleFilterChange('min_debt_eur', e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Registros por página</label>
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
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setFilters({
                                                page: 1,
                                                per_page: 25,
                                                sort_by: 'debt_eur',
                                                sort_dir: 'desc',
                                                search: '',
                                                market_id: '',
                                                min_debt_eur: '',
                                            })
                                        }
                                    >
                                        Limpiar Filtros
                                    </Button>
                                    <Button onClick={handleExport} variant="default">
                                        <Download className="mr-2 h-4 w-4" />
                                        Exportar CSV
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        {/* KPIs Resumen */}
                        {data && (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardDescription>Deuda Total</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">
                                            € {(data.summary.total_debt_eur_minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Bs. {(data.summary.total_debt_bs_minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}
                                        </p>
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
                                        <CardDescription>Deuda Promedio</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">
                                            € {(data.summary.avg_debt_eur_minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardDescription>Promedio Días Vencidos</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{data.summary.avg_days_overdue}</div>
                                        <p className="text-muted-foreground mt-1 text-xs">días</p>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {/* Tabla */}
                        <Card>
                            <CardHeader>
                                <CardTitle>📊 Cesionarios Morosos</CardTitle>
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
                                                <th className="px-4 py-3 text-right text-sm font-medium">Deuda EUR</th>
                                                <th className="px-4 py-3 text-center text-sm font-medium">Días Vencidos</th>
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
                                                        <td className="px-4 py-3">{getSeverityBadge(item.severity)}</td>
                                                        <td className="px-4 py-3">
                                                            <div>
                                                                <div className="font-medium">{item.full_name}</div>
                                                                <div className="text-muted-foreground text-xs">{item.document_number}</div>
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">{item.market_name}</td>
                                                        <td className="px-4 py-3 text-right font-mono">
                                                            € {(item.debt_eur_minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}
                                                        </td>
                                                        <td className="px-4 py-3 text-center">{item.days_overdue_avg} días</td>
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

                                {/* Paginación */}
                                {data && data.meta.last_page > 1 && (
                                    <div className="mt-4 flex items-center justify-between">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleFilterChange('page', data.meta.current_page - 1)}
                                            disabled={data.meta.current_page === 1}
                                        >
                                            <ChevronLeft className="mr-1 h-4 w-4" />
                                            Anterior
                                        </Button>
                                        <div className="flex items-center gap-1">
                                            {Array.from({ length: Math.min(5, data.meta.last_page) }, (_, i) => {
                                                const page = i + 1;
                                                return (
                                                    <Button
                                                        key={page}
                                                        variant={data.meta.current_page === page ? 'default' : 'outline'}
                                                        size="sm"
                                                        onClick={() => handleFilterChange('page', page)}
                                                    >
                                                        {page}
                                                    </Button>
                                                );
                                            })}
                                            {data.meta.last_page > 5 && <span className="px-2">...</span>}
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleFilterChange('page', data.meta.current_page + 1)}
                                            disabled={data.meta.current_page === data.meta.last_page}
                                        >
                                            Siguiente
                                            <ChevronRight className="ml-1 h-4 w-4" />
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 2: Por Local */}
                    <TabsContent value="locals">
                        <LocalsDebtView />
                    </TabsContent>

                    {/* Tab 3: Solventes */}
                    <TabsContent value="solvent">
                        <SolventView />
                    </TabsContent>

                    {/* Tab 4: Distribución */}
                    <TabsContent value="distribution">
                        <DistributionView />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
