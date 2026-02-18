import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { Label, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

type Item = {
    local_type_id: number;
    local_type_name: string;
    amount_eur_minor: number;
    amount_bs_minor: number;
    locals_count: number;
};

type ApiResponse = {
    period_start: string;
    period_label: string;
    total_eur_minor: number;
    total_bs_minor: number;
    by_local_type: Item[];
    fx_rate_ves_per_eur: number;
    fx_rate_date?: string | null;
    generated_at: string;
};

export default function ProjectedRevenueByLocalTypeDonut() {
    const { data, isLoading, isError, refetch } = useQuery<ApiResponse>({
        queryKey: ['revenue', 'projection', 'by_local_type'],
        staleTime: 180_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/revenue/projection', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load projection');
            return (await res.json()) as ApiResponse;
        },
    });

    const { chartData, chartConfig, totalBs } = useMemo(() => {
        if (!data)
            return {
                chartData: [] as Array<{ id: number; label: string; name: string; value: number; fill: string; _key: string }>,
                chartConfig: {} as ChartConfig,
                totalBs: 0,
            };
        const cfg: ChartConfig = {};
        const items = (data.by_local_type ?? []).map((it, i) => {
            const tokenIndex = (i % 5) + 1;
            const key = `lt_proj_${it.local_type_id}`;
            cfg[key] = { label: it.local_type_name, color: `var(--chart-${tokenIndex})` };
            return {
                id: it.local_type_id,
                label: it.local_type_name,
                name: it.local_type_name,
                value: (it.amount_bs_minor ?? 0) / 100,
                fill: `var(--color-${key})`,
                _key: key,
            };
        });
        const total = items.reduce((acc, cur) => acc + (cur.value || 0), 0);
        return { chartData: items, chartConfig: cfg, totalBs: total };
    }, [data]);

    return (
        <Card className="flex flex-col">
            <CardHeader className="items-center pb-0">
                <CardTitle>Proyección por tipo de local</CardTitle>
                <CardDescription>
                    Ingresos mensuales estimados en Bs ({data?.period_label})
                    {data
                        ? ` · € ${(data.total_eur_minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                        : ''}
                </CardDescription>
            </CardHeader>
            <CardContent className="min-h-[340px] flex-1 pb-0">
                {isLoading && <Skeleton className="mx-auto h-[250px] w-[250px] rounded-full" />}
                {isError && (
                    <div className="text-error text-sm">
                        No se pudo cargar el gráfico.{' '}
                        <button className="underline" onClick={() => refetch()}>
                            Reintentar
                        </button>
                    </div>
                )}
                {data && (
                    <ChartContainer config={chartConfig} className="mx-auto aspect-square max-h-[250px]">
                        <ResponsiveContainer>
                            <PieChart>
                                <Tooltip cursor={false} content={(props) => <ChartTooltipContent {...props} suffix="Bs." locale="es-VE" />} />
                                <Pie data={chartData} dataKey="value" nameKey="label" innerRadius={60} strokeWidth={5}>
                                    <Label
                                        // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                        content={(props: any) => {
                                            const cx = props?.viewBox?.cx as number | undefined;
                                            const cy = props?.viewBox?.cy as number | undefined;
                                            if (typeof cx !== 'number' || typeof cy !== 'number') return null;
                                            return (
                                                <text x={cx} y={cy} textAnchor="middle" dominantBaseline="middle">
                                                    <tspan x={cx} y={cy} className="fill-foreground text-center text-2xl font-bold">
                                                        Bs. {totalBs.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                    </tspan>
                                                    <tspan x={cx} y={(cy || 0) + 24} className="fill-muted-foreground text-xs">
                                                        Base comparativa
                                                    </tspan>
                                                </text>
                                            );
                                        }}
                                    />
                                </Pie>
                            </PieChart>
                        </ResponsiveContainer>
                    </ChartContainer>
                )}
                {data && (data.by_local_type?.length ?? 0) === 0 && <div className="text-muted-foreground text-sm">Sin datos de proyección.</div>}
            </CardContent>
        </Card>
    );
}
