import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardTitle } from '@/components/ui/card';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { TrendingDown, TrendingUp, type LucideIcon } from 'lucide-react';
import * as React from 'react';
import { Area, AreaChart, ResponsiveContainer } from 'recharts';

export type SparkPoint = { x: number | string; y: number };

export type KpiStatCardProps = {
    title: string;
    value?: number | string;
    subtitle?: string;
    isLoading?: boolean;
    href?: string;
    icon?: LucideIcon;
    deltaLabel?: string;
    deltaVariant?: 'up' | 'down' | 'neutral';
    className?: string;
    series?: SparkPoint[];
    sparkColor?: string;
    tintVariant?: 'success' | 'destructive' | 'warning' | 'neutral' | 'info';
};

const tintClasses: Record<string, string> = {
    success: 'border-b-green-500',
    destructive: 'border-b-red-500',
    warning: 'border-b-amber-500',
    info: 'border-b-blue-500',
    neutral: 'border-b-border',
};

const tintBgClasses: Record<string, string> = {
    success: 'bg-green-500/[0.03]',
    destructive: 'bg-red-500/[0.03]',
    warning: 'bg-amber-500/[0.03]',
    info: 'bg-blue-500/[0.03]',
    neutral: '',
};

const iconBgClasses: Record<string, string> = {
    success: 'bg-green-500/10 text-green-600 dark:text-green-400',
    destructive: 'bg-red-500/10 text-red-600 dark:text-red-400',
    warning: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    info: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    neutral: 'bg-primary/10 text-primary',
};

export function KpiStatCard({
    title,
    value,
    subtitle,
    isLoading,
    href,
    icon: Icon,
    deltaLabel,
    deltaVariant = 'neutral',
    className,
    series,
    sparkColor = 'var(--chart-1)',
    tintVariant = 'neutral',
}: KpiStatCardProps) {
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

    const chartConfig: ChartConfig = React.useMemo(() => ({ spark: { color: sparkColor } }), [sparkColor]);

    const hasSpark = Array.isArray(series) && series.length > 1;

    return (
        <Card
            className={cn(
                'relative overflow-hidden border-b-2 shadow-sm transition-all duration-200 hover:shadow-md',
                tintClasses[tintVariant] ?? tintClasses.neutral,
                tintBgClasses[tintVariant] ?? '',
                isLink && 'cursor-pointer hover:scale-[1.01]',
                className,
            )}
            role={isLink ? 'link' : undefined}
            aria-label={ariaLabel}
            tabIndex={isLink ? 0 : undefined}
            onKeyDown={onKeyDown}
            onClick={isLink ? onActivate : undefined}
        >
            <CardContent className="flex flex-col gap-1 p-4 pb-0">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-2.5">
                        {Icon && (
                            <div
                                className={cn(
                                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                    iconBgClasses[tintVariant] ?? iconBgClasses.neutral,
                                )}
                            >
                                <Icon className="h-4.5 w-4.5" />
                            </div>
                        )}
                        <CardDescription className="text-xs font-medium tracking-tight sm:text-sm">{title}</CardDescription>
                    </div>
                    {deltaLabel && (
                        <Badge
                            className="shrink-0 gap-0.5 text-[10px]"
                            variant={deltaBadgeVariant as 'success' | 'destructive' | 'outline'}
                            aria-label={deltaLabel}
                            title={deltaLabel}
                        >
                            {DeltaIcon && <DeltaIcon className="h-3 w-3" />}
                            <span>{deltaLabel}</span>
                        </Badge>
                    )}
                </div>

                <CardTitle className="mt-1 text-2xl font-bold tabular-nums sm:text-3xl" aria-live="polite">
                    {isLoading ? <span className="bg-muted inline-block h-8 w-28 animate-pulse rounded" /> : formatted || '0'}
                </CardTitle>

                {subtitle && !isLoading && <p className="text-muted-foreground text-[11px] leading-tight">{subtitle}</p>}
            </CardContent>

            {hasSpark && (
                <div className="mt-1 h-[48px] w-full px-1">
                    <ChartContainer config={chartConfig} className="h-full w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={series} margin={{ left: 0, right: 0, top: 4, bottom: 0 }}>
                                <defs>
                                    <linearGradient id={`grad-${title.replace(/\s/g, '')}`} x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="var(--color-spark)" stopOpacity={0.3} />
                                        <stop offset="95%" stopColor="var(--color-spark)" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <Area
                                    type="monotone"
                                    dataKey="y"
                                    stroke="var(--color-spark)"
                                    fill={`url(#grad-${title.replace(/\s/g, '')})`}
                                    strokeWidth={1.5}
                                    dot={false}
                                    isAnimationActive={true}
                                    animationDuration={800}
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </ChartContainer>
                </div>
            )}

            {!hasSpark && <div className="h-3" />}
        </Card>
    );
}
