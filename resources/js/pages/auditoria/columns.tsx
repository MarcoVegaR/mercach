import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ColumnDef } from '@tanstack/react-table';
import { format, formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { Info } from 'lucide-react';
import React from 'react';

export type TAudit = {
    id: number;
    created_at: string;
    user_id: number | null;
    user_name: string | null;
    event: string;
    auditable_type: string | null;
    auditable_id: number | null;
    ip_address: string | null;
    url: string | null;
    tags: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    user_agent: string | null;
};

function AuditDetailsCell({ audit }: { audit: TAudit }) {
    const [open, setOpen] = React.useState(false);

    const hasChanges = audit.old_values || audit.new_values;
    const hasDetails = hasChanges || audit.user_agent || audit.tags;

    if (!hasDetails) return null;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                    <Info className="h-4 w-4" />
                    <span className="sr-only">Ver detalles</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-96" align="end">
                <div className="space-y-4">
                    <div className="space-y-2">
                        <h4 className="leading-none font-medium">Detalles del Evento</h4>
                        <p className="text-muted-foreground text-sm">
                            {audit.event} en {audit.auditable_type}
                        </p>
                    </div>

                    {audit.old_values && (
                        <div className="space-y-2">
                            <h5 className="text-sm font-medium">Valores Anteriores</h5>
                            <pre className="bg-muted max-h-32 overflow-auto rounded p-2 text-xs">{JSON.stringify(audit.old_values, null, 2)}</pre>
                        </div>
                    )}

                    {audit.new_values && (
                        <div className="space-y-2">
                            <h5 className="text-sm font-medium">Valores Nuevos</h5>
                            <pre className="bg-muted max-h-32 overflow-auto rounded p-2 text-xs">{JSON.stringify(audit.new_values, null, 2)}</pre>
                        </div>
                    )}

                    {audit.user_agent && (
                        <div className="space-y-2">
                            <h5 className="text-sm font-medium">User Agent</h5>
                            <p className="text-muted-foreground text-xs break-all">{audit.user_agent}</p>
                        </div>
                    )}

                    {audit.tags && (
                        <div className="space-y-2">
                            <h5 className="text-sm font-medium">Tags</h5>
                            <Badge variant="outline" className="text-xs">
                                {audit.tags}
                            </Badge>
                        </div>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}

function EventBadge({ event }: { event: string }) {
    const variant = React.useMemo(() => {
        switch (event.toLowerCase()) {
            case 'created':
                return 'default';
            case 'updated':
                return 'secondary';
            case 'deleted':
                return 'destructive';
            case 'login':
                return 'default';
            case 'logout':
                return 'outline';
            default:
                return 'outline';
        }
    }, [event]);

    return (
        <Badge variant={variant} className="text-xs">
            {event}
        </Badge>
    );
}

export const columns: ColumnDef<TAudit>[] = [
    {
        accessorKey: 'created_at',
        header: 'Fecha',
        meta: {
            accessorKeyServer: 'created_at',
            exportLabel: 'Fecha',
        },
        cell: ({ row }) => {
            const date = new Date(row.getValue('created_at'));
            return (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <div className="text-sm">{format(date, 'dd/MM/yyyy HH:mm', { locale: es })}</div>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{formatDistanceToNow(date, { addSuffix: true, locale: es })}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            );
        },
    },
    {
        accessorKey: 'user_name',
        header: 'Usuario',
        meta: {
            accessorKeyServer: 'user_name',
            exportLabel: 'Usuario',
        },
        cell: ({ row }) => {
            const userName = row.getValue('user_name') as string | null;
            const userId = row.original.user_id;

            if (!userName && !userId) {
                return <span className="text-muted-foreground text-sm">Sistema</span>;
            }

            return (
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{userName || `Usuario #${userId}`}</span>
                </div>
            );
        },
    },
    {
        accessorKey: 'event',
        header: 'Evento',
        meta: {
            accessorKeyServer: 'event',
            exportLabel: 'Evento',
        },
        cell: ({ row }) => {
            const event = row.getValue('event') as string;
            return <EventBadge event={event} />;
        },
    },
    {
        accessorKey: 'auditable_type',
        header: 'Entidad',
        meta: {
            accessorKeyServer: 'auditable_type',
            exportLabel: 'Entidad',
        },
        cell: ({ row }) => {
            const type = row.getValue('auditable_type') as string | null;
            if (!type) return <span className="text-muted-foreground">-</span>;

            // Extract class name from full namespace
            const className = type.split('\\').pop() || type;
            return (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="text-sm">{className}</span>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{type}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            );
        },
    },
    {
        accessorKey: 'auditable_id',
        header: 'ID Entidad',
        meta: {
            accessorKeyServer: 'auditable_id',
            exportLabel: 'ID Entidad',
        },
        cell: ({ row }) => {
            const id = row.getValue('auditable_id') as number | null;
            return id ? <span className="font-mono text-sm">{id}</span> : <span className="text-muted-foreground">-</span>;
        },
    },
    {
        accessorKey: 'ip_address',
        header: 'IP',
        meta: {
            accessorKeyServer: 'ip_address',
            exportLabel: 'Dirección IP',
        },
        cell: ({ row }) => {
            const ip = row.getValue('ip_address') as string | null;
            return ip ? <span className="font-mono text-sm">{ip}</span> : <span className="text-muted-foreground">-</span>;
        },
    },
    {
        accessorKey: 'url',
        header: 'URL',
        meta: {
            accessorKeyServer: 'url',
            exportLabel: 'URL',
        },
        cell: ({ row }) => {
            const url = row.getValue('url') as string | null;
            if (!url) return <span className="text-muted-foreground">-</span>;

            return (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="block max-w-32 truncate text-sm text-blue-600 dark:text-blue-400">{url}</span>
                        </TooltipTrigger>
                        <TooltipContent side="left" className="max-w-md">
                            <p className="break-all">{url}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            );
        },
    },
    {
        id: 'actions',
        header: 'Detalles',
        cell: ({ row }) => <AuditDetailsCell audit={row.original} />,
    },
];
