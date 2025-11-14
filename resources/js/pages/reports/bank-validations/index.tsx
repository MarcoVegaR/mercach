import { DataTable } from '@/components/index/DataTable';
import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { FormDataConvertible, PageProps } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnFiltersState, SortingState, VisibilityState } from '@tanstack/react-table';
import { Download, FileBarChart, FileText } from 'lucide-react';
import React from 'react';
import { columns, type TBankValidationRow } from './columns';
import { BankValidationsFilters, type BankValidationsFilterValue, type ResponseCodeOption } from './filters';

interface BankValidationsIndexProps extends PageProps {
    rows: TBankValidationRow[];
    meta: {
        current_page?: number;
        per_page?: number;
        last_page?: number;
        total: number;
        from?: number | null;
        to?: number | null;
    };
    responseCodes: ResponseCodeOption[];
    auth?: {
        can?: Record<string, boolean>;
        user?: { id: number; name: string };
    };
}

type QueryState = {
    page: number;
    per_page: number;
    search: string;
    sort: string;
    dir: 'asc' | 'desc';
    filters: BankValidationsFilterValue;
};

function getInitialQuery(): QueryState {
    const params = new URLSearchParams(window.location.search);
    const filters: Partial<BankValidationsFilterValue> = {};
    params.forEach((value, key) => {
        const match = key.match(/^filters\[(.+?)\](?:\[(.*?)\])?$/);
        if (!match) return;
        const filterKey = match[1];
        const subKey = match[2];
        if (subKey === undefined) {
            if (filterKey === 'response_code') filters.response_code = value;
            else if (filterKey === 'status') filters.status = value;
        } else if (filterKey === 'paid_between') {
            filters.paid_between = { ...(filters.paid_between || {}), [subKey]: value } as BankValidationsFilterValue['paid_between'];
        }
    });
    const dirParam = params.get('dir');
    const dir: 'asc' | 'desc' = dirParam === 'desc' ? 'desc' : 'asc';
    return {
        page: parseInt(params.get('page') || '1'),
        per_page: parseInt(params.get('per_page') || '15'),
        search: params.get('q') || '',
        sort: params.get('sort') || '',
        dir,
        filters: filters as BankValidationsFilterValue,
    };
}

export default function BankValidationsIndex() {
    const { rows, meta, responseCodes, auth } = usePage<BankValidationsIndexProps>().props;

    const initialQuery = getInitialQuery();
    const [pageIndex, setPageIndex] = React.useState(initialQuery.page - 1);
    const [pageSize, setPageSize] = React.useState(initialQuery.per_page);
    const [globalFilter, setGlobalFilter] = React.useState(initialQuery.search);
    const [sorting, setSorting] = React.useState<SortingState>(() => {
        if (!initialQuery.sort) return [];
        return [{ id: initialQuery.sort, desc: initialQuery.dir === 'desc' }];
    });
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [filters, setFilters] = React.useState<BankValidationsFilterValue>(initialQuery.filters);
    const [density, setDensity] = React.useState<'comfortable' | 'compact'>(() => {
        if (typeof window === 'undefined') return 'comfortable';
        const saved = window.localStorage.getItem('bank_validations_table_density');
        return saved === 'compact' ? 'compact' : 'comfortable';
    });

    const reloadData = React.useCallback(() => {
        const params: Record<string, FormDataConvertible> = {
            page: pageIndex + 1,
            per_page: pageSize,
        };
        if (globalFilter) params.q = globalFilter;
        if (sorting.length > 0) {
            const s = sorting[0];
            params.sort = s.id;
            params.dir = s.desc ? 'desc' : 'asc';
        }
        if (filters && Object.keys(filters).length > 0) {
            const sanitized: Record<string, FormDataConvertible> = {};
            (Object.entries(filters) as Array<[keyof BankValidationsFilterValue, unknown]>).forEach(([k, v]) => {
                if (Array.isArray(v)) {
                    if (v.length > 0) sanitized[k as string] = v as unknown as FormDataConvertible;
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
            if (Object.keys(sanitized).length > 0) (params as any).filters = sanitized;
        }
        router.get('/reports/bank-validations', params, { only: ['rows', 'meta'], preserveState: true, preserveScroll: true });
    }, [pageIndex, pageSize, globalFilter, sorting, filters]);

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

    // Avoid triggering a partial reload on first mount (prevents reading page.component before it's ready)
    const didMountRef = React.useRef(false);
    const initialSearchRef = React.useRef(initialQuery.search);

    React.useEffect(() => {
        if (!didMountRef.current) {
            didMountRef.current = true;
            return;
        }
        reloadData();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pageIndex, pageSize, sorting, filters]);

    React.useEffect(() => {
        if (globalFilter !== initialSearchRef.current) {
            reloadData();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [globalFilter]);

    const canExport = !!auth?.can?.['reports.bank_validations.export'];

    const handleFiltersChange = React.useCallback((newFilters: BankValidationsFilterValue) => {
        setFilters(newFilters);
        setPageIndex(0);
    }, []);

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
            if (filters && Object.keys(filters).length > 0) {
                type FilterPrimitive = string | number | boolean;
                type FilterValue = FilterPrimitive | FilterPrimitive[] | Record<string, FilterPrimitive | undefined | null>;
                const appendFilter = (key: string, val: FilterValue) => {
                    if (Array.isArray(val)) {
                        val.forEach((v) => usp.append(`filters[${key}][]`, String(v)));
                    } else if (val && typeof val === 'object') {
                        Object.entries(val).forEach(([subKey, subVal]) => {
                            if (subVal !== undefined && subVal !== null && subVal !== '') usp.append(`filters[${key}][${subKey}]`, String(subVal));
                        });
                    } else if (val !== undefined && val !== null && val !== '') {
                        usp.append(`filters[${key}]`, String(val));
                    }
                };
                (Object.entries(filters) as Array<[string, unknown]>).forEach(([k, v]) => {
                    if (Array.isArray(v)) appendFilter(k, v as FilterPrimitive[]);
                    else if (v && typeof v === 'object') appendFilter(k, v as Record<string, FilterPrimitive | undefined | null>);
                    else appendFilter(k, v as FilterPrimitive);
                });
            }
            window.location.href = `/reports/bank-validations/export?${usp.toString()}`;
        },
        [pageIndex, pageSize, globalFilter, sorting, filters],
    );

    return (
        <>
            <Head title="Validaciones Bancarias" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={FileBarChart}
                                title="Validaciones Bancarias"
                                description="Reporte de validaciones realizadas con el gateway bancario"
                                actions={
                                    <div className="flex gap-2">
                                        {canExport && (
                                            <>
                                                <Button variant="outline" size="sm" onClick={() => handleExport('csv')}>
                                                    <Download className="mr-2 h-4 w-4" /> Exportar CSV
                                                </Button>
                                                <Button variant="outline" size="sm" onClick={() => handleExport('json')}>
                                                    <FileText className="mr-2 h-4 w-4" /> Exportar JSON
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                }
                            />
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
                                    onPageChange={(i) => setPageIndex(i)}
                                    onPageSizeChange={(s) => {
                                        setPageSize(s);
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
                                    toolbar={<BankValidationsFilters value={filters} onChange={handleFiltersChange} responseCodes={responseCodes} />}
                                    canExport={canExport}
                                    onExportClick={canExport ? (fmt) => handleExport(fmt) : undefined}
                                    enableRowSelection={false}
                                    enableGlobalFilter={true}
                                    density={density}
                                    onDensityChange={(d) => {
                                        setDensity(d);
                                        if (typeof window !== 'undefined') window.localStorage.setItem('bank_validations_table_density', d);
                                    }}
                                    getRowId={(row) => String((row as TBankValidationRow).id)}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

BankValidationsIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Reportes', href: '' },
            { title: 'Validaciones Bancarias', href: '' },
        ]}
    >
        {page}
    </AppLayout>
);
