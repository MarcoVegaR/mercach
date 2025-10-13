import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { DataTable } from '@/components/index/DataTable';
import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ColumnFiltersState, RowSelectionState, SortingState, VisibilityState } from '@tanstack/react-table';
import { Banknote, Database, Plus } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { columns, type Row as TRow } from './columns';

interface IndexProps extends PageProps {
    rows: TRow[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number;
        to: number;
    };
    stats?: { total?: number; active?: number };
    flash?: { success?: string; error?: string; warning?: string; info?: string };
    auth?: { can?: Record<string, boolean> };
}

export default function IndexPage() {
    const { rows, meta, auth, stats, flash } = usePage<IndexProps>().props;

    // State
    const [pageIndex, setPageIndex] = React.useState(Math.max(0, ((meta as any)?.current_page ?? (meta as any)?.currentPage ?? 1) - 1));
    const [pageSize, setPageSize] = React.useState(((meta as any)?.per_page ?? (meta as any)?.perPage ?? 10) as number);
    const [globalFilter, setGlobalFilter] = React.useState('');
    const [sorting, setSorting] = React.useState<SortingState>([{ id: 'paid_on', desc: true }]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({
        // Hide '#' by default and keep 'Creado' hidden
        id: false,
        created_at: false,
        // Hide sensitive payer fields and reference by default
        payer_document_number: false,
        payer_account_number: false,
        payer_phone_e164: false,
        reference: false,
    });
    const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});
    const [density, setDensity] = React.useState<'comfortable' | 'compact'>(() => {
        if (typeof window === 'undefined') return 'comfortable';
        const saved = window.localStorage.getItem('payment_table_density');
        return saved === 'compact' ? 'compact' : 'comfortable';
    });

    const permissions = {
        canCreate: auth?.can?.['catalogs.payment.create'] || false,
        canEdit: auth?.can?.['catalogs.payment.update'] || false,
        canDelete: auth?.can?.['catalogs.payment.delete'] || false,
        canBulkDelete: auth?.can?.['catalogs.payment.delete'] || false,
    };

    // Debounce search
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

    const reloadData = React.useCallback(() => {
        const params: Record<string, string | number | boolean> = {
            page: pageIndex + 1,
            per_page: pageSize,
        };

        if (globalFilter) params.q = globalFilter;
        if (sorting.length > 0) {
            const s = sorting[0];
            params.sort = s.id as string;
            params.dir = s.desc ? 'desc' : 'asc';
        }

        router.get('/payments', params, {
            only: ['rows', 'meta'],
            preserveState: true,
            preserveScroll: true,
        });
    }, [pageIndex, pageSize, globalFilter, sorting]);

    React.useEffect(() => {
        reloadData();
    }, [reloadData]);

    // Flash messages
    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);

    const breadcrumbs = [{ title: 'Pagos', href: '/payments' }];

    const getSelectedIds = React.useCallback((): number[] => {
        const ids = Object.keys(rowSelection).map((key) => Number(key));
        return Array.from(new Set(ids.filter((v) => Number.isFinite(v) && Number.isInteger(v) && v > 0)));
    }, [rowSelection]);

    const [openBulkDelete, setOpenBulkDelete] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });
    const handleBulkDelete = React.useCallback(() => {
        const selected = getSelectedIds();
        setOpenBulkDelete({ show: true, count: selected.length });
    }, [getSelectedIds]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pagos" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {/* Header hero (with description) */}
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={Banknote}
                                title="Pagos"
                                description="Listado y gestión de pagos. Desde aquí puede registrar, verificar y consultar el estado de los pagos."
                                actions={
                                    permissions.canCreate ? (
                                        <Link href="/payments/create">
                                            <Button className="flex items-center gap-2">
                                                <Plus className="h-4 w-4" />
                                                Nuevo Pago
                                            </Button>
                                        </Link>
                                    ) : undefined
                                }
                            />
                        </div>

                        {/* Stats Cards (optional) */}
                        {stats?.total !== undefined && (
                            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Pagos</p>
                                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                                {stats?.total ?? meta?.total ?? rows.length}
                                            </p>
                                        </div>
                                        <Database className="h-8 w-8 text-indigo-500 opacity-50" />
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Main Table Card */}
                        <div className="overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                            <div className="p-6">
                                <DataTable
                                    columns={columns}
                                    data={rows}
                                    rowCount={meta?.total ?? rows.length}
                                    pageIndex={pageIndex}
                                    pageSize={pageSize}
                                    onPageChange={setPageIndex}
                                    onPageSizeChange={(size) => {
                                        setPageSize(size);
                                        setPageIndex(0);
                                    }}
                                    sorting={sorting}
                                    onSortingChange={setSorting}
                                    globalFilter={globalFilter}
                                    onGlobalFilterChange={debouncedSearch}
                                    searchPlaceholder="Buscar referencia, banco, documento..."
                                    columnFilters={columnFilters}
                                    onColumnFiltersChange={setColumnFilters}
                                    columnVisibility={columnVisibility}
                                    onColumnVisibilityChange={setColumnVisibility}
                                    rowSelection={rowSelection}
                                    onRowSelectionChange={setRowSelection}
                                    permissions={permissions}
                                    onDeleteSelectedClick={permissions.canBulkDelete ? handleBulkDelete : undefined}
                                    canExport={false}
                                    enableRowSelection={true}
                                    enableGlobalFilter={true}
                                    density={density}
                                    onDensityChange={(d) => {
                                        setDensity(d);
                                        if (typeof window !== 'undefined') window.localStorage.setItem('payment_table_density', d);
                                    }}
                                    stickyLeftColumnId="reference"
                                    getRowId={(row) => String((row as unknown as { id?: number | string }).id ?? '')}
                                />
                            </div>
                        </div>

                        <ConfirmAlert
                            open={openBulkDelete.show}
                            onOpenChange={(open) => !open && setOpenBulkDelete({ show: false, count: 0 })}
                            title="Eliminar seleccionados"
                            description={`¿Está seguro de eliminar ${openBulkDelete.count} registro(s)? Esta acción no se puede deshacer.`}
                            confirmLabel="Eliminar"
                            onConfirm={async () => {
                                const ids = getSelectedIds();
                                await new Promise<void>((resolve, reject) => {
                                    router.post(
                                        '/payments/bulk',
                                        { action: 'delete', ids },
                                        {
                                            preserveState: false,
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setRowSelection({});
                                                resolve();
                                            },
                                            onError: () => reject(new Error('bulk_delete_failed')),
                                        },
                                    );
                                });
                                setOpenBulkDelete({ show: false, count: 0 });
                            }}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
