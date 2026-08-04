import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Download, FileBarChart, Filter, Search, X } from 'lucide-react';
import React from 'react';

type ReportType = 'income' | 'exonerations';
type GroupBy = 'day' | 'week' | 'month';

interface Row {
    period_start: string;
    period_key: string;
    period_label: string;
    count: number;
    amount_bs_minor: number;
    average_bs_minor: number;
    registered_count: number;
    confirmed_count: number;
    applied_count: number;
}

interface Totals {
    count: number;
    amount_bs_minor: number;
    average_bs_minor: number;
}

interface Meta {
    current_page: number;
    per_page: number;
    last_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
}

interface AppliedFilters {
    report_type: ReportType;
    group_by: GroupBy;
    paid_from: string;
    paid_to: string;
    method?: string | null;
    bank_id?: number | null;
    bank_name?: string | null;
}

interface FilterOptions {
    methods: Array<{ code: string; name: string }>;
    banks: Array<{ id: number; name: string }>;
}

interface PaymentFinancialSummaryPageProps extends PageProps {
    rows: Row[];
    totals: Totals;
    meta: Meta;
    filters: AppliedFilters;
    filterOptions: FilterOptions;
    auth?: {
        can?: Record<string, boolean>;
    };
}

function formatBsMinor(value?: number | null): string {
    return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0) / 100);
}

function reportTitle(reportType: ReportType): string {
    return reportType === 'exonerations' ? 'Exoneraciones realizadas' : 'Ingresos registrados';
}

export default function PaymentFinancialSummaryReport() {
    const { rows, totals, meta, filters, filterOptions, auth } = usePage<PaymentFinancialSummaryPageProps>().props;

    const { data, setData, processing } = useForm({
        report_type: filters.report_type ?? 'income',
        group_by: filters.group_by ?? 'day',
        paid_from: filters.paid_from ?? '',
        paid_to: filters.paid_to ?? '',
        method: filters.method ?? '',
        bank_id: filters.bank_id ? String(filters.bank_id) : '',
        per_page: meta.per_page ?? 25,
    });

    const breadcrumbs = [
        { title: 'Reportes', href: '' },
        { title: 'Ingresos y exoneraciones', href: '' },
    ];

    const canExport = !!auth?.can?.['reports.payment_financial_summary.export'];
    const incomeMethods = (filterOptions.methods ?? []).filter((method) => method.code !== 'EXO');
    const receiverBanks = filterOptions.banks ?? [];

    const buildFilters = (): Record<string, unknown> => {
        const reportType = data.report_type as ReportType;
        const builtFilters: Record<string, unknown> = {
            report_type: reportType,
            group_by: data.group_by,
        };

        if (data.paid_from || data.paid_to) {
            const between: Record<string, string> = {};
            if (data.paid_from) between.from = data.paid_from;
            if (data.paid_to) between.to = data.paid_to;
            builtFilters.paid_between = between;
        }

        if (reportType === 'income' && data.method) {
            builtFilters.method = data.method;
        }

        if (data.bank_id) {
            builtFilters.bank_id = Number(data.bank_id);
        }

        return builtFilters;
    };

    const buildParams = (page = 1): Record<string, unknown> => ({
        page,
        per_page: data.per_page,
        filters: buildFilters(),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        router.get(route('reports.payment-financial-summary'), buildParams(1) as any, {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'meta', 'totals', 'filters'],
        });
    };

    const handleClear = () => {
        router.get(route('reports.payment-financial-summary'), {}, { preserveState: false, preserveScroll: true });
    };

    const handleExportPdf = () => {
        const params = new URLSearchParams();
        params.set('filters', JSON.stringify(buildFilters()));
        window.open(route('reports.payment-financial-summary.export') + '?' + params.toString(), '_blank');
    };

    const hasActiveFilters = Boolean(
        data.report_type !== filters.report_type ||
            data.group_by !== filters.group_by ||
            data.paid_from !== filters.paid_from ||
            data.paid_to !== filters.paid_to ||
            (data.method || '') !== (filters.method || '') ||
            (data.bank_id || '') !== (filters.bank_id ? String(filters.bank_id) : ''),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ingresos y exoneraciones" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={FileBarChart}
                                title="Ingresos y exoneraciones"
                                description="Reporte financiero agrupado por fecha de pago, con exportación PDF membretada."
                                actions={
                                    canExport ? (
                                        <Button variant="outline" size="sm" onClick={handleExportPdf} disabled={processing}>
                                            <Download className="mr-2 h-4 w-4" /> Exportar PDF
                                        </Button>
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
                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-6 xl:grid-cols-[repeat(17,minmax(0,1fr))]">
                                        <div className="space-y-2 xl:col-span-2">
                                            <Label htmlFor="report_type">Reporte</Label>
                                            <select
                                                id="report_type"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.report_type}
                                                onChange={(e) => {
                                                    const next = e.target.value as ReportType;
                                                    setData({
                                                        ...data,
                                                        report_type: next,
                                                        method: next === 'exonerations' ? '' : data.method,
                                                    });
                                                }}
                                            >
                                                <option value="income">Ingresos</option>
                                                <option value="exonerations">Exoneraciones</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2 xl:col-span-2">
                                            <Label htmlFor="group_by">Agrupación</Label>
                                            <select
                                                id="group_by"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.group_by}
                                                onChange={(e) => setData('group_by', e.target.value as GroupBy)}
                                            >
                                                <option value="day">Diaria</option>
                                                <option value="week">Semanal</option>
                                                <option value="month">Mensual</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2 xl:col-span-2">
                                            <Label htmlFor="paid_from">Desde</Label>
                                            <Input
                                                id="paid_from"
                                                type="date"
                                                value={data.paid_from}
                                                onChange={(e) => setData('paid_from', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2 xl:col-span-2">
                                            <Label htmlFor="paid_to">Hasta</Label>
                                            <Input
                                                id="paid_to"
                                                type="date"
                                                value={data.paid_to}
                                                onChange={(e) => setData('paid_to', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2 xl:col-span-2">
                                            <Label htmlFor="method">Tipo de pago</Label>
                                            <select
                                                id="method"
                                                className="w-full rounded-md border px-3 py-2 text-sm disabled:opacity-60"
                                                value={data.method ?? ''}
                                                disabled={data.report_type === 'exonerations'}
                                                onChange={(e) => setData('method', e.target.value)}
                                            >
                                                <option value="">Todos</option>
                                                {incomeMethods.map((method) => (
                                                    <option key={method.code} value={method.code}>
                                                        {method.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="space-y-2 xl:col-span-3">
                                            <Label htmlFor="bank_id">Banco receptor</Label>
                                            <select
                                                id="bank_id"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.bank_id ?? ''}
                                                onChange={(e) => setData('bank_id', e.target.value)}
                                            >
                                                <option value="">Todos</option>
                                                {receiverBanks.map((bank) => (
                                                    <option key={bank.id} value={String(bank.id)}>
                                                        {bank.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="space-y-2 xl:col-span-2">
                                            <Label htmlFor="per_page">Registros</Label>
                                            <Input
                                                id="per_page"
                                                type="number"
                                                min={10}
                                                max={200}
                                                value={data.per_page}
                                                onChange={(e) => setData('per_page', Number(e.target.value) || 25)}
                                            />
                                        </div>
                                        <div className="flex flex-wrap items-end gap-2 md:col-span-2 lg:col-span-6 xl:col-span-2 xl:flex-nowrap">
                                            <Button type="submit" size="sm" disabled={processing}>
                                                <Search className="mr-2 h-4 w-4" /> Buscar
                                            </Button>
                                            {hasActiveFilters && (
                                                <Button type="button" variant="outline" size="sm" onClick={handleClear} disabled={processing}>
                                                    <X className="mr-2 h-4 w-4" /> Limpiar
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Reporte</div>
                                    <div className="mt-1 text-xl font-semibold">{reportTitle(filters.report_type)}</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Total registros</div>
                                    <div className="mt-1 text-xl font-semibold">{totals.count}</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Total Bs</div>
                                    <div className="mt-1 text-xl font-semibold">Bs. {formatBsMinor(totals.amount_bs_minor)}</div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resultados ({meta.total} períodos)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="text-muted-foreground border-b text-xs">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">Periodo</th>
                                                <th className="px-3 py-2 text-right font-medium">Registros</th>
                                                <th className="px-3 py-2 text-right font-medium">Total Bs</th>
                                                <th className="px-3 py-2 text-right font-medium">Promedio Bs</th>
                                                <th className="px-3 py-2 text-right font-medium">REG / CONF / CONC</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {rows.length === 0 ? (
                                                <tr>
                                                    <td colSpan={5} className="text-muted-foreground px-3 py-6 text-center text-sm">
                                                        No se encontraron registros con los filtros aplicados.
                                                    </td>
                                                </tr>
                                            ) : (
                                                rows.map((row) => (
                                                    <tr key={row.period_key} className="hover:bg-muted/40">
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.period_label}</td>
                                                        <td className="px-3 py-2 text-right text-xs whitespace-nowrap">{row.count}</td>
                                                        <td className="px-3 py-2 text-right text-xs whitespace-nowrap">
                                                            {formatBsMinor(row.amount_bs_minor)}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs whitespace-nowrap">
                                                            {formatBsMinor(row.average_bs_minor)}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs whitespace-nowrap">
                                                            {row.registered_count} / {row.confirmed_count} / {row.applied_count}
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
                                                router.get(route('reports.payment-financial-summary'), buildParams(meta.current_page - 1) as any, {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                    only: ['rows', 'meta', 'totals', 'filters'],
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
                                                router.get(route('reports.payment-financial-summary'), buildParams(meta.current_page + 1) as any, {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                    only: ['rows', 'meta', 'totals', 'filters'],
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
