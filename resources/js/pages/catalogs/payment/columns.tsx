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
import { Link, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Edit, Eye, MoreHorizontal, Trash2 } from 'lucide-react';
import React from 'react';

export type Row = {
    id: number | string;
    local_id?: number | null;
    debtor_type?: string | null;
    debtor_id?: number | null;
    debtor_name?: string | null;
    company_bank_account_id?: number | null;
    company_bank_account_label?: string | null;
    method?: string | null;
    origin_bank_id?: number | null;
    origin_bank_name?: string | null;
    payer_document_type?: string | null;
    payer_document_number?: string | null;
    payer_account_number?: string | null;
    payer_phone_e164?: string | null;
    reference?: string | null;
    amount_bs_minor?: number | null;
    paid_on?: string | null;
    fx_rate_id?: number | null;
    status?: string | null;
    gateway_request?: string | null;
    gateway_response?: string | null;
    gateway_resp_code?: string | null;
    gateway_message?: string | null;
    payer_details?: string | null;
    idempotency_key?: string | null;
    created_at?: string | null;
    [key: string]: unknown;
};

function ActionsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canUpdate = !!auth?.can?.['catalogs.payment.update'];
    const canDelete = !!auth?.can?.['catalogs.payment.delete'];
    const [openDelete, setOpenDelete] = React.useState(false);

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
                        <Link href={`/payments/${row.id}`} className="cursor-pointer">
                            <Eye className="mr-2 h-4 w-4" />
                            Ver detalles
                        </Link>
                    </DropdownMenuItem>
                    {canUpdate && (
                        <DropdownMenuItem asChild>
                            <Link href={`/payments/${row.id}/edit`} className="cursor-pointer">
                                <Edit className="mr-2 h-4 w-4" />
                                Editar
                            </Link>
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
                description={`¿Está seguro de eliminar el registro "${String(row.id)}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                onConfirm={async () => {
                    await new Promise<void>((resolve, reject) => {
                        router.delete(`/payments/${row.id}`, {
                            preserveState: false,
                            preserveScroll: true,
                            onSuccess: () => resolve(),
                            onError: () => reject(new Error('delete_failed')),
                        });
                    });
                }}
            />
        </>
    );
}

function fmtDate(d?: string | null): string {
    if (!d) return '—';
    try {
        return new Date(d).toLocaleString('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        });
    } catch {
        return '—';
    }
}

function fmtBsCentsToBs(n?: number | null): string {
    const v = typeof n === 'number' ? n : Number(n ?? 0);
    const bs = v / 100;
    const s = new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(bs);
    return `Bs ${s}`;
}

function statusLabel(v?: string | null): string {
    switch ((v || '').toUpperCase()) {
        case 'REGISTERED':
            return 'Registrado';
        case 'CONFIRMED':
            return 'Confirmado';
        case 'APPLIED':
            return 'Aplicado';
        default:
            return v || '—';
    }
}

export const columns: ColumnDef<Row>[] = [
    { accessorKey: 'id', header: '#', enableSorting: true },
    { accessorKey: 'local_id', header: 'Local', enableSorting: true },
    // Show friendly debtor name instead of id/type
    { accessorKey: 'debtor_name', header: 'Deudor', enableSorting: true },
    { accessorKey: 'company_bank_account_label', header: 'Cuenta receptora', enableSorting: true },
    { accessorKey: 'method', header: 'Método', enableSorting: true },
    { accessorKey: 'origin_bank_name', header: 'Banco origen', enableSorting: true },
    // Documento pagador mostrado como "<tipo>-<número>"
    {
        accessorKey: 'payer_document_number',
        header: 'Documento pagador',
        enableSorting: true,
        cell: ({ row }) => {
            const r = row.original as Row;
            const t = (r as any).document_type_code || (r.payer_document_type ?? '').toString();
            const n = (r.payer_document_number ?? '').toString();
            const sep = t ? '-' : '';
            return t || n ? `${t}${sep}${n}` : '—';
        },
    },
    { accessorKey: 'payer_account_number', header: 'Cuenta pagador', enableSorting: true },
    { accessorKey: 'payer_phone_e164', header: 'Teléfono pagador', enableSorting: true },
    { accessorKey: 'reference', header: 'Referencia', enableSorting: true },
    { accessorKey: 'amount_bs_minor', header: 'Monto (Bs)', enableSorting: true, cell: ({ getValue }) => fmtBsCentsToBs(getValue() as number) },
    { accessorKey: 'paid_on', header: 'Pagado el', enableSorting: true, cell: ({ getValue }) => fmtDate(String(getValue() ?? '')) },
    {
        accessorKey: 'status',
        header: 'Estado',
        enableSorting: true,
        cell: ({ getValue }) => <Badge className="font-medium">{statusLabel(String(getValue() ?? ''))}</Badge>,
    },
    // Remove FX rate and gateway/idempotency details from table per request
    { accessorKey: 'created_at', header: 'Creado', enableSorting: true, cell: ({ getValue }) => fmtDate(String(getValue() ?? '')) },
    {
        id: 'actions',
        header: 'Acciones',
        enableSorting: false,
        cell: ({ row }) => <ActionsCell row={row.original as Row} />,
    },
];
