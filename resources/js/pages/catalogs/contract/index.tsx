import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { DataTable } from '@/components/index/DataTable';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ColumnFiltersState, RowSelectionState, SortingState, VisibilityState } from '@tanstack/react-table';
import { Database, Plus } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { columns, type Row as TRow } from './columns';
import { ContractFilters, type FilterOptions as ContractFilterOptions, type Filters as ContractFilterValue } from './filters';

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
    filterOptions?: ContractFilterOptions;
}

export default function IndexPage() {
    const { rows, meta, auth, stats, flash, filterOptions } = usePage<IndexProps>().props;

    // State
    const [pageIndex, setPageIndex] = React.useState(Math.max(0, ((meta as any)?.current_page ?? (meta as any)?.currentPage ?? 1) - 1));
    const [pageSize, setPageSize] = React.useState(((meta as any)?.per_page ?? (meta as any)?.perPage ?? 10) as number);
    const [globalFilter, setGlobalFilter] = React.useState('');
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({
        // Defaults requested by user
        id: false,
        contract_modality: false,
        is_active: false,
        // Show by default
        contract_status: true,
        number: true,
        start_date: true,
        end_date: true,
        trade_category: true,
        // Others hidden by default unless toggled
        billing_day: false,
        monthly_price_eur: false,
        created_at: false,
    });
    const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});
    const [density, setDensity] = React.useState<'comfortable' | 'compact'>(() => {
        if (typeof window === 'undefined') return 'comfortable';
        const saved = window.localStorage.getItem('contract_table_density');
        return saved === 'compact' ? 'compact' : 'comfortable';
    });

    const permissions = {
        canCreate: auth?.can?.['catalogs.contract.create'] || false,
        canEdit: auth?.can?.['catalogs.contract.update'] || false,
        canDelete: auth?.can?.['catalogs.contract.delete'] || false,
        canExport: auth?.can?.['catalogs.contract.export'] || false,
        canBulkDelete: auth?.can?.['catalogs.contract.delete'] || false,
        canSetActive: auth?.can?.['catalogs.contract.setActive'] || false,
        canBulkSetActive: auth?.can?.['catalogs.contract.setActive'] || false,
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

    // Server-side filters
    const [filters, setFilters] = React.useState<ContractFilterValue>({});
    const didMountRef = React.useRef(false);

    const reloadData = React.useCallback(() => {
        const params: Record<string, string | number | boolean | Record<string, any>> = {
            page: pageIndex + 1,
            per_page: pageSize,
        };

        if (globalFilter) params.q = globalFilter;
        if (sorting.length > 0) {
            const s = sorting[0];
            params.sort = s.id as string;
            params.dir = s.desc ? 'desc' : 'asc';
        }

        // Attach sanitized nested filters
        if (filters && Object.keys(filters).length > 0) {
            const sanitized: Record<string, any> = {};
            Object.entries(filters).forEach(([k, v]) => {
                if (v !== undefined && v !== null) sanitized[k] = v;
            });
            if (Object.keys(sanitized).length > 0) params.filters = sanitized;
        }

        router.get('/catalogs/contract', params, {
            only: ['rows', 'meta'],
            preserveState: true,
            preserveScroll: true,
        });
    }, [pageIndex, pageSize, globalFilter, sorting, filters]);

    // Reload on state changes, but skip the very first render to avoid early Inertia calls
    React.useEffect(() => {
        if (!didMountRef.current) {
            didMountRef.current = true;
            return;
        }
        reloadData();
    }, [reloadData]);

    // Flash messages
    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Contratos', href: '/catalogs/contract' },
    ];

    const handleExport = React.useCallback(
        (format: string = 'csv') => {
            const usp = new URLSearchParams();
            usp.set('format', format);
            usp.set('page', String(pageIndex + 1));
            usp.set('per_page', String(pageSize));
            if (globalFilter) usp.set('q', globalFilter);
            if (sorting.length > 0) {
                const s = sorting[0];
                usp.set('sort', String(s.id));
                usp.set('dir', s.desc ? 'desc' : 'asc');
            }
            // Append filters
            if (filters && Object.keys(filters).length > 0) {
                Object.entries(filters).forEach(([k, v]) => {
                    if (v !== undefined && v !== null) {
                        usp.append(`filters[${k}]`, String(v));
                    }
                });
            }
            window.location.href = `/catalogs/contract/export?${usp.toString()}`;
        },
        [pageIndex, pageSize, globalFilter, sorting, filters],
    );

    // Bulk actions helpers
    const getSelectedIds = React.useCallback((): number[] => {
        const ids = Object.keys(rowSelection).map((key) => Number(key));
        return Array.from(new Set(ids.filter((v) => Number.isFinite(v) && Number.isInteger(v) && v > 0)));
    }, [rowSelection]);

    const [openBulkDelete, setOpenBulkDelete] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });
    const [openBulkActivate, setOpenBulkActivate] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });
    const [openBulkDeactivate, setOpenBulkDeactivate] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });
    const [openBulkConfirm, setOpenBulkConfirm] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });
    const [openBulkTerminate, setOpenBulkTerminate] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });

    const handleBulkDelete = React.useCallback(() => {
        const selected = getSelectedIds();
        setOpenBulkDelete({ show: true, count: selected.length });
    }, [getSelectedIds]);

    const handleBulkActivate = React.useCallback(() => {
        const selected = getSelectedIds();
        setOpenBulkActivate({ show: true, count: selected.length });
    }, [getSelectedIds]);

    const handleBulkDeactivate = React.useCallback(() => {
        const selected = getSelectedIds();
        setOpenBulkDeactivate({ show: true, count: selected.length });
    }, [getSelectedIds]);

    const handleBulkConfirm = React.useCallback(() => {
        const selected = getSelectedIds();
        setOpenBulkConfirm({ show: true, count: selected.length });
    }, [getSelectedIds]);

    const handleBulkTerminate = React.useCallback(() => {
        const selected = getSelectedIds();
        setOpenBulkTerminate({ show: true, count: selected.length });
    }, [getSelectedIds]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contratos" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {/* Header with title and actions */}
                        <div className="mb-8 flex items-center justify-between">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Contratos</h1>
                            {permissions.canCreate && (
                                <Link href="/catalogs/contract/create">
                                    <Button className="flex items-center gap-2">
                                        <Plus className="h-4 w-4" />
                                        Nuevo Contrato
                                    </Button>
                                </Link>
                            )}
                        </div>

                        {/* Stats Cards (optional) */}
                        {(stats?.total !== undefined || stats?.active !== undefined) && (
                            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Contratos</p>
                                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                                {stats?.total ?? meta?.total ?? rows.length}
                                            </p>
                                        </div>
                                        <Database className="h-8 w-8 text-indigo-500 opacity-50" />
                                    </div>
                                </div>
                                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Contrato Activos</p>
                                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats?.active ?? 0}</p>
                                        </div>
                                        <Badge className="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Activo</Badge>
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
                                    columnFilters={columnFilters}
                                    onColumnFiltersChange={setColumnFilters}
                                    columnVisibility={columnVisibility}
                                    onColumnVisibilityChange={setColumnVisibility}
                                    rowSelection={rowSelection}
                                    onRowSelectionChange={setRowSelection}
                                    permissions={permissions}
                                    toolbar={
                                        <ContractFilters
                                            value={filters}
                                            onChange={(f) => {
                                                setFilters(f);
                                                setPageIndex(0);
                                            }}
                                            options={filterOptions}
                                        />
                                    }
                                    onDeleteSelectedClick={permissions.canBulkDelete ? handleBulkDelete : undefined}
                                    onActivateSelectedClick={permissions.canBulkSetActive ? handleBulkActivate : undefined}
                                    onDeactivateSelectedClick={permissions.canBulkSetActive ? handleBulkDeactivate : undefined}
                                    bulkActions={
                                        permissions.canEdit ? (
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-8 text-emerald-700 hover:bg-emerald-600 hover:text-white"
                                                    onClick={handleBulkConfirm}
                                                >
                                                    Confirmar
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-8 text-red-700 hover:bg-red-600 hover:text-white"
                                                    onClick={handleBulkTerminate}
                                                >
                                                    Terminar
                                                </Button>
                                            </div>
                                        ) : undefined
                                    }
                                    canExport={permissions.canExport}
                                    onExportClick={permissions.canExport ? (fmt) => handleExport(fmt) : undefined}
                                    enableRowSelection={true}
                                    enableGlobalFilter={true}
                                    density={density}
                                    onDensityChange={(d) => {
                                        setDensity(d);
                                        if (typeof window !== 'undefined') window.localStorage.setItem('contract_table_density', d);
                                    }}
                                    getRowId={(row) => String((row as unknown as { id?: number | string }).id ?? '')}
                                />
                            </div>
                        </div>

                        {/* Bulk dialogs */}
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
                                        '/catalogs/contract/bulk',
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

                        <ConfirmAlert
                            open={openBulkActivate.show}
                            onOpenChange={(open) => !open && setOpenBulkActivate({ show: false, count: 0 })}
                            title="Activar seleccionados"
                            description={`¿Activar ${openBulkActivate.count} registro(s)?`}
                            confirmLabel="Activar"
                            onConfirm={async () => {
                                const ids = getSelectedIds();
                                await new Promise<void>((resolve, reject) => {
                                    router.post(
                                        '/catalogs/contract/bulk',
                                        { action: 'setActive', ids, active: true },
                                        {
                                            preserveState: false,
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setRowSelection({});
                                                resolve();
                                            },
                                            onError: () => reject(new Error('bulk_activate_failed')),
                                        },
                                    );
                                });
                                setOpenBulkActivate({ show: false, count: 0 });
                            }}
                        />

                        <ConfirmAlert
                            open={openBulkDeactivate.show}
                            onOpenChange={(open) => !open && setOpenBulkDeactivate({ show: false, count: 0 })}
                            title="Desactivar seleccionados"
                            description={`¿Desactivar ${openBulkDeactivate.count} registro(s)?`}
                            confirmLabel="Desactivar"
                            onConfirm={async () => {
                                const ids = getSelectedIds();
                                await new Promise<void>((resolve, reject) => {
                                    router.post(
                                        '/catalogs/contract/bulk',
                                        { action: 'setActive', ids, active: false },
                                        {
                                            preserveState: false,
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setRowSelection({});
                                                resolve();
                                            },
                                            onError: () => reject(new Error('bulk_deactivate_failed')),
                                        },
                                    );
                                });
                                setOpenBulkDeactivate({ show: false, count: 0 });
                            }}
                        />

                        {/* Bulk Confirm */}
                        <ConfirmAlert
                            open={openBulkConfirm.show}
                            onOpenChange={(open) => !open && setOpenBulkConfirm({ show: false, count: 0 })}
                            title="Confirmar contratos seleccionados"
                            description={`¿Confirmar ${openBulkConfirm.count} contrato(s)? Pasarán a Vigente y ocuparán sus locales.`}
                            confirmLabel="Confirmar"
                            onConfirm={async () => {
                                const ids = getSelectedIds();
                                await new Promise<void>((resolve, reject) => {
                                    router.post(
                                        '/catalogs/contract/bulk',
                                        { action: 'confirm', ids },
                                        {
                                            preserveState: false,
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setRowSelection({});
                                                try {
                                                    window.dispatchEvent(new CustomEvent('data:locals:changed'));
                                                } catch (_e) {
                                                    void _e;
                                                }
                                                resolve();
                                            },
                                            onError: () => reject(new Error('bulk_confirm_failed')),
                                        },
                                    );
                                });
                                setOpenBulkConfirm({ show: false, count: 0 });
                            }}
                        />

                        {/* Bulk Terminate */}
                        <ConfirmAlert
                            open={openBulkTerminate.show}
                            onOpenChange={(open) => !open && setOpenBulkTerminate({ show: false, count: 0 })}
                            title="Terminar contratos seleccionados"
                            description={`¿Terminar ${openBulkTerminate.count} contrato(s)? Liberará sus locales.`}
                            confirmLabel="Terminar"
                            onConfirm={async () => {
                                const ids = getSelectedIds();
                                await new Promise<void>((resolve, reject) => {
                                    router.post(
                                        '/catalogs/contract/bulk',
                                        { action: 'terminate', ids },
                                        {
                                            preserveState: false,
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setRowSelection({});
                                                try {
                                                    window.dispatchEvent(new CustomEvent('data:locals:changed'));
                                                } catch (_e) {
                                                    void _e;
                                                }
                                                resolve();
                                            },
                                            onError: () => reject(new Error('bulk_terminate_failed')),
                                        },
                                    );
                                });
                                setOpenBulkTerminate({ show: false, count: 0 });
                            }}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
