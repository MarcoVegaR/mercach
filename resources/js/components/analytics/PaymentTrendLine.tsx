import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useQuery } from '@tanstack/react-query';
import * as React from 'react';
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

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

type TrendGroup = 'month' | 'day';

const chartConfig = {
    amount: {
        label: 'Recaudación',
        color: 'var(--chart-revenue)',
    },
} satisfies ChartConfig;

export function PaymentTrendLine() {
    const [group, setGroup] = React.useState<TrendGroup>('month');
    const months = 12;
    const days = 30;

    const { data, isLoading, isError } = useQuery<PaymentTrendResponse>({
        queryKey: ['dashboard', 'payment-trend', group],
        staleTime: 180_000,
        queryFn: async () => {
            const params = new URLSearchParams();
            params.set('group', group);
            if (group === 'day') params.set('days', String(days));
            else params.set('months', String(months));

            const res = await fetch(`/api/dashboard/payment/trend?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load payment trend');
            return (await res.json()) as PaymentTrendResponse;
        },
    });

    const chartData = React.useMemo(() => {
        if (!data || !data.items || data.items.length === 0) return [];
        return data.items.map((item) => ({
            label: item.month_label,
            period: item.month,
            value: item.amount_bs_minor / 100, // Convert to major units
            count: item.count,
        }));
    }, [data]);

    const totalRevenue = React.useMemo(() => {
        if (!data) return 0;
        return data.items.reduce((acc, curr) => acc + curr.amount_bs_minor, 0) / 100;
    }, [data]);

    const title = group === 'day' ? 'Recaudación Diaria' : 'Recaudación Mensual';
    const description = group === 'day' ? `Últimos ${days} días` : `Últimos ${months} meses`;

    if (isLoading) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    <Skeleton className="h-[340px] w-full" />
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
                    <div className="text-destructive flex h-[340px] items-center justify-center text-sm">No se pudo cargar la gráfica.</div>
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
                    <div className="text-muted-foreground flex h-[340px] items-center justify-center text-sm">Sin datos de pagos disponibles.</div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="flex flex-col items-stretch border-b !p-0 sm:flex-row">
                <div className="flex flex-1 flex-col justify-center gap-2 px-6 pt-5 pb-3 sm:py-6">
                    <div className="flex items-center justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle>{title}</CardTitle>
                            <CardDescription>
                                {group === 'day' ? `Últimos ${data.items.length} días con pagos` : `Últimos ${data.items.length} meses con pagos`}
                            </CardDescription>
                        </div>
                        <ToggleGroup type="single" value={group} onValueChange={(v) => v && setGroup(v as TrendGroup)}>
                            <ToggleGroupItem value="month" className="px-2 py-1 text-xs">
                                Mes
                            </ToggleGroupItem>
                            <ToggleGroupItem value="day" className="px-2 py-1 text-xs">
                                Día
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
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
                <ChartContainer config={chartConfig} className="h-[360px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart
                            data={chartData}
                            margin={{
                                left: 12,
                                right: 12,
                                top: 20,
                                bottom: 20,
                            }}
                        >
                            <defs>
                                <linearGradient id="gradRevenue" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--color-amount)" stopOpacity={0.25} />
                                    <stop offset="95%" stopColor="var(--color-amount)" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} strokeOpacity={0.4} />
                            <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} tick={{ fontSize: 11 }} />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                tick={{ fontSize: 11 }}
                                tickFormatter={(value) => `Bs. ${value.toLocaleString('es-VE', { maximumFractionDigits: 0 })}`}
                                width={90}
                            />
                            <Tooltip
                                cursor={{ stroke: 'var(--color-amount)', strokeWidth: 1, strokeDasharray: '4 4' }}
                                content={({ active, payload }) => {
                                    if (!active || !payload?.length) return null;
                                    const d = payload[0];
                                    const val = d.value as number;
                                    const count = d.payload?.count as number;
                                    const label = d.payload?.label as string;
                                    const period = d.payload?.period as string;
                                    const fmt = val.toLocaleString('es-VE', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    });

                                    return (
                                        <div className="bg-background border-border rounded-lg border px-3 py-2 shadow-md">
                                            <p className="text-muted-foreground mb-1 text-xs font-medium">{label}</p>
                                            <p className="text-muted-foreground mb-2 text-[11px]">{period}</p>
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-foreground text-sm font-medium">Recaudado:</span>
                                                    <span className="text-foreground text-sm font-bold">Bs. {fmt}</span>
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
                            <Area
                                type="monotone"
                                dataKey="value"
                                stroke="var(--color-amount)"
                                fill="url(#gradRevenue)"
                                strokeWidth={2.5}
                                dot={{ r: 3, fill: 'var(--card)', strokeWidth: 2, stroke: 'var(--color-amount)' }}
                                activeDot={{ r: 5, strokeWidth: 2 }}
                                isAnimationActive={true}
                                animationDuration={800}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
