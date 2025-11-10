import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import * as React from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type DebtItem = {
    id: number;
    name: string;
    debt_bs_minor: number;
    debt_eur_minor: number;
    max_days_overdue: number;
    avg_days_overdue: number;
    severity: 'critical' | 'high' | 'medium';
};

type DebtRankingResponse = {
    items: DebtItem[];
    generated_at: string;
};

const chartConfig = {
    debt: {
        label: 'Deuda',
    },
} satisfies ChartConfig;

const severityColors = {
    critical: 'hsl(0 84% 60%)', // Red
    high: 'hsl(25 95% 53%)', // Orange
    medium: 'hsl(48 96% 53%)', // Yellow
};

export function DebtRankingBar() {
    const limit = 10;
    const queryClient = useQueryClient();

    const { data, isLoading, isError, refetch } = useQuery<DebtRankingResponse>({
        queryKey: ['dashboard', 'debt-ranking', { limit }],
        staleTime: 120_000,
        queryFn: async () => {
            const params = new URLSearchParams({ limit: String(limit) });
            const res = await fetch(`/api/dashboard/debt/ranking?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load debt ranking');
            return (await res.json()) as DebtRankingResponse;
        },
    });

    const chartData = React.useMemo(() => {
        if (!data || !data.items || data.items.length === 0) return [];
        return data.items.map((item) => ({
            id: item.id,
            name: item.name,
            value: item.debt_eur_minor / 100, // EUR in major units
            debtBs: item.debt_bs_minor / 100, // Bs in major units
            days: item.max_days_overdue,
            fill: severityColors[item.severity],
        }));
    }, [data]);

    const totalDebtEur = React.useMemo(() => {
        if (!data) return 0;
        return data.items.reduce((acc, curr) => acc + curr.debt_eur_minor, 0) / 100;
    }, [data]);

    const totalDebtBs = React.useMemo(() => {
        if (!data) return 0;
        return data.items.reduce((acc, curr) => acc + curr.debt_bs_minor, 0) / 100;
    }, [data]);

    const handleRefresh = React.useCallback(() => {
        queryClient.invalidateQueries({ queryKey: ['dashboard', 'debt-ranking'] });
        refetch();
    }, [queryClient, refetch]);

    const title = 'Top 10 Morosos';
    const description = 'Concesionarios con mayor deuda vencida';

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
                    <div className="text-destructive flex h-[250px] items-center justify-center text-sm">
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
                <div className="flex items-center border-t px-6 py-4 sm:border-t-0 sm:border-l">
                    <div className="flex flex-col gap-1">
                        <span className="text-muted-foreground text-xs">Deuda Total</span>
                        <span className="text-lg leading-none font-bold sm:text-3xl">
                            € {totalDebtEur.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span>
                        <span className="text-muted-foreground text-[10px]">
                            Bs. {totalDebtBs.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span>
                    </div>
                </div>
                <div className="flex items-center border-t px-4 py-2 sm:border-t-0 sm:border-l">
                    <Button variant="ghost" size="icon" onClick={handleRefresh} title="Refrescar datos">
                        <RefreshCw className="h-4 w-4" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                <ChartContainer config={chartConfig} className="h-[280px] w-full sm:h-[340px]">
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
                                    const valueEur = data.value as number;
                                    const valueBs = data.payload?.debtBs as number;
                                    const name = data.payload?.name as string;
                                    const days = data.payload?.days as number;
                                    const formattedEur = valueEur.toLocaleString('es-VE', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    });
                                    const formattedBs = valueBs.toLocaleString('es-VE', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    });

                                    return (
                                        <div className="bg-background border-border rounded-lg border px-3 py-2 shadow-md">
                                            <p className="text-muted-foreground mb-1 text-xs font-medium">{name}</p>
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-foreground text-sm font-medium">Deuda:</span>
                                                    <span className="text-foreground text-sm font-bold">€ {formattedEur}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-muted-foreground text-xs">Bs. {formattedBs}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-foreground text-sm font-medium">Atraso:</span>
                                                    <span className="text-destructive text-sm font-bold">{days} días</span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                }}
                            />
                            <Bar
                                dataKey="value"
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
                <div className="mt-4 flex items-center justify-center gap-4 text-xs">
                    <div className="flex items-center gap-1.5">
                        <div className="h-3 w-3 rounded" style={{ backgroundColor: severityColors.critical }} />
                        <span className="text-muted-foreground">&gt;90 días</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <div className="h-3 w-3 rounded" style={{ backgroundColor: severityColors.high }} />
                        <span className="text-muted-foreground">30-90 días</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <div className="h-3 w-3 rounded" style={{ backgroundColor: severityColors.medium }} />
                        <span className="text-muted-foreground">&lt;30 días</span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
