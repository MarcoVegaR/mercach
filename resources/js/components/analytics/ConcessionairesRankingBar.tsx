import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowDownUp, RefreshCw } from 'lucide-react';
import * as React from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type RankingItem = { id: number; name: string; value: number };
type RankingResponse = {
    metric: 'contracts' | 'm2';
    order: 'top' | 'bottom';
    items: RankingItem[];
    generated_at: string;
};

type Metric = 'contracts' | 'm2';
type Order = 'top' | 'bottom';

const chartConfig = {
    contracts: {
        label: 'Contratos',
        // Use brand primary to align with Supabase-like theme for single-series bars
        color: 'var(--primary)',
    },
    m2: {
        label: 'm²',
        color: 'var(--chart-2)',
    },
} satisfies ChartConfig;

export function ConcessionairesRankingBar() {
    const [activeMetric, setActiveMetric] = React.useState<Metric>('contracts');
    const [order, setOrder] = React.useState<Order>('top');
    const limit = 10;

    const queryClient = useQueryClient();

    const { data, isLoading, isError, refetch } = useQuery<RankingResponse>({
        queryKey: ['dashboard', 'rankings', { metric: activeMetric, order, limit }],
        staleTime: 120_000,
        queryFn: async () => {
            const params = new URLSearchParams({ metric: activeMetric, order, limit: String(limit) });
            const res = await fetch(`/api/dashboard/rankings?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load rankings');
            return (await res.json()) as RankingResponse;
        },
    });

    const chartData = React.useMemo(() => {
        if (!data || !data.items || data.items.length === 0) return [];
        return data.items.map((item) => ({
            id: item.id,
            name: item.name,
            value: item.value,
        }));
    }, [data]);

    const total = React.useMemo(() => {
        if (!data) return 0;
        return data.items.reduce((acc, curr) => acc + curr.value, 0);
    }, [data]);

    const handleRefresh = React.useCallback(() => {
        queryClient.invalidateQueries({ queryKey: ['dashboard', 'rankings'] });
        refetch();
    }, [queryClient, refetch]);

    const toggleOrder = React.useCallback(() => {
        setOrder((prev) => (prev === 'top' ? 'bottom' : 'top'));
    }, []);

    const title =
        order === 'top'
            ? activeMetric === 'contracts'
                ? 'Mayor cantidad de contratos'
                : 'Mayor cantidad de m²'
            : activeMetric === 'contracts'
              ? 'Menor cantidad de contratos'
              : 'Menor cantidad de m²';
    const description = 'Cesionarios con valores vigentes';

    if (isLoading) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    <Skeleton className="h-[250px] w-full" />
                </CardContent>
            </Card>
        );
    }

    if (isError) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="text-error flex h-[250px] items-center justify-center text-sm">
                        No se pudo cargar el ranking.{' '}
                        <button className="underline" onClick={handleRefresh}>
                            Reintentar
                        </button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!data || chartData.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="text-muted-foreground flex h-[250px] items-center justify-center text-sm">Sin datos disponibles.</div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="py-0">
            <CardHeader className="flex flex-col items-stretch border-b !p-0 sm:flex-row">
                <div className="flex flex-1 flex-col justify-center gap-1 px-6 pt-5 pb-3 sm:py-6">
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <div className="flex">
                    {(['contracts', 'm2'] as const).map((key) => {
                        const metric = key as keyof typeof chartConfig;
                        return (
                            <button
                                key={metric}
                                data-active={activeMetric === metric}
                                className="data-[active=true]:bg-muted/50 relative z-30 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-4 text-left even:border-l sm:border-t-0 sm:border-l sm:px-8 sm:py-6"
                                onClick={() => setActiveMetric(metric)}
                            >
                                <span className="text-muted-foreground text-xs">{chartConfig[metric].label}</span>
                                <span className="text-lg leading-none font-bold sm:text-3xl">
                                    {activeMetric === metric
                                        ? activeMetric === 'm2'
                                            ? total.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                            : total.toLocaleString('es-VE')
                                        : '—'}
                                </span>
                            </button>
                        );
                    })}
                </div>
                <div className="flex items-center gap-2 border-t px-4 py-2 sm:border-t-0 sm:border-l">
                    <Button variant="ghost" size="icon" onClick={toggleOrder} title={order === 'top' ? 'Ver últimos' : 'Ver top'}>
                        <ArrowDownUp className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={handleRefresh} title="Refrescar datos">
                        <RefreshCw className="h-4 w-4" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                <ChartContainer config={chartConfig} className="h-[260px] w-full sm:h-[320px]">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            accessibilityLayer
                            data={chartData}
                            margin={{
                                left: 12,
                                right: 12,
                                top: 20,
                                bottom: 20,
                            }}
                        >
                            <CartesianGrid vertical={false} strokeDasharray="3 3" />
                            <XAxis dataKey="name" hide />
                            <YAxis hide />
                            <Tooltip
                                cursor={false}
                                content={({ active, payload }) => {
                                    if (!active || !payload?.length) return null;
                                    const data = payload[0];
                                    const value = data.value as number;
                                    const name = data.payload?.name as string;
                                    const suffix = activeMetric === 'm2' ? ' m²' : '';
                                    const formatted =
                                        activeMetric === 'm2'
                                            ? value.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                            : value.toLocaleString('es-VE');

                                    return (
                                        <div className="bg-background border-border rounded-lg border px-3 py-2 shadow-md">
                                            <p className="text-muted-foreground mb-1 text-xs font-medium">{name}</p>
                                            <div className="flex items-center gap-2">
                                                <div className="h-2 w-2 rounded-full" style={{ backgroundColor: chartConfig[activeMetric].color }} />
                                                <span className="text-foreground text-sm font-medium">{chartConfig[activeMetric].label}:</span>
                                                <span className="text-foreground text-sm font-bold">
                                                    {formatted}
                                                    {suffix}
                                                </span>
                                            </div>
                                        </div>
                                    );
                                }}
                            />
                            <Bar
                                dataKey="value"
                                fill={chartConfig[activeMetric].color}
                                radius={[4, 4, 0, 0]}
                                onClick={(data: { id?: number }) => {
                                    if (data?.id) {
                                        router.visit(`/catalogs/concessionaire/${data.id}`, {
                                            preserveScroll: false,
                                        });
                                    }
                                }}
                                style={{ cursor: 'pointer' }}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
