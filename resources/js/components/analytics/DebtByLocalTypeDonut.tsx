import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { Label, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

type Item = { local_type_id: number; local_type_name: string; debt_eur_minor: number; debt_bs_minor: number; locals_count: number };

type ApiResponse = {
    by_local_type: Item[];
    fx_rate: number;
    generated_at: string;
};

export default function DebtByLocalTypeDonut() {
    const { data, isLoading, isError, refetch } = useQuery<ApiResponse>({
        queryKey: ['debt-analysis', 'distributions'],
        staleTime: 300_000,
        queryFn: async () => {
            const res = await fetch('/api/debt-analysis/distributions', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load debt distributions');
            return (await res.json()) as ApiResponse;
        },
    });

    const { chartData, chartConfig, totalEur } = useMemo(() => {
        if (!data)
            return {
                chartData: [] as Array<{ id: number; label: string; name: string; value: number; fill: string; _key: string }>,
                chartConfig: {} as ChartConfig,
                totalEur: 0,
            };
        const cfg: ChartConfig = {};
        const items = (data.by_local_type ?? []).map((it, i) => {
            const tokenIndex = (i % 5) + 1;
            const key = `lt_debt_${it.local_type_id}`;
            cfg[key] = { label: it.local_type_name, color: `var(--chart-${tokenIndex})` };
            return {
                id: it.local_type_id,
                label: it.local_type_name,
                name: it.local_type_name,
                value: (it.debt_eur_minor ?? 0) / 100,
                fill: `var(--color-${key})`,
                _key: key,
            };
        });
        const total = items.reduce((acc, cur) => acc + (cur.value || 0), 0);
        return { chartData: items, chartConfig: cfg, totalEur: total };
    }, [data]);

    return (
        <Card className="flex flex-col">
            <CardHeader className="items-center pb-0">
                <CardTitle>Deuda por tipo de local</CardTitle>
                <CardDescription>Distribución de la deuda vencida</CardDescription>
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
                                <Tooltip cursor={false} content={(props) => <ChartTooltipContent {...props} suffix="€" locale="es-VE" />} />
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
                                                        € {totalEur.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                    </tspan>
                                                    <tspan x={cx} y={(cy || 0) + 24} className="fill-muted-foreground text-xs">
                                                        Deuda total
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
                {data && (data.by_local_type?.length ?? 0) === 0 && <div className="text-muted-foreground text-sm">Sin datos de deuda vencida.</div>}
            </CardContent>
        </Card>
    );
}
