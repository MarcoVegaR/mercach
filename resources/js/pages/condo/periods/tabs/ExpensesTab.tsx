import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { DataTable } from '@/components/index/DataTable';
import { Button } from '@/components/ui/button';
import { exportVisibleAsCSV, exportVisibleAsJSON } from '@/lib/export-from-table';
import type { SortingState, VisibilityState } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import ExpenseFormDialog from './ExpenseFormDialog';
import { buildExpenseColumns, type ExpRow } from './expenses-columns';

type Period = {
    id: number;
    market_id: number;
    period: string; // YYYY-MM-DD
    expenses?: Array<{
        id: number;
        expense_type_id: number;
        amount_usd_minor: number;
        invoice_number?: string | null;
        expense_date?: string | null;
        attachment_path?: string | null;
        note?: string | null;
    }>;
};

interface Props {
    period: Period;
    canUpdate: boolean;
    options?: { expense_types?: Array<{ id: number; name: string; code?: string }> };
    onTotalsUpdate?: (totals: { expenses_count: number; total_usd_minor: number }) => void;
}

// CSRF helper for non-Inertia JSON calls
const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

export default function ExpensesTab({ period, canUpdate, options, onTotalsUpdate }: Props) {
    // DataTable state
    const [rows, setRows] = React.useState<ExpRow[]>([]);
    const [total, setTotal] = React.useState<number>(0);
    const [pageIndex, setPageIndex] = React.useState(0);
    const [pageSize, setPageSize] = React.useState(10);
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [loading, setLoading] = React.useState(false);
    const initialQ = typeof window !== 'undefined' ? (new URLSearchParams(window.location.search).get('q') ?? '') : '';
    const [globalFilter, setGlobalFilter] = React.useState<string>(initialQ);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({ id: false });

    const [openForm, setOpenForm] = React.useState(false);
    const [formMode, setFormMode] = React.useState<'create' | 'edit'>('create');
    const [editRow, setEditRow] = React.useState<ExpRow | null>(null);
    const [confirmDelete, setConfirmDelete] = React.useState<{ open: boolean; row: ExpRow | null }>({ open: false, row: null });

    // Fetch JSON data for DataTable
    const fetchExpenses = React.useCallback(async () => {
        try {
            setLoading(true);
            const usp = new URLSearchParams();
            usp.set('pageIndex', String(pageIndex));
            usp.set('pageSize', String(pageSize));
            if (sorting[0]) {
                usp.set('sortBy', String(sorting[0].id));
                usp.set('desc', String(!!sorting[0].desc));
            }
            if (globalFilter) {
                usp.set('q', globalFilter);
            }
            const url = `/condo/periods/${period.id}/expenses?${usp.toString()}`;
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Cache-Control': 'no-store, no-cache, must-revalidate' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!res.ok) throw new Error('fetch_failed');
            const data = (await res.json()) as { rows: ExpRow[]; meta: { total: number } };
            setRows(Array.isArray(data.rows) ? data.rows : []);
            setTotal(Number(data.meta?.total ?? 0));
        } catch {
            setRows([]);
            setTotal(0);
            toast.error('No se pudieron cargar los gastos');
        } finally {
            setLoading(false);
        }
    }, [pageIndex, pageSize, sorting, globalFilter, period.id]);

    React.useEffect(() => {
        void fetchExpenses();
    }, [fetchExpenses]);

    // Sync 'q' in URL without navigation (preserve Inertia history state)
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
            // no-op
        }
    }, [globalFilter]);

    // Row actions
    const onEdit = React.useCallback(
        (r: ExpRow) => {
            if (!canUpdate) return;
            setEditRow(r);
            setFormMode('edit');
            setOpenForm(true);
        },
        [canUpdate],
    );

    const onDelete = React.useCallback(
        (r: ExpRow) => {
            if (!canUpdate) return;
            setConfirmDelete({ open: true, row: r });
        },
        [canUpdate],
    );

    const columns = React.useMemo(
        () => buildExpenseColumns({ onEdit, onDelete, canEdit: canUpdate, canDelete: canUpdate }),
        [onEdit, onDelete, canUpdate],
    );

    // Debounced search like Users/Roles index
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
                        columns={columns}
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
                        getRowId={(r) => String((r as ExpRow).id)}
                        enableRowSelection={false}
                        enableGlobalFilter={true}
                        globalFilter={globalFilter}
                        onGlobalFilterChange={debouncedSearch}
                        columnVisibility={columnVisibility}
                        onColumnVisibilityChange={setColumnVisibility}
                        canExport={true}
                        onExportClick={(fmt, table) => {
                            if (fmt === 'json') {
                                return exportVisibleAsJSON(table as any, 'gastos.json');
                            }
                            // Use CSV for both CSV and XLSX (Excel-compatible CSV)
                            const filename = fmt === 'xlsx' ? 'gastos.xlsx' : 'gastos.csv';
                            return exportVisibleAsCSV(table as any, filename);
                        }}
                        toolbar={
                            canUpdate ? (
                                <div className="ml-auto">
                                    <Button
                                        type="button"
                                        onClick={() => {
                                            setFormMode('create');
                                            setEditRow(null);
                                            setOpenForm(true);
                                        }}
                                        className="inline-flex items-center gap-2"
                                        size="sm"
                                    >
                                        <Plus className="h-4 w-4" /> Agregar gasto
                                    </Button>
                                </div>
                            ) : null
                        }
                        stickyLeftColumnId="type_name"
                    />
                </div>
            </div>

            {/* Form dialog */}
            <ExpenseFormDialog
                open={openForm}
                onOpenChange={setOpenForm}
                periodId={period.id}
                usedExpenseTypeIds={rows.map((r) => r.expense_type_id)}
                options={(options?.expense_types ?? []).map((t) => ({ id: t.id, name: t.name }))}
                mode={formMode}
                row={editRow}
                onSaved={(totals) => {
                    setPageIndex(0);
                    void fetchExpenses();
                    if (totals) onTotalsUpdate?.(totals);
                }}
            />

            {/* Delete confirm */}
            <ConfirmAlert
                open={confirmDelete.open}
                onOpenChange={(v) => setConfirmDelete((prev) => ({ ...prev, open: v }))}
                title="Eliminar gasto"
                description="Esta acción no se puede deshacer."
                confirmLabel="Eliminar"
                onConfirm={async () => {
                    const r = confirmDelete.row;
                    if (!r) return;
                    try {
                        const res = await fetch(`/condo/expenses/${r.id}`, {
                            method: 'DELETE',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) throw new Error('delete_failed');
                        const body = await res.json().catch(() => ({}) as any);
                        toast.success('Gasto eliminado');
                        setConfirmDelete({ open: false, row: null });
                        await fetchExpenses();
                        if ((body as any)?.totals) onTotalsUpdate?.((body as any).totals);
                    } catch {
                        toast.error('No se pudo eliminar el gasto');
                    }
                }}
            />
        </div>
    );
}
