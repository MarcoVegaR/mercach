import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowDownUp, RefreshCw } from 'lucide-react';
import * as React from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type TimelineItem = {
    id: number;
    code: string;
    start_date: string;
    end_date: string | null;
    duration_total_days: number;
    elapsed_days: number;
    remaining_days: number | null;
    concessionaire_names: string;
};

type TimelineResponse = {
    sort_by: 'start_date' | 'end_date';
    order: 'asc' | 'desc';
    items: TimelineItem[];
    generated_at: string;
};

type SortBy = 'start_date' | 'end_date';
type Order = 'asc' | 'desc';

const chartConfig = {
    start_date: {
        label: 'Fecha Inicio',
        color: 'var(--chart-3)',
    },
    end_date: {
        label: 'Fecha Fin',
        color: 'var(--chart-4)',
    },
} satisfies ChartConfig;

function formatDate(dateStr: string | null): string {
    if (!dateStr) return 'Indefinido';
    const [year, month, day] = dateStr.split('-');
    return `${day}/${month}/${year}`;
}

export function ContractsTimelineBar() {
    const [activeSortBy, setActiveSortBy] = React.useState<SortBy>('start_date');
    const [order, setOrder] = React.useState<Order>('asc');
    const limit = 20;

    const queryClient = useQueryClient();

    const { data, isLoading, isError, refetch } = useQuery<TimelineResponse>({
        queryKey: ['dashboard', 'contracts-timeline', { sort_by: activeSortBy, order, limit }],
        staleTime: 120_000,
        queryFn: async () => {
            const params = new URLSearchParams({ sort_by: activeSortBy, order, limit: String(limit) });
            const res = await fetch(`/api/dashboard/contracts/timeline?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load timeline');
            return (await res.json()) as TimelineResponse;
        },
    });

    const chartData = React.useMemo(() => {
        if (!data || !data.items || data.items.length === 0) return [] as Array<TimelineItem & { value: number }>;
        return data.items.map((item) => ({
            ...item,
            // Represent bar length as duration in days (for indefinidos: hoy - inicio)
            value: Math.max(0, item.duration_total_days ?? 0),
        }));
    }, [data]);

    const total = React.useMemo(() => {
        if (!data) return 0;
        return data.items.length;
    }, [data]);

    const handleRefresh = React.useCallback(() => {
        queryClient.invalidateQueries({ queryKey: ['dashboard', 'contracts-timeline'] });
        refetch();
    }, [queryClient, refetch]);

    const toggleOrder = React.useCallback(() => {
        setOrder((prev) => (prev === 'asc' ? 'desc' : 'asc'));
    }, []);

    const title = 'Línea de Tiempo de Contratos Vigentes';
    const description = `Ordenado por ${chartConfig[activeSortBy].label} (${order === 'asc' ? 'más viejo → más nuevo' : 'más nuevo → más viejo'})`;

    if (isLoading) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    <Skeleton className="h-[350px] w-full" />
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
                    <div className="text-error flex h-[350px] items-center justify-center text-sm">
                        No se pudo cargar la línea de tiempo.{' '}
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
                    <div className="text-muted-foreground flex h-[350px] items-center justify-center text-sm">Sin contratos vigentes.</div>
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
                    {(['start_date', 'end_date'] as const).map((key) => {
                        const sortByField = key as keyof typeof chartConfig;
                        return (
                            <button
                                key={sortByField}
                                data-active={activeSortBy === sortByField}
                                className="data-[active=true]:bg-muted/50 relative z-30 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-4 text-left even:border-l sm:border-t-0 sm:border-l sm:px-8 sm:py-6"
                                onClick={() => setActiveSortBy(sortByField)}
                            >
                                <span className="text-muted-foreground text-xs">{chartConfig[sortByField].label}</span>
                                <span className="text-lg leading-none font-bold sm:text-3xl">
                                    {activeSortBy === sortByField ? total.toLocaleString('es-VE') : '—'}
                                </span>
                            </button>
                        );
                    })}
                </div>
                <div className="flex items-center gap-2 border-t px-4 py-2 sm:border-t-0 sm:border-l">
                    <Button variant="ghost" size="icon" onClick={toggleOrder} title={order === 'asc' ? 'Invertir orden' : 'Ver ascendente'}>
                        <ArrowDownUp className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={handleRefresh} title="Refrescar datos">
                        <RefreshCw className="h-4 w-4" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                <ChartContainer config={chartConfig} className="h-[380px] w-full">
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
                            layout="vertical"
                        >
                            <CartesianGrid horizontal={false} strokeDasharray="3 3" />
                            <XAxis type="number" hide />
                            <YAxis
                                dataKey="code"
                                type="category"
                                tickLine={false}
                                axisLine={false}
                                width={80}
                                tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            />
                            <Tooltip
                                cursor={false}
                                content={({ active, payload }) => {
                                    if (!active || !payload?.length) return null;
                                    const dataPoint = payload[0];
                                    const item = dataPoint.payload as (typeof chartData)[0];

                                    return (
                                        <div className="bg-background border-border rounded-lg border px-3 py-2 shadow-md">
                                            <p className="text-muted-foreground mb-1 text-xs font-medium">{item.code}</p>
                                            <div className="space-y-1 text-xs">
                                                <p>
                                                    <span className="text-foreground font-medium">Inicio:</span> {formatDate(item.start_date)}
                                                </p>
                                                <p>
                                                    <span className="text-foreground font-medium">Fin:</span> {formatDate(item.end_date)}
                                                </p>
                                                <p>
                                                    <span className="text-foreground font-medium">Duración:</span>{' '}
                                                    {item.duration_total_days.toLocaleString('es-VE')} días
                                                </p>
                                                <p>
                                                    <span className="text-foreground font-medium">Transcurridos:</span>{' '}
                                                    {item.elapsed_days.toLocaleString('es-VE')} días
                                                </p>
                                                {item.remaining_days !== null && (
                                                    <p>
                                                        <span className="text-foreground font-medium">Restantes:</span>{' '}
                                                        {item.remaining_days.toLocaleString('es-VE')} días
                                                    </p>
                                                )}
                                                <p className="text-muted-foreground mt-1 text-[10px]">{item.concessionaire_names}</p>
                                            </div>
                                        </div>
                                    );
                                }}
                            />
                            <Bar
                                dataKey="value"
                                fill={chartConfig[activeSortBy].color}
                                radius={[0, 4, 4, 0]}
                                onClick={(data: { id?: number }) => {
                                    if (data?.id) {
                                        router.visit(`/catalogs/contract/${data.id}`, {
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
