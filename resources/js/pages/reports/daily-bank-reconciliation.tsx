import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Download, FileText, Filter, Landmark, Search, X } from 'lucide-react';
import React from 'react';

interface Row {
    payment_id: number;
    date?: string;
    paid_on: string;
    created_at?: string;
    reference: string;
    amount_bs: number;
    status: string;
    method: string;
    destination_bank_name: string;
    destination_account: string;
    origin_bank_name: string;
    origin_account: string;
    payer_document: string;
}

interface Meta {
    current_page: number;
    per_page: number;
    last_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
}

interface FilterOptions {
    destination_banks: Array<{ id: number; name: string }>;
    statuses: Array<{ code: string; name: string }>;
    methods: Array<{ code: string; name: string }>;
}

interface DailyBankReconciliationPageProps extends PageProps {
    rows: Row[];
    meta: Meta;
    filterOptions: FilterOptions;
    auth?: {
        can?: Record<string, boolean>;
    };
}

function todayIso(): string {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
}

function formatAmount(amount: number): string {
    return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount ?? 0);
}

export default function DailyBankReconciliationReport() {
    const { rows, meta, filterOptions, auth } = usePage<DailyBankReconciliationPageProps>().props;

    const defaultDate = todayIso();

    const { data, setData, processing } = useForm({
        date_basis: 'PAID_ON',
        paid_from: defaultDate,
        paid_to: defaultDate,
        destination_bank_id: '',
        status: '',
        method: '',
        per_page: meta.per_page ?? 25,
    });

    const breadcrumbs = [
        { title: 'Reportes', href: '' },
        { title: 'Conciliación diaria por banco', href: '' },
    ];

    const canExport = !!auth?.can?.['reports.daily_bank_reconciliation.export'];

    const buildFilters = (): Record<string, unknown> => {
        const filters: Record<string, unknown> = {};

        if (data.date_basis) {
            filters.date_basis = data.date_basis;
        }

        if (data.paid_from || data.paid_to) {
            const between: Record<string, string> = {};
            if (data.paid_from) between.from = data.paid_from;
            if (data.paid_to) between.to = data.paid_to;
            filters.paid_between = between;
        }

        if (data.destination_bank_id) filters.destination_bank_id = Number(data.destination_bank_id);
        if (data.status) filters.status = data.status;
        if (data.method) filters.method = data.method;

        return filters;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const params: Record<string, unknown> = {
            page: 1,
            per_page: data.per_page,
        };
        const filters = buildFilters();
        if (Object.keys(filters).length > 0) params.filters = filters;

        router.get(route('reports.daily-bank-reconciliation'), params as any, {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'meta'],
        });
    };

    const handleClear = () => {
        setData({
            date_basis: 'PAID_ON',
            paid_from: defaultDate,
            paid_to: defaultDate,
            destination_bank_id: '',
            status: '',
            method: '',
            per_page: data.per_page,
        });
        router.get(route('reports.daily-bank-reconciliation'), {}, { preserveState: false, preserveScroll: true });
    };

    const handleExport = (format: 'csv' | 'json') => {
        const usp = new URLSearchParams();
        usp.set('format', format);

        const filters = buildFilters();
        if (Object.keys(filters).length > 0) usp.set('filters', JSON.stringify(filters));

        window.open(route('reports.daily-bank-reconciliation.export') + '?' + usp.toString(), '_blank');
    };

    const hasActiveFilters = Boolean(
        data.date_basis !== 'PAID_ON' ||
            data.destination_bank_id ||
            data.status ||
            data.method ||
            data.paid_from !== defaultDate ||
            data.paid_to !== defaultDate,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Conciliación diaria por banco" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={Landmark}
                                title="Conciliación diaria por banco"
                                description="Pagos recibidos por banco destino para conciliación de tesorería"
                                actions={
                                    canExport ? (
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" onClick={() => handleExport('csv')} disabled={processing}>
                                                <Download className="mr-2 h-4 w-4" /> Exportar CSV
                                            </Button>
                                            <Button variant="outline" size="sm" onClick={() => handleExport('json')} disabled={processing}>
                                                <FileText className="mr-2 h-4 w-4" /> Exportar JSON
                                            </Button>
                                        </div>
                                    ) : undefined
                                }
                            />
                        </div>

                        <Card className="mb-6">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Filter className="h-5 w-5" />
                                    Filtros
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-7">
                                        <div className="space-y-2">
                                            <Label htmlFor="date_basis">Fecha según</Label>
                                            <select
                                                id="date_basis"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.date_basis}
                                                onChange={(e) => setData('date_basis', e.target.value)}
                                            >
                                                <option value="PAID_ON">Fecha de pago</option>
                                                <option value="CREATED_AT">Fecha de registro</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="paid_from">Desde</Label>
                                            <Input
                                                id="paid_from"
                                                type="date"
                                                value={data.paid_from}
                                                onChange={(e) => setData('paid_from', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="paid_to">Hasta</Label>
                                            <Input
                                                id="paid_to"
                                                type="date"
                                                value={data.paid_to}
                                                onChange={(e) => setData('paid_to', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="destination_bank_id">Banco destino</Label>
                                            <select
                                                id="destination_bank_id"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.destination_bank_id}
                                                onChange={(e) => setData('destination_bank_id', e.target.value)}
                                            >
                                                <option value="">Todos</option>
                                                {filterOptions.destination_banks.map((b) => (
                                                    <option key={b.id} value={b.id}>
                                                        {b.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="status">Estatus</Label>
                                            <select
                                                id="status"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.status}
                                                onChange={(e) => setData('status', e.target.value)}
                                            >
                                                <option value="">Todos</option>
                                                {filterOptions.statuses.map((s) => (
                                                    <option key={s.code} value={s.code}>
                                                        {s.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="method">Método</Label>
                                            <select
                                                id="method"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.method}
                                                onChange={(e) => setData('method', e.target.value)}
                                            >
                                                <option value="">Todos</option>
                                                {filterOptions.methods.map((m) => (
                                                    <option key={m.code} value={m.code}>
                                                        {m.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="per_page">Registros por página</Label>
                                            <Input
                                                id="per_page"
                                                type="number"
                                                min={10}
                                                max={200}
                                                value={data.per_page}
                                                onChange={(e) => setData('per_page', Number(e.target.value) || 25)}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button type="submit" size="sm" disabled={processing}>
                                            <Search className="mr-2 h-4 w-4" /> Buscar
                                        </Button>
                                        {hasActiveFilters && (
                                            <Button type="button" variant="outline" size="sm" onClick={handleClear} disabled={processing}>
                                                <X className="mr-2 h-4 w-4" /> Limpiar filtros
                                            </Button>
                                        )}
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resultados ({meta.total} pagos)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="text-muted-foreground border-b text-xs">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">Fecha (según filtro)</th>
                                                <th className="px-3 py-2 text-left font-medium">Fecha de pago</th>
                                                <th className="px-3 py-2 text-left font-medium">Fecha de registro</th>
                                                <th className="px-3 py-2 text-left font-medium">Banco destino</th>
                                                <th className="px-3 py-2 text-left font-medium">Cuenta destino</th>
                                                <th className="px-3 py-2 text-left font-medium">Método</th>
                                                <th className="px-3 py-2 text-left font-medium">Referencia</th>
                                                <th className="px-3 py-2 text-right font-medium">Monto (Bs)</th>
                                                <th className="px-3 py-2 text-left font-medium">Banco origen</th>
                                                <th className="px-3 py-2 text-left font-medium">Origen</th>
                                                <th className="px-3 py-2 text-left font-medium">Cédula/RIF</th>
                                                <th className="px-3 py-2 text-left font-medium">Estatus</th>
                                                <th className="px-3 py-2 text-left font-medium">Pago</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {rows.length === 0 ? (
                                                <tr>
                                                    <td colSpan={13} className="text-muted-foreground px-3 py-6 text-center text-sm">
                                                        No se encontraron pagos con los filtros aplicados
                                                    </td>
                                                </tr>
                                            ) : (
                                                rows.map((row) => (
                                                    <tr key={row.payment_id} className="hover:bg-muted/40">
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.date || row.paid_on}</td>
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.paid_on || '—'}</td>
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">
                                                            {row.created_at ? row.created_at.slice(0, 10) : '—'}
                                                        </td>
                                                        <td className="px-3 py-2 text-xs">{row.destination_bank_name || '—'}</td>
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.destination_account || '—'}</td>
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.method || '—'}</td>
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.reference || '—'}</td>
                                                        <td className="px-3 py-2 text-right text-xs whitespace-nowrap">
                                                            {formatAmount(row.amount_bs)}
                                                        </td>
                                                        <td className="px-3 py-2 text-xs">{row.origin_bank_name || '—'}</td>
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.origin_account || '—'}</td>
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.payer_document || '—'}</td>
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.status || '—'}</td>
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">
                                                            <a
                                                                href={`/payments/${row.payment_id}`}
                                                                className="text-blue-600 hover:underline dark:text-blue-400"
                                                            >
                                                                #{row.payment_id}
                                                            </a>
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="text-muted-foreground mt-4 flex items-center justify-between text-xs">
                                    <div>
                                        Mostrando {meta.from ?? 0} - {meta.to ?? 0} de {meta.total}
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={meta.current_page <= 1 || processing}
                                            onClick={() => {
                                                const params: Record<string, unknown> = {
                                                    page: meta.current_page - 1,
                                                    per_page: meta.per_page,
                                                };
                                                const filters = buildFilters();
                                                if (Object.keys(filters).length > 0) params.filters = filters;
                                                router.get(route('reports.daily-bank-reconciliation'), params as any, {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                    only: ['rows', 'meta'],
                                                });
                                            }}
                                        >
                                            Anterior
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={meta.current_page >= meta.last_page || processing}
                                            onClick={() => {
                                                const params: Record<string, unknown> = {
                                                    page: meta.current_page + 1,
                                                    per_page: meta.per_page,
                                                };
                                                const filters = buildFilters();
                                                if (Object.keys(filters).length > 0) params.filters = filters;
                                                router.get(route('reports.daily-bank-reconciliation'), params as any, {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                    only: ['rows', 'meta'],
                                                });
                                            }}
                                        >
                                            Siguiente
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
