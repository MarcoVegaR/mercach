import type { ColumnDef } from '@tanstack/react-table';

export type TBankValidationRow = {
    id: number;
    paid_on: string;
    reference: string;
    origin_account: string | null;
    destination_account: string | null;
    amount_bs: number;
    payer_document: string | null;
    gateway_resp_code: string | null;
    gateway_message: string | null;
    req_id: string | null;
    status: string;
    method: string | null;
};

export const columns: ColumnDef<TBankValidationRow>[] = [
    {
        accessorKey: 'paid_on',
        header: 'Fecha de pago',
        enableSorting: true,
        cell: ({ row }) => <span className="whitespace-nowrap">{row.original.paid_on}</span>,
    },
    {
        accessorKey: 'reference',
        header: 'Nro. Referencia',
        enableSorting: true,
        cell: ({ row }) => <span className="font-mono text-xs">{row.original.reference}</span>,
    },
    {
        accessorKey: 'origin_account',
        header: 'Cuenta/Origen',
        enableSorting: false,
        cell: ({ row }) => <span className="max-w-[180px] truncate font-mono text-xs">{row.original.origin_account || '-'}</span>,
    },
    {
        accessorKey: 'destination_account',
        header: 'Cuenta/Destino',
        enableSorting: false,
        cell: ({ row }) => <span className="max-w-[220px] truncate text-xs">{row.original.destination_account || '-'}</span>,
    },
    {
        accessorKey: 'amount_bs',
        header: 'Monto',
        enableSorting: true,
        cell: ({ row }) => (
            <span className="font-medium">
                {new Intl.NumberFormat('es-VE', { style: 'currency', currency: 'VES' }).format(row.original.amount_bs)}
            </span>
        ),
        meta: { align: 'right' },
    },
    {
        accessorKey: 'payer_document',
        header: 'Cedula/RIF',
        enableSorting: false,
        cell: ({ row }) => <span className="font-mono text-xs">{row.original.payer_document || '-'}</span>,
    },
    {
        accessorKey: 'gateway_resp_code',
        header: 'Código',
        enableSorting: true,
        cell: ({ row }) => <span className="font-mono text-xs">{row.original.gateway_resp_code || '-'}</span>,
    },
    {
        accessorKey: 'gateway_message',
        header: 'Respuesta',
        enableSorting: false,
        cell: ({ row }) => <span className="text-muted-foreground text-xs">{row.original.gateway_message || '-'}</span>,
    },
    {
        accessorKey: 'req_id',
        header: 'ReqId',
        enableSorting: false,
        cell: ({ row }) => <span className="text-muted-foreground font-mono text-xs">{row.original.req_id || '-'}</span>,
    },
];
