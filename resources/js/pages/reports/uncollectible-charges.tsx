import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Download, FileText, Filter, Search, X } from 'lucide-react';
import React from 'react';

type CollectibilityStatus = 'current' | 'restored' | 'all';

interface Row {
    event_id: number;
    charge_id: number;
    marked_at: string;
    restored_at: string | null;
    is_current: boolean;
    currency: string;
    kind: string;
    kind_label: string;
    period: string | null;
    due_on: string | null;
    status_code: string;
    market_name: string;
    local_code: string;
    concessionaire_name: string;
    reason: string;
    marked_by: string;
    declared_outstanding_amount_minor: number;
    declared_outstanding_bs_minor: number;
    current_outstanding_amount_minor: number;
    current_outstanding_bs_minor: number;
}

interface Totals {
    count: number;
    declared_outstanding_amount_minor: number;
    declared_outstanding_bs_minor: number;
    current_outstanding_amount_minor: number;
    current_outstanding_bs_minor: number;
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
    marked_between?: { from?: string | null; to?: string | null };
    status?: CollectibilityStatus;
    currency?: string | null;
    q?: string;
}

interface UncollectibleChargesPageProps extends PageProps {
    rows: Row[];
    totals: Totals;
    meta: Meta;
    filters: AppliedFilters;
    auth?: { can?: Record<string, boolean> };
}

function formatMinor(value?: number | null): string {
    return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0) / 100);
}

export default function UncollectibleChargesReport() {
    const { rows, totals, meta, filters, auth } = usePage<UncollectibleChargesPageProps>().props;

    const { data, setData, processing } = useForm({
        q: filters.q ?? '',
        status: filters.status ?? 'current',
        currency: filters.currency ?? '',
        marked_from: filters.marked_between?.from ?? '',
        marked_to: filters.marked_between?.to ?? '',
        per_page: meta.per_page ?? 25,
    });

    const breadcrumbs = [
        { title: 'Reportes', href: '' },
        { title: 'Cargos incobrables', href: '' },
    ];

    const canExport = !!auth?.can?.['reports.uncollectible_charges.export'];

    const buildParams = (page = 1): Record<string, unknown> => ({
        page,
        per_page: data.per_page,
        q: data.q || undefined,
        filters: {
            status: data.status,
            currency: data.currency || undefined,
            marked_between: {
                from: data.marked_from || undefined,
                to: data.marked_to || undefined,
            },
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        router.get(route('reports.uncollectible-charges'), buildParams(1) as any, {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'meta', 'totals', 'filters'],
        });
    };

    const handleClear = () => {
        router.get(route('reports.uncollectible-charges'), {}, { preserveState: false, preserveScroll: true });
    };

    const handleExport = (format: 'csv' | 'json' | 'pdf') => {
        const params = new URLSearchParams();
        params.set('format', format);
        if (data.q) params.set('q', data.q);
        params.set('filters[status]', data.status);
        if (data.currency) params.set('filters[currency]', data.currency);
        if (data.marked_from) params.set('filters[marked_between][from]', data.marked_from);
        if (data.marked_to) params.set('filters[marked_between][to]', data.marked_to);

        if (format === 'pdf') {
            window.open(route('reports.uncollectible-charges.export') + '?' + params.toString(), '_blank');
            return;
        }

        window.location.href = route('reports.uncollectible-charges.export') + '?' + params.toString();
    };

    const goToPage = (page: number) => {
        router.get(route('reports.uncollectible-charges'), buildParams(page) as any, {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'meta', 'totals', 'filters'],
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cargos incobrables" />

            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={AlertTriangle}
                                title="Cargos incobrables"
                                description="Historial funcional de cargos declarados incobrables, restauraciones y saldo declarado."
                                actions={
                                    canExport ? (
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" onClick={() => handleExport('csv')} disabled={processing}>
                                                <Download className="mr-2 h-4 w-4" /> CSV
                                            </Button>
                                            <Button variant="outline" size="sm" onClick={() => handleExport('json')} disabled={processing}>
                                                <FileText className="mr-2 h-4 w-4" /> JSON
                                            </Button>
                                            <Button variant="outline" size="sm" onClick={() => handleExport('pdf')} disabled={processing}>
                                                <FileText className="mr-2 h-4 w-4" /> PDF
                                            </Button>
                                        </div>
                                    ) : undefined
                                }
                            />
                        </div>

                        <div className="mb-6 grid gap-4 md:grid-cols-3">
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Eventos</div>
                                    <div className="mt-1 text-2xl font-semibold">{totals.count}</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Saldo declarado equivalente Bs</div>
                                    <div className="mt-1 text-2xl font-semibold">Bs. {formatMinor(totals.declared_outstanding_bs_minor)}</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Saldo incobrable actual Bs</div>
                                    <div className="mt-1 text-2xl font-semibold">Bs. {formatMinor(totals.current_outstanding_bs_minor)}</div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card className="mb-6">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Filter className="h-5 w-5" /> Filtros
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div className="grid gap-4 md:grid-cols-6">
                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor="q">Buscar</Label>
                                            <Input
                                                id="q"
                                                value={data.q}
                                                onChange={(e) => setData('q', e.target.value)}
                                                placeholder="Cargo, local, cesionario o motivo"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="status">Estado</Label>
                                            <select
                                                id="status"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.status}
                                                onChange={(e) => setData('status', e.target.value as CollectibilityStatus)}
                                            >
                                                <option value="current">Actuales</option>
                                                <option value="restored">Restaurados</option>
                                                <option value="all">Todos</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="currency">Moneda</Label>
                                            <select
                                                id="currency"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.currency}
                                                onChange={(e) => setData('currency', e.target.value)}
                                            >
                                                <option value="">Todas</option>
                                                <option value="VES">VES</option>
                                                <option value="USD">USD</option>
                                                <option value="EUR">EUR</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="marked_from">Desde</Label>
                                            <Input
                                                id="marked_from"
                                                type="date"
                                                value={data.marked_from}
                                                onChange={(e) => setData('marked_from', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="marked_to">Hasta</Label>
                                            <Input
                                                id="marked_to"
                                                type="date"
                                                value={data.marked_to}
                                                onChange={(e) => setData('marked_to', e.target.value)}
                                            />
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button type="submit" size="sm" disabled={processing}>
                                            <Search className="mr-2 h-4 w-4" /> Buscar
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" onClick={handleClear} disabled={processing}>
                                            <X className="mr-2 h-4 w-4" /> Limpiar
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resultados ({meta.total})</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="text-muted-foreground border-b text-xs">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">Cargo</th>
                                                <th className="px-3 py-2 text-left font-medium">Fecha</th>
                                                <th className="px-3 py-2 text-left font-medium">Estado</th>
                                                <th className="px-3 py-2 text-left font-medium">Local</th>
                                                <th className="px-3 py-2 text-left font-medium">Cesionario</th>
                                                <th className="px-3 py-2 text-left font-medium">Tipo</th>
                                                <th className="px-3 py-2 text-right font-medium">Declarado origen</th>
                                                <th className="px-3 py-2 text-right font-medium">Actual equiv. Bs</th>
                                                <th className="px-3 py-2 text-left font-medium">Motivo</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {rows.length === 0 ? (
                                                <tr>
                                                    <td colSpan={9} className="text-muted-foreground px-3 py-6 text-center text-sm">
                                                        No se encontraron resultados
                                                    </td>
                                                </tr>
                                            ) : (
                                                rows.map((row) => (
                                                    <tr key={row.event_id} className="hover:bg-muted/40">
                                                        <td className="px-3 py-2 text-xs font-medium">#{row.charge_id}</td>
                                                        <td className="px-3 py-2 text-xs">{row.marked_at}</td>
                                                        <td className="px-3 py-2 text-xs">
                                                            <Badge variant={row.is_current ? 'destructive' : 'secondary'}>
                                                                {row.is_current ? 'Actual' : 'Restaurado'}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2 text-xs">{row.local_code || row.market_name || '—'}</td>
                                                        <td className="px-3 py-2 text-xs">{row.concessionaire_name || 'No determinado'}</td>
                                                        <td className="px-3 py-2 text-xs">{row.kind_label || row.kind || '—'}</td>
                                                        <td className="px-3 py-2 text-right text-xs tabular-nums">
                                                            {row.currency} {formatMinor(row.declared_outstanding_amount_minor)}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs tabular-nums">
                                                            Bs. {formatMinor(row.current_outstanding_bs_minor)}
                                                        </td>
                                                        <td className="max-w-xs truncate px-3 py-2 text-xs" title={row.reason}>
                                                            {row.reason || '—'}
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
                                            onClick={() => goToPage(meta.current_page - 1)}
                                        >
                                            Anterior
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={meta.current_page >= meta.last_page}
                                            onClick={() => goToPage(meta.current_page + 1)}
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
