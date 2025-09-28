import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
import { Edit, Eye, FilePlus2, MoreHorizontal, Power, SplitSquareHorizontal, Trash2 } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

export type Row = {
    id: number | string;
    number?: string | null;
    contract_type_id?: string | null;
    contract_status_id?: string | null;
    contract_modality_id?: string | null;
    trade_category_id?: string | null;
    contract_type?: string | null;
    contract_status?: string | null;
    contract_status_code?: string | null;
    contract_modality?: string | null;
    trade_category?: string | null;
    start_date?: string | null;
    end_date?: string | null;
    billing_day?: string | null;
    monthly_price_eur?: number | null;
    pdf_path?: string | null;
    pdf_file?: string | null;
    is_active?: boolean | null;
    created_at?: string | null;
    locals_count?: number | null;
    locals?: string[] | null;
    [key: string]: unknown;
};

function ActionsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canUpdate = !!auth?.can?.['catalogs.contract.update'];
    const canDelete = !!auth?.can?.['catalogs.contract.delete'];
    const canSetActive = !!auth?.can?.['catalogs.contract.setActive'];

    const [openDelete, setOpenDelete] = React.useState(false);
    const [openToggle, setOpenToggle] = React.useState(false);
    const [openConfirmDlg, setOpenConfirmDlg] = React.useState(false);
    const [openTerminateDlg, setOpenTerminateDlg] = React.useState(false);
    const [openExtendDlg, setOpenExtendDlg] = React.useState(false);
    const [extendDate, setExtendDate] = React.useState<string>('');
    const [extendFile, setExtendFile] = React.useState<File | null>(null);
    const isActive = !!row.is_active;
    const statusCode = String(row.contract_status_code ?? '').toUpperCase();
    const canDeactivate = isActive ? statusCode === 'TERM' : true;
    const isDraft = statusCode === 'BORR';
    const isActiveLike = statusCode === 'VIG' || statusCode === 'EXT';
    const minExtendDate = React.useMemo(() => {
        if (!row.end_date) return undefined as string | undefined;
        try {
            const d = new Date(row.end_date as string);
            d.setDate(d.getDate() + 1);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        } catch {
            return undefined;
        }
    }, [row.end_date]);

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
                        <Link href={`/catalogs/contract/${row.id}`} className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4" />
                            Ver detalles
                        </Link>
                    </DropdownMenuItem>
                    {canUpdate && isDraft && (
                        <DropdownMenuItem asChild>
                            <Link href={`/catalogs/contract/${row.id}/edit`} className="cursor-pointer">
                                <Edit className="mr-2 h-4 w-4" />
                                Editar
                            </Link>
                        </DropdownMenuItem>
                    )}
                    {canUpdate && isDraft && (
                        <DropdownMenuItem
                            onSelect={() => setTimeout(() => setOpenConfirmDlg(true), 50)}
                            className="text-emerald-700 dark:text-emerald-300"
                        >
                            <SplitSquareHorizontal className="mr-2 h-4 w-4" />
                            Confirmar
                        </DropdownMenuItem>
                    )}
                    {canUpdate && isActiveLike && (
                        <DropdownMenuItem onSelect={() => setTimeout(() => setOpenTerminateDlg(true), 50)} className="text-red-600 dark:text-red-400">
                            <Power className="mr-2 h-4 w-4" />
                            Terminar
                        </DropdownMenuItem>
                    )}
                    {canUpdate && isActiveLike && (
                        <DropdownMenuItem
                            onSelect={() => setTimeout(() => setOpenExtendDlg(true), 50)}
                            className="text-indigo-700 dark:text-indigo-300"
                        >
                            <FilePlus2 className="mr-2 h-4 w-4" />
                            Extender
                        </DropdownMenuItem>
                    )}
                    {canSetActive && (
                        <DropdownMenuItem
                            onSelect={() => {
                                if (!isActive || canDeactivate) setTimeout(() => setOpenToggle(true), 100);
                            }}
                            className={
                                isActive
                                    ? canDeactivate
                                        ? 'text-amber-600 focus:text-amber-700 dark:text-amber-400 dark:focus:text-amber-300'
                                        : 'text-muted-foreground cursor-not-allowed'
                                    : 'text-emerald-600 focus:text-emerald-700 dark:text-emerald-400 dark:focus:text-emerald-300'
                            }
                        >
                            <Power className="mr-2 h-4 w-4" />
                            {isActive ? 'Desactivar' : 'Activar'}
                        </DropdownMenuItem>
                    )}
                    {canDelete && isDraft && (
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

            {/* Delete for draft contracts */}
            <ConfirmAlert
                open={openDelete}
                onOpenChange={setOpenDelete}
                title="Eliminar contrato"
                description={`¿Eliminar el contrato "${String(row.number ?? row.id)}"? Solo se permite eliminar en estado Borrador.`}
                confirmLabel="Eliminar"
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.delete(`/catalogs/contract/${row.id}`, {
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
                description={`¿Está seguro de ${isActive ? 'desactivar' : 'activar'} el registro "${String(row.id)}"?`}
                confirmLabel={isActive ? 'Desactivar' : 'Activar'}
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.patch(
                            `/catalogs/contract/${row.id}/active`,
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

            {/* Confirm (BORR -> VIG) */}
            <ConfirmAlert
                open={openConfirmDlg}
                onOpenChange={setOpenConfirmDlg}
                title="Confirmar contrato"
                description={`¿Confirmar el contrato "${String(row.number ?? row.id)}" para pasar a Vigente y ocupar sus locales?`}
                confirmLabel="Confirmar"
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.patch(
                            `/catalogs/contract/${row.id}/confirm`,
                            {},
                            {
                                preserveState: false,
                                preserveScroll: true,
                                onSuccess: () => {
                                    // Avisar a la vista de Locales (si abierta) que recargue
                                    try {
                                        window.dispatchEvent(new CustomEvent('data:locals:changed'));
                                    } catch (_e) {
                                        void _e;
                                    }
                                    resolve();
                                },
                                onError: () => reject(new Error('confirm_failed')),
                            },
                        );
                    });
                }}
            />

            {/* Terminate (VIG/EXT -> TERM) */}
            <ConfirmAlert
                open={openTerminateDlg}
                onOpenChange={setOpenTerminateDlg}
                title="Terminar contrato"
                description={`¿Terminar el contrato "${String(row.number ?? row.id)}"? Liberará sus locales.`}
                confirmLabel="Terminar"
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.patch(
                            `/catalogs/contract/${row.id}/terminate`,
                            {},
                            {
                                preserveState: false,
                                preserveScroll: true,
                                onSuccess: () => {
                                    try {
                                        window.dispatchEvent(new CustomEvent('data:locals:changed'));
                                    } catch (_e) {
                                        void _e;
                                    }
                                    resolve();
                                },
                                onError: () => reject(new Error('terminate_failed')),
                            },
                        );
                    });
                }}
            />

            {/* Extend (VIG/EXT -> EXT) */}
            <Dialog open={openExtendDlg} onOpenChange={setOpenExtendDlg}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Prorrogar contrato</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div>
                            <label htmlFor={`extend_date_${row.id}`} className="text-sm font-medium">
                                Nueva fecha de fin
                            </label>
                            <input
                                id={`extend_date_${row.id}`}
                                type="date"
                                min={minExtendDate}
                                className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                value={extendDate}
                                onChange={(e) => setExtendDate(e.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor={`extend_pdf_${row.id}`} className="text-sm font-medium">
                                Documento de prórroga (PDF, obligatorio)
                            </label>
                            <input
                                id={`extend_pdf_${row.id}`}
                                type="file"
                                accept="application/pdf"
                                className="mt-1 w-full text-sm"
                                onChange={(e) => setExtendFile(e.target.files?.[0] ?? null)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpenExtendDlg(false)}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={async () => {
                                if (!extendDate) {
                                    toast.error('Seleccione la nueva fecha de fin');
                                    return;
                                }
                                if (!extendFile) {
                                    toast.error('Debe adjuntar el PDF de la prórroga');
                                    return;
                                }
                                if (row.end_date) {
                                    try {
                                        const cur = new Date(row.end_date as string);
                                        const nxt = new Date(extendDate);
                                        if (!(nxt > cur)) {
                                            toast.error('La nueva fecha debe ser posterior a la actual');
                                            return;
                                        }
                                    } catch (_e) {
                                        void _e;
                                    }
                                }
                                const fd = new FormData();
                                fd.append('new_end_date', extendDate);
                                if (extendFile) fd.append('extension_pdf', extendFile);
                                await new Promise<void>((resolve, reject) => {
                                    router.post(`/catalogs/contract/${row.id}/extend`, fd, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => resolve(),
                                        onError: () => reject(new Error('extend_failed')),
                                    });
                                });
                                setExtendDate('');
                                setExtendFile(null);
                                setOpenExtendDlg(false);
                            }}
                        >
                            Guardar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

export const columns: ColumnDef<Row>[] = [
    // First: Número (badge)
    {
        accessorKey: 'number',
        header: 'Número',
        enableSorting: true,
        cell: ({ getValue }) => {
            const v = String(getValue() ?? '');
            if (!v) return '';
            return (
                <Badge variant="secondary" className="px-2 py-0.5 font-mono text-xs">
                    {v}
                </Badge>
            );
        },
    },
    // Hidden by default in index state: id
    { accessorKey: 'contract_type', header: 'Tipo', enableSorting: false },
    {
        accessorKey: 'start_date',
        header: 'Fecha inicio',
        enableSorting: true,
        cell: ({ getValue }) => {
            const v = getValue<string | null | undefined>();
            if (!v) return '';
            const d = new Date(v);
            return isNaN(d.getTime()) ? v : d.toLocaleDateString();
        },
    },
    {
        accessorKey: 'end_date',
        header: 'Fecha fin',
        enableSorting: true,
        cell: ({ getValue }) => {
            const v = getValue<string | null | undefined>();
            if (!v) return '';
            const d = new Date(v);
            return isNaN(d.getTime()) ? v : d.toLocaleDateString();
        },
    },
    {
        accessorKey: 'locals_count',
        header: 'Locales',
        enableSorting: true,
        cell: ({ row, getValue }) => {
            const count = (getValue() as number) ?? 0;
            const locals = ((row.original as Row).locals ?? []) as string[];

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
                                    <TooltipContent>Ver locales asignados</TooltipContent>
                                </Tooltip>
                                <PopoverContent className="w-80">
                                    <div className="space-y-2">
                                        <h4 className="text-sm font-medium">Locales asignados ({count})</h4>
                                        <div className="flex max-h-64 flex-wrap gap-1 overflow-auto">
                                            {locals.map((name, i) => (
                                                <Badge key={`loc-${String((row.original as Row).id)}-${i}`} variant="outline" className="text-xs">
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
    { accessorKey: 'trade_category', header: 'Rubro', enableSorting: false },
    {
        accessorKey: 'contract_status',
        header: 'Estado contrato',
        enableSorting: false,
        cell: ({ row }) => {
            const code = String((row.original as Row).contract_status_code ?? '').toUpperCase();
            const name = (row.original as Row).contract_status ?? '';
            let cls = 'bg-muted text-foreground/80';
            if (code === 'BORR') cls = 'bg-amber-100 text-amber-800 dark:bg-amber-400/10 dark:text-amber-300';
            else if (code === 'VIG') cls = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300';
            else if (code === 'EXT') cls = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300';
            else if (code === 'TERM') cls = 'bg-red-100 text-red-700 dark:bg-red-400/10 dark:text-red-300';
            else if (code === 'VENC') cls = 'bg-amber-100 text-amber-800 dark:bg-amber-400/10 dark:text-amber-300';
            return <Badge className={`px-2.5 py-0.5 text-xs font-semibold ${cls}`}>{name || code}</Badge>;
        },
    },
    // Keep actions last as requested order
    {
        id: 'actions',
        header: 'Acciones',
        enableSorting: false,
        cell: ({ row }) => <ActionsCell row={row.original as Row} />,
    },
    // The rest of columns remain available for toggling in the table toolbar
    { accessorKey: 'id', header: '#', enableSorting: true },
    { accessorKey: 'contract_modality', header: 'Modalidad', enableSorting: false },
    { accessorKey: 'billing_day', header: 'Día facturación', enableSorting: true },
    {
        accessorKey: 'monthly_price_eur',
        header: 'Precio mensual (€)',
        enableSorting: true,
        cell: ({ getValue }) => {
            const raw = getValue<number | string | null | undefined>();
            if (raw === null || raw === undefined || raw === '') return '';
            const val = typeof raw === 'string' ? parseFloat(raw) : raw;
            if (typeof val !== 'number' || isNaN(val)) return String(raw);
            try {
                return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' }).format(val);
            } catch {
                return String(val);
            }
        },
    },
    // PDF column removed per requirement
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
    { accessorKey: 'created_at', header: 'Creado', enableSorting: true },
];
