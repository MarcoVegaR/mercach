import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Link, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Eye, MoreHorizontal, Power, RotateCcw, ShieldCheck, Trash2 } from 'lucide-react';
import React from 'react';

export type Row = {
    id: number;
    market_id: number;
    period: string; // YYYY-MM-DD
    status: 'DRAFT' | 'FINAL';
    expenses_count?: number;
    participants_count?: number;
    total_usd_minor?: number;
    expenses?: string[]; // details for popover
    participants?: string[]; // details for popover (local codes)
    market?: { id: number; name?: string | null };
    is_active?: boolean;
    created_at?: string | null;
    [key: string]: unknown;
};

function formatUsdMinor(minor?: number) {
    const value = (Number(minor || 0) / 100).toFixed(2);
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'USD' }).format(Number(value));
}

function ActionsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canDelete = !!auth?.can?.['condo_period.delete'];
    const canSetActive = !!auth?.can?.['condo_period.setActive'];
    const canFinalize = !!auth?.can?.['condo_period.finalize'];
    const canReopen = !!auth?.can?.['condo_period.reopen'];

    const [openDelete, setOpenDelete] = React.useState(false);
    const [openToggle, setOpenToggle] = React.useState(false);
    const isActive = !!row.is_active;
    const isDraft = row.status === 'DRAFT';
    const canToggleActive = canSetActive && isDraft;
    const canDeleteDraft = canDelete && isDraft;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" className="h-8 w-8 p-0">
                        <span className="sr-only">Abrir menú</span>
                        <MoreHorizontal className="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                        <Link href={`/condo/periods/${row.id}/show`} className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4" />
                            Abrir workspace
                        </Link>
                    </DropdownMenuItem>
                    {canFinalize && isDraft && (
                        <DropdownMenuItem
                            onSelect={() => router.post(`/condo/periods/${row.id}/finalize`, {}, { preserveScroll: true, preserveState: true })}
                            className="text-emerald-600 focus:text-emerald-700 dark:text-emerald-400 dark:focus:text-emerald-300"
                        >
                            <ShieldCheck className="mr-2 h-4 w-4" />
                            Confirmar (FINAL)
                        </DropdownMenuItem>
                    )}
                    {canReopen && row.status === 'FINAL' && (
                        <DropdownMenuItem
                            onSelect={() => router.post(`/condo/periods/${row.id}/reopen`, {}, { preserveScroll: true, preserveState: true })}
                            className="text-violet-600 focus:text-violet-700 dark:text-violet-400 dark:focus:text-violet-300"
                        >
                            <RotateCcw className="mr-2 h-4 w-4" />
                            Reabrir (DRAFT)
                        </DropdownMenuItem>
                    )}
                    {canToggleActive && (
                        <DropdownMenuItem
                            onSelect={() => setTimeout(() => setOpenToggle(true), 100)}
                            className={
                                isActive
                                    ? 'text-amber-600 focus:text-amber-700 dark:text-amber-400 dark:focus:text-amber-300'
                                    : 'text-emerald-600 focus:text-emerald-700 dark:text-emerald-400 dark:focus:text-emerald-300'
                            }
                        >
                            <Power className="mr-2 h-4 w-4" />
                            {isActive ? 'Desactivar' : 'Activar'}
                        </DropdownMenuItem>
                    )}
                    {canDeleteDraft && (
                        <DropdownMenuItem
                            onSelect={() => setTimeout(() => setOpenDelete(true), 100)}
                            className="text-red-600 focus:text-red-700 dark:text-red-400 dark:focus:text-red-300"
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Eliminar
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            {/* Confirm delete */}
            <ConfirmAlert
                open={openDelete}
                onOpenChange={setOpenDelete}
                title="Eliminar período"
                description={`¿Eliminar el período ${String(row.period)}? Se eliminarán gastos y participantes.`}
                confirmLabel="Eliminar"
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.delete(`/condo/periods/${row.id}`, {
                            preserveState: false,
                            preserveScroll: true,
                            onSuccess: () => resolve(),
                            onError: () => reject(new Error('delete_failed')),
                        });
                    });
                }}
            />

            {/* Confirm toggle active */}
            <ConfirmAlert
                open={openToggle}
                onOpenChange={setOpenToggle}
                title={isActive ? 'Desactivar' : 'Activar'}
                description={`¿Está seguro de ${isActive ? 'desactivar' : 'activar'} el período ${String(row.period)}?`}
                confirmLabel={isActive ? 'Desactivar' : 'Activar'}
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.patch(
                            `/condo/periods/${row.id}/active`,
                            { active: !isActive },
                            {
                                preserveState: false,
                                preserveScroll: true,
                                onSuccess: () => resolve(),
                                onError: () => reject(new Error('set_active_failed')),
                            },
                        );
                    });
                }}
            />
        </>
    );
}

export const columns: ColumnDef<Row>[] = [
    { accessorKey: 'id', header: '#', enableSorting: true },
    {
        accessorKey: 'period',
        header: 'Período',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = String(getValue() ?? '');
            if (!value) return null;
            const d = new Date(value);
            const label = format(d, 'yyyy-MM', { locale: es });
            return (
                <Badge variant="secondary" className="px-2 py-1 font-mono text-xs" title={label}>
                    {label}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'market_id',
        header: 'Mercado',
        enableSorting: true,
        cell: ({ row }) => {
            const m = (row.original as Row).market;
            if (m?.name) {
                return (
                    <span className="truncate text-sm" title={m.name}>
                        {m.name}
                    </span>
                );
            }
            return <span className="text-muted-foreground text-xs">—</span>;
        },
    },
    {
        accessorKey: 'expenses_count',
        header: 'Gastos',
        enableSorting: true,
        cell: ({ row, getValue }) => {
            const count = (getValue() as number) ?? 0;
            const names = (row.original.expenses || []) as string[];
            if (count === 0) {
                return (
                    <div className="flex items-center justify-center">
                        <Badge variant="outline" className="text-muted-foreground text-xs">
                            0
                        </Badge>
                    </div>
                );
            }

            return (
                <TooltipProvider>
                    <div className="flex items-center justify-center">
                        {names.length > 0 ? (
                            <Popover>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <PopoverTrigger asChild>
                                            <Badge variant="secondary" className="cursor-pointer font-medium">
                                                {count}
                                            </Badge>
                                        </PopoverTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>Ver gastos del período</TooltipContent>
                                </Tooltip>
                                <PopoverContent className="w-80">
                                    <div className="space-y-2">
                                        <h4 className="text-sm font-medium">Gastos ({count})</h4>
                                        <div className="flex max-h-64 flex-wrap gap-1 overflow-auto">
                                            {names.map((n, i) => (
                                                <Badge key={`exp-${row.original.id}-${i}`} variant="outline" className="text-xs">
                                                    {n}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        ) : (
                            <Badge variant="secondary" className="font-medium">
                                {count}
                            </Badge>
                        )}
                    </div>
                </TooltipProvider>
            );
        },
    },
    {
        accessorKey: 'participants_count',
        header: 'Excluidos',
        enableSorting: true,
        cell: ({ row, getValue }) => {
            const count = (getValue() as number) ?? 0;
            const codes = (row.original.participants || []) as string[];
            if (count === 0) {
                return (
                    <div className="flex items-center justify-center">
                        <Badge variant="outline" className="text-muted-foreground text-xs">
                            0
                        </Badge>
                    </div>
                );
            }

            return (
                <TooltipProvider>
                    <div className="flex items-center justify-center">
                        {codes.length > 0 ? (
                            <Popover>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <PopoverTrigger asChild>
                                            <Badge variant="secondary" className="cursor-pointer font-medium">
                                                {count}
                                            </Badge>
                                        </PopoverTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>Ver locales excluidos</TooltipContent>
                                </Tooltip>
                                <PopoverContent className="w-80">
                                    <div className="space-y-2">
                                        <h4 className="text-sm font-medium">Excluidos ({count})</h4>
                                        <div className="flex max-h-64 flex-wrap gap-1 overflow-auto">
                                            {codes.map((c, i) => (
                                                <Badge key={`p-${row.original.id}-${i}`} variant="outline" className="text-xs">
                                                    {c}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        ) : (
                            <Badge variant="secondary" className="font-medium">
                                {count}
                            </Badge>
                        )}
                    </div>
                </TooltipProvider>
            );
        },
    },
    {
        accessorKey: 'total_usd_minor',
        header: 'Total USD',
        enableSorting: true,
        cell: ({ getValue }) => <span className="font-mono">{formatUsdMinor(Number(getValue()))}</span>,
    },
    {
        accessorKey: 'status',
        header: 'Estado',
        enableSorting: true,
        cell: ({ getValue }) => {
            const status = String(getValue() ?? 'DRAFT');
            const isFinal = status === 'FINAL';
            const label = isFinal ? 'Final' : 'Borrador';
            return (
                <div className="flex items-center gap-2">
                    <span className={'h-2 w-2 shrink-0 rounded-full ' + (isFinal ? 'bg-indigo-500' : 'bg-amber-500')} />
                    <Badge variant={isFinal ? 'secondary' : 'default'} className="font-medium">
                        {label}
                    </Badge>
                </div>
            );
        },
    },
    {
        accessorKey: 'is_active',
        header: 'Activo',
        enableSorting: true,
        cell: ({ getValue }) => {
            const active = Boolean(getValue());
            return (
                <div className="flex items-center gap-2">
                    <span className={'h-2 w-2 shrink-0 rounded-full ' + (active ? 'bg-emerald-500' : 'bg-red-400')} />
                    <Badge variant={active ? 'default' : 'destructive'} className="font-medium">
                        {active ? 'Activo' : 'Inactivo'}
                    </Badge>
                </div>
            );
        },
    },
    {
        id: 'actions',
        header: 'Acciones',
        enableSorting: false,
        cell: ({ row }) => <ActionsCell row={row.original as Row} />,
    },
];
