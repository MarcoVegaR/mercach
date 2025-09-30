import { Badge } from '@/components/ui/badge';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { TrendingDown, TrendingUp } from 'lucide-react';
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
};

export function KpiCard({ title, description, value, isLoading, href, deltaLabel, deltaVariant = 'neutral', className }: KpiCardProps) {
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

    return (
        <Card
            className={cn('hover:ring-border/60 transition-colors hover:ring-1', isLink && 'cursor-pointer', className)}
            role={isLink ? 'link' : undefined}
            aria-label={ariaLabel}
            tabIndex={isLink ? 0 : undefined}
            onKeyDown={onKeyDown}
            onClick={isLink ? onActivate : undefined}
        >
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between gap-2">
                    <CardDescription>{title}</CardDescription>
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
                <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl" aria-live="polite">
                    {isLoading ? <span className="bg-muted inline-block h-8 w-24 animate-pulse rounded" /> : formatted || '0'}
                </CardTitle>
                {description ? <div className="sr-only">{description}</div> : null}
            </CardHeader>
        </Card>
    );
}
