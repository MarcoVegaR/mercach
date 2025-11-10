import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useQuery } from '@tanstack/react-query';
import * as React from 'react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type PaymentTrendItem = {
    month: string;
    month_label: string;
    count: number;
    amount_bs_minor: number;
};

type PaymentTrendResponse = {
    items: PaymentTrendItem[];
    generated_at: string;
};

const chartConfig = {
    amount: {
        label: 'Recaudación',
        color: 'hsl(var(--chart-1))',
    },
} satisfies ChartConfig;

export function PaymentTrendLine() {
    const { data, isLoading, isError } = useQuery<PaymentTrendResponse>({
        queryKey: ['dashboard', 'payment-trend'],
        staleTime: 180_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/payment/trend?months=12', {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load payment trend');
            return (await res.json()) as PaymentTrendResponse;
        },
    });

    const chartData = React.useMemo(() => {
        if (!data || !data.items || data.items.length === 0) return [];
        return data.items.map((item) => ({
            month: item.month_label,
            value: item.amount_bs_minor / 100, // Convert to major units
            count: item.count,
        }));
    }, [data]);

    const totalRevenue = React.useMemo(() => {
        if (!data) return 0;
        return data.items.reduce((acc, curr) => acc + curr.amount_bs_minor, 0) / 100;
    }, [data]);

    if (isLoading) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Recaudación Mensual</CardTitle>
                    <CardDescription>Últimos 12 meses</CardDescription>
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
                    <CardTitle>Recaudación Mensual</CardTitle>
                    <CardDescription>Últimos 12 meses</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="text-destructive flex h-[250px] items-center justify-center text-sm">No se pudo cargar la gráfica.</div>
                </CardContent>
            </Card>
        );
    }

    if (!data || chartData.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Recaudación Mensual</CardTitle>
                    <CardDescription>Últimos 12 meses</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="text-muted-foreground flex h-[250px] items-center justify-center text-sm">Sin datos de pagos disponibles.</div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="flex flex-col items-stretch border-b !p-0 sm:flex-row">
                <div className="flex flex-1 flex-col justify-center gap-1 px-6 pt-5 pb-3 sm:py-6">
                    <CardTitle>Recaudación Mensual</CardTitle>
                    <CardDescription>Últimos {data.items.length} meses con pagos</CardDescription>
                </div>
                <div className="flex items-center border-t px-6 py-4 sm:border-t-0 sm:border-l">
                    <div className="flex flex-col gap-1">
                        <span className="text-muted-foreground text-xs">Total Período</span>
                        <span className="text-lg leading-none font-bold sm:text-3xl">
                            Bs. {totalRevenue.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                <ChartContainer config={chartConfig} className="h-[280px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart
                            data={chartData}
                            margin={{
                                left: 12,
                                right: 12,
                                top: 20,
                                bottom: 20,
                            }}
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} tick={{ fontSize: 12 }} />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                tick={{ fontSize: 12 }}
                                tickFormatter={(value) => `Bs. ${value.toLocaleString('es-VE', { maximumFractionDigits: 0 })}`}
                            />
                            <Tooltip
                                cursor={false}
                                content={({ active, payload }) => {
                                    if (!active || !payload?.length) return null;
                                    const data = payload[0];
                                    const value = data.value as number;
                                    const count = data.payload?.count as number;
                                    const month = data.payload?.month as string;
                                    const formatted = value.toLocaleString('es-VE', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    });

                                    return (
                                        <div className="bg-background border-border rounded-lg border px-3 py-2 shadow-md">
                                            <p className="text-muted-foreground mb-1 text-xs font-medium">{month}</p>
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-foreground text-sm font-medium">Recaudado:</span>
                                                    <span className="text-foreground text-sm font-bold">Bs. {formatted}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-muted-foreground text-xs">
                                                        {count} pago{count !== 1 ? 's' : ''}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                }}
                            />
                            <Line type="monotone" dataKey="value" stroke="var(--color-amount)" strokeWidth={2} dot={{ r: 4 }} activeDot={{ r: 6 }} />
                        </LineChart>
                    </ResponsiveContainer>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
