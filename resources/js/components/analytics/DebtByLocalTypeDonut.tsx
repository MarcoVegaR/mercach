import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { Label, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

type Item = {
    local_type_id: number;
    local_type_name: string;
    debt_eur_minor: number;
    debt_usd_minor?: number;
    debt_bs_minor: number;
    locals_count: number;
};

type ApiResponse = {
    by_local_type: Item[];
    by_local_type_bs?: Array<
        Item & {
            debt_bs_minor_eur?: number;
            debt_bs_minor_usd?: number;
        }
    >;
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

    const { chartData, chartConfig, totalBs, rows } = useMemo(() => {
        if (!data)
            return {
                chartData: [] as Array<{ id: number; label: string; name: string; value: number; fill: string; _key: string }>,
                chartConfig: {} as ChartConfig,
                totalBs: 0,
                rows: [] as Array<Item>,
            };

        const source = (data.by_local_type_bs ?? data.by_local_type ?? []) as Array<Item>;
        const cfg: ChartConfig = {};
        const items = source.map((it, i) => {
            const tokenIndex = (i % 5) + 1;
            const key = `lt_debt_${it.local_type_id}`;
            cfg[key] = { label: it.local_type_name, color: `var(--chart-${tokenIndex})` };
            return {
                id: it.local_type_id,
                label: it.local_type_name,
                name: it.local_type_name,
                value: (it.debt_bs_minor ?? 0) / 100,
                fill: `var(--color-${key})`,
                _key: key,
            };
        });
        const total = items.reduce((acc, cur) => acc + (cur.value || 0), 0);
        return { chartData: items, chartConfig: cfg, totalBs: total, rows: source };
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
                    <>
                        <ChartContainer config={chartConfig} className="mx-auto aspect-square max-h-[300px]">
                            <ResponsiveContainer>
                                <PieChart>
                                    <Tooltip
                                        cursor={false}
                                        content={({ active, payload }) => {
                                            if (!active || !payload?.length) return null;
                                            const p = payload[0];
                                            const name = (p.payload?.name as string) ?? '';
                                            const valueBs = (p.value as number) ?? 0;

                                            const row = rows.find((r) => r.local_type_name === name);
                                            const eur = (row?.debt_eur_minor ?? 0) / 100;
                                            const usd = (row?.debt_usd_minor ?? 0) / 100;

                                            return (
                                                <div className="bg-background border-border rounded-lg border px-3 py-2 shadow-md">
                                                    <p className="text-muted-foreground mb-1 text-xs font-medium">{name}</p>
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-foreground text-sm font-medium">Bs:</span>
                                                            <span className="text-foreground text-sm font-bold">
                                                                {valueBs.toLocaleString('es-VE', {
                                                                    minimumFractionDigits: 2,
                                                                    maximumFractionDigits: 2,
                                                                })}
                                                            </span>
                                                        </div>
                                                        {eur > 0 && (
                                                            <div className="text-muted-foreground text-xs">
                                                                €{' '}
                                                                {eur.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                            </div>
                                                        )}
                                                        {usd > 0 && (
                                                            <div className="text-muted-foreground text-xs">
                                                                ${' '}
                                                                {usd.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        }}
                                    />
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
                                                            Bs.{' '}
                                                            {totalBs.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
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
                        <ChartLegend items={chartData.map((d) => ({ label: d.label, color: d.fill }))} />
                    </>
                )}
                {data && (data.by_local_type?.length ?? 0) === 0 && <div className="text-muted-foreground text-sm">Sin datos de deuda vencida.</div>}
            </CardContent>
        </Card>
    );
}
