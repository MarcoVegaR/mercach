import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Download, FileText, Filter, Search, X } from 'lucide-react';
import React from 'react';

interface Row {
    local_id: number;
    local_code: string;
    local_name: string;
    market_name: string;
    contract_number: string;
    concessionaire_id: number;
    concessionaire_name: string;
    concessionaire_total_area_m2?: number | null;
    area_m2: number | null;
    last_paid_rent_period: string | null;
    last_paid_condo_period: string | null;
    rent_debt_currency: string | null;
    condo_debt_currency: string | null;
    rent_debt: string;
    condo_debt: string;
    rent_debt_bs: string;
    condo_debt_bs: string;
    total_debt_bs: string;
}

interface Meta {
    current_page: number;
    per_page: number;
    last_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
}

interface PagePropsExtended extends PageProps {
    rows: Row[];
    meta: Meta;
}

export default function LocalsFinancialStatusReport() {
    const { rows, meta } = usePage<PagePropsExtended>().props;

    const { data, setData } = useForm({
        q: '',
        per_page: meta.per_page ?? 25,
    });

    const breadcrumbs = [
        { title: 'Reportes', href: '' },
        { title: 'Estado financiero de locales', href: '' },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const params: Record<string, unknown> = {
            page: 1,
            per_page: data.per_page,
        };
        if (data.q) params.q = data.q;

        router.get(route('reports.locals-financial-status'), params as any, {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'meta'],
        });
    };

    const handleClear = () => {
        setData({ q: '', per_page: data.per_page });
        router.get(route('reports.locals-financial-status'), {}, { preserveState: false, preserveScroll: true });
    };

    const exportUrl = (format: 'csv' | 'json' | 'xlsx') => {
        const usp = new URLSearchParams();
        usp.set('format', format);
        if (data.q) usp.set('q', data.q);
        usp.set('per_page', String(data.per_page));

        return '/reports/locals-financial-status/export' + '?' + usp.toString();
    };

    const handleExport = (format: 'csv' | 'json' | 'xlsx') => {
        window.location.href = exportUrl(format);
    };

    const hasActiveFilters = Boolean(data.q);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Estado financiero de locales" />

            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={FileText}
                                title="Estado financiero de locales"
                                description="Área (m²), último mes pagado (Uso/Condominio) y deuda pendiente"
                                actions={
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" type="button" onClick={() => handleExport('csv')}>
                                            <Download className="mr-2 h-4 w-4" /> Exportar CSV
                                        </Button>
                                        <Button variant="outline" size="sm" type="button" onClick={() => handleExport('xlsx')}>
                                            <Download className="mr-2 h-4 w-4" /> Exportar Excel
                                        </Button>
                                        <Button variant="outline" size="sm" type="button" onClick={() => handleExport('json')}>
                                            <FileText className="mr-2 h-4 w-4" /> Exportar JSON
                                        </Button>
                                    </div>
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
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor="q">Buscar</Label>
                                            <Input
                                                id="q"
                                                placeholder="Código/local, cesionario, contrato"
                                                value={data.q}
                                                onChange={(e) => setData('q', e.target.value)}
                                            />
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
                                        <Button type="submit" size="sm">
                                            <Search className="mr-2 h-4 w-4" /> Buscar
                                        </Button>
                                        {hasActiveFilters && (
                                            <Button type="button" variant="outline" size="sm" onClick={handleClear}>
                                                <X className="mr-2 h-4 w-4" /> Limpiar
                                            </Button>
                                        )}
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resultados ({meta.total} locales)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="text-muted-foreground border-b text-xs">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">Local</th>
                                                <th className="px-3 py-2 text-left font-medium">Mercado</th>
                                                <th className="px-3 py-2 text-left font-medium">Contrato</th>
                                                <th className="px-3 py-2 text-left font-medium">Cesionario</th>
                                                <th className="px-3 py-2 text-right font-medium">Área (m²)</th>
                                                <th className="px-3 py-2 text-center font-medium">Último mes (Uso)</th>
                                                <th className="px-3 py-2 text-center font-medium">Último mes (Condo)</th>
                                                <th className="px-3 py-2 text-right font-medium">Deuda Uso</th>
                                                <th className="px-3 py-2 text-right font-medium">Deuda Condo</th>
                                                <th className="px-3 py-2 text-right font-medium">Deuda Total (Bs)</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {rows.length === 0 ? (
                                                <tr>
                                                    <td colSpan={10} className="text-muted-foreground px-3 py-6 text-center text-sm">
                                                        No se encontraron resultados
                                                    </td>
                                                </tr>
                                            ) : (
                                                rows.map((row) => (
                                                    <tr key={row.local_id} className="hover:bg-muted/40">
                                                        <td className="px-3 py-2 text-xs">
                                                            <a
                                                                href={`/catalogs/local/${row.local_id}`}
                                                                className="text-blue-600 hover:underline dark:text-blue-400"
                                                            >
                                                                {row.local_code || row.local_name || row.local_id}
                                                            </a>
                                                        </td>
                                                        <td className="px-3 py-2 text-xs">{row.market_name || '—'}</td>
                                                        <td className="px-3 py-2 text-xs">{row.contract_number || '—'}</td>
                                                        <td className="px-3 py-2 text-xs">{row.concessionaire_name || '—'}</td>
                                                        <td className="px-3 py-2 text-right text-xs">{row.area_m2 ?? '—'}</td>
                                                        <td className="px-3 py-2 text-center text-xs">{row.last_paid_rent_period ?? '—'}</td>
                                                        <td className="px-3 py-2 text-center text-xs">{row.last_paid_condo_period ?? '—'}</td>
                                                        <td className="px-3 py-2 text-right text-xs tabular-nums">
                                                            {(row.rent_debt_currency ? row.rent_debt_currency + ' ' : '') + row.rent_debt}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs tabular-nums">
                                                            {(row.condo_debt_currency ? row.condo_debt_currency + ' ' : '') + row.condo_debt}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs font-semibold tabular-nums">
                                                            {row.total_debt_bs}
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
                                            disabled={meta.current_page <= 1}
                                            onClick={() => {
                                                const params: Record<string, unknown> = {
                                                    page: meta.current_page - 1,
                                                    per_page: meta.per_page,
                                                };
                                                if (data.q) params.q = data.q;
                                                router.get(route('reports.locals-financial-status'), params as any, {
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
                                            disabled={meta.current_page >= meta.last_page}
                                            onClick={() => {
                                                const params: Record<string, unknown> = {
                                                    page: meta.current_page + 1,
                                                    per_page: meta.per_page,
                                                };
                                                if (data.q) params.q = data.q;
                                                router.get(route('reports.locals-financial-status'), params as any, {
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
