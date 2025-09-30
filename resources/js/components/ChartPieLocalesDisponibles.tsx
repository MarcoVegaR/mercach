import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegend, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { Label, Pie, PieChart, ResponsiveContainer } from 'recharts';

export type DistItem = { label: string; id: number; value: number };
export type DistResponse = {
    by: 'local_type';
    items: DistItem[];
    total: number;
    status_disp_id?: number;
    generated_at: string;
};

export function ChartPieLocalesDisponibles() {
    const { data, isLoading, isError, refetch } = useQuery<DistResponse>({
        queryKey: ['dashboard', 'distributions', 'local_type', 'available'],
        staleTime: 180_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/distributions?by=local_type&scope=available', {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load distribution');
            return (await res.json()) as DistResponse;
        },
    });

    const { chartData, chartConfig } = useMemo(() => {
        if (!data)
            return {
                chartData: [] as Array<{ id: number; label: string; value: number; fill: string; _key: string }>,
                chartConfig: {} as ChartConfig,
            };
        const cfg: ChartConfig = {};
        const items = data.items.map((it, i) => {
            const tokenIndex = (i % 5) + 1; // cycle --chart-1..5
            const key = `lt${it.id}`;
            cfg[key] = { label: it.label, color: `var(--chart-${tokenIndex})` };
            return {
                id: it.id,
                label: it.label,
                value: it.value,
                // Fill references CSS var exposed by ChartContainer via config
                fill: `var(--color-${key})`,
                _key: key,
            };
        });
        return { chartData: items, chartConfig: cfg };
    }, [data]);

    return (
        <Card aria-label={`Locales disponibles por tipo, total ${data?.total ?? 0}`}>
            <CardHeader className="items-center pb-0">
                <CardTitle>Locales disponibles por tipo</CardTitle>
                <CardDescription>Disponibles hoy</CardDescription>
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
                                <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel />} />
                                <Pie
                                    data={chartData}
                                    dataKey="value"
                                    nameKey="label"
                                    innerRadius={60}
                                    strokeWidth={5}
                                    onClick={(slice) => {
                                        const id = (slice?.payload as { id?: number })?.id;
                                        if (!id) return;
                                        const localStatusId = data.status_disp_id ?? undefined;
                                        router.visit(route('catalogs.local.index'), {
                                            method: 'get',
                                            data: {
                                                filters: {
                                                    ...(localStatusId ? { local_status_id: localStatusId } : {}),
                                                    local_type_id: id,
                                                },
                                                page: 1,
                                                per_page: 15,
                                            },
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <Label
                                        // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                        content={(props: any) => {
                                            const cx = props?.viewBox?.cx as number | undefined;
                                            const cy = props?.viewBox?.cy as number | undefined;
                                            if (typeof cx !== 'number' || typeof cy !== 'number') return null;
                                            return (
                                                <text x={cx} y={cy} textAnchor="middle" dominantBaseline="middle">
                                                    <tspan x={cx} y={cy} className="fill-foreground text-3xl font-bold">
                                                        {data.total.toLocaleString()}
                                                    </tspan>
                                                    <tspan x={cx} y={(cy || 0) + 24} className="fill-muted-foreground">
                                                        Locales
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
                {data && data.items.length === 0 && <div className="text-muted-foreground text-sm">Sin datos disponibles.</div>}
                {data && data.items.length > 0 && (
                    <div className="mt-4">
                        <ChartLegend items={chartData.map((d) => ({ label: d.label, color: `var(--color-${d._key})` }))} />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
