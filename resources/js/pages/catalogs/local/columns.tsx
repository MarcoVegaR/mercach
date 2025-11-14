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
import { format, formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { Edit, Eye, MoreHorizontal, Power, Trash2 } from 'lucide-react';
import React from 'react';

export type Row = {
    id: number | string;
    code?: string | null;
    name?: string | null;
    market_id?: string | null;
    local_type_id?: string | null;
    local_status_id?: string | null;
    local_location_id?: string | null;
    market_name?: string | null;
    local_type_name?: string | null;
    local_status_name?: string | null;
    local_location_name?: string | null;
    area_m2?: number | null;
    active_concessionaires_count?: number | null;
    active_concessionaires?: string[] | null;
    active_concessionaires_text?: string | null;
    active_concessionaires_detailed?: { id: number; name: string }[] | null;
    active_contract_numbers?: string[] | null;
    active_contracts_text?: string | null;
    active_contracts_detailed?: { id: number; number: string }[] | null;
    is_active?: boolean | null;
    created_at?: string | null;
    [key: string]: unknown;
};

function ActionsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canUpdate = !!auth?.can?.['catalogs.local.update'];
    const canDelete = !!auth?.can?.['catalogs.local.delete'];
    const canSetActive = !!auth?.can?.['catalogs.local.setActive'];

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
                        <Link href={`/catalogs/local/${row.id}`} className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4" />
                            Ver detalles
                        </Link>
                    </DropdownMenuItem>
                    {canUpdate && (
                        <DropdownMenuItem asChild>
                            <Link href={`/catalogs/local/${row.id}/edit`} className="cursor-pointer">
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
                        router.delete(`/catalogs/local/${row.id}`, {
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
                            `/catalogs/local/${row.id}/active`,
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

function ActiveConcessionairesCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canViewConcessionaire = !!auth?.can?.['catalogs.concessionaire.view'];

    const r = row;
    const count = Number(r.active_concessionaires_count ?? 0);
    const detailed = (r.active_concessionaires_detailed ?? []) as { id: number; name: string }[];
    const namesFallback = (r.active_concessionaires ?? []) as string[];

    const items: { id: number; name: string }[] = detailed.length > 0 ? detailed : namesFallback.map((name) => ({ id: 0, name }));

    if (!count || items.length === 0) {
        return <span className="text-muted-foreground text-xs">—</span>;
    }

    return (
        <TooltipProvider>
            <div className="flex items-center">
                <Popover>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <PopoverTrigger asChild>
                                <Badge variant="secondary" className="cursor-pointer font-medium">
                                    {count}
                                </Badge>
                            </PopoverTrigger>
                        </TooltipTrigger>
                        <TooltipContent>Ver concesionarios</TooltipContent>
                    </Tooltip>
                    <PopoverContent className="w-80">
                        <div className="space-y-2">
                            <h4 className="text-sm font-medium">Concesionarios ({count})</h4>
                            <div className="flex max-h-64 flex-col gap-1 overflow-auto">
                                {items.map((item, i) => {
                                    const key = `concessionaire-${String(r.id)}-${i}`;
                                    if (canViewConcessionaire && item.id > 0) {
                                        return (
                                            <Link
                                                key={key}
                                                href={`/catalogs/concessionaire/${item.id}`}
                                                className="text-primary text-sm hover:underline"
                                            >
                                                {item.name}
                                            </Link>
                                        );
                                    }

                                    return (
                                        <span key={key} className="text-sm">
                                            {item.name}
                                        </span>
                                    );
                                })}
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>
            </div>
        </TooltipProvider>
    );
}

function ActiveContractsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canViewContract = !!auth?.can?.['catalogs.contract.view'];

    const r = row;
    const detailed = (r.active_contracts_detailed ?? []) as { id: number; number: string }[];
    const numbersFallback = (r.active_contract_numbers ?? []) as string[];

    const items: { id: number; number: string }[] = detailed.length > 0 ? detailed : numbersFallback.map((num) => ({ id: 0, number: num }));

    if (!items.length) {
        return <span className="text-muted-foreground text-xs">—</span>;
    }

    const count = items.length;

    return (
        <TooltipProvider>
            <div className="flex items-center">
                <Popover>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <PopoverTrigger asChild>
                                <Badge variant="secondary" className="cursor-pointer font-medium">
                                    {count}
                                </Badge>
                            </PopoverTrigger>
                        </TooltipTrigger>
                        <TooltipContent>Ver contratos</TooltipContent>
                    </Tooltip>
                    <PopoverContent className="w-80">
                        <div className="space-y-2">
                            <h4 className="text-sm font-medium">Contratos ({count})</h4>
                            <div className="flex max-h-64 flex-wrap gap-1 overflow-auto">
                                {items.map((item, i) => {
                                    const key = `local-${String(r.id)}-contract-${i}`;
                                    const badge = (
                                        <Badge key={key} variant="outline" className="font-mono text-xs">
                                            {item.number}
                                        </Badge>
                                    );

                                    if (canViewContract && item.id > 0) {
                                        return (
                                            <Link key={key} href={`/catalogs/contract/${item.id}`} className="inline-block">
                                                {badge}
                                            </Link>
                                        );
                                    }

                                    return badge;
                                })}
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>
            </div>
        </TooltipProvider>
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
                <span className="block max-w-[160px] truncate whitespace-nowrap" title={value}>
                    {value}
                </span>
            );
        },
    },
    {
        accessorKey: 'market_name',
        header: 'Mercado',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = String(getValue() ?? '');
            return (
                <span className="block max-w-[160px] truncate text-sm whitespace-nowrap" title={value}>
                    {value}
                </span>
            );
        },
    },
    {
        accessorKey: 'local_type_name',
        header: 'Tipo de local',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = String(getValue() ?? '');
            return (
                <span className="block max-w-[160px] truncate text-sm whitespace-nowrap" title={value}>
                    {value}
                </span>
            );
        },
    },
    {
        accessorKey: 'local_status_name',
        header: 'Estado de local',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = String(getValue() ?? '');
            return (
                <span className="block max-w-[160px] truncate text-sm whitespace-nowrap" title={value}>
                    {value}
                </span>
            );
        },
    },
    {
        accessorKey: 'local_location_name',
        header: 'Ubicación',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = String(getValue() ?? '');
            return (
                <span className="block max-w-[160px] truncate text-sm whitespace-nowrap" title={value}>
                    {value}
                </span>
            );
        },
    },
    {
        id: 'active_concessionaires',
        header: 'Concesionarios',
        enableSorting: false,
        cell: ({ row }) => <ActiveConcessionairesCell row={row.original as Row} />,
    },
    {
        id: 'active_contracts',
        header: 'Contratos',
        enableSorting: false,
        cell: ({ row }) => <ActiveContractsCell row={row.original as Row} />,
    },
    { accessorKey: 'area_m2', header: 'Área (m²)', enableSorting: true },
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
