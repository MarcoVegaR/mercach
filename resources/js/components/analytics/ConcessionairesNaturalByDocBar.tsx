import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useQuery } from '@tanstack/react-query';
import { useMemo } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type Item = { label: string; code: string; value: number };

type ApiResponse = {
    items: Item[];
    total: number;
    generated_at: string;
};

export default function ConcessionairesNaturalByDocBar() {
    const { data, isLoading, isError, refetch } = useQuery<ApiResponse>({
        queryKey: ['dashboard', 'concessionaires', 'natural-by-document'],
        staleTime: 300_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/concessionaires/natural-by-document', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load PNAT by document');
            return (await res.json()) as ApiResponse;
        },
    });

    const { chartData, chartConfig } = useMemo(() => {
        if (!data)
            return {
                chartData: [] as Array<{ label: string; value: number; fill: string; _key: string }>,
                chartConfig: {} as ChartConfig,
            };
        const cfg: ChartConfig = { V: { label: 'Venezolano', color: 'var(--chart-2)' }, E: { label: 'Extranjero', color: 'var(--chart-3)' } };
        const items = data.items
            .filter((it) => it.code === 'V' || it.code === 'E')
            .map((it) => ({ label: it.label, value: it.value, fill: `var(--color-${it.code})`, _key: it.code }));
        return { chartData: items, chartConfig: cfg };
    }, [data]);

    return (
        <Card className="flex flex-col">
            <CardHeader className="pb-0">
                <CardTitle>Personas naturales por documento</CardTitle>
                <CardDescription>Comparativa V vs E</CardDescription>
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
                                <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                                <YAxis allowDecimals={false} />
                                <Tooltip
                                    cursor={{ fill: 'rgba(0,0,0,0.03)' }}
                                    content={(props) => <ChartTooltipContent {...props} suffix="cesionarios" locale="es-VE" />}
                                />
                                <Bar dataKey="value" name="Cesionarios" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
