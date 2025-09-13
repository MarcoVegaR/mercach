import { ArrowDownRightIcon, ArrowUpRightIcon, MinusIcon } from 'lucide-react';
import * as React from 'react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type StatsCardIntent = 'neutral' | 'success' | 'warning' | 'error' | 'info';
export type StatsCardDirection = 'up' | 'down' | 'neutral' | 'auto';

export type StatsCardProps = {
    title: React.ReactNode;
    value: React.ReactNode;
    delta?: number | string;
    deltaDirection?: StatsCardDirection;
    deltaLabel?: string;
    subtitle?: React.ReactNode;
    icon?: React.ReactNode;
    intent?: StatsCardIntent;
    compact?: boolean;
    onClick?: () => void;
    className?: string;
};

function getIntentClasses(intent: StatsCardIntent | undefined) {
    switch (intent) {
        case 'success':
            return {
                text: 'text-success',
                bgSoft: 'bg-success/10',
            };
        case 'warning':
            return { text: 'text-warning', bgSoft: 'bg-warning/10' };
        case 'error':
            return { text: 'text-error', bgSoft: 'bg-error/10' };
        case 'info':
            return { text: 'text-info', bgSoft: 'bg-info/10' };
        default:
            return { text: 'text-muted-foreground', bgSoft: 'bg-muted' };
    }
}

export function StatsCard({
    title,
    value,
    delta,
    deltaDirection = 'auto',
    deltaLabel,
    subtitle,
    icon,
    intent = 'neutral',
    compact,
    onClick,
    className,
}: StatsCardProps) {
    const asButton = typeof onClick === 'function';
    const { text, bgSoft } = getIntentClasses(intent);

    let dir: StatsCardDirection = 'neutral';
    if (deltaDirection === 'auto') {
        if (typeof delta === 'number') dir = delta > 0 ? 'up' : delta < 0 ? 'down' : 'neutral';
    } else {
        dir = deltaDirection;
    }

    const Icon = dir === 'up' ? ArrowUpRightIcon : dir === 'down' ? ArrowDownRightIcon : MinusIcon;
    const srLabel =
        deltaLabel ??
        (typeof delta === 'number'
            ? `${delta > 0 ? 'Aumento' : delta < 0 ? 'Disminución' : 'Sin cambio'} ${Math.abs(delta)}%`
            : typeof delta === 'string'
              ? delta
              : undefined);

    return (
        <Card
            className={cn('transition-colors', asButton && 'hover:bg-accent/50 cursor-pointer', className)}
            onClick={onClick}
            role={asButton ? 'button' : undefined}
            tabIndex={asButton ? 0 : undefined}
            onKeyDown={
                asButton
                    ? (e) => {
                          if (e.key === 'Enter' || e.key === ' ') {
                              e.preventDefault();
                              onClick?.();
                          }
                      }
                    : undefined
            }
        >
            <CardHeader className={cn('pb-2', compact && 'pb-1')}>
                <div className="flex items-start justify-between">
                    <div>
                        <CardTitle className={cn('text-muted-foreground text-sm font-medium')}>{title}</CardTitle>
                        {subtitle ? <div className="text-muted-foreground mt-1 text-xs">{subtitle}</div> : null}
                    </div>
                    {icon ? (
                        <div className={cn('rounded-md p-1.5', bgSoft)} aria-hidden>
                            {icon}
                        </div>
                    ) : null}
                </div>
            </CardHeader>
            <CardContent className={cn('pt-0', compact && 'pt-0')}>
                <div className="flex items-end justify-between gap-3">
                    <div className={cn('text-2xl leading-none font-semibold tracking-tight')}>{value}</div>
                    {(delta !== undefined && delta !== null) || srLabel ? (
                        <div className={cn('flex items-center gap-1.5 text-sm', text)}>
                            <Icon aria-hidden className="size-4" />
                            {typeof delta === 'string' ? <span>{delta}</span> : typeof delta === 'number' ? <span>{Math.abs(delta)}%</span> : null}
                            {srLabel ? <span className="sr-only">{srLabel}</span> : null}
                        </div>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}
