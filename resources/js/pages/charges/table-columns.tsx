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
import { router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { DollarSign, Euro, MoreHorizontal } from 'lucide-react';
import React from 'react';

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
    amount_bs_minor_issued?: number | null;
    allocated_bs_minor?: number | null;
    fx_rate_issued_id?: number | null;
    period: string;
    issued_on?: string | null;
    due_on?: string | null;
    charge_status_id?: number | null;
    charge_status_name?: string | null;
    charge_status_code?: string | null;
    source?: string | null;
    created_at?: string | null;
    [key: string]: unknown;
};

export type FxNow = { USD?: number | null; EUR?: number | null };

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
            return 'Tasa de uso por convenio';
        case 'RENT_EUR_FIXED':
            return 'Alquiler por Contrato';
        case 'CONDO_USD':
            return 'Gastos Comunes';
        case 'FINE':
            return 'Multa';
        case 'ADJ':
            return 'Ajuste';
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

function statusClasses(code?: string | null): string {
    const c = (code || '').toUpperCase();
    if (c === 'ISSUED') return 'bg-amber-100 text-amber-800 border border-amber-200';
    if (c === 'PARTIAL') return 'bg-sky-100 text-sky-800 border border-sky-200';
    if (c === 'SETTLED') return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
    if (c === 'CANCELED') return 'bg-slate-200 text-slate-700 border border-slate-300 line-through';
    return 'bg-slate-100 text-slate-800 border border-slate-200';
}

function ActionsCell({ row }: { row: Row }) {
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;
    const canCancel = !!auth?.can?.['charges.cancel'];
    const statusCode = String(row.charge_status_code ?? '').toUpperCase();
    const isCancelable = canCancel && (statusCode === 'ISSUED' || statusCode === 'PARTIAL');

    const [openCancel, setOpenCancel] = React.useState(false);

    if (!isCancelable) return null;

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
                    <DropdownMenuItem
                        onSelect={() => setTimeout(() => setOpenCancel(true), 100)}
                        className="text-amber-600 focus:text-amber-700 dark:text-amber-400 dark:focus:text-amber-300"
                    >
                        Anular cargo
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmAlert
                open={openCancel}
                onOpenChange={setOpenCancel}
                title="Anular cargo"
                description={`¿Está seguro de anular el cargo #${String(row.id)}? Esta acción no se puede deshacer.`}
                confirmLabel="Anular"
                requireReason
                reasonLabel="Motivo de anulación"
                reasonPlaceholder="Ej: Error en la emisión, se generó por duplicado..."
                reasonMinLength={5}
                onConfirm={async (reason) => {
                    const trimmed = (reason || '').trim();
                    const payload = trimmed !== '' ? { note: trimmed } : {};

                    await new Promise<void>((resolve, reject) => {
                        router.post(`/charges/${row.id}/cancel`, payload, {
                            preserveState: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                router.reload({ only: ['rows', 'meta', 'flash', 'stats'] });
                                resolve();
                            },
                            onError: () => reject(new Error('cancel_failed')),
                        });
                    });
                }}
            />
        </>
    );
}

export function buildColumns(fxNow?: FxNow): ColumnDef<Row>[] {
    const fmtBs = (v: number): string => 'Bs ' + new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
    const getRate = (ccy?: string | null): number | null => {
        const c = String(ccy || '').toUpperCase();
        if (c === 'USD') return typeof fxNow?.USD === 'number' ? fxNow!.USD! : null;
        if (c === 'EUR') return typeof fxNow?.EUR === 'number' ? fxNow!.EUR! : null;
        return null;
    };

    return [
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
            id: 'amount_bs',
            header: 'Monto (Bs)',
            enableSorting: false,
            cell: ({ row }) => {
                const r = row.original as Row;
                const statusCode = String(r.charge_status_code ?? '').toUpperCase();
                if (statusCode === 'SETTLED') {
                    const allocated = Number(r.allocated_bs_minor ?? NaN);
                    if (Number.isFinite(allocated)) {
                        return fmtBs(allocated / 100);
                    }
                }
                if (statusCode === 'CANCELED') {
                    const issued = Number(r.amount_bs_minor_issued ?? NaN);
                    if (Number.isFinite(issued)) {
                        return fmtBs(Number(issued) / 100);
                    }
                }
                const rate = getRate(r.currency);
                if (!rate || Number.isNaN(rate)) return '—';
                const bs = (Number(r.amount_minor ?? 0) / 100) * Number(rate);
                return fmtBs(bs);
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
            cell: ({ row, getValue }) => {
                const name = String(getValue() ?? '—');
                if (!name || name === '—') return name;
                const code = (row.original as Row).charge_status_code ?? null;
                return (
                    <Badge variant="outline" className={'px-2 py-0.5 font-medium ' + statusClasses(code)}>
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
        {
            id: 'actions',
            header: 'Acciones',
            enableSorting: false,
            cell: ({ row }) => <ActionsCell row={row.original as Row} />,
        },
    ];
}
