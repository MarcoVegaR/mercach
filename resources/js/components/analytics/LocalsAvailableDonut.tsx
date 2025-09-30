import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { Label, Pie, PieChart, ResponsiveContainer } from 'recharts';

type Item = { label: string; value: number; type_id: number };

type ApiResponse = {
    items: Item[];
    total: number;
    generated_at: string;
};

export default function LocalsAvailableDonut() {
    const { data, isLoading, isError, refetch } = useQuery<ApiResponse>({
        queryKey: ['dashboard', 'locals', 'by-type'],
        staleTime: 300_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/locals/by-type', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load distribution');
            return (await res.json()) as ApiResponse;
        },
    });

    const { chartData, chartConfig } = useMemo(() => {
        if (!data)
            return {
                chartData: [] as Array<{ id: number; label: string; name: string; value: number; fill: string; _key: string }>,
                chartConfig: {} as ChartConfig,
            };
        const cfg: ChartConfig = {};
        const items = data.items.map((it, i) => {
            const tokenIndex = (i % 5) + 1; // cycle --chart-1..5
            const key = `lt${it.type_id}`;
            cfg[key] = { label: it.label, color: `var(--chart-${tokenIndex})` };
            return {
                id: it.type_id,
                label: it.label,
                name: it.label, // ensures Recharts tooltip sees a name
                value: it.value,
                // Fill references CSS var exposed by ChartContainer via config
                fill: `var(--color-${key})`,
                _key: key,
            };
        });
        return { chartData: items, chartConfig: cfg };
    }, [data]);

    return (
        <Card className="flex flex-col" aria-label={`Locales por tipo (total), total ${data?.total ?? 0}`}>
            <CardHeader className="items-center pb-0">
                <CardTitle>Locales por tipo</CardTitle>
                <CardDescription>Total de locales</CardDescription>
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
                                <ChartTooltip cursor={false} content={<ChartTooltipContent suffix="locales" locale="es-VE" />} />
                                <Pie
                                    data={chartData}
                                    dataKey="value"
                                    nameKey="label"
                                    innerRadius={60}
                                    strokeWidth={5}
                                    onClick={(slice) => {
                                        const id = (slice?.payload as { id?: number })?.id;
                                        if (!id) return;
                                        router.visit(route('catalogs.local.index'), {
                                            method: 'get',
                                            data: {
                                                filters: { local_type_id: id },
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
                                                        {(data.total ?? 0).toLocaleString('es-VE')}
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
                {/* Legend removido para que los tipos aparezcan sólo en el tooltip */}
                {data && data.items.length === 0 && <div className="text-muted-foreground text-sm">Sin datos disponibles.</div>}
            </CardContent>
        </Card>
    );
}
