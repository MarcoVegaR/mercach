import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { FileDropzone } from '@/components/ui/file-dropzone';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Link, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { AlertCircle, Calendar, Edit, Eye, FilePlus2, FileText, Info, MoreHorizontal, Power, SplitSquareHorizontal, Trash2 } from 'lucide-react';
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
    signed_at?: string | null;
    is_active?: boolean | null;
    created_at?: string | null;
    locals_count?: number | null;
    locals?: string[] | null;
    concessionaires_count?: number | null;
    concessionaires_text?: string | null;
    concessionaires_detailed?: { id: number; name: string; document?: string | null }[] | null;
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
    const [openSignDlg, setOpenSignDlg] = React.useState(false);
    const [extendDate, setExtendDate] = React.useState<string>('');
    const [extendFile, setExtendFile] = React.useState<File | null>(null);
    const [extendDateError, setExtendDateError] = React.useState<string>('');
    const [signNumber, setSignNumber] = React.useState<string>('');
    const [signEndDate, setSignEndDate] = React.useState<string>('');
    const [signFile, setSignFile] = React.useState<File | null>(null);
    const [signDateError, setSignDateError] = React.useState<string>('');

    // Pre-cargar datos en diálogo Firmar cuando se abre
    React.useEffect(() => {
        if (openSignDlg) {
            setSignNumber(String(row.number ?? ''));
            setSignEndDate(row.end_date ? String(row.end_date) : '');
            setSignFile(null);
            setSignDateError('');
        }
    }, [openSignDlg, row.number, row.end_date]);

    // Limpiar errores de Extend cuando se abre
    React.useEffect(() => {
        if (openExtendDlg) {
            setExtendDate('');
            setExtendFile(null);
            setExtendDateError('');
        }
    }, [openExtendDlg]);
    const isActive = !!row.is_active;
    const statusCode = String(row.contract_status_code ?? '').toUpperCase();
    const canDeactivate = isActive ? statusCode === 'TERM' : true;
    const isDraft = statusCode === 'BORR';
    const isActiveLike = statusCode === 'VIG' || statusCode === 'EXT';
    const isExpired = statusCode === 'VENC';
    const isUnsigned = !row.signed_at;
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
                    {canUpdate && (isActiveLike || isExpired) && (
                        <DropdownMenuItem onSelect={() => setTimeout(() => setOpenTerminateDlg(true), 50)} className="text-red-600 dark:text-red-400">
                            <Power className="mr-2 h-4 w-4" />
                            Terminar
                        </DropdownMenuItem>
                    )}
                    {canUpdate && (isActiveLike || isExpired) && !isUnsigned && (
                        <DropdownMenuItem
                            onSelect={() => setTimeout(() => setOpenExtendDlg(true), 50)}
                            className="text-indigo-700 dark:text-indigo-300"
                        >
                            <FilePlus2 className="mr-2 h-4 w-4" />
                            Extender
                        </DropdownMenuItem>
                    )}
                    {canUpdate && statusCode === 'VIG' && isUnsigned && (
                        <DropdownMenuItem
                            onSelect={() => setTimeout(() => setOpenSignDlg(true), 50)}
                            className="text-emerald-700 dark:text-emerald-300"
                        >
                            <SplitSquareHorizontal className="mr-2 h-4 w-4" />
                            Firmar
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

            {/* Extend (VIG/EXT -> EXT) - Diseño Moderno */}
            <Dialog open={openExtendDlg} onOpenChange={setOpenExtendDlg}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-xl">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/20">
                                <FilePlus2 className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            Prorrogar contrato
                        </DialogTitle>
                        <DialogDescription>Extiende la vigencia del contrato adjuntando el documento de prórroga firmado.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-5 py-4">
                        {/* Info contextual */}
                        <Alert className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30">
                            <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            <AlertDescription className="text-sm text-blue-800 dark:text-blue-300">
                                Fecha actual de fin:{' '}
                                <strong>{row.end_date ? new Date(row.end_date as string).toLocaleDateString('es-ES') : 'No definida'}</strong>
                            </AlertDescription>
                        </Alert>

                        {/* Campo fecha */}
                        <div className="space-y-2">
                            <Label htmlFor={`extend_date_${row.id}`} className="flex items-center gap-2">
                                <Calendar className="h-4 w-4 text-indigo-600" />
                                Nueva fecha de fin <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id={`extend_date_${row.id}`}
                                type="date"
                                min={minExtendDate}
                                value={extendDate}
                                onChange={(e) => {
                                    setExtendDate(e.target.value);
                                    setExtendDateError('');
                                }}
                                className={extendDateError ? 'border-destructive' : ''}
                            />
                            {extendDateError && (
                                <p className="text-destructive flex items-center gap-1 text-sm">
                                    <AlertCircle className="h-3 w-3" />
                                    {extendDateError}
                                </p>
                            )}
                            <p className="text-muted-foreground text-xs">Debe ser posterior a la fecha actual de fin</p>
                        </div>

                        {/* Campo archivo */}
                        <div className="space-y-2">
                            <Label htmlFor={`extend_pdf_${row.id}`} className="flex items-center gap-2">
                                <FileText className="h-4 w-4 text-indigo-600" />
                                Documento de prórroga <span className="text-destructive">*</span>
                            </Label>
                            <FileDropzone
                                onFileSelect={(file) => setExtendFile(file)}
                                file={extendFile}
                                accept="application/pdf"
                                maxSize="10 MB"
                                placeholder="Seleccionar PDF firmado"
                            />
                            <p className="text-muted-foreground text-xs">Formato PDF, máximo 10 MB</p>
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button variant="outline" onClick={() => setOpenExtendDlg(false)} type="button">
                            Cancelar
                        </Button>
                        <Button
                            onClick={async () => {
                                // Validaciones con feedback visual
                                let hasError = false;

                                if (!extendDate) {
                                    setExtendDateError('Seleccione la nueva fecha de fin');
                                    toast.error('Complete todos los campos requeridos');
                                    hasError = true;
                                }

                                if (!extendFile) {
                                    toast.error('Debe adjuntar el PDF de la prórroga');
                                    hasError = true;
                                }

                                if (row.end_date && extendDate) {
                                    try {
                                        const cur = new Date(row.end_date as string);
                                        const nxt = new Date(extendDate);
                                        if (!(nxt > cur)) {
                                            setExtendDateError('La nueva fecha debe ser posterior a la actual');
                                            toast.error('La nueva fecha debe ser posterior a la actual');
                                            hasError = true;
                                        }
                                    } catch (_e) {
                                        void _e;
                                    }
                                }

                                if (hasError) return;

                                const fd = new FormData();
                                fd.append('new_end_date', extendDate);
                                if (extendFile) fd.append('extension_pdf', extendFile);

                                await new Promise<void>((resolve, reject) => {
                                    router.post(`/catalogs/contract/${row.id}/extend`, fd, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            toast.success('Contrato prorrogado correctamente');
                                            resolve();
                                        },
                                        onError: () => reject(new Error('extend_failed')),
                                    });
                                });
                                setExtendDate('');
                                setExtendFile(null);
                                setExtendDateError('');
                                setOpenExtendDlg(false);
                            }}
                        >
                            <FilePlus2 className="mr-2 h-4 w-4" />
                            Guardar prórroga
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Sign (VIG unsigned -> set signed_at) - Diseño Moderno */}
            <Dialog open={openSignDlg} onOpenChange={setOpenSignDlg}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-xl">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/20">
                                <SplitSquareHorizontal className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            Firmar contrato
                        </DialogTitle>
                        <DialogDescription>Registra la firma del contrato actualizando número, fecha final y documento firmado.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-5 py-4">
                        {/* Info contextual */}
                        <Alert className="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30">
                            <Info className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                            <AlertDescription className="text-sm text-amber-800 dark:text-amber-300">
                                Contrato provisional. Completa los datos del contrato firmado.
                            </AlertDescription>
                        </Alert>

                        {/* Campo número */}
                        <div className="space-y-2">
                            <Label htmlFor={`sign_number_${row.id}`} className="flex items-center gap-2">
                                <FileText className="h-4 w-4 text-emerald-600" />
                                Número del contrato
                            </Label>
                            <Input
                                id={`sign_number_${row.id}`}
                                type="text"
                                value={signNumber}
                                onChange={(e) => setSignNumber(e.target.value)}
                                placeholder="Ej: CONT-2024-001"
                            />
                            <p className="text-muted-foreground text-xs">Número oficial asignado al contrato firmado</p>
                        </div>

                        {/* Campo fecha fin con validación */}
                        <div className="space-y-2">
                            <Label htmlFor={`sign_end_${row.id}`} className="flex items-center gap-2">
                                <Calendar className="h-4 w-4 text-emerald-600" />
                                Fecha de fin
                            </Label>
                            <Input
                                id={`sign_end_${row.id}`}
                                type="date"
                                value={signEndDate}
                                min={row.start_date ? String(row.start_date) : undefined}
                                onChange={(e) => {
                                    setSignEndDate(e.target.value);
                                    setSignDateError('');

                                    // Validación en tiempo real
                                    if (row.start_date && e.target.value) {
                                        try {
                                            const start = new Date(row.start_date as string);
                                            const end = new Date(e.target.value);
                                            if (end < start) {
                                                setSignDateError('La fecha fin debe ser igual o posterior a la fecha de inicio');
                                            }
                                        } catch (_e) {
                                            void _e;
                                        }
                                    }
                                }}
                                className={signDateError ? 'border-destructive' : ''}
                            />
                            {signDateError && (
                                <p className="text-destructive flex items-center gap-1 text-sm">
                                    <AlertCircle className="h-3 w-3" />
                                    {signDateError}
                                </p>
                            )}
                            {row.start_date && (
                                <p className="text-muted-foreground text-xs">
                                    Fecha de inicio: <strong>{new Date(row.start_date as string).toLocaleDateString('es-ES')}</strong>
                                </p>
                            )}
                        </div>

                        {/* Campo archivo */}
                        <div className="space-y-2">
                            <Label htmlFor={`sign_pdf_${row.id}`} className="flex items-center gap-2">
                                <FileText className="h-4 w-4 text-emerald-600" />
                                Contrato firmado (PDF)
                            </Label>
                            <FileDropzone
                                onFileSelect={(file) => setSignFile(file)}
                                file={signFile}
                                existingFileUrl={row.pdf_path ? `/${row.pdf_path}` : undefined}
                                existingFileName={row.pdf_path ? String(row.pdf_path).split('/').pop() : undefined}
                                accept="application/pdf"
                                maxSize="10 MB"
                                placeholder="Seleccionar PDF firmado"
                            />
                            <p className="text-muted-foreground text-xs">Actualiza el documento con la versión firmada si es necesario</p>
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => {
                                setOpenSignDlg(false);
                                setSignDateError('');
                            }}
                            type="button"
                        >
                            Cancelar
                        </Button>
                        <Button
                            onClick={async () => {
                                // Validación fecha fin >= fecha inicio
                                if (row.start_date && signEndDate) {
                                    try {
                                        const start = new Date(row.start_date as string);
                                        const end = new Date(signEndDate);
                                        if (end < start) {
                                            setSignDateError('La fecha fin debe ser igual o posterior a la fecha de inicio');
                                            toast.error('La fecha fin no puede ser anterior a la fecha de inicio');
                                            return;
                                        }
                                    } catch (_e) {
                                        void _e;
                                    }
                                }

                                const fd = new FormData();
                                if (signNumber) fd.append('number', signNumber);
                                if (signEndDate) fd.append('end_date', signEndDate);
                                if (signFile) fd.append('pdf', signFile);

                                await new Promise<void>((resolve, reject) => {
                                    router.patch(`/catalogs/contract/${row.id}/sign`, fd, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            toast.success('Contrato firmado correctamente');
                                            resolve();
                                        },
                                        onError: () => reject(new Error('sign_failed')),
                                    });
                                });
                                setSignNumber('');
                                setSignEndDate('');
                                setSignFile(null);
                                setSignDateError('');
                                setOpenSignDlg(false);
                            }}
                            disabled={!!signDateError}
                        >
                            <SplitSquareHorizontal className="mr-2 h-4 w-4" />
                            Registrar firma
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function ConcessionairesCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canViewConcessionaire = !!auth?.can?.['catalogs.concessionaire.view'];

    const r = row;
    const items = (r.concessionaires_detailed ?? []) as { id: number; name: string; document?: string | null }[];

    if (!items.length) {
        return (
            <div className="flex items-center justify-center">
                <Badge variant="outline" className="text-muted-foreground text-xs">
                    0
                </Badge>
            </div>
        );
    }

    const count = items.length;

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
                        <TooltipContent>Ver concesionarios</TooltipContent>
                    </Tooltip>
                    <PopoverContent className="w-80">
                        <div className="space-y-2">
                            <h4 className="text-sm font-medium">Concesionarios ({count})</h4>
                            <div className="flex max-h-64 flex-col gap-1 overflow-auto">
                                {items.map((item, i) => {
                                    const key = `contract-${String(r.id)}-concessionaire-${i}`;
                                    const label = item.document ? `${item.document} — ${item.name}` : item.name;
                                    const content = <span className="inline-flex items-center gap-1 text-xs">{label}</span>;

                                    if (canViewConcessionaire && item.id > 0) {
                                        return (
                                            <Link key={key} href={`/catalogs/concessionaire/${item.id}`} className="inline-block">
                                                {content}
                                            </Link>
                                        );
                                    }

                                    return (
                                        <span key={key} className="inline-block">
                                            {content}
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
        accessorFn: (row) => {
            const v = row.start_date;
            if (!v) return '';
            const d = new Date(v as string);
            return isNaN(d.getTime()) ? String(v) : d.toLocaleDateString();
        },
        cell: ({ getValue }) => {
            const formatted = getValue<string>();
            return formatted || '';
        },
    },
    {
        accessorKey: 'end_date',
        header: 'Fecha fin',
        enableSorting: true,
        accessorFn: (row) => {
            const v = row.end_date;
            if (!v) return '';
            const d = new Date(v as string);
            return isNaN(d.getTime()) ? String(v) : d.toLocaleDateString();
        },
        cell: ({ getValue }) => {
            const formatted = getValue<string>();
            return formatted || '';
        },
    },
    {
        accessorKey: 'locals_count',
        header: 'Locales',
        enableSorting: true,
        accessorFn: (row) => {
            const locals = (row.locals ?? []) as string[];
            // For export/copy: return the list of locals separated by comma
            return locals.length > 0 ? locals.join(', ') : '0';
        },
        cell: ({ row, getValue: _getValue }) => {
            const count = ((row.original as Row).locals_count as number) ?? 0;
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
    {
        id: 'concessionaires',
        header: 'Concesionarios',
        enableSorting: false,
        cell: ({ row }) => <ConcessionairesCell row={row.original as Row} />,
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
            const isUnsigned = !(row.original as Row).signed_at && code === 'VIG';
            return (
                <div className="flex items-center gap-2">
                    <Badge className={`px-2.5 py-0.5 text-xs font-semibold ${cls}`}>{name || code}</Badge>
                    {isUnsigned ? (
                        <Badge className="bg-amber-50 px-2 py-0.5 text-[10px] text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">
                            Provisional
                        </Badge>
                    ) : null}
                </div>
            );
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
    {
        accessorKey: 'created_at',
        header: 'Creado',
        enableSorting: true,
        accessorFn: (row) => {
            const v = row.created_at;
            if (!v) return '';
            const d = new Date(v as string);
            return isNaN(d.getTime()) ? String(v) : d.toLocaleDateString();
        },
        cell: ({ getValue }) => {
            const formatted = getValue<string>();
            return formatted || '';
        },
    },
];
