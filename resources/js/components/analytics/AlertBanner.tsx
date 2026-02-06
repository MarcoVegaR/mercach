import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { AlertTriangle, ChevronDown, ChevronUp, Info, XCircle } from 'lucide-react';
import * as React from 'react';

export type DashboardAlert = {
    level: 'critical' | 'warning' | 'info';
    code: string;
    message: string;
    href?: string;
};

const levelConfig = {
    critical: {
        icon: XCircle,
        bg: 'bg-red-500/8 dark:bg-red-500/15',
        border: 'border-red-500/30',
        text: 'text-red-700 dark:text-red-400',
        badge: 'bg-red-500/15 text-red-700 dark:text-red-400',
        label: 'Cr\u00edtico',
    },
    warning: {
        icon: AlertTriangle,
        bg: 'bg-amber-500/8 dark:bg-amber-500/15',
        border: 'border-amber-500/30',
        text: 'text-amber-700 dark:text-amber-400',
        badge: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        label: 'Alerta',
    },
    info: {
        icon: Info,
        bg: 'bg-blue-500/8 dark:bg-blue-500/15',
        border: 'border-blue-500/30',
        text: 'text-blue-700 dark:text-blue-400',
        badge: 'bg-blue-500/15 text-blue-700 dark:text-blue-400',
        label: 'Info',
    },
};

export function AlertBanner({ alerts }: { alerts: DashboardAlert[] }) {
    const [collapsed, setCollapsed] = React.useState(false);

    if (!alerts || alerts.length === 0) return null;

    const sorted = [...alerts].sort((a, b) => {
        const order = { critical: 0, warning: 1, info: 2 };
        return order[a.level] - order[b.level];
    });

    return (
        <div className="space-y-2">
            <button
                onClick={() => setCollapsed(!collapsed)}
                className="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-xs font-medium transition-colors"
            >
                {collapsed ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronUp className="h-3.5 w-3.5" />}
                {sorted.length} alerta{sorted.length !== 1 ? 's' : ''} activa{sorted.length !== 1 ? 's' : ''}
            </button>

            {!collapsed && (
                <div className="space-y-1.5">
                    {sorted.map((alert) => {
                        const cfg = levelConfig[alert.level];
                        const Icon = cfg.icon;
                        return (
                            <div
                                key={alert.code}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg border px-3 py-2.5',
                                    cfg.bg,
                                    cfg.border,
                                    alert.href && 'cursor-pointer transition-opacity hover:opacity-80',
                                )}
                                onClick={alert.href ? () => router.visit(alert.href!) : undefined}
                                role={alert.href ? 'link' : undefined}
                            >
                                <Icon className={cn('h-4 w-4 shrink-0', cfg.text)} />
                                <span className={cn('rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase', cfg.badge)}>{cfg.label}</span>
                                <span className="text-foreground text-sm">{alert.message}</span>
                                {alert.href && <span className={cn('ml-auto text-xs font-medium', cfg.text)}>Ver →</span>}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
