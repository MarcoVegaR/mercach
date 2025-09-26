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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Link, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { format, formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { Edit, Eye, MoreHorizontal, Power, Trash2 } from 'lucide-react';
import React from 'react';

export type Row = {
    id: number | string;
    code?: string | null;
    name?: string | null;
    is_active?: boolean | null;
    created_at?: string | null;
    [key: string]: unknown;
};

function ActionsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canUpdate = !!auth?.can?.['catalogs.payment-status.update'];
    const canDelete = !!auth?.can?.['catalogs.payment-status.delete'];
    const canSetActive = !!auth?.can?.['catalogs.payment-status.setActive'];

    const [openDelete, setOpenDelete] = React.useState(false);
    const [openToggle, setOpenToggle] = React.useState(false);
    const isActive = !!row.is_active;

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
                        <Link href={`/catalogs/payment-status/${row.id}`} className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4" />
                            Ver detalles
                        </Link>
                    </DropdownMenuItem>
                    {canUpdate && (
                        <DropdownMenuItem asChild>
                            <Link href={`/catalogs/payment-status/${row.id}/edit`} className="cursor-pointer">
                                <Edit className="mr-2 h-4 w-4" />
                                Editar
                            </Link>
                        </DropdownMenuItem>
                    )}
                    {canSetActive && (
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
                    {canDelete && (
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
                title="Eliminar registro"
                description={`¿Está seguro de eliminar el registro "${String(row.name ?? row.code ?? row.id)}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.delete(`/catalogs/payment-status/${row.id}`, {
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
                description={`¿Está seguro de ${isActive ? 'desactivar' : 'activar'} el registro "${String(row.name ?? row.code ?? row.id)}"?`}
                confirmLabel={isActive ? 'Desactivar' : 'Activar'}
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.patch(
                            `/catalogs/payment-status/${row.id}/active`,
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
        accessorKey: 'code',
        header: 'Código',
        enableSorting: true,
        cell: ({ getValue }) => <span className="font-mono text-xs">{String(getValue() ?? '')}</span>,
    },
    {
        accessorKey: 'name',
        header: 'Nombre',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = String(getValue() ?? '');
            return (
                <span className="block max-w-[180px] truncate whitespace-nowrap" title={value}>
                    {value}
                </span>
            );
        },
    },
    {
        accessorKey: 'is_active',
        header: 'Estado',
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
        accessorKey: 'created_at',
        header: 'Creado',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = getValue() as string;
            if (!value) return null;
            const d = new Date(value);
            const short = format(d, 'dd MMM yyyy', { locale: es });
            const full = format(d, 'PPpp', { locale: es });
            const relative = formatDistanceToNow(d, { locale: es, addSuffix: true });
            return (
                <TooltipProvider>
                    <div className="text-center">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span className="text-sm whitespace-nowrap" title={full}>
                                    {short}
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>
                                <div className="flex flex-col gap-0.5">
                                    <span>{full}</span>
                                    <span className="text-muted-foreground">{relative}</span>
                                </div>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
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
