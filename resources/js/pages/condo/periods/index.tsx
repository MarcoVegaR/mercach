import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { DataTable } from '@/components/index/DataTable';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/react';
import type { ColumnFiltersState, RowSelectionState, SortingState, VisibilityState } from '@tanstack/react-table';
import { CalendarDays, Database, Plus } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { columns, type Row as TRow } from './columns';
import CreatePeriodDialog, { type MarketOption } from './components/CreatePeriodDialog';
import { CondoPeriodFilters, type Filters as FilterValue } from './filters';

interface IndexProps extends PageProps {
    rows: TRow[];
    meta: {
        current_page?: number;
        currentPage?: number;
        per_page?: number;
        perPage?: number;
        total?: number;
        last_page?: number;
        lastPage?: number;
        from?: number;
        to?: number;
    };
    stats?: { total?: number; active?: number; total_usd_minor?: number };
    flash?: { success?: string; error?: string; warning?: string; info?: string };
    auth?: { can?: Record<string, boolean> };
    options?: { markets?: MarketOption[] };
}

export default function CondoPeriodsIndexPage() {
    const pageCtx = usePage<IndexProps>();
    const { rows, meta, auth, stats, flash, options } = pageCtx.props;
    const _pageComponent = (pageCtx as any)?.component as string | undefined;

    // State
    const [pageIndex, setPageIndex] = React.useState(Math.max(0, ((meta as any)?.current_page ?? (meta as any)?.currentPage ?? 1) - 1));
    const [pageSize, setPageSize] = React.useState(((meta as any)?.per_page ?? (meta as any)?.perPage ?? 10) as number);
    const initialQ = typeof window !== 'undefined' ? (new URLSearchParams(window.location.search).get('q') ?? '') : '';
    const [globalFilter, setGlobalFilter] = React.useState(initialQ);
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({ id: false, is_active: false });
    const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});
    const [density, setDensity] = React.useState<'comfortable' | 'compact'>(() => {
        if (typeof window === 'undefined') return 'comfortable';
        const saved = window.localStorage.getItem('condo_periods_table_density');
        return saved === 'compact' ? 'compact' : 'comfortable';
    });

    // Filters (server-side)
    const [filters, setFilters] = React.useState<FilterValue>({});

    const permissions = {
        canCreate: auth?.can?.['condo_period.create'] || false,
        canDelete: auth?.can?.['condo_period.delete'] || false,
        canExport: auth?.can?.['condo_period.export'] || false,
        canBulkDelete: auth?.can?.['condo_period.delete'] || false,
    };

    const canSelectRows = permissions.canBulkDelete;

    const [openCreate, setOpenCreate] = React.useState(false);

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
        const params: Record<string, any> = {
            page: pageIndex + 1,
            per_page: pageSize,
        };

        if (globalFilter) params.q = globalFilter;
        if (sorting.length > 0) {
            const s = sorting[0];
            params.sort = s.id as string;
            params.dir = s.desc ? 'desc' : 'asc';
        }

        // Attach nested filters object (roles pattern) to avoid header parsing issues
        if (filters && Object.keys(filters).length > 0) {
            const sanitized: Record<string, any> = {};
            Object.entries(filters).forEach(([k, v]) => {
                if (Array.isArray(v)) {
                    if (v.length > 0) sanitized[k] = v;
                } else if (v && typeof v === 'object') {
                    const obj = v as Record<string, unknown>;
                    const nested: Record<string, any> = {};
                    Object.entries(obj).forEach(([nk, nv]) => {
                        if (nv !== undefined && nv !== null && nv !== '') nested[nk] = nv;
                    });
                    if (Object.keys(nested).length > 0) sanitized[k] = nested;
                } else if (v !== undefined && v !== null && v !== '') {
                    sanitized[k] = v;
                }
            });
            if (Object.keys(sanitized).length > 0) params.filters = sanitized;
        }

        router.get('/condo/periods', params, {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'meta'],
        });
    }, [pageIndex, pageSize, globalFilter, sorting, filters]);

    // Skip initial reload to avoid Inertia page being undefined on first mount
    const didMountRef = React.useRef(false);
    React.useEffect(() => {
        if (!didMountRef.current) {
            didMountRef.current = true;
            return;
        }
        reloadData();
        // Trigger reloads when these change (post-mount)
    }, [pageIndex, pageSize, globalFilter, sorting, filters, reloadData]);

    // Flash messages
    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);

    const breadcrumbs = [
        { title: 'Condominio', href: '/condo' },
        { title: 'Períodos', href: '/condo/periods' },
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
            // Serialize filters properly
            if (filters && Object.keys(filters).length > 0) {
                Object.entries(filters).forEach(([k, v]) => {
                    if (v !== undefined && v !== null && v !== '') {
                        usp.append(`filters[${k}]`, String(v));
                    }
                });
            }
            window.location.href = `/condo/periods/export?${usp.toString()}`;
        },
        [pageIndex, pageSize, globalFilter, sorting, filters],
    );

    const handleFiltersChange = React.useCallback((newFilters: FilterValue) => {
        setFilters(newFilters);
        setPageIndex(0);
    }, []);

    // Bulk actions helpers
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
            <Head title="Períodos de condominio" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {/* Header with Icon, Title and Description */}
                        <div className="mb-8">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center space-x-3">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                                        <CalendarDays className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                    <div>
                                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Gestión de Períodos</h1>
                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Administra los períodos mensuales y sus gastos/participantes
                                        </p>
                                    </div>
                                </div>
                                {permissions.canCreate && (
                                    <div className="mt-4 sm:mt-0">
                                        <Button onClick={() => setOpenCreate(true)} className="flex items-center gap-2">
                                            <Plus className="h-4 w-4" /> Nuevo período
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Stats Cards */}
                        {(stats?.total !== undefined || stats?.active !== undefined || stats?.total_usd_minor !== undefined) && (
                            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total USD Gastos</p>
                                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                                {new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'USD' }).format(
                                                    ((stats?.total_usd_minor || 0) as number) / 100,
                                                )}
                                            </p>
                                            {typeof (stats as any)?.unit_usd_minor === 'number' && (
                                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    USD/m²:{' '}
                                                    {new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'USD' }).format(
                                                        ((stats as any).unit_usd_minor || 0) / 100,
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <Database className="h-8 w-8 text-emerald-600 opacity-50" />
                                    </div>
                                </div>
                                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Períodos activos</p>
                                            <p className="text-2xl font-bold text-indigo-600">{stats?.active ?? 0}</p>
                                        </div>
                                    </div>
                                </div>
                                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Períodos</p>
                                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                                {stats?.total ?? meta?.total ?? rows.length}
                                            </p>
                                        </div>
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
                                    rowCount={(meta as any)?.total ?? rows.length}
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
                                    searchPlaceholder="Buscar período (YYYY-MM o YYYY-MM-DD)"
                                    columnFilters={columnFilters}
                                    onColumnFiltersChange={setColumnFilters}
                                    columnVisibility={columnVisibility}
                                    onColumnVisibilityChange={setColumnVisibility}
                                    rowSelection={rowSelection}
                                    onRowSelectionChange={setRowSelection}
                                    permissions={permissions}
                                    onDeleteSelectedClick={permissions.canBulkDelete ? handleBulkDelete : undefined}
                                    toolbar={<CondoPeriodFilters value={filters} onChange={handleFiltersChange} markets={options?.markets ?? []} />}
                                    canExport={permissions.canExport}
                                    onExportClick={permissions.canExport ? (fmt: string) => handleExport(fmt) : undefined}
                                    enableRowSelection={canSelectRows}
                                    enableGlobalFilter={true}
                                    density={density}
                                    onDensityChange={(d: 'comfortable' | 'compact') => {
                                        setDensity(d);
                                        if (typeof window !== 'undefined') window.localStorage.setItem('condo_periods_table_density', d);
                                    }}
                                    stickyLeftColumnId="period"
                                    getRowId={(row: any) => String((row as { id?: number | string }).id ?? '')}
                                />
                            </div>
                        </div>

                        {/* Bulk delete */}
                        <ConfirmAlert
                            open={openBulkDelete.show}
                            onOpenChange={(open) => !open && setOpenBulkDelete({ show: false, count: 0 })}
                            title="Eliminar seleccionados"
                            description={`¿Está seguro de eliminar ${openBulkDelete.count} período(s)? Esto eliminará gastos y participantes.`}
                            confirmLabel="Eliminar"
                            onConfirm={async () => {
                                const ids = getSelectedIds();
                                await new Promise<void>((resolve, reject) => {
                                    router.post(
                                        '/condo/periods/bulk',
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
                        {/* Create dialog */}
                        <CreatePeriodDialog open={openCreate} onOpenChange={setOpenCreate} markets={options?.markets ?? []} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
