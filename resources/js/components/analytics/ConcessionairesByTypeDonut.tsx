import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { Label, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

type Item = { label: string; code: string; value: number };

type ApiResponse = {
    items: Item[];
    total: number;
    generated_at: string;
};

export default function ConcessionairesByTypeDonut() {
    const { data, isLoading, isError, refetch } = useQuery<ApiResponse>({
        queryKey: ['dashboard', 'concessionaires', 'by-type'],
        staleTime: 300_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/concessionaires/by-type', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load concessionaires by type');
            return (await res.json()) as ApiResponse;
        },
    });

    const { chartData, chartConfig } = useMemo(() => {
        if (!data)
            return {
                chartData: [] as Array<{ label: string; name: string; value: number; fill: string; _key: string }>,
                chartConfig: {} as ChartConfig,
            };
        const cfg: ChartConfig = {};
        const items = data.items.map((it, i) => {
            const tokenIndex = (i % 5) + 1;
            const key = `ct_${it.code}`;
            cfg[key] = { label: it.label, color: `var(--chart-${tokenIndex})` };
            return { label: it.label, name: it.label, value: it.value, fill: `var(--color-${key})`, _key: key };
        });
        return { chartData: items, chartConfig: cfg };
    }, [data]);

    return (
        <Card className="flex flex-col">
            <CardHeader className="items-center pb-0">
                <CardTitle>Cesionarios por tipo</CardTitle>
                <CardDescription>Natural vs Jurídica</CardDescription>
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
                                <Tooltip
                                    cursor={false}
                                    content={(props) => <ChartTooltipContent {...props} suffix="concesionarios" locale="es-VE" />}
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
                                                    <tspan x={cx} y={cy} className="fill-foreground text-3xl font-bold">
                                                        {(data.total ?? 0).toLocaleString('es-VE')}
                                                    </tspan>
                                                    <tspan x={cx} y={(cy || 0) + 24} className="fill-muted-foreground">
                                                        Cesionarios
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
            </CardContent>
        </Card>
    );
}
