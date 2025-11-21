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
    id: number;
    number: string;
    contract_type: string;
    contract_status: string;
    contract_status_code: string;
    start_date: string;
    end_date: string | null;
}

interface FilterOptions {
    contract_types: Array<{ id: number; name: string }>;
    contract_statuses: Array<{ id: number; name: string }>;
}

interface Meta {
    current_page: number;
    per_page: number;
    last_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
}

interface ContractsUnsignedPageProps extends PageProps {
    rows: Row[];
    meta: Meta;
    filterOptions: FilterOptions;
}

export default function ContractsUnsignedReport() {
    const { rows, meta, filterOptions } = usePage<ContractsUnsignedPageProps>().props;

    const { data, setData, processing } = useForm({
        q: '',
        contract_type_id: '',
        contract_status_id: '',
        start_from: '',
        start_to: '',
        per_page: meta.per_page ?? 15,
    });

    const breadcrumbs = [
        { title: 'Reportes', href: '' },
        { title: 'Contratos sin firma', href: '' },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const filters: Record<string, unknown> = {};
        if (data.contract_type_id) filters.contract_type_id = Number(data.contract_type_id);
        if (data.contract_status_id) filters.contract_status_id = Number(data.contract_status_id);
        if (data.start_from || data.start_to) {
            const between: Record<string, string> = {};
            if (data.start_from) between.from = data.start_from;
            if (data.start_to) between.to = data.start_to;
            filters.start_between = between;
        }

        const params: Record<string, unknown> = {
            page: 1,
            per_page: data.per_page,
        };
        if (data.q) params.q = data.q;
        if (Object.keys(filters).length > 0) params.filters = filters;

        router.get(route('reports.contracts-unsigned'), params as any, {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'meta'],
        });
    };

    const handleClear = () => {
        setData({ q: '', contract_type_id: '', contract_status_id: '', start_from: '', start_to: '', per_page: data.per_page });
        router.get(route('reports.contracts-unsigned'), {}, { preserveState: false, preserveScroll: true });
    };

    const handleExport = (format: 'csv' | 'json') => {
        const usp = new URLSearchParams();
        usp.set('format', format);
        if (data.q) usp.set('q', data.q);

        const filters: Record<string, unknown> = {};
        if (data.contract_type_id) filters.contract_type_id = Number(data.contract_type_id);
        if (data.contract_status_id) filters.contract_status_id = Number(data.contract_status_id);
        if (data.start_from || data.start_to) {
            const between: Record<string, string> = {};
            if (data.start_from) between.from = data.start_from;
            if (data.start_to) between.to = data.start_to;
            filters.start_between = between;
        }
        if (Object.keys(filters).length > 0) usp.set('filters', JSON.stringify(filters));

        window.open(route('reports.contracts-unsigned.export') + '?' + usp.toString(), '_blank');
    };

    const hasActiveFilters = Boolean(data.q || data.contract_type_id || data.contract_status_id || data.start_from || data.start_to);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contratos sin firma" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={FileText}
                                title="Contratos sin firma"
                                description="Contratos que aún no tienen fecha de firma registrada"
                                actions={
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" onClick={() => handleExport('csv')} disabled={processing}>
                                            <Download className="mr-2 h-4 w-4" /> Exportar CSV
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={() => handleExport('json')} disabled={processing}>
                                            <FileText className="mr-2 h-4 w-4" /> Exportar JSON
                                        </Button>
                                    </div>
                                }
                            />
                        </div>

                        {/* Filters Card */}
                        <Card className="mb-6">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Filter className="h-5 w-5" />
                                    Filtros
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="q">Número de contrato</Label>
                                            <Input
                                                id="q"
                                                value={data.q}
                                                onChange={(e) => setData('q', e.target.value)}
                                                placeholder="Buscar por número"
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="contract_type_id">Tipo de contrato</Label>
                                            <select
                                                id="contract_type_id"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.contract_type_id}
                                                onChange={(e) => setData('contract_type_id', e.target.value)}
                                            >
                                                <option value="">Todos</option>
                                                {filterOptions.contract_types.map((t) => (
                                                    <option key={t.id} value={t.id}>
                                                        {t.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="contract_status_id">Estado</Label>
                                            <select
                                                id="contract_status_id"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.contract_status_id}
                                                onChange={(e) => setData('contract_status_id', e.target.value)}
                                            >
                                                <option value="">Todos</option>
                                                {filterOptions.contract_statuses.map((s) => (
                                                    <option key={s.id} value={s.id}>
                                                        {s.name}
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
                                                max={100}
                                                value={data.per_page}
                                                onChange={(e) => setData('per_page', Number(e.target.value) || 15)}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="start_from">Fecha inicio desde</Label>
                                            <Input
                                                id="start_from"
                                                type="date"
                                                value={data.start_from}
                                                onChange={(e) => setData('start_from', e.target.value)}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="start_to">Fecha inicio hasta</Label>
                                            <Input
                                                id="start_to"
                                                type="date"
                                                value={data.start_to}
                                                onChange={(e) => setData('start_to', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button type="submit" size="sm" disabled={processing}>
                                            <Search className="mr-2 h-4 w-4" />
                                            Buscar
                                        </Button>
                                        {hasActiveFilters && (
                                            <Button type="button" variant="outline" size="sm" onClick={handleClear} disabled={processing}>
                                                <X className="mr-2 h-4 w-4" />
                                                Limpiar filtros
                                            </Button>
                                        )}
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        {/* Results */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resultados ({meta.total} contratos sin firma)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="text-muted-foreground border-b text-xs">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">Número</th>
                                                <th className="px-3 py-2 text-left font-medium">Tipo</th>
                                                <th className="px-3 py-2 text-left font-medium">Estado</th>
                                                <th className="px-3 py-2 text-left font-medium">Fecha inicio</th>
                                                <th className="px-3 py-2 text-left font-medium">Fecha fin</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {rows.length === 0 ? (
                                                <tr>
                                                    <td colSpan={5} className="text-muted-foreground px-3 py-6 text-center text-sm">
                                                        No se encontraron contratos sin firma con los filtros aplicados
                                                    </td>
                                                </tr>
                                            ) : (
                                                rows.map((row) => (
                                                    <tr key={row.id} className="hover:bg-muted/40">
                                                        <td className="px-3 py-2 font-mono text-xs">{row.number}</td>
                                                        <td className="px-3 py-2 text-xs">{row.contract_type || '—'}</td>
                                                        <td className="px-3 py-2 text-xs">
                                                            {row.contract_status || row.contract_status_code || '—'}
                                                        </td>
                                                        <td className="px-3 py-2 text-xs">{row.start_date}</td>
                                                        <td className="px-3 py-2 text-xs">{row.end_date || '—'}</td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Simple pagination info */}
                                <div className="text-muted-foreground mt-4 flex items-center justify-between text-xs">
                                    <div>
                                        Mostrando {meta.from ?? 0} - {meta.to ?? 0} de {meta.total}
                                    </div>
                                    {/* Navegación básica usando page param */}
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
                                                router.get(route('reports.contracts-unsigned'), params as any, {
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
                                                router.get(route('reports.contracts-unsigned'), params as any, {
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
