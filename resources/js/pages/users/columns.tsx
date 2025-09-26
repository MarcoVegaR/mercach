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

export type TUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles_count: number;
    roles?: string[];
    created_at: string;
};

function UserActionsCell({ user }: { user: TUser }) {
    const [openDelete, setOpenDelete] = React.useState(false);
    const [openToggle, setOpenToggle] = React.useState(false);
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;

    const canUpdate = !!auth?.can?.['users.update'];
    const canDelete = !!auth?.can?.['users.delete'];
    const canSetActive = !!auth?.can?.['users.setActive'];

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
                        <Link href={`/users/${user.id}`} className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4" />
                            Ver detalles
                        </Link>
                    </DropdownMenuItem>
                    {canUpdate && (
                        <DropdownMenuItem asChild>
                            <Link href={`/users/${user.id}/edit`} className="cursor-pointer">
                                <Edit className="mr-2 h-4 w-4" />
                                Editar
                            </Link>
                        </DropdownMenuItem>
                    )}
                    {canSetActive && (
                        <DropdownMenuItem
                            onSelect={() => setTimeout(() => setOpenToggle(true), 100)}
                            className={
                                user.is_active
                                    ? 'text-amber-600 focus:text-amber-700 dark:text-amber-400 dark:focus:text-amber-300'
                                    : 'text-emerald-600 focus:text-emerald-700 dark:text-emerald-400 dark:focus:text-emerald-300'
                            }
                        >
                            <Power className="mr-2 h-4 w-4" />
                            {user.is_active ? 'Desactivar' : 'Activar'}
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

            {/* Toggle Active */}
            <ConfirmAlert
                open={openToggle}
                onOpenChange={setOpenToggle}
                title={user.is_active ? 'Desactivar Usuario' : 'Activar Usuario'}
                description={`¿Está seguro de ${user.is_active ? 'desactivar' : 'activar'} el usuario "${user.name}"?`}
                confirmLabel={user.is_active ? 'Desactivar' : 'Activar'}
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.patch(
                            `/users/${user.id}/active`,
                            { active: !user.is_active },
                            {
                                preserveState: false,
                                preserveScroll: true,
                                onSuccess: () => resolve(),
                                onError: () => reject(new Error('user_toggle_failed')),
                            },
                        );
                    });
                }}
                toastMessages={{
                    loading: `${user.is_active ? 'Desactivando' : 'Activando'} "${user.name}"…`,
                    success: user.is_active ? 'Usuario desactivado' : 'Usuario activado',
                    error: 'No se pudo cambiar el estado del usuario',
                }}
            />

            {/* Delete */}
            <ConfirmAlert
                open={openDelete}
                onOpenChange={setOpenDelete}
                title="Eliminar Usuario"
                description={`¿Está seguro de eliminar el usuario "${user.name}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.delete(`/users/${user.id}`, {
                            preserveState: false,
                            preserveScroll: true,
                            onSuccess: () => resolve(),
                            onError: () => reject(new Error('user_delete_failed')),
                        });
                    });
                }}
                toastMessages={{
                    loading: `Eliminando "${user.name}"…`,
                    success: 'Usuario eliminado',
                    error: 'No se pudo eliminar el usuario',
                }}
            />
        </>
    );
}

export const columns: ColumnDef<TUser>[] = [
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
                    <span className="block max-w-[160px] truncate font-medium whitespace-nowrap" title={name}>
                        {name}
                    </span>
                </div>
            );
        },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        meta: {
            exportable: true,
        },
        enableSorting: true,
        cell: ({ getValue }) => {
            const email = String(getValue() ?? '');
            return (
                <div className="min-w-0">
                    <span className="block max-w-[220px] truncate font-mono text-xs whitespace-nowrap" title={email}>
                        {email}
                    </span>
                </div>
            );
        },
    },
    {
        accessorKey: 'roles_count',
        header: 'Roles',
        meta: {
            exportable: true,
        },
        enableSorting: true,
        cell: ({ row, getValue }) => {
            const count = getValue() as number;
            const roles = (row.original.roles || []) as string[];

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
                        {roles.length > 0 ? (
                            <Popover>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <PopoverTrigger asChild>
                                            <Badge variant="secondary" className="cursor-pointer font-medium">
                                                {count}
                                            </Badge>
                                        </PopoverTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>Ver roles asignados</TooltipContent>
                                </Tooltip>
                                <PopoverContent className="w-80">
                                    <div className="space-y-2">
                                        <h4 className="text-sm font-medium">Roles asignados ({count})</h4>
                                        <div className="flex max-h-64 flex-wrap gap-1 overflow-auto">
                                            {roles.map((name, i) => (
                                                <Badge key={`role-${row.original.id}-${i}`} variant="outline" className="text-xs">
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
        accessorKey: 'is_active',
        header: 'Estado',
        meta: {
            exportable: true,
        },
        enableSorting: true,
        cell: ({ getValue }) => {
            const isActive = !!getValue();
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
        id: 'actions',
        header: 'Acciones',
        meta: {
            exportable: false,
        },
        enableSorting: false,
        cell: ({ row }) => {
            const user = row.original;
            return <UserActionsCell user={user} />;
        },
    },
];
