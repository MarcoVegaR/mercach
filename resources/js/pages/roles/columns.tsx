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
import { ColumnDef } from '@tanstack/react-table';
import { format, formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { Edit, Eye, MoreHorizontal, Power, Trash2 } from 'lucide-react';
import React from 'react';

export type TRole = {
    id: number;
    name: string;
    guard_name: string;
    permissions_count: number;
    permissions?: Array<{ id: number; name: string; description?: string }>;
    users_count: number;
    users?: string[];
    is_active: boolean;
    created_at: string;
};

function RoleActionsCell({ role }: { role: TRole }) {
    const [open, setOpen] = React.useState(false);
    const [openToggle, setOpenToggle] = React.useState(false);
    const { auth } = usePage<{
        auth?: { can?: Record<string, boolean> };
    }>().props;

    const canUpdate = !!auth?.can?.['roles.update'];
    const canDelete = !!auth?.can?.['roles.delete'];
    const canSetActive = !!auth?.can?.['roles.setActive'];

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
                        <Link href={`/roles/${role.id}`} className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4" />
                            Ver detalles
                        </Link>
                    </DropdownMenuItem>
                    {canUpdate && (
                        <DropdownMenuItem asChild>
                            <Link href={`/roles/${role.id}/edit`} className="cursor-pointer">
                                <Edit className="mr-2 h-4 w-4" />
                                Editar
                            </Link>
                        </DropdownMenuItem>
                    )}
                    {canSetActive && (
                        <DropdownMenuItem
                            onSelect={() => {
                                setTimeout(() => setOpenToggle(true), 100);
                            }}
                            className={
                                role.is_active
                                    ? 'text-amber-600 focus:text-amber-700 dark:text-amber-400 dark:focus:text-amber-300'
                                    : 'text-emerald-600 focus:text-emerald-700 dark:text-emerald-400 dark:focus:text-emerald-300'
                            }
                        >
                            <Power className="mr-2 h-4 w-4" />
                            {role.is_active ? 'Desactivar' : 'Activar'}
                        </DropdownMenuItem>
                    )}
                    {canDelete && (
                        <DropdownMenuItem
                            onSelect={() => {
                                // Defer to avoid focus conflicts with closing menu
                                setTimeout(() => setOpen(true), 100);
                            }}
                            className="text-red-600 focus:text-red-700 dark:text-red-400 dark:focus:text-red-300"
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Eliminar
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmAlert
                open={open}
                onOpenChange={setOpen}
                title="Eliminar Rol"
                description={`¿Está seguro de eliminar el rol "${role.name}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.delete(`/roles/${role.id}`, {
                            preserveState: false,
                            preserveScroll: true,
                            onSuccess: () => resolve(),
                            onError: () => reject(new Error('delete_failed')),
                        });
                    });
                }}
                toastMessages={{
                    loading: `Eliminando "${role.name}"…`,
                    success: 'Rol eliminado',
                    error: 'No se pudo eliminar el rol',
                }}
            />
            <ConfirmAlert
                open={openToggle}
                onOpenChange={setOpenToggle}
                title={role.is_active ? 'Desactivar Rol' : 'Activar Rol'}
                description={`¿Está seguro de ${role.is_active ? 'desactivar' : 'activar'} el rol "${role.name}"?`}
                confirmLabel={role.is_active ? 'Desactivar' : 'Activar'}
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.patch(
                            `/roles/${role.id}/active`,
                            { active: !role.is_active },
                            {
                                preserveState: false,
                                preserveScroll: true,
                                onSuccess: () => resolve(),
                                onError: () => reject(new Error('set_active_failed')),
                            },
                        );
                    });
                }}
                toastMessages={{
                    loading: `${role.is_active ? 'Desactivando' : 'Activando'} "${role.name}"…`,
                    success: role.is_active ? 'Rol desactivado' : 'Rol activado',
                    error: 'No se pudo cambiar el estado del rol',
                }}
            />
        </>
    );
}

export const columns: ColumnDef<TRole>[] = [
    {
        accessorKey: 'id',
        header: '#',
        meta: {
            exportable: true,
        },
        enableSorting: true,
    },
    {
        accessorKey: 'name',
        header: 'Nombre',
        meta: {
            exportable: true,
        },
        enableSorting: true,
        cell: ({ getValue }) => {
            const name = String(getValue() ?? '');
            return (
                <div className="min-w-0">
                    <span className="truncate font-medium" title={name}>
                        {name}
                    </span>
                </div>
            );
        },
    },
    {
        accessorKey: 'users_count',
        header: 'Usuarios',
        meta: {
            exportable: true,
        },
        enableSorting: true,
        cell: ({ row, getValue }) => {
            const count = getValue() as number;
            const users = (row.original.users || []) as string[];

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
                        {users.length > 0 ? (
                            <Popover>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <PopoverTrigger asChild>
                                            <Badge variant="secondary" className="cursor-pointer font-medium">
                                                {count}
                                            </Badge>
                                        </PopoverTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>Ver usuarios asignados</TooltipContent>
                                </Tooltip>
                                <PopoverContent className="w-80">
                                    <div className="space-y-2">
                                        <h4 className="text-sm font-medium">Usuarios asignados ({count})</h4>
                                        <div className="flex max-h-64 flex-wrap gap-1 overflow-auto">
                                            {users.map((name, i) => (
                                                <Badge key={`user-${row.original.id}-${i}`} variant="outline" className="text-xs">
                                                    {name}
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
        accessorKey: 'permissions_count',
        header: 'Permisos',
        meta: {
            exportable: true,
        },
        enableSorting: true,
        cell: ({ row, getValue }) => {
            const count = getValue() as number;
            const permissions = row.original.permissions || [];
            const allNames: string[] = permissions.map((perm) =>
                typeof perm === 'object' && perm?.description
                    ? String(perm.description)
                    : typeof perm === 'object' && perm?.name
                      ? String(perm.name)
                      : String(perm),
            );

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
                        <Popover>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <PopoverTrigger asChild>
                                        <Badge variant="secondary" className="cursor-pointer font-medium">
                                            {count}
                                        </Badge>
                                    </PopoverTrigger>
                                </TooltipTrigger>
                                <TooltipContent>Ver permisos asignados</TooltipContent>
                            </Tooltip>
                            <PopoverContent className="w-80">
                                <div className="space-y-2">
                                    <h4 className="text-sm font-medium">Permisos asignados ({count})</h4>
                                    <div className="flex max-h-64 flex-wrap gap-1 overflow-auto">
                                        {allNames.length > 0 ? (
                                            allNames.map((name, i) => (
                                                <Badge key={`perm-${row.original.id}-${i}`} variant="outline" className="text-xs">
                                                    {name}
                                                </Badge>
                                            ))
                                        ) : (
                                            <span className="text-muted-foreground text-sm">Sin permisos detallados</span>
                                        )}
                                    </div>
                                </div>
                            </PopoverContent>
                        </Popover>
                    </div>
                </TooltipProvider>
            );
        },
    },
    {
        accessorKey: 'guard_name',
        header: 'Guard',
        meta: {
            exportable: true,
        },
        enableSorting: true,
        cell: ({ getValue }) => {
            const guard = String(getValue() ?? 'web');
            return (
                <Badge variant="outline" className="font-mono text-xs">
                    {guard}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'created_at',
        header: 'Creado',
        meta: {
            exportable: true,
        },
        enableSorting: true,
        cell: ({ getValue }) => {
            const value = getValue() as string;
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
        accessorKey: 'is_active',
        header: 'Estado',
        meta: {
            exportable: true,
        },
        enableSorting: true,
        cell: ({ getValue }) => {
            const isActive = getValue() as boolean;
            return (
                <div className="flex items-center gap-2">
                    <span
                        className={'h-2 w-2 shrink-0 rounded-full ' + (isActive ? 'bg-emerald-500' : 'bg-red-400')}
                        aria-label={isActive ? 'Activo' : 'Inactivo'}
                    />
                    <Badge variant={isActive ? 'default' : 'destructive'} className="font-medium">
                        {isActive ? 'Activo' : 'Inactivo'}
                    </Badge>
                </div>
            );
        },
    },
    {
        id: 'actions',
        header: 'Acciones',
        meta: {
            exportable: false,
        },
        enableSorting: false,
        cell: ({ row }) => {
            const role = row.original;
            return <RoleActionsCell role={role} />;
        },
    },
];
