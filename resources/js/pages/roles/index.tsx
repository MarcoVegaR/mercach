import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { DataTable } from '@/components/index/DataTable';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { resourceCrumbs } from '@/lib/breadcrumbs';
import type { FormDataConvertible, PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ColumnFiltersState, RowSelectionState, SortingState, VisibilityState } from '@tanstack/react-table';
import { Plus, Shield } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { columns, TRole } from './columns';
import type { RoleFilterValue } from './RoleFilters';
import { RoleFilters } from './RoleFilters';

interface RolesIndexProps extends PageProps {
    rows: TRole[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number;
        to: number;
    };
    stats?: {
        total: number;
        active: number;
        with_permissions: number;
    };
    auth?: {
        can?: Record<string, boolean>;
        user?: {
            id: number;
            name: string;
        };
    };
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    availablePermissions?: Array<{ id: number; name: string }>;
}

// Parse query params from URL
type QueryState = {
    page: number;
    per_page: number;
    search: string;
    sort: string;
    dir: 'asc' | 'desc';
    filters: RoleFilterValue;
};

function getInitialQuery(): QueryState {
    const params = new URLSearchParams(window.location.search);

    // Parse filters from nested parameters (supports arrays and nested objects)
    const filters: Partial<RoleFilterValue> = {};
    params.forEach((value, key) => {
        // Matches: filters[key], filters[key][], filters[key][sub]
        const match = key.match(/^filters\[(.+?)\](?:\[(.*?)\])?$/);
        if (!match) return;
        const filterKey = match[1];
        const subKey = match[2];

        if (subKey === undefined) {
            // Simple scalar or repeated key (accumulate as array if repeated)
            if (filterKey === 'permissions') {
                const current = filters.permissions;
                if (Array.isArray(current)) {
                    filters.permissions = [...current, value];
                } else if (current) {
                    filters.permissions = [current as unknown as string, value];
                } else {
                    filters.permissions = [value];
                }
            } else if (filterKey === 'guard_name') {
                filters.guard_name = value;
            } else if (filterKey === 'is_active') {
                // interpret 'true'/'false' if present as direct value
                if (value === 'true') filters.is_active = true;
                else if (value === 'false') filters.is_active = false;
            }
        } else if (subKey === '') {
            // Array notation []
            if (filterKey === 'permissions') {
                if (!Array.isArray(filters.permissions)) filters.permissions = [];
                (filters.permissions as string[]).push(value);
            }
        } else {
            // Nested object (e.g., created_between[from])
            if (filterKey === 'created_between') {
                filters.created_between = { ...(filters.created_between || {}), [subKey]: value } as RoleFilterValue['created_between'];
            }
        }
    });

    // Normalize specific filters
    if (typeof filters.permissions === 'string') {
        filters.permissions = [filters.permissions];
    }
    // Coerce sort direction to a strict union
    const dirParam = params.get('dir');
    const dir: 'asc' | 'desc' = dirParam === 'desc' ? 'desc' : 'asc';

    return {
        page: parseInt(params.get('page') || '1'),
        per_page: parseInt(params.get('per_page') || '10'),
        search: params.get('q') || '',
        sort: params.get('sort') || '',
        dir,
        filters: filters as RoleFilterValue,
    };
}

export default function RolesIndex() {
    const { rows, meta, auth, flash, availablePermissions, stats } = usePage<RolesIndexProps>().props;
    const initialQuery = getInitialQuery();

    // State
    const [pageIndex, setPageIndex] = React.useState(initialQuery.page - 1);
    const [pageSize, setPageSize] = React.useState(initialQuery.per_page);
    const [globalFilter, setGlobalFilter] = React.useState(initialQuery.search);
    const [sorting, setSorting] = React.useState<SortingState>(() => {
        if (!initialQuery.sort) return [];
        return [{ id: initialQuery.sort, desc: initialQuery.dir === 'desc' }];
    });
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({
        id: false,
        guard_name: false,
    });
    const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});
    const [filters, setFilters] = React.useState(initialQuery.filters);
    const [density, setDensity] = React.useState<'comfortable' | 'compact'>(() => {
        if (typeof window === 'undefined') return 'comfortable';
        const saved = window.localStorage.getItem('roles_table_density');
        return saved === 'compact' ? 'compact' : 'comfortable';
    });
    // Avoid triggering a partial reload on first mount (prevents reading page.component before it's ready)
    const didMountRef = React.useRef(false);
    // Bulk action confirmation states
    const [deleteConfirm, setDeleteConfirm] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });
    const [activateConfirm, setActivateConfirm] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });
    const [deactivateConfirm, setDeactivateConfirm] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });

    // Show flash messages
    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);

    // Reload data when query changes
    const reloadData = React.useCallback(() => {
        const params: Record<string, FormDataConvertible> = {
            page: pageIndex + 1,
            per_page: pageSize,
        };

        if (globalFilter) {
            params.q = globalFilter; // Changed from 'search' to 'q'
        }

        if (sorting.length > 0) {
            const sort = sorting[0];
            params.sort = sort.id;
            params.dir = sort.desc ? 'desc' : 'asc'; // Separate sort and dir parameters
        }

        // Sanitize and attach nested filters object so arrays are encoded correctly
        if (filters && Object.keys(filters).length > 0) {
            const sanitized: Record<string, FormDataConvertible> = {};
            (Object.entries(filters) as Array<[keyof RoleFilterValue, unknown]>).forEach(([k, v]) => {
                if (Array.isArray(v)) {
                    if (v.length > 0) sanitized[k as string] = v;
                } else if (v && typeof v === 'object') {
                    const obj = v as Record<string, unknown>;
                    const nested: Record<string, FormDataConvertible> = {};
                    Object.entries(obj).forEach(([nk, nv]) => {
                        if (nv !== undefined && nv !== null && nv !== '') nested[nk] = nv as FormDataConvertible;
                    });
                    if (Object.keys(nested).length > 0) sanitized[k as string] = nested;
                } else if (v !== undefined && v !== null && v !== '') {
                    sanitized[k as string] = v as FormDataConvertible;
                }
            });
            if (Object.keys(sanitized).length > 0) {
                params.filters = sanitized;
            }
        }

        router.get('/roles', params, {
            only: ['rows', 'meta'],
            preserveState: true,
            preserveScroll: true,
        });
    }, [pageIndex, pageSize, globalFilter, sorting, filters]);

    // Debounce search
    const debouncedSearch = React.useMemo(() => {
        let timeoutId: ReturnType<typeof setTimeout>;
        return (value: string) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                setGlobalFilter(value);
                setPageIndex(0); // Reset to first page on search
            }, 300);
        };
    }, []);

    // Handle export
    const handleExport = React.useCallback(
        (format: string = 'csv') => {
            // Base params
            const usp = new URLSearchParams();
            usp.set('format', format);
            usp.set('page', String(pageIndex + 1));
            usp.set('per_page', String(pageSize));

            if (globalFilter) {
                usp.set('q', globalFilter);
            }

            if (sorting.length > 0) {
                const sort = sorting[0];
                usp.set('sort', String(sort.id));
                usp.set('dir', sort.desc ? 'desc' : 'asc');
            }

            // Append filters with correct array/nested encoding
            if (filters && Object.keys(filters).length > 0) {
                type FilterPrimitive = string | number | boolean;
                type FilterValue = FilterPrimitive | FilterPrimitive[] | Record<string, FilterPrimitive | undefined | null>;
                const appendFilter = (key: string, val: FilterValue) => {
                    if (Array.isArray(val)) {
                        val.forEach((v) => usp.append(`filters[${key}][]`, String(v)));
                    } else if (val && typeof val === 'object') {
                        Object.entries(val).forEach(([subKey, subVal]) => {
                            if (subVal !== undefined && subVal !== null && subVal !== '') {
                                usp.append(`filters[${key}][${subKey}]`, String(subVal));
                            }
                        });
                    } else if (val !== undefined && val !== null && val !== '') {
                        usp.append(`filters[${key}]`, String(val));
                    }
                };

                (Object.entries(filters) as Array<[string, unknown]>).forEach(([k, v]) => {
                    if (Array.isArray(v)) {
                        appendFilter(k, v as FilterPrimitive[]);
                    } else if (v && typeof v === 'object') {
                        appendFilter(k, v as Record<string, FilterPrimitive | undefined | null>);
                    } else {
                        appendFilter(k, v as FilterPrimitive);
                    }
                });
            }

            window.location.href = `/roles/export?${usp.toString()}`;
        },
        [pageIndex, pageSize, globalFilter, sorting, filters],
    );

    // Handle filters change
    const handleFiltersChange = React.useCallback((newFilters: RoleFilterValue) => {
        setFilters(newFilters);
        setPageIndex(0); // Reset to first page when filters change
    }, []);

    // Handle density change and persist
    const handleDensityChange = React.useCallback((d: 'comfortable' | 'compact') => {
        setDensity(d);
        if (typeof window !== 'undefined') {
            window.localStorage.setItem('roles_table_density', d);
        }
    }, []);
    // Extract and sanitize selected role IDs from rowSelection keys (getRowId set to role.id)
    const getSanitizedSelectedIds = React.useCallback((): number[] => {
        const ids = Object.keys(rowSelection).map((key) => Number(key));
        const sanitized = Array.from(new Set(ids.filter((v) => Number.isFinite(v) && Number.isInteger(v) && v > 0)));
        return sanitized;
    }, [rowSelection]);
    // Handle bulk delete
    const handleBulkDelete = React.useCallback(() => {
        const selectedIds = getSanitizedSelectedIds();

        if (selectedIds.length === 0) {
            toast.warning('No hay filas seleccionadas');
            return;
        }

        setDeleteConfirm({ show: true, count: selectedIds.length });
    }, [getSanitizedSelectedIds]);

    // Handle bulk activate
    const handleBulkActivate = React.useCallback(() => {
        const selectedIds = getSanitizedSelectedIds();

        if (selectedIds.length === 0) {
            toast.warning('No hay filas seleccionadas');
            return;
        }

        setActivateConfirm({ show: true, count: selectedIds.length });
    }, [getSanitizedSelectedIds]);

    // Handle bulk deactivate
    const handleBulkDeactivate = React.useCallback(() => {
        const selectedIds = getSanitizedSelectedIds();

        if (selectedIds.length === 0) {
            toast.warning('No hay filas seleccionadas');
            return;
        }

        setDeactivateConfirm({ show: true, count: selectedIds.length });
    }, [getSanitizedSelectedIds]);

    // Update URL and reload when state changes (skip first mount)
    React.useEffect(() => {
        if (!didMountRef.current) {
            didMountRef.current = true;
            return;
        }
        reloadData();
    }, [reloadData]);

    // Trigger reload when globalFilter changes
    const initialSearchRef = React.useRef(initialQuery.search);
    React.useEffect(() => {
        if (globalFilter !== initialSearchRef.current) {
            reloadData();
        }
    }, [globalFilter, reloadData]);

    const permissions = {
        canCreate: auth?.can?.['roles.create'] || false,
        canEdit: auth?.can?.['roles.update'] || false,
        canDelete: auth?.can?.['roles.delete'] || false,
        canExport: auth?.can?.['roles.export'] || false,
        canBulkDelete: auth?.can?.['roles.delete'] || false,
        canSetActive: auth?.can?.['roles.setActive'] || false,
        canBulkSetActive: auth?.can?.['roles.setActive'] || false,
    };

    return (
        <>
            <Head title="Roles - Merca Chacao" />

            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                {/* Main Content */}
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {/* Header with Title and Actions */}
                        <div className="mb-8">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center space-x-3">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                                        <Shield className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                    <div>
                                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Gestión de Roles</h1>
                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Administra los roles y permisos del sistema</p>
                                    </div>
                                </div>
                                <div className="mt-4 flex space-x-3 sm:mt-0">
                                    {permissions.canCreate && (
                                        <Link href="/roles/create">
                                            <Button className="flex items-center gap-2">
                                                <Plus className="h-4 w-4" />
                                                Nuevo Rol
                                            </Button>
                                        </Link>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Stats Cards */}
                        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Roles</p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats?.total ?? meta.total}</p>
                                    </div>
                                    <Shield className="h-8 w-8 text-indigo-500 opacity-50" />
                                </div>
                            </div>
                            <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Roles Activos</p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats?.active ?? 0}</p>
                                    </div>
                                    <Badge className="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Activo</Badge>
                                </div>
                            </div>
                        </div>

                        {/* Main Table Card */}
                        <div className="overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                            <div className="p-6">
                                <DataTable
                                    columns={columns}
                                    data={rows}
                                    rowCount={meta.total}
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
                                    onDeleteSelectedClick={handleBulkDelete}
                                    onActivateSelectedClick={handleBulkActivate}
                                    onDeactivateSelectedClick={handleBulkDeactivate}
                                    toolbar={
                                        <RoleFilters value={filters} onChange={handleFiltersChange} availablePermissions={availablePermissions} />
                                    }
                                    canExport={permissions.canExport}
                                    onExportClick={permissions.canExport ? (format: string) => handleExport(format) : undefined}
                                    enableRowSelection={true}
                                    enableGlobalFilter={true}
                                    density={density}
                                    onDensityChange={handleDensityChange}
                                    getRowId={(row) => String(row.id)}
                                />
                            </div>
                        </div>
                        {/* Bulk Action Confirmation Dialogs */}
                        <ConfirmAlert
                            open={deleteConfirm.show}
                            onOpenChange={(open) => {
                                if (!open) setDeleteConfirm({ show: false, count: 0 });
                            }}
                            title="Eliminar Roles Seleccionados"
                            description={`¿Está seguro de eliminar ${deleteConfirm.count} rol(es)? Esta acción no se puede deshacer.`}
                            confirmLabel="Eliminar"
                            onConfirm={async () => {
                                const selectedIds = getSanitizedSelectedIds();
                                await new Promise<void>((resolve, reject) => {
                                    router.post(
                                        '/roles/bulk',
                                        { action: 'delete', ids: selectedIds },
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
                                setDeleteConfirm({ show: false, count: 0 });
                            }}
                        />

                        <ConfirmAlert
                            open={activateConfirm.show}
                            onOpenChange={(open) => {
                                if (!open) setActivateConfirm({ show: false, count: 0 });
                            }}
                            title="Activar Roles Seleccionados"
                            description={`¿Está seguro de activar ${activateConfirm.count} rol(es)?`}
                            confirmLabel="Activar"
                            onConfirm={async () => {
                                const selectedIds = getSanitizedSelectedIds();
                                await new Promise<void>((resolve, reject) => {
                                    router.post(
                                        '/roles/bulk',
                                        { action: 'setActive', ids: selectedIds, active: true },
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
                                setActivateConfirm({ show: false, count: 0 });
                            }}
                        />

                        <ConfirmAlert
                            open={deactivateConfirm.show}
                            onOpenChange={(open) => {
                                if (!open) setDeactivateConfirm({ show: false, count: 0 });
                            }}
                            title="Desactivar Roles Seleccionados"
                            description={`¿Está seguro de desactivar ${deactivateConfirm.count} rol(es)?`}
                            confirmLabel="Desactivar"
                            onConfirm={async () => {
                                const selectedIds = getSanitizedSelectedIds();
                                await new Promise<void>((resolve, reject) => {
                                    router.post(
                                        '/roles/bulk',
                                        { action: 'setActive', ids: selectedIds, active: false },
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
                                setDeactivateConfirm({ show: false, count: 0 });
                            }}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

RolesIndex.layout = (page: React.ReactNode) => <AppLayout breadcrumbs={resourceCrumbs('roles', 'index')}>{page}</AppLayout>;
