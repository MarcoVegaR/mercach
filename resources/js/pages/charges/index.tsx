import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { DataTable } from '@/components/index/DataTable';
import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ColumnFiltersState, RowSelectionState, SortingState, VisibilityState } from '@tanstack/react-table';
import { FileSpreadsheet, ListChecks, Play, PlusCircle } from 'lucide-react';
import React from 'react';
import { ChargesFilters, defaultFilters, type Filters as ChargesFiltersType } from './filters';
import { buildColumns, type FxNow, type Row as TRow } from './table-columns';

interface IndexProps extends PageProps {
    rows: TRow[];
    meta: { current_page: number; per_page: number; total: number; last_page: number; from: number; to: number };
    stats?: { total?: number };
    flash?: { success?: string; error?: string; warning?: string; info?: string };
    auth?: { can?: Record<string, boolean> };
    runOptions?: { types: Array<{ value: string; label: string }>; markets: Array<{ id: number; name: string }> };
    filterOptions?: {
        statuses: Array<{ id: number; code: string; name: string }>;
        locals: Array<{ id: number; name: string }>;
        concessionaires: Array<{ id: number; name: string }>;
        types: Array<{ value: string; label: string }>;
    };
    extraKinds?: Array<{ value: string; label: string }>;
    fxNow?: FxNow;
}

export default function ChargesIndexPage() {
    const { rows, meta, auth, runOptions, filterOptions, extraKinds, fxNow } = usePage<IndexProps>().props;
    const success = (usePage<IndexProps>().props.flash?.success ?? '') as string;
    const error = (usePage<IndexProps>().props.flash?.error ?? '') as string;

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
    const [density, setDensity] = React.useState<'comfortable' | 'compact'>(() => {
        if (typeof window === 'undefined') return 'comfortable';
        const saved = window.localStorage.getItem('charges_table_density');
        return saved === 'compact' ? 'compact' : 'comfortable';
    });

    const canRun = !!auth?.can?.['charges.run'];
    const canExport = !!auth?.can?.['charges.export'];
    const canExtra = !!auth?.can?.['charges.extra.create'];
    const canCancel = !!auth?.can?.['charges.cancel'];

    const permissions = {
        canExport,
        canBulkCancel: canCancel,
    };

    const canSelectRows = permissions.canBulkCancel;

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

    // Filters (server-side) using app pattern component
    const [filters, setFilters] = React.useState<ChargesFiltersType>(defaultFilters);

    const reloadData = React.useCallback(() => {
        const params: Record<string, any> = { page: pageIndex + 1, per_page: pageSize };
        if (globalFilter) params.q = globalFilter;
        if (sorting.length > 0) {
            const s = sorting[0];
            params.sort = s.id as string;
            params.dir = s.desc ? 'desc' : 'asc';
        }
        // Attach filters
        const sanitized: Record<string, any> = {};
        Object.entries(filters).forEach(([k, v]) => {
            if (v !== undefined && v !== null && String(v) !== '') sanitized[k] = v;
        });
        if (Object.keys(sanitized).length > 0) params.filters = sanitized;

        router.get('/charges', params, { preserveState: true, preserveScroll: true, only: ['rows', 'meta'] });
    }, [pageIndex, pageSize, globalFilter, sorting, filters]);

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
    const [openExtra, setOpenExtra] = React.useState<boolean>(false);

    const options = runOptions ?? {
        types: [
            { value: 'ALL', label: 'Todos (M2, Fijo, Condominio)' },
            { value: 'RENT_EUR_M2', label: 'Alquiler por m² (EUR)' },
            { value: 'RENT_EUR_FIXED', label: 'Alquiler fijo (USD)' },
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

    const baseExtraKinds: Array<{ value: string; label: string }> = [
        { value: 'FINE', label: 'Multa' },
        { value: 'ADJ', label: 'Ajuste' },
    ];

    const extraKindOptions: Array<{ value: string; label: string }> = (
        extraKinds && extraKinds.length > 0 ? [...baseExtraKinds, ...extraKinds] : baseExtraKinds
    ).filter((opt, index, self) => self.findIndex((o) => o.value === opt.value) === index);

    const defaultExtraKind = extraKindOptions.find((k) => k.value === 'FINE')?.value ?? extraKindOptions[0]?.value ?? 'FINE';
    const extraForm = useForm({
        debtor_type: 'CONCESSIONAIRE' as string,
        debtor_id: '' as string | number,
        local_id: '' as string | number,
        kind: defaultExtraKind,
        currency: 'EUR' as string,
        period_month: '' as string,
        amount_minor: 0 as number,
        note: '' as string,
    });

    const [extraAmountMajor, setExtraAmountMajor] = React.useState<string>('0.00');
    React.useEffect(() => {
        const cents = Number((extraForm.data as any).amount_minor ?? 0);
        setExtraAmountMajor((cents / 100).toFixed(2));
    }, [extraForm.data]);

    const handleExtraAmountChange = (raw: string) => {
        const digits = String(raw).replace(/\D+/g, '');
        const intVal = digits === '' ? 0 : Number(digits);
        const major = (intVal / 100).toFixed(2);
        setExtraAmountMajor(major);
        extraForm.setData('amount_minor', intVal);
    };

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

    const submitExtra = (e: React.FormEvent) => {
        e.preventDefault();
        const dt = String((extraForm.data as any).debtor_type ?? 'CONCESSIONAIRE').toUpperCase();
        extraForm.transform((data) => ({
            debtor_type: dt,
            debtor_id: dt === 'CONCESSIONAIRE' && (data as any).debtor_id ? Number((data as any).debtor_id) : null,
            local_id: dt === 'LOCAL' && (data as any).local_id ? Number((data as any).local_id) : null,
            kind: data.kind || null,
            currency: data.currency || null,
            period: data.period_month ? `${String(data.period_month)}-01` : '',
            amount_minor: Number((data as any).amount_minor ?? 0),
            note: data.note || '',
        }));

        extraForm.post(route('charges.extra.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setOpenExtra(false);
            },
        });
    };

    const breadcrumbs = [{ title: 'Cargos', href: '/charges' }];

    const getSelectedIds = React.useCallback((): number[] => {
        const ids = Object.keys(rowSelection).map((key) => Number(key));
        return Array.from(new Set(ids.filter((v) => Number.isFinite(v) && Number.isInteger(v) && v > 0)));
    }, [rowSelection]);

    const [openBulkCancel, setOpenBulkCancel] = React.useState<{ show: boolean; count: number }>({ show: false, count: 0 });

    const handleBulkCancel = React.useCallback(() => {
        const selected = getSelectedIds();
        setOpenBulkCancel({ show: true, count: selected.length });
    }, [getSelectedIds]);

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

    const cols = React.useMemo(() => buildColumns(fxNow), [fxNow]);

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
                                    <div className="flex flex-wrap items-center gap-2">
                                        {canRun && (
                                            <Button className="flex items-center gap-2" variant="default" onClick={() => setOpenRun(true)}>
                                                <Play className="h-4 w-4" /> Ejecutar ahora
                                            </Button>
                                        )}
                                        {canExtra && (
                                            <Button className="flex items-center gap-2" variant="outline" onClick={() => setOpenExtra(true)}>
                                                <PlusCircle className="h-4 w-4" /> Cargo extraordinario
                                            </Button>
                                        )}
                                    </div>
                                }
                            />
                        </div>

                        {error && <div className="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</div>}
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
                                    columns={cols}
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
                                    searchPlaceholder="Buscar cargos..."
                                    columnFilters={columnFilters}
                                    onColumnFiltersChange={setColumnFilters}
                                    columnVisibility={columnVisibility}
                                    onColumnVisibilityChange={setColumnVisibility}
                                    rowSelection={rowSelection}
                                    onRowSelectionChange={setRowSelection}
                                    permissions={permissions}
                                    toolbar={
                                        <ChargesFilters
                                            value={filters}
                                            onChange={(f) => {
                                                setFilters(f);
                                                setPageIndex(0);
                                            }}
                                            options={filterOptions}
                                        />
                                    }
                                    canExport={canExport}
                                    onExportClick={canExport ? (fmt) => handleExport(fmt) : undefined}
                                    enableRowSelection={canSelectRows}
                                    enableGlobalFilter={true}
                                    density={density}
                                    onDensityChange={(d) => {
                                        setDensity(d);
                                        if (typeof window !== 'undefined') window.localStorage.setItem('charges_table_density', d);
                                    }}
                                    getRowId={(row) => String((row as any).id ?? '')}
                                    bulkActions={
                                        permissions.canBulkCancel ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-8 text-amber-700 hover:bg-amber-600 hover:text-white"
                                                onClick={handleBulkCancel}
                                            >
                                                Anular cargos
                                            </Button>
                                        ) : undefined
                                    }
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

                        {canExtra && (
                            <Dialog open={openExtra} onOpenChange={setOpenExtra}>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Crear cargo extraordinario</DialogTitle>
                                    </DialogHeader>
                                    {Object.keys(extraForm.errors).length > 0 && (
                                        <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                            {Object.values(extraForm.errors).map((err, idx) => (
                                                <div key={idx}>{String(err)}</div>
                                            ))}
                                        </div>
                                    )}
                                    <form onSubmit={submitExtra} className="space-y-4">
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Deudor</label>
                                                <select
                                                    className="w-full rounded-md border px-3 py-2 text-sm"
                                                    value={String((extraForm.data as any).debtor_type ?? 'CONCESSIONAIRE')}
                                                    onChange={(e) => {
                                                        const next = e.target.value;
                                                        extraForm.setData('debtor_type', next);
                                                        if (String(next).toUpperCase() === 'LOCAL') {
                                                            extraForm.setData('debtor_id', '');
                                                        } else {
                                                            extraForm.setData('local_id', '');
                                                        }
                                                    }}
                                                >
                                                    <option value="CONCESSIONAIRE">Cesionario</option>
                                                    <option value="LOCAL">Local</option>
                                                </select>
                                            </div>

                                            {String((extraForm.data as any).debtor_type ?? 'CONCESSIONAIRE').toUpperCase() === 'LOCAL' ? (
                                                <div>
                                                    <label className="mb-1 block text-sm font-medium">Local</label>
                                                    <Combobox
                                                        id="extra_local_id"
                                                        withinDialog
                                                        options={(filterOptions?.locals ?? []).map((l) => ({ value: String(l.id), label: l.name }))}
                                                        value={String((extraForm.data as any).local_id ?? '')}
                                                        onChange={(v) => {
                                                            const val = Array.isArray(v) ? v[0] : v;
                                                            extraForm.setData('local_id', val);
                                                        }}
                                                        placeholder="Seleccionar local"
                                                        searchPlaceholder="Buscar local..."
                                                        emptyText="Sin resultados"
                                                    />
                                                </div>
                                            ) : (
                                                <div>
                                                    <label className="mb-1 block text-sm font-medium">Cesionario</label>
                                                    <Combobox
                                                        id="extra_debtor_id"
                                                        withinDialog
                                                        options={(filterOptions?.concessionaires ?? []).map((c) => ({
                                                            value: String(c.id),
                                                            label: c.name,
                                                        }))}
                                                        value={String((extraForm.data as any).debtor_id ?? '')}
                                                        onChange={(v) => {
                                                            const val = Array.isArray(v) ? v[0] : v;
                                                            extraForm.setData('debtor_id', val);
                                                        }}
                                                        placeholder="Seleccionar cesionario"
                                                        searchPlaceholder="Buscar cesionario..."
                                                        emptyText="Sin resultados"
                                                    />
                                                </div>
                                            )}
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Tipo de cargo</label>
                                                <Combobox
                                                    id="extra_kind"
                                                    withinDialog
                                                    options={extraKindOptions.map((opt) => ({ value: opt.value, label: opt.label }))}
                                                    value={String(extraForm.data.kind ?? '')}
                                                    onChange={(v) => {
                                                        const val = Array.isArray(v) ? v[0] : v;
                                                        extraForm.setData('kind', val);
                                                    }}
                                                    placeholder="Seleccionar tipo"
                                                    searchPlaceholder="Buscar tipo..."
                                                    emptyText="Sin resultados"
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Moneda</label>
                                                <select
                                                    className="w-full rounded-md border px-3 py-2 text-sm"
                                                    value={extraForm.data.currency as string}
                                                    onChange={(e) => extraForm.setData('currency', e.target.value)}
                                                >
                                                    <option value="EUR">EUR</option>
                                                    <option value="USD">USD</option>
                                                    <option value="VES">VES</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Periodo (YYYY-MM)</label>
                                                <Input
                                                    type="month"
                                                    value={extraForm.data.period_month as string}
                                                    onChange={(e) => extraForm.setData('period_month', e.target.value)}
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium">Monto</label>
                                                <Input
                                                    value={extraAmountMajor}
                                                    onChange={(e) => handleExtraAmountChange(e.target.value)}
                                                    inputMode="numeric"
                                                    placeholder="0.00"
                                                />
                                                {extraForm.errors.amount_minor && (
                                                    <p className="mt-1 text-xs text-red-600">{extraForm.errors.amount_minor as any}</p>
                                                )}
                                            </div>
                                            <div className="md:col-span-2">
                                                <label className="mb-1 block text-sm font-medium">Motivo (opcional)</label>
                                                <textarea
                                                    className="w-full rounded-md border px-3 py-2 text-sm"
                                                    rows={3}
                                                    value={extraForm.data.note as string}
                                                    onChange={(e) => extraForm.setData('note', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button type="submit" disabled={extraForm.processing}>
                                                Crear cargo
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}

                        <ConfirmAlert
                            open={openBulkCancel.show}
                            onOpenChange={(open) => !open && setOpenBulkCancel({ show: false, count: 0 })}
                            title="Anular cargos seleccionados"
                            description={`¿Está seguro de anular ${openBulkCancel.count} cargo(s)? Esta acción no se puede deshacer.`}
                            confirmLabel="Anular"
                            requireReason
                            reasonLabel="Motivo de anulación"
                            reasonPlaceholder="Ej: Ajuste masivo por error de facturación..."
                            reasonMinLength={5}
                            onConfirm={async (reason) => {
                                const ids = getSelectedIds();
                                const trimmed = (reason || '').trim();
                                const payload: any = { ids };
                                if (trimmed !== '') {
                                    payload.note = trimmed;
                                }

                                await new Promise<void>((resolve, reject) => {
                                    router.post('/charges/bulk-cancel', payload, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setRowSelection({});
                                            resolve();
                                        },
                                        onError: () => reject(new Error('bulk_cancel_failed')),
                                    });
                                });
                                setOpenBulkCancel({ show: false, count: 0 });
                            }}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
