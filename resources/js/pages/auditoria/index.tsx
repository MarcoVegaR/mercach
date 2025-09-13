import { DataTable } from '@/components/index/DataTable';
import AppLayout from '@/layouts/app-layout';
import { auditCrumbs } from '@/lib/breadcrumbs';
import type { FormDataConvertible, PageProps } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnFiltersState, SortingState, VisibilityState } from '@tanstack/react-table';
import { History } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import { AuditFilters, type AuditFilterValue } from './AuditFilters';
import { columns, TAudit } from './columns';

interface AuditoriaIndexProps extends PageProps {
    rows: TAudit[];
    meta: {
        total: number;
        // Support both snake_case and camelCase from backend
        current_page?: number;
        per_page?: number;
        last_page?: number;
        currentPage?: number;
        perPage?: number;
        lastPage?: number;
    };
    stats?: {
        total?: number;
        last24h?: number;
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
}

// Parse query params from URL
type QueryState = {
    page: number;
    per_page: number;
    search: string;
    sort: string;
    dir: 'asc' | 'desc';
    filters: AuditFilterValue;
};

function getInitialQuery(): QueryState {
    const params = new URLSearchParams(window.location.search);

    // Parse filters from nested parameters (supports arrays and nested objects)
    const filters: Partial<AuditFilterValue> = {};
    params.forEach((value, key) => {
        // Matches: filters[key], filters[key][], filters[key][sub]
        const match = key.match(/^filters\[(.+?)\](?:\[(.*?)\])?$/);
        if (!match) return;
        const filterKey = match[1];
        const subKey = match[2];

        if (subKey === undefined) {
            // Simple scalar filter
            if (filterKey === 'user_id') filters.user_id = value;
            else if (filterKey === 'event') filters.event = value;
            else if (filterKey === 'auditable_type') filters.auditable_type = value;
            else if (filterKey === 'auditable_id') filters.auditable_id = value;
            else if (filterKey === 'ip_address') filters.ip_address = value;
            else if (filterKey === 'url') filters.url = value;
            else if (filterKey === 'tags') filters.tags = value;
        } else {
            // Nested object (e.g., created_between[from])
            if (filterKey === 'created_between') {
                filters.created_between = { ...(filters.created_between || {}), [subKey]: value } as AuditFilterValue['created_between'];
            }
        }
    });

    // Coerce sort direction to a strict union
    const dirParam = params.get('dir');
    const dir: 'asc' | 'desc' = dirParam === 'desc' ? 'desc' : 'asc';

    return {
        page: parseInt(params.get('page') || '1'),
        // Align with backend default (AuditoriaIndexRequest::defaultPerPage = 25)
        per_page: parseInt(params.get('per_page') || '25'),
        search: params.get('q') || '',
        sort: params.get('sort') || '',
        dir,
        filters: filters as AuditFilterValue,
    };
}

export default function AuditoriaIndex() {
    const { rows, meta, auth, flash, stats } = usePage<AuditoriaIndexProps>().props;
    const initialQuery = getInitialQuery();

    // State
    const [pageIndex, setPageIndex] = React.useState(() => {
        const p = meta.current_page ?? meta.currentPage ?? initialQuery.page;
        return Math.max(0, (p || 1) - 1);
    });
    const [pageSize, setPageSize] = React.useState(() => {
        const ps = meta.per_page ?? meta.perPage ?? initialQuery.per_page;
        return ps || 25;
    });
    const [globalFilter, setGlobalFilter] = React.useState(initialQuery.search);
    const [sorting, setSorting] = React.useState<SortingState>(() => {
        if (!initialQuery.sort) return [];
        return [{ id: initialQuery.sort, desc: initialQuery.dir === 'desc' }];
    });
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [filters, setFilters] = React.useState(initialQuery.filters);
    const [density, setDensity] = React.useState<'comfortable' | 'compact'>(() => {
        if (typeof window === 'undefined') return 'comfortable';
        const saved = window.localStorage.getItem('auditoria_table_density');
        return saved === 'compact' ? 'compact' : 'comfortable';
    });

    // Avoid triggering a partial reload on first mount (prevents reading page.component before it's ready)
    const didMountRef = React.useRef(false);
    const isInternalReloadRef = React.useRef(false);

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
            (Object.entries(filters) as Array<[keyof AuditFilterValue, unknown]>).forEach(([k, v]) => {
                if (v && typeof v === 'object' && !Array.isArray(v)) {
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

        isInternalReloadRef.current = true;
        router.get('/auditoria', params, {
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
                    if (val && typeof val === 'object' && !Array.isArray(val)) {
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
                    if (v && typeof v === 'object' && !Array.isArray(v)) {
                        appendFilter(k, v as Record<string, FilterPrimitive | undefined | null>);
                    } else {
                        appendFilter(k, v as FilterPrimitive);
                    }
                });
            }

            window.location.href = `/auditoria/export?${usp.toString()}`;
        },
        [pageIndex, pageSize, globalFilter, sorting, filters],
    );

    // Handle filters change
    const handleFiltersChange = React.useCallback((newFilters: AuditFilterValue) => {
        setFilters(newFilters);
        setPageIndex(0); // Reset to first page when filters change
    }, []);

    // Handle density change and persist
    const handleDensityChange = React.useCallback((d: 'comfortable' | 'compact') => {
        setDensity(d);
        if (typeof window !== 'undefined') {
            window.localStorage.setItem('auditoria_table_density', d);
        }
    }, []);

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

    // Re-initialize query state on SPA navigation success to this page
    React.useEffect(() => {
        const unsubscribe = router.on('success', (event: unknown) => {
            try {
                // Narrow unknown event payload from Inertia
                const evt = event as { detail?: { page?: { url?: string } } };
                // Skip re-initialization when the reload was initiated internally by this component
                if (isInternalReloadRef.current) {
                    isInternalReloadRef.current = false;
                    return;
                }
                const url: string = evt?.detail?.page?.url || '';
                if (url.startsWith('/auditoria')) {
                    const q = getInitialQuery();
                    setPageIndex(q.page - 1);
                    setPageSize(q.per_page);
                    setGlobalFilter(q.search);
                    setSorting(q.sort ? [{ id: q.sort, desc: q.dir === 'desc' }] : []);
                    setFilters(q.filters);
                }
            } catch {
                // noop
            }
        });

        // Fallback: if rows are empty on first mount, force a reload
        if (!rows || rows.length === 0) {
            reloadData();
        }

        return () => {
            if (typeof unsubscribe === 'function') unsubscribe();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const permissions = {
        canExport: auth?.can?.['auditoria.export'] || false,
    };

    return (
        <>
            <Head title="Auditoría - Merca Chacao" />

            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                {/* Main Content */}
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {/* Header with Title */}
                        <div className="mb-8">
                            <div className="flex items-center space-x-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/30">
                                    <History className="h-6 w-6 text-orange-600 dark:text-orange-400" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Auditoría del Sistema</h1>
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Historial completo de actividad y cambios en el sistema
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Stats Cards */}
                        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {/* Total events (prefer backend stats if present) */}
                            <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Eventos</p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats?.total ?? meta.total}</p>
                                    </div>
                                    <History className="h-8 w-8 text-orange-500 opacity-50" />
                                </div>
                            </div>

                            {/* Last 24 hours (only if provided by backend) */}
                            {typeof stats?.last24h === 'number' && (
                                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Últimas 24 horas</p>
                                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{stats.last24h}</p>
                                        </div>
                                        <History className="h-8 w-8 text-orange-500 opacity-50" />
                                    </div>
                                </div>
                            )}
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
                                    toolbar={<AuditFilters value={filters} onChange={handleFiltersChange} />}
                                    canExport={permissions.canExport}
                                    onExportClick={permissions.canExport ? (format: string) => handleExport(format) : undefined}
                                    enableRowSelection={false}
                                    enableGlobalFilter={true}
                                    density={density}
                                    onDensityChange={handleDensityChange}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

AuditoriaIndex.layout = (page: React.ReactNode) => <AppLayout breadcrumbs={auditCrumbs()}>{page}</AppLayout>;
