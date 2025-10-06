import { Badge } from '@/components/ui/badge';
import type { ColumnDef } from '@tanstack/react-table';
import { DollarSign, Euro } from 'lucide-react';

export type Row = {
    id: number | string;
    market_id: number;
    market_name?: string | null;
    local_id: number;
    local_name?: string | null;
    local_area_m2?: number | null;
    contract_id?: number | null;
    contract_number?: string | null;
    debtor_type: string;
    debtor_id: number;
    kind: string;
    currency: string;
    amount_minor: number;
    period: string;
    issued_on?: string | null;
    due_on?: string | null;
    charge_status_id?: number | null;
    charge_status_name?: string | null;
    source?: string | null;
    created_at?: string | null;
    [key: string]: unknown;
};

function formatMinorToMajor(v: unknown): string {
    const n = Number(v ?? 0);
    const major = n / 100;
    // Use fixed decimals with dot separator to match examples like 0.10 and 6.90
    return major.toFixed(2);
}

function formatDate(v?: string | null): string {
    if (!v) return '—';
    try {
        return new Date(v).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: '2-digit' });
    } catch {
        return String(v);
    }
}

function formatPeriod(v?: string | null): string {
    if (!v) return '—';
    try {
        return new Date(v).toLocaleDateString('es-ES', { year: 'numeric', month: 'short' });
    } catch {
        return String(v);
    }
}

function friendlyKind(kind: string): string {
    switch (kind) {
        case 'RENT_EUR_M2':
            return 'Alquiler por Convenio';
        case 'RENT_EUR_FIXED':
            return 'Alquiler por Contrato';
        case 'CONDO_USD':
            return 'Gastos Comunes';
        default:
            return kind;
    }
}

function friendlySource(src?: string | null): string {
    const s = (src || '').toUpperCase();
    switch (s) {
        case 'SYSTEM':
            return 'Sistema';
        case 'RUN':
        case 'GENERATED':
            return 'Generado';
        case 'MANUAL':
            return 'Manual';
        default:
            return src || '—';
    }
}

function CurrencyIcon({ code }: { code?: string | null }) {
    const c = (code || '').toUpperCase();
    if (c === 'USD') return <DollarSign className="h-4 w-4 text-emerald-600" />;
    if (c === 'EUR') return <Euro className="h-4 w-4 text-indigo-600" />;
    return <span className="font-mono text-xs">{c || '—'}</span>;
}

export const columns: ColumnDef<Row>[] = [
    { accessorKey: 'id', header: '#', enableSorting: true },
    {
        id: 'market_id',
        header: 'Mercado',
        accessorFn: (row) => String((row as Row).market_name ?? '—'),
        enableSorting: true,
    },
    {
        id: 'local_id',
        header: 'Local',
        accessorFn: (row) => String((row as Row).local_name ?? '—'),
        enableSorting: true,
    },
    {
        accessorKey: 'local_area_m2',
        header: 'm²',
        enableSorting: true,
        cell: ({ row }) => {
            const v = Number((row.original as Row).local_area_m2 ?? 0);
            return isFinite(v) ? v.toFixed(2) : '0.00';
        },
    },
    { accessorKey: 'debtor_type', header: 'Deudor tipo', enableSorting: true },
    { accessorKey: 'debtor_id', header: 'Deudor ID', enableSorting: true },
    {
        accessorKey: 'kind',
        header: 'Tipo',
        enableSorting: true,
        cell: ({ getValue }) => friendlyKind(String(getValue() ?? '')),
    },
    {
        accessorKey: 'currency',
        header: 'Moneda',
        enableSorting: true,
        cell: ({ row }) => <CurrencyIcon code={(row.original as Row).currency} />,
    },
    {
        accessorKey: 'amount_minor',
        header: 'Monto',
        enableSorting: true,
        cell: ({ row }) => {
            const r = row.original as Row;
            return (
                <span className="inline-flex items-center gap-1">
                    <CurrencyIcon code={r.currency} />
                    <span>{formatMinorToMajor(r.amount_minor)}</span>
                </span>
            );
        },
    },
    {
        accessorKey: 'period',
        header: 'Periodo',
        enableSorting: true,
        cell: ({ getValue }) => formatPeriod(getValue() as string | null | undefined),
    },
    {
        accessorKey: 'issued_on',
        header: 'Emitido',
        enableSorting: true,
        cell: ({ getValue }) => formatDate(getValue() as string | null | undefined),
    },
    {
        accessorKey: 'due_on',
        header: 'Vence',
        enableSorting: true,
        cell: ({ getValue }) => formatDate(getValue() as string | null | undefined),
    },
    {
        id: 'charge_status_id',
        header: 'Estado',
        accessorFn: (row) => (row as Row).charge_status_name ?? '—',
        enableSorting: true,
        cell: ({ getValue }) => {
            const name = String(getValue() ?? '—');
            if (!name || name === '—') return name;
            return (
                <Badge variant="default" className="font-medium">
                    {name}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'source',
        header: 'Origen',
        enableSorting: true,
        cell: ({ getValue }) => friendlySource(getValue() as string | null | undefined),
    },
    {
        accessorKey: 'created_at',
        header: 'Creado',
        enableSorting: true,
        cell: ({ getValue }) => formatDate(getValue() as string | null | undefined),
    },
];
