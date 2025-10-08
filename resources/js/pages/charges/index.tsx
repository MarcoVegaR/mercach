import { DataTable } from '@/components/index/DataTable';
import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ColumnFiltersState, RowSelectionState, SortingState, VisibilityState } from '@tanstack/react-table';
import { FileSpreadsheet, ListChecks, Play } from 'lucide-react';
import React from 'react';
import { columns, type Row as TRow } from './table-columns';

interface IndexProps extends PageProps {
    rows: TRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number; from: number; to: number };
    stats?: { total?: number };
    flash?: { success?: string; error?: string; warning?: string; info?: string };
    auth?: { can?: Record<string, boolean> };
    runOptions?: { types: Array<{ value: string; label: string }>; markets: Array<{ id: number; name: string }> };
}

export default function ChargesIndexPage() {
    const { rows, meta, auth, runOptions } = usePage<IndexProps>().props;
    const success = (usePage<IndexProps>().props.flash?.success ?? '') as string;

    // State for table
    const [pageIndex, setPageIndex] = React.useState(Math.max(0, ((meta as any)?.current_page ?? 1) - 1));
    const [pageSize, setPageSize] = React.useState(((meta as any)?.per_page ?? 15) as number);
    const [globalFilter, setGlobalFilter] = React.useState('');
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({
        debtor_type: false,
        debtor_id: false,
        currency: false,
        issued_on: false,
        due_on: false,
        source: false,
        created_at: false,
    });
    const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});

    const canRun = !!auth?.can?.['charges.run'];
    const canExport = !!auth?.can?.['charges.export'];

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
        const params: Record<string, string | number | boolean> = { page: pageIndex + 1, per_page: pageSize };
        if (globalFilter) params.q = globalFilter;
        if (sorting.length > 0) {
            const s = sorting[0];
            params.sort = s.id as string;
            params.dir = s.desc ? 'desc' : 'asc';
        }
        router.get('/charges', params, { preserveState: true, preserveScroll: true });
    }, [pageIndex, pageSize, globalFilter, sorting]);

    React.useEffect(() => {
        reloadData();
    }, [reloadData]);

    // Run modal state (auto-open if ?run=1 is present)
    const openInitial = React.useMemo(() => {
        try {
            const url = new URL(window.location.href);
            return url.searchParams.get('run') === '1';
        } catch {
            return false;
        }
    }, []);
    const [openRun, setOpenRun] = React.useState<boolean>(openInitial);

    const options = runOptions ?? {
        types: [
            { value: 'ALL', label: 'Todos (M2, Fijo, Condominio)' },
            { value: 'RENT_EUR_M2', label: 'Alquiler por m² (EUR)' },
            { value: 'RENT_EUR_FIXED', label: 'Alquiler fijo (EUR)' },
            { value: 'CONDO_USD', label: 'Condominio (USD)' },
        ],
        markets: [],
    };

    const form = useForm({
        type: options.types[0]?.value ?? 'RENT_EUR_M2',
        market_id: '' as string | number,
        period: '',
        period_month: '',
        date: '',
        idempotency_key: '',
    });

    // Period is required for ALL, M2, CONDO and also FIXED (monthly run)
    const requiresPeriod = ['ALL', 'RENT_EUR_M2', 'RENT_EUR_FIXED', 'CONDO_USD'].includes(form.data.type as string);
    // We no longer require a specific DATE for FIXED in the modal (monthly period suffices)
    const requiresDate = false;

    // Market is required for ALL, M2 and CONDO
    const requiresMarket = ['ALL', 'RENT_EUR_M2', 'CONDO_USD'].includes(form.data.type as string);

    // Period month (YYYY-MM) kept like condo dialogs; transform to YYYY-MM-01 on submit
    const missingPeriod = requiresPeriod && !/^\d{4}-(0[1-9]|1[0-2])$/.test(String(form.data.period_month || ''));
    const missingMarket = requiresMarket && String(form.data.market_id || '') === '';
    const canSubmit = !form.processing && !missingPeriod && !missingMarket;

    const submitRun = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            period: data.period_month ? `${String(data.period_month)}-01` : '',
        }));
        form.post(route('charges.run.execute'), {
            preserveScroll: true,
            onSuccess: () => {
                setOpenRun(false);
                try {
                    history.replaceState({}, '', '/charges');
                } catch {
                    /* noop: replaceState not supported */
                }
            },
        });
    };

    const breadcrumbs = [{ title: 'Cargos', href: '/charges' }];

    const handleExport = React.useCallback(
        (format: string = 'csv') => {
            if (!canExport) return;
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
            window.location.href = `/charges/export?${usp.toString()}`;
        },
        [pageIndex, pageSize, globalFilter, sorting, canExport],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cargos" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={ListChecks}
                                title="Cargos"
                                description="Listado de cargos generados"
                                actions={
                                    canRun ? (
                                        <Button className="flex items-center gap-2" variant="default" onClick={() => setOpenRun(true)}>
                                            <Play className="h-4 w-4" /> Ejecutar ahora
                                        </Button>
                                    ) : undefined
                                }
                            />
                        </div>

                        {success && <div className="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">{success}</div>}

                        {/* Stats Cards */}
                        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Cargos</p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{meta?.total ?? rows.length}</p>
                                    </div>
                                    <FileSpreadsheet className="h-8 w-8 text-indigo-500 opacity-50" />
                                </div>
                            </div>
                        </div>

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
                                    permissions={{ canExport }}
                                    canExport={canExport}
                                    onExportClick={canExport ? (fmt) => handleExport(fmt) : undefined}
                                    enableRowSelection={false}
                                    enableGlobalFilter={true}
                                    density={'comfortable'}
                                    getRowId={(row) => String((row as any).id ?? '')}
                                />
                            </div>
                        </div>

                        {canRun && (
                            <Dialog
                                open={openRun}
                                onOpenChange={(o) => {
                                    setOpenRun(o);
                                    if (!o) {
                                        try {
                                            history.replaceState({}, '', '/charges');
                                        } catch {
                                            /* noop */
                                        }
                                    }
                                }}
                            >
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Ejecutar generación de cargos</DialogTitle>
                                    </DialogHeader>
                                    {Object.keys(form.errors).length > 0 && (
                                        <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                            {Object.values(form.errors).map((err, idx) => (
                                                <div key={idx}>{String(err)}</div>
                                            ))}
                                        </div>
                                    )}
                                    <form onSubmit={submitRun} className="space-y-4">
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Tipo de cargo</label>
                                                <select
                                                    className="w-full rounded-md border px-3 py-2 text-sm"
                                                    value={form.data.type}
                                                    onChange={(e) => form.setData('type', e.target.value)}
                                                >
                                                    {options.types.map((t) => (
                                                        <option key={t.value} value={t.value}>
                                                            {t.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Mercado</label>
                                                <select
                                                    className="w-full rounded-md border px-3 py-2 text-sm"
                                                    value={form.data.market_id}
                                                    onChange={(e) => form.setData('market_id', e.target.value)}
                                                >
                                                    <option value="">Selecciona…</option>
                                                    {options.markets.map((m) => (
                                                        <option key={m.id} value={m.id}>
                                                            {m.name}
                                                        </option>
                                                    ))}
                                                </select>
                                                {missingMarket && <p className="mt-1 text-xs text-red-600">El mercado es requerido.</p>}
                                            </div>
                                            {requiresPeriod && (
                                                <div>
                                                    <label className="mb-1 block text-sm font-medium">Periodo (YYYY-MM)</label>
                                                    <Input
                                                        type="month"
                                                        value={String((form.data as any).period_month || '')}
                                                        onChange={(e) => form.setData('period_month', e.target.value)}
                                                    />
                                                    {missingPeriod && <p className="mt-1 text-xs text-red-600">El periodo es requerido.</p>}
                                                </div>
                                            )}
                                            {requiresDate && (
                                                <div>
                                                    <label className="mb-1 block text-sm font-medium">Fecha (Y-m-d)</label>
                                                    <Input
                                                        type="date"
                                                        value={form.data.date as string}
                                                        onChange={(e) => form.setData('date', e.target.value)}
                                                    />
                                                </div>
                                            )}
                                            <div className="md:col-span-2">
                                                <label className="mb-1 block text-sm font-medium">Idempotency key (opcional)</label>
                                                <Input
                                                    type="text"
                                                    maxLength={64}
                                                    value={form.data.idempotency_key as string}
                                                    onChange={(e) => form.setData('idempotency_key', e.target.value)}
                                                    placeholder="UUID o cadena única para evitar duplicados"
                                                />
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    Opcional. Si lo dejas vacío, los índices únicos evitarán duplicados igualmente.
                                                </p>
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button type="submit" disabled={!canSubmit}>
                                                Ejecutar ahora
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
