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
    recovered_at: string;
    local_id: number;
    local_code: string;
    local_name: string;
    market_name: string;
    contract_id: number;
    contract_number: string;
    concessionaire_name: string;
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

export default function LocalsRecoveredReport() {
    const { rows, meta } = usePage<PagePropsExtended>().props;

    const { data, setData } = useForm({
        recovered_from: '',
        recovered_to: '',
        per_page: meta.per_page ?? 25,
    });

    const breadcrumbs = [
        { title: 'Reportes', href: '' },
        { title: 'Locales recuperados', href: '' },
    ];

    const buildFilters = () => {
        const filters: Record<string, unknown> = {};
        if (data.recovered_from || data.recovered_to) {
            const between: Record<string, string> = {};
            if (data.recovered_from) between.from = data.recovered_from;
            if (data.recovered_to) between.to = data.recovered_to;
            filters.recovered_between = between;
        }
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

        router.get(route('reports.locals-recovered'), params as any, {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'meta'],
        });
    };

    const handleClear = () => {
        setData({ recovered_from: '', recovered_to: '', per_page: data.per_page });
        router.get(route('reports.locals-recovered'), {}, { preserveState: false, preserveScroll: true });
    };

    const handleExport = (format: 'csv' | 'json') => {
        const usp = new URLSearchParams();
        usp.set('format', format);
        const filters = buildFilters();
        if (Object.keys(filters).length > 0) usp.set('filters', JSON.stringify(filters));

        window.open(route('reports.locals-recovered.export') + '?' + usp.toString(), '_blank');
    };

    const hasActiveFilters = Boolean(data.recovered_from || data.recovered_to);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Locales recuperados" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={FileText}
                                title="Locales recuperados"
                                description="Locales que pasaron de ocupados a disponibles por terminación de contrato"
                                actions={
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" onClick={() => handleExport('csv')}>
                                            <Download className="mr-2 h-4 w-4" /> Exportar CSV
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={() => handleExport('json')}>
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
                                        <div className="space-y-2">
                                            <Label htmlFor="recovered_from">Recuperado desde</Label>
                                            <Input
                                                id="recovered_from"
                                                type="date"
                                                value={data.recovered_from}
                                                onChange={(e) => setData('recovered_from', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="recovered_to">Recuperado hasta</Label>
                                            <Input
                                                id="recovered_to"
                                                type="date"
                                                value={data.recovered_to}
                                                onChange={(e) => setData('recovered_to', e.target.value)}
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
                                                <X className="mr-2 h-4 w-4" /> Limpiar filtros
                                            </Button>
                                        )}
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resultados ({meta.total} recuperaciones)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="text-muted-foreground border-b text-xs">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">Fecha recuperación</th>
                                                <th className="px-3 py-2 text-left font-medium">Local</th>
                                                <th className="px-3 py-2 text-left font-medium">Mercado</th>
                                                <th className="px-3 py-2 text-left font-medium">Contrato</th>
                                                <th className="px-3 py-2 text-left font-medium">Cesionario</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {rows.length === 0 ? (
                                                <tr>
                                                    <td colSpan={5} className="text-muted-foreground px-3 py-6 text-center text-sm">
                                                        No se encontraron locales recuperados con los filtros aplicados
                                                    </td>
                                                </tr>
                                            ) : (
                                                rows.map((row, idx) => (
                                                    <tr key={`${row.local_id}-${idx}`} className="hover:bg-muted/40">
                                                        <td className="px-3 py-2 text-xs whitespace-nowrap">{row.recovered_at}</td>
                                                        <td className="px-3 py-2 text-xs">
                                                            <a
                                                                href={`/catalogs/local/${row.local_id}`}
                                                                className="text-blue-600 hover:underline dark:text-blue-400"
                                                            >
                                                                {row.local_code || row.local_name || row.local_id}
                                                            </a>
                                                        </td>
                                                        <td className="px-3 py-2 text-xs">{row.market_name || '—'}</td>
                                                        <td className="px-3 py-2 text-xs">
                                                            <a
                                                                href={`/catalogs/contract/${row.contract_id}`}
                                                                className="text-blue-600 hover:underline dark:text-blue-400"
                                                            >
                                                                {row.contract_number}
                                                            </a>
                                                        </td>
                                                        <td className="px-3 py-2 text-xs">{row.concessionaire_name || '—'}</td>
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
                                                const filters = buildFilters();
                                                if (Object.keys(filters).length > 0) params.filters = filters;
                                                router.get(route('reports.locals-recovered'), params as any, {
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
                                                const filters = buildFilters();
                                                if (Object.keys(filters).length > 0) params.filters = filters;
                                                router.get(route('reports.locals-recovered'), params as any, {
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
