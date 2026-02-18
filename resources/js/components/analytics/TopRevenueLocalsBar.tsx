import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import * as React from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type Item = {
    local_id: number;
    code: string;
    name: string;
    total_eur_minor: number;
    m2_eur_minor: number;
    fixed_eur_minor: number;
    total_bs_minor: number;
    m2_bs_minor: number;
    fixed_bs_minor: number;
};

type ApiResponse = {
    period_start: string;
    period_label: string;
    total_bs_minor: number;
    items: Item[];
    fx_rate_ves_per_eur: number;
    fx_rate_date?: string | null;
    generated_at: string;
};

const chartConfig = {
    m2: { label: 'M2', color: 'var(--chart-1)' },
    fixed: { label: 'Renta fija', color: 'var(--chart-2)' },
} satisfies ChartConfig;

export default function TopRevenueLocalsBar({ limit = 10 }: { limit?: number }) {
    const l = Math.max(1, Math.min(100, limit));
    const queryClient = useQueryClient();

    const { data, isLoading, isError, refetch } = useQuery<ApiResponse>({
        queryKey: ['dashboard', 'revenue', 'top-locals', { limit: l }],
        staleTime: 180_000,
        queryFn: async () => {
            const params = new URLSearchParams({ limit: String(l) });
            const res = await fetch(`/api/dashboard/revenue/top-locals?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load top revenue locals');
            return (await res.json()) as ApiResponse;
        },
    });

    const chartData = React.useMemo(() => {
        if (!data)
            return [] as Array<{
                id: number;
                label: string;
                name: string;
                m2: number;
                fixed: number;
                total: number;
            }>;
        return data.items.map((it) => ({
            id: it.local_id,
            label: it.code,
            name: it.name,
            m2: it.m2_bs_minor / 100,
            fixed: it.fixed_bs_minor / 100,
            total: it.total_bs_minor / 100,
        }));
    }, [data]);

    const totalProjected = React.useMemo(() => {
        if (!data) return 0;
        return data.total_bs_minor / 100;
    }, [data]);

    const handleRefresh = React.useCallback(() => {
        queryClient.invalidateQueries({ queryKey: ['dashboard', 'revenue', 'top-locals'] });
        refetch();
    }, [queryClient, refetch]);

    const onBarClick = React.useCallback((d: unknown) => {
        if (d && typeof d === 'object' && 'id' in d) {
            const id = (d as { id?: number }).id;
            if (typeof id === 'number') router.visit(`/catalogs/local/${id}`);
        }
    }, []);

    const title = `Top ${l} Locales por aporte`;
    const description = 'Proyección mensual en Bs (M2 + fija)';

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
                    <CardDescription>
                        {description} • {data.period_label}
                    </CardDescription>
                </div>
                <div className="flex items-center border-t px-6 py-4 sm:border-t-0 sm:border-l">
                    <div className="flex flex-col gap-1">
                        <span className="text-muted-foreground text-xs">Suma top</span>
                        <span className="text-lg leading-none font-bold sm:text-3xl">
                            Bs. {totalProjected.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span>
                    </div>
                </div>
                <div className="flex items-center border-t px-4 py-2 sm:border-t-0 sm:border-l">
                    <button className="inline-flex h-8 w-8 items-center justify-center" onClick={handleRefresh} title="Refrescar datos">
                        <RefreshCw className="h-4 w-4" />
                    </button>
                </div>
            </CardHeader>
            <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                <ChartContainer config={chartConfig} className="h-[280px] w-full sm:h-[340px]">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart accessibilityLayer data={chartData} margin={{ left: 12, right: 12, top: 20, bottom: 20 }}>
                            <CartesianGrid vertical={false} strokeDasharray="3 3" />
                            <XAxis dataKey="label" hide />
                            <YAxis hide />
                            <Tooltip
                                cursor={false}
                                content={({ active, payload }) => {
                                    if (!active || !payload?.length) return null;
                                    const p0 = payload.find((p) => p.dataKey === 'm2');
                                    const p1 = payload.find((p) => p.dataKey === 'fixed');
                                    const row = (payload[0]?.payload ?? {}) as { label?: string; name?: string };
                                    const m2Val = (p0?.value as number) ?? 0;
                                    const fixedVal = (p1?.value as number) ?? 0;
                                    const total = m2Val + fixedVal;
                                    const format = (n: number) => n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    return (
                                        <div className="bg-background border-border rounded-lg border px-3 py-2 shadow-md">
                                            <p className="text-muted-foreground mb-1 text-xs font-medium">
                                                {row.label} — {row.name}
                                            </p>
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-foreground text-xs">M2:</span>
                                                    <span className="text-foreground text-sm font-bold">Bs. {format(m2Val)}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-foreground text-xs">Renta fija:</span>
                                                    <span className="text-foreground text-sm font-bold">Bs. {format(fixedVal)}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-muted-foreground text-xs">Total:</span>
                                                    <span className="text-foreground text-sm font-bold">Bs. {format(total)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                }}
                            />
                            <Bar
                                dataKey="m2"
                                stackId="a"
                                name="M2"
                                fill="var(--color-m2)"
                                radius={[4, 4, 0, 0]}
                                onClick={onBarClick}
                                style={{ cursor: 'pointer' }}
                            />
                            <Bar
                                dataKey="fixed"
                                stackId="a"
                                name="Renta fija"
                                fill="var(--color-fixed)"
                                radius={[4, 4, 0, 0]}
                                onClick={onBarClick}
                                style={{ cursor: 'pointer' }}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
