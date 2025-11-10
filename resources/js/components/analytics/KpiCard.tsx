import { Badge } from '@/components/ui/badge';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { TrendingDown, TrendingUp, type LucideIcon } from 'lucide-react';
import * as React from 'react';

export type KpiCardProps = {
    title: string;
    description?: string;
    value?: number | string;
    isLoading?: boolean;
    href?: string;
    deltaLabel?: string;
    deltaVariant?: 'up' | 'down' | 'neutral';
    className?: string;
    icon?: LucideIcon;
    iconClassName?: string;
    borderVariant?: 'success' | 'destructive' | 'primary' | 'neutral';
    subtitle?: string;
};

export function KpiCard({
    title,
    description,
    value,
    isLoading,
    href,
    deltaLabel,
    deltaVariant = 'neutral',
    className,
    icon: Icon,
    iconClassName,
    borderVariant = 'neutral',
    subtitle,
}: KpiCardProps) {
    const isLink = Boolean(href);

    // Memoized number formatter to avoid re-creating on each render
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

    const borderClass = {
        success: 'border-l-4 border-l-green-500',
        destructive: 'border-l-4 border-l-red-500',
        primary: 'border-l-4 border-l-primary',
        neutral: '',
    }[borderVariant];

    return (
        <Card
            className={cn('transition-all duration-200 hover:shadow-lg', isLink && 'cursor-pointer hover:scale-[1.02]', borderClass, className)}
            role={isLink ? 'link' : undefined}
            aria-label={ariaLabel}
            tabIndex={isLink ? 0 : undefined}
            onKeyDown={onKeyDown}
            onClick={isLink ? onActivate : undefined}
        >
            <CardHeader className="gap-2 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-3">
                        {Icon && (
                            <div className={cn('bg-primary/10 flex h-10 w-10 items-center justify-center rounded-full', iconClassName)}>
                                <Icon className="text-primary h-5 w-5" />
                            </div>
                        )}
                        <CardDescription className="text-sm font-medium">{title}</CardDescription>
                    </div>
                    {deltaLabel ? (
                        <Badge
                            className="shrink-0 gap-1"
                            variant={deltaBadgeVariant as 'success' | 'destructive' | 'outline'}
                            aria-label={deltaLabel}
                            title={deltaLabel}
                        >
                            {DeltaIcon ? <DeltaIcon className="h-3 w-3" /> : null}
                            <span className="text-xs">{deltaLabel}</span>
                        </Badge>
                    ) : null}
                </div>
                <CardTitle className="text-3xl font-bold tabular-nums sm:text-4xl" aria-live="polite">
                    {isLoading ? <span className="bg-muted inline-block h-10 w-32 animate-pulse rounded" /> : formatted || '0'}
                </CardTitle>
                {subtitle && !isLoading && <p className="text-muted-foreground text-xs">{subtitle}</p>}
                {description ? <div className="sr-only">{description}</div> : null}
            </CardHeader>
        </Card>
    );
}
