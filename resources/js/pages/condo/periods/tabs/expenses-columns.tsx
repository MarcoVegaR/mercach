import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import { Edit, Eye, MoreHorizontal, Trash2 } from 'lucide-react';

export type ExpRow = {
    id: number;
    condo_period_id: number;
    expense_type_id: number;
    type_name: string;
    amount_usd_minor: number;
    invoice_number?: string | null;
    expense_date?: string | null; // YYYY-MM-DD
    attachment_url?: string | null;
    note?: string | null;
    is_active?: boolean;
};

export function buildExpenseColumns(opts: {
    onEdit: (row: ExpRow) => void;
    onDelete: (row: ExpRow) => void;
    canEdit: boolean;
    canDelete: boolean;
}): ColumnDef<ExpRow>[] {
    const { onEdit, onDelete, canEdit, canDelete } = opts;

    const columns: ColumnDef<ExpRow>[] = [
        { accessorKey: 'id', header: '#', enableSorting: true },
        {
            accessorKey: 'type_name',
            header: 'Tipo',
            enableSorting: true,
            cell: ({ getValue }) => {
                const v = String(getValue() ?? '');
                return (
                    <span className="block max-w-[260px] truncate font-semibold text-slate-800 dark:text-slate-100" title={v}>
                        {v}
                    </span>
                );
            },
        },
        {
            accessorKey: 'amount_usd_minor',
            header: 'Monto USD',
            enableSorting: true,
            cell: ({ getValue }) => {
                const minor = Number(getValue() ?? 0) || 0;
                const f = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'USD' }).format(minor / 100);
                return (
                    <span className="rounded px-2 py-0.5 font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:text-emerald-300 dark:ring-emerald-800">
                        {f}
                    </span>
                );
            },
        },
        {
            accessorKey: 'expense_date',
            header: 'Fecha',
            enableSorting: true,
            cell: ({ getValue }) => {
                const s = getValue() as string | null | undefined;
                if (!s) return <span className="text-muted-foreground">—</span>;
                try {
                    const d = new Date(s);
                    return <span className="whitespace-nowrap">{format(d, 'yyyy-MM-dd')}</span>;
                } catch {
                    return <span>{s}</span>;
                }
            },
        },
        {
            accessorKey: 'invoice_number',
            header: 'Factura',
            enableSorting: true,
            cell: ({ getValue }) => {
                const v = String(getValue() ?? '');
                return v ? <span className="font-mono text-xs">{v}</span> : <span className="text-muted-foreground">—</span>;
            },
        },
        {
            id: 'actions',
            header: 'Acciones',
            enableSorting: false,
            cell: ({ row }) => {
                const r = row.original as ExpRow;
                return (
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
                            {r.attachment_url && (
                                <DropdownMenuItem asChild>
                                    <a href={r.attachment_url} target="_blank" rel="noreferrer" className="cursor-pointer">
                                        <Eye className="mr-2 h-4 w-4" /> Ver comprobante
                                    </a>
                                </DropdownMenuItem>
                            )}
                            {canEdit && (
                                <DropdownMenuItem onSelect={() => setTimeout(() => onEdit(r), 0)} className="cursor-pointer">
                                    <Edit className="mr-2 h-4 w-4" /> Editar
                                </DropdownMenuItem>
                            )}
                            {canDelete && (
                                <DropdownMenuItem
                                    onSelect={() => setTimeout(() => onDelete(r), 0)}
                                    className="cursor-pointer text-red-600 dark:text-red-400"
                                >
                                    <Trash2 className="mr-2 h-4 w-4" /> Eliminar
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return columns;
}
