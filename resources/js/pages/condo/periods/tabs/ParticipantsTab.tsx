import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { DataTable } from '@/components/index/DataTable';
import { Button } from '@/components/ui/button';
import type { SortingState, VisibilityState } from '@tanstack/react-table';
import { Trash2 } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import ParticipantsExcludeDialog from './ParticipantsExcludeDialog';

type Period = {
    id: number;
    market_id: number;
    period: string; // YYYY-MM-DD
};

interface Props {
    period: Period;
    canUpdate: boolean;
    onTotalsUpdate?: (totals: { participants_count: number }) => void;
}

type ExRow = {
    id: number;
    condo_period_id: number;
    local_id: number;
    local_code: string;
    local_name: string;
    area_m2_snapshot: string;
    included: boolean; // always false in this tab
    is_active: boolean;
};

export default function ParticipantsTab({ period, canUpdate, onTotalsUpdate }: Props) {
    const [rows, setRows] = React.useState<ExRow[]>([]);
    const [total, setTotal] = React.useState<number>(0);
    const [pageIndex, setPageIndex] = React.useState(0);
    const [pageSize, setPageSize] = React.useState(10);
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [loading, setLoading] = React.useState(false);
    const initialQ = typeof window !== 'undefined' ? (new URLSearchParams(window.location.search).get('q') ?? '') : '';
    const [globalFilter, setGlobalFilter] = React.useState<string>(initialQ);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({ id: false, included: false, is_active: false });

    const [openExclude, setOpenExclude] = React.useState(false);
    const [confirmDelete, setConfirmDelete] = React.useState<{ open: boolean; row: ExRow | null }>({ open: false, row: null });

    const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

    const fetchExcluded = React.useCallback(async () => {
        try {
            setLoading(true);
            const usp = new URLSearchParams();
            usp.set('pageIndex', String(pageIndex));
            usp.set('pageSize', String(pageSize));
            if (sorting[0]) {
                usp.set('sortBy', String(sorting[0].id));
                usp.set('desc', String(!!sorting[0].desc));
            }
            if (globalFilter) usp.set('q', globalFilter);
            const res = await fetch(`/condo/periods/${period.id}/participants?${usp.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Cache-Control': 'no-store' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!res.ok) throw new Error('fetch_failed');
            const data = (await res.json()) as { rows: ExRow[]; meta: { total: number } };
            setRows(Array.isArray(data.rows) ? data.rows : []);
            setTotal(Number(data.meta?.total ?? 0));
        } catch {
            setRows([]);
            setTotal(0);
        } finally {
            setLoading(false);
        }
    }, [pageIndex, pageSize, sorting, globalFilter, period.id]);

    React.useEffect(() => {
        void fetchExcluded();
    }, [fetchExcluded]);

    // Keep 'q' synced in URL without navigation
    React.useEffect(() => {
        if (typeof window === 'undefined') return;
        const usp = new URLSearchParams(window.location.search);
        if (globalFilter) usp.set('q', globalFilter);
        else usp.delete('q');
        const qs = usp.toString();
        const newUrl = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
        try {
            window.history.replaceState(window.history.state, '', newUrl);
        } catch {
            // Ignore history errors
        }
    }, [globalFilter]);

    const onDelete = React.useCallback(
        (r: ExRow) => {
            if (!canUpdate) return;
            setConfirmDelete({ open: true, row: r });
        },
        [canUpdate],
    );

    const columns = React.useMemo(() => {
        return [
            {
                accessorKey: 'local_code',
                header: 'Local',
                cell: ({ row }: any) => <div className="font-medium">{(row.original as ExRow).local_code}</div>,
            },
            { accessorKey: 'local_name', header: 'Nombre', cell: ({ row }: any) => (row.original as ExRow).local_name },
            { accessorKey: 'area_m2_snapshot', header: 'Área m²', cell: ({ row }: any) => (row.original as ExRow).area_m2_snapshot },
            {
                id: 'actions',
                header: '',
                cell: ({ row }: any) => (
                    <Button variant="ghost" size="sm" onClick={() => onDelete(row.original as ExRow)} className="text-destructive">
                        <Trash2 className="h-4 w-4" />
                    </Button>
                ),
            },
        ] as any;
    }, [onDelete]);

    const debouncedSearch = React.useMemo(() => {
        let timeoutId: ReturnType<typeof setTimeout>;
        return (value: string) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                setGlobalFilter(value);
                setPageIndex(0);
            }, 300);
        };
    }, []);

    return (
        <div className="space-y-6">
            <div className="overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <div className="p-4">
                    <DataTable
                        columns={columns as any}
                        data={rows}
                        rowCount={total}
                        pageIndex={pageIndex}
                        pageSize={pageSize}
                        onPageChange={setPageIndex}
                        onPageSizeChange={(s) => {
                            setPageSize(s);
                            setPageIndex(0);
                        }}
                        sorting={sorting}
                        onSortingChange={setSorting}
                        isLoading={loading}
                        getRowId={(r) => String((r as ExRow).id)}
                        enableRowSelection={false}
                        enableGlobalFilter={true}
                        globalFilter={globalFilter}
                        onGlobalFilterChange={debouncedSearch}
                        columnVisibility={columnVisibility}
                        onColumnVisibilityChange={setColumnVisibility}
                        toolbar={
                            canUpdate ? (
                                <div className="ml-auto">
                                    <Button type="button" size="sm" onClick={() => setOpenExclude(true)}>
                                        Excluir locales
                                    </Button>
                                </div>
                            ) : null
                        }
                        stickyLeftColumnId="local_code"
                        searchPlaceholder="Buscar local (código o nombre)"
                    />
                </div>
            </div>

            <ParticipantsExcludeDialog
                open={openExclude}
                onOpenChange={setOpenExclude}
                periodId={period.id}
                marketId={period.market_id}
                onSaved={(totals) => {
                    setPageIndex(0);
                    void fetchExcluded();
                    if (totals) onTotalsUpdate?.(totals);
                }}
            />

            <ConfirmAlert
                open={confirmDelete.open}
                onOpenChange={(v) => setConfirmDelete((prev) => ({ ...prev, open: v }))}
                title="Incluir local (quitar de excluidos)"
                description="Esta acción quita el local de la lista de excluidos."
                confirmLabel="Incluir"
                onConfirm={async () => {
                    const r = confirmDelete.row;
                    if (!r) return;
                    try {
                        const res = await fetch(`/condo/participants/${r.id}`, {
                            method: 'DELETE',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) {
                            const body = await res.json().catch(() => ({}) as any);
                            throw new Error((body as any)?.message || 'No se pudo incluir el local');
                        }
                        const body = await res.json().catch(() => ({}) as any);
                        toast.success('Local incluido exitosamente');
                        setConfirmDelete({ open: false, row: null });
                        await fetchExcluded();
                        if ((body as any)?.totals) onTotalsUpdate?.((body as any).totals);
                    } catch (e: any) {
                        toast.error(e?.message || 'No se pudo incluir el local');
                    }
                }}
            />
        </div>
    );
}
