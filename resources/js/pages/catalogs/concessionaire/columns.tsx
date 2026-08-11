import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { format, formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { Edit, Eye, MoreHorizontal, Power, Printer, Trash2, UserPlus } from 'lucide-react';
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
    portal_user_exists?: boolean | null;
    last_life_proof_at?: string | null;
    life_proof_due_on?: string | null;
    life_proof_status?: 'current' | 'requires_citation' | 'missing';
    life_proof_requires_citation?: boolean;
    created_at?: string | null;
    active_locals_count?: number;
    active_locals?: string[];
    active_locals_text?: string;
    active_contract_numbers?: string[];
    active_contracts_text?: string;
    active_contracts_detailed?: { id: number; number: string }[];
    [key: string]: unknown;
};

export function printLifeProofForms(ids: Array<number | string>) {
    const params = new URLSearchParams();
    ids.forEach((id) => params.append('ids[]', String(id)));
    window.open(`/catalogs/concessionaire/life-proof-forms?${params.toString()}`, '_blank', 'noopener,noreferrer');
}

function ActionsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canUpdate = !!auth?.can?.['catalogs.concessionaire.update'];
    const canDelete = !!auth?.can?.['catalogs.concessionaire.delete'];
    const canSetActive = !!auth?.can?.['catalogs.concessionaire.setActive'];

    const [openDelete, setOpenDelete] = React.useState(false);
    const [openToggle, setOpenToggle] = React.useState(false);
    const [openInvite, setOpenInvite] = React.useState(false);
    const isActive = !!row.is_active;

    const invite = useForm<{ name: string; email: string }>({
        name: String(row.full_name ?? ''),
        email: String(row.email ?? ''),
    });

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
                    <DropdownMenuItem onSelect={() => printLifeProofForms([row.id])}>
                        <Printer className="mr-2 h-4 w-4" />
                        Imprimir fe de vida
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href={`/catalogs/concessionaire/${row.id}`} className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4" />
                            Ver detalles
                        </Link>
                    </DropdownMenuItem>
                    {canUpdate && row.portal_user_exists !== true && (
                        <DropdownMenuItem onSelect={() => setTimeout(() => setOpenInvite(true), 100)}>
                            <UserPlus className="mr-2 h-4 w-4" />
                            Generar usuario de Portal
                        </DropdownMenuItem>
                    )}
                    {canUpdate && row.portal_user_exists === true && (
                        <DropdownMenuItem
                            onSelect={() =>
                                setTimeout(() => {
                                    router.post(`/catalogs/concessionaire/${row.id}/portal-users/reset`, {}, { preserveScroll: true });
                                }, 100)
                            }
                        >
                            <UserPlus className="mr-2 h-4 w-4" />
                            Restablecer acceso
                        </DropdownMenuItem>
                    )}
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

            {/* Generar usuario de Portal */}
            <Dialog open={openInvite} onOpenChange={setOpenInvite}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Generar usuario de Portal</DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.post(`/catalogs/concessionaire/${row.id}/portal-users`, invite.data, {
                                preserveScroll: true,
                                onSuccess: () => setOpenInvite(false),
                            });
                        }}
                        className="space-y-4"
                    >
                        <div className="space-y-2">
                            <Label htmlFor={`name-${row.id}`}>Nombre</Label>
                            <Input id={`name-${row.id}`} value={invite.data.name} onChange={(e) => invite.setData('name', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`email-${row.id}`}>Correo</Label>
                            <Input id={`email-${row.id}`} type="email" value={invite.data.email} disabled readOnly />
                            <p className="text-muted-foreground text-xs">
                                Debe coincidir con el correo registrado del concesionario. Para cambiarlo, actualiza el correo del concesionario.
                            </p>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="ghost" onClick={() => setOpenInvite(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit">Invitar</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

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

function ActiveContractsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canViewContract = !!auth?.can?.['catalogs.contract.view'];

    const r = row;
    const detailed = (r.active_contracts_detailed ?? []) as { id: number; number: string }[];
    const numbersFallback = (r.active_contract_numbers ?? []) as string[];

    const items: { id: number; number: string }[] = detailed.length > 0 ? detailed : numbersFallback.map((num) => ({ id: 0, number: num }));

    if (!items.length) {
        return (
            <div className="flex items-center">
                <Badge variant="outline" className="text-muted-foreground text-xs">
                    0
                </Badge>
            </div>
        );
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
                                    const key = `concessionaire-${String(r.id)}-contract-${i}`;
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
    // ID (#) — disponible pero puede ocultarse por defecto desde la página Index
    { accessorKey: 'id', header: '#', enableSorting: true },
    // Avatar (foto miniatura circular)
    {
        id: 'avatar',
        header: '',
        enableSorting: false,
        meta: { exportable: false },
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
        accessorKey: 'document_number',
        header: 'Documento',
        enableSorting: true,
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
    {
        accessorKey: 'last_life_proof_at',
        header: 'Fe de vida',
        enableSorting: true,
        cell: ({ row }) => {
            const item = row.original as Row;
            const status = item.life_proof_status ?? 'missing';
            const labels = {
                current: 'Vigente',
                requires_citation: 'Requiere citación',
                missing: 'Sin registro',
            };
            const classes = {
                current: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
                requires_citation: 'bg-red-100 text-red-700 dark:bg-red-400/10 dark:text-red-300',
                missing: 'bg-slate-100 text-slate-700 dark:bg-slate-400/10 dark:text-slate-300',
            };
            const date = item.last_life_proof_at ? format(new Date(`${item.last_life_proof_at}T12:00:00`), 'dd MMM yyyy', { locale: es }) : null;

            return (
                <div className="flex min-w-[130px] flex-col items-start gap-1">
                    <Badge className={classes[status]}>{labels[status]}</Badge>
                    {date && <span className="text-muted-foreground text-xs">{date}</span>}
                </div>
            );
        },
    },
    {
        accessorKey: 'active_locals_count',
        header: 'Locales',
        accessorFn: (row) => {
            const r = row as Row;
            return r.active_locals_text && r.active_locals_text.length > 0 ? r.active_locals_text : (r.active_locals ?? []).map(String).join('\n');
        },
        meta: {
            exportable: true,
            exportHeader: 'Locales',
            exportFormat: (value: unknown, row: Row): string => {
                const text: string | undefined = row.active_locals_text;
                if (text && text.length > 0) return text;
                const locals = Array.isArray(row.active_locals) ? row.active_locals : [];
                return locals.map((c) => String(c)).join('\n');
            },
        },
        enableSorting: true,
        cell: ({ row, getValue: _getValue }) => {
            const count = Number((row.original as Row).active_locals_count ?? 0);
            const locals = ((row.original as Row).active_locals || []) as string[];

            if (!count) {
                return (
                    <div className="flex items-center">
                        <Badge variant="outline" className="text-muted-foreground text-xs">
                            0
                        </Badge>
                    </div>
                );
            }

            return (
                <TooltipProvider>
                    <div className="flex items-center">
                        {locals.length > 0 ? (
                            <Popover>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <PopoverTrigger asChild>
                                            <Badge variant="secondary" className="cursor-pointer font-medium">
                                                {count}
                                            </Badge>
                                        </PopoverTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>Ver locales</TooltipContent>
                                </Tooltip>
                                <PopoverContent className="w-80">
                                    <div className="space-y-2">
                                        <h4 className="text-sm font-medium">Locales ({count})</h4>
                                        <div className="flex max-h-64 flex-wrap gap-1 overflow-auto">
                                            {locals.map((code, i) => (
                                                <Badge
                                                    key={`local-${String((row.original as Row).id)}-${i}`}
                                                    variant="outline"
                                                    className="font-mono text-xs"
                                                >
                                                    {code}
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
        id: 'active_contracts',
        header: 'Contratos',
        enableSorting: false,
        cell: ({ row }) => <ActiveContractsCell row={row.original as Row} />,
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
        meta: { exportable: false },
        cell: ({ row }) => <ActionsCell row={row.original as Row} />,
    },
];
