import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type Item = { month: string; month_label: string; count: number };

type ApiResponse = {
    items: Item[];
    generated_at: string;
};

export default function OpenChargesByMonthBar({ months = 12 }: { months?: number }) {
    const { data, isLoading, isError, refetch } = useQuery<ApiResponse>({
        queryKey: ['dashboard', 'charges', 'open-by-month', months],
        staleTime: 180_000,
        queryFn: async () => {
            const res = await fetch(`/api/dashboard/charges/open-by-month?months=${months}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load open charges by month');
            return (await res.json()) as ApiResponse;
        },
    });

    const { chartData, chartConfig } = useMemo(() => {
        if (!data)
            return {
                chartData: [] as Array<{ label: string; value: number; fill: string; _key: string }>,
                chartConfig: {} as ChartConfig,
            };

        const cfg: ChartConfig = { count: { label: 'Cargos abiertos', color: 'var(--chart-4)' } };
        const items = data.items.map((it) => ({ label: it.month_label, value: it.count, fill: 'var(--color-count)', _key: 'count' }));
        return { chartData: items, chartConfig: cfg };
    }, [data]);

    return (
        <Card className="flex flex-col">
            <CardHeader className="pb-0">
                <CardTitle>Cargos abiertos por mes</CardTitle>
                <CardDescription>Emitidos o parciales (últimos {months} meses)</CardDescription>
            </CardHeader>
            <CardContent className="min-h-[300px]">
                {isLoading && <Skeleton className="h-[220px] w-full" />}
                {isError && (
                    <div className="text-error text-sm">
                        No se pudo cargar el gráfico.{' '}
                        <button className="underline" onClick={() => refetch()}>
                            Reintentar
                        </button>
                    </div>
                )}
                {data && (
                    <ChartContainer config={chartConfig} className="w-full">
                        <ResponsiveContainer width="100%" height={240}>
                            <BarChart data={chartData} margin={{ left: 8, right: 8 }}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="label" tick={{ fontSize: 12 }} interval={0} angle={-20} textAnchor="end" height={60} />
                                <YAxis allowDecimals={false} />
                                <Tooltip
                                    cursor={{ fill: 'rgba(0,0,0,0.03)' }}
                                    content={(props) => <ChartTooltipContent {...props} suffix="cargos" locale="es-VE" />}
                                />
                                <Bar dataKey="value" name="Cargos" fill="var(--color-count)" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
