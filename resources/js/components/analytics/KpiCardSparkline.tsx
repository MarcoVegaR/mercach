import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { TrendingDown, TrendingUp } from 'lucide-react';
import * as React from 'react';
import { Area, AreaChart, ResponsiveContainer, Tooltip } from 'recharts';

export type SparkPoint = { x: number | string; y: number };

export type KpiCardSparklineProps = {
    title: string;
    description?: string;
    value?: number | string;
    isLoading?: boolean;
    href?: string;
    deltaLabel?: string;
    deltaVariant?: 'up' | 'down' | 'neutral';
    className?: string;
    series?: SparkPoint[]; // Optional series; render sparkline only if provided
};

export function KpiCardSparkline({
    title,
    description,
    value,
    isLoading,
    href,
    deltaLabel,
    deltaVariant = 'neutral',
    className,
    series,
}: KpiCardSparklineProps) {
    const isLink = Boolean(href);
    const numberFormatter = React.useMemo(() => new Intl.NumberFormat(undefined), []);
    const formatted = React.useMemo(() => {
        if (value === undefined || value === null) return '';
        return typeof value === 'number' ? numberFormatter.format(value) : String(value);
    }, [value, numberFormatter]);

    const ariaLabel = `${title}${formatted ? `, ${formatted}` : ''}`;

    const onActivate = React.useCallback(() => {
        if (href) router.visit(href);
    }, [href]);

    const onKeyDown = React.useCallback(
        (e: React.KeyboardEvent) => {
            if (!href) return;
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onActivate();
            }
        },
        [href, onActivate],
    );

    const DeltaIcon = deltaVariant === 'up' ? TrendingUp : deltaVariant === 'down' ? TrendingDown : undefined;
    const deltaBadgeVariant = deltaVariant === 'up' ? 'success' : deltaVariant === 'down' ? 'destructive' : 'outline';

    // Chart config exposes a single color token for the sparkline
    const chartConfig: ChartConfig = React.useMemo(() => ({ spark: { color: 'var(--chart-1)' } }), []);

    return (
        <Card
            className={cn('hover:ring-border/60 transition-colors hover:ring-1', isLink && 'cursor-pointer', className)}
            role={isLink ? 'link' : undefined}
            aria-label={ariaLabel}
            tabIndex={isLink ? 0 : undefined}
            onKeyDown={onKeyDown}
            onClick={isLink ? onActivate : undefined}
        >
            <CardHeader className="pb-0">
                <CardTitle className="text-muted-foreground text-base font-medium">{title}</CardTitle>
                <div className="flex items-center gap-2">
                    {description ? <CardDescription>{description}</CardDescription> : null}
                    {deltaLabel ? (
                        <Badge
                            className="gap-1"
                            variant={deltaBadgeVariant as 'success' | 'destructive' | 'outline'}
                            aria-label={deltaLabel}
                            title={deltaLabel}
                        >
                            {DeltaIcon ? <DeltaIcon className="h-3.5 w-3.5" /> : null}
                            {deltaLabel}
                        </Badge>
                    ) : null}
                </div>
            </CardHeader>
            <CardContent className="pt-2">
                <div aria-live="polite" className="text-3xl font-bold tabular-nums">
                    {isLoading ? <span className="bg-muted inline-block h-8 w-24 animate-pulse rounded" /> : formatted || '0'}
                </div>
                {Array.isArray(series) && series.length > 0 ? (
                    <div className="mt-3 min-h-[80px]">
                        <ChartContainer config={chartConfig} className="w-full">
                            <ResponsiveContainer>
                                <AreaChart data={series} margin={{ left: 0, right: 0, top: 6, bottom: 0 }}>
                                    <Tooltip cursor={false} content={(props) => <ChartTooltipContent {...props} hideLabel />} />
                                    <Area
                                        type="monotone"
                                        dataKey="y"
                                        stroke="var(--color-spark)"
                                        fill="var(--color-spark)"
                                        fillOpacity={0.15}
                                        strokeWidth={2}
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        </ChartContainer>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}
