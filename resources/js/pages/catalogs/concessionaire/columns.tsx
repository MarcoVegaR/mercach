import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
    // Friendly relation names provided by service
    concessionaire_type_name?: string | null;
    document_type_name?: string | null;
    document_type_code?: string | null;
    // Raw attributes
    concessionaire_type_id?: string | null;
    document_type_id?: string | null;
    full_name?: string | null;
    document_number?: string | null;
    fiscal_address?: string | null;
    email?: string | null;
    phone_area_code_id?: string | null;
    phone_number?: string | null;
    photo_path?: string | null;
    photo_url?: string | null;
    id_document_path?: string | null;
    is_active?: boolean | null;
    created_at?: string | null;
    [key: string]: unknown;
};

function ActionsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canUpdate = !!auth?.can?.['catalogs.concessionaire.update'];
    const canDelete = !!auth?.can?.['catalogs.concessionaire.delete'];
    const canSetActive = !!auth?.can?.['catalogs.concessionaire.setActive'];

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
                        <Link href={`/catalogs/concessionaire/${row.id}`} className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4" />
                            Ver detalles
                        </Link>
                    </DropdownMenuItem>
                    {canUpdate && (
                        <DropdownMenuItem asChild>
                            <Link href={`/catalogs/concessionaire/${row.id}/edit`} className="cursor-pointer">
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
                description={`¿Está seguro de eliminar el registro "${String(row.full_name ?? row.document_number ?? row.id)}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.delete(`/catalogs/concessionaire/${row.id}`, {
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
                description={`¿Está seguro de ${isActive ? 'desactivar' : 'activar'} el registro "${String(row.full_name ?? row.document_number ?? row.id)}"?`}
                confirmLabel={isActive ? 'Desactivar' : 'Activar'}
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.patch(
                            `/catalogs/concessionaire/${row.id}/active`,
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
    // ID (#) — disponible pero puede ocultarse por defecto desde la página Index
    { accessorKey: 'id', header: '#', enableSorting: true },
    // Avatar (foto miniatura circular)
    {
        id: 'avatar',
        header: '',
        enableSorting: false,
        cell: ({ row }) => {
            const r = row.original as Row;
            const src = r.photo_path ? `/storage/${r.photo_path}` : (r.photo_url ?? undefined);
            const fallback = (r.full_name ?? '').trim().charAt(0).toUpperCase() || 'C';
            return (
                <div className="w-10">
                    <Avatar className="h-9 w-9">
                        <AvatarImage src={src} alt={r.full_name ?? 'Foto'} />
                        <AvatarFallback>{fallback}</AvatarFallback>
                    </Avatar>
                </div>
            );
        },
    },
    // Documento (tipo + número) en una sola columna
    {
        id: 'document',
        header: 'Documento',
        enableSorting: false,
        cell: ({ row }) => {
            const r = row.original as Row;
            const code = r.document_type_code ?? '';
            const num = r.document_number ?? '';
            const composed = code && num ? `${code}-${num}` : `${code}${num}`;
            return (
                <div className="min-w-0">
                    <span className="block max-w-[140px] truncate font-mono text-xs whitespace-nowrap" title={composed}>
                        {composed}
                    </span>
                </div>
            );
        },
    },
    // Nombre completo
    {
        accessorKey: 'full_name',
        header: 'Nombre completo',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = (getValue() as string) ?? '';
            return (
                <div className="min-w-0">
                    <span className="block max-w-[160px] truncate font-medium whitespace-nowrap" title={value}>
                        {value}
                    </span>
                </div>
            );
        },
    },
    // Email
    {
        accessorKey: 'email',
        header: 'Correo electrónico',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = (getValue() as string) ?? '';
            return (
                <div className="min-w-0">
                    <span className="block max-w-[220px] truncate font-mono text-xs whitespace-nowrap" title={value}>
                        {value}
                    </span>
                </div>
            );
        },
    },
    // Tipo de concesionario (nombre, no ID)
    {
        accessorKey: 'concessionaire_type_name',
        header: 'Tipo de concesionario',
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = String(getValue() ?? '');
            return (
                <div className="min-w-0">
                    <span className="block max-w-[180px] truncate text-sm whitespace-nowrap" title={value}>
                        {value}
                    </span>
                </div>
            );
        },
    },
    // Estado (oculto por defecto desde la página Index via columnVisibility)
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
    // Creado (oculto por defecto desde la página Index via columnVisibility)
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
    // Acciones
    {
        id: 'actions',
        header: 'Acciones',
        enableSorting: false,
        cell: ({ row }) => <ActionsCell row={row.original as Row} />,
    },
];
