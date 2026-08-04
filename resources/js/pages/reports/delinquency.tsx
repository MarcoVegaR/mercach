import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Download, Filter, Search, X } from 'lucide-react';
import React from 'react';

type Scope = 'concessionaire' | 'local';
type DebtType = 'overdue' | 'current';

interface Row {
    debtor_type: string;
    debtor_id: number;
    debtor_name: string;
    debtor_code: string;
    debtor_document: string;
    concessionaire_name: string;
    market_names: string;
    local_codes: string;
    locals_count: number;
    selected_charge_count: number;
    gross_selected_bs_minor: number;
    final_due_bs_minor: number;
    credits_open_bs_minor: number;
    payments_available_bs_minor: number;
    max_days_overdue: number;
    oldest_due_on?: string | null;
    next_due_on?: string | null;
}

interface Totals {
    debtors_count: number;
    locals_count: number;
    charges_count: number;
    gross_selected_bs_minor: number;
    final_due_bs_minor: number;
    credits_open_bs_minor: number;
    payments_available_bs_minor: number;
    max_days_overdue: number;
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
    scope: Scope;
    debt_type: DebtType;
    cutoff_date: string;
    cutoff_at: string;
}

interface DelinquencyPageProps extends PageProps {
    rows: Row[];
    totals: Totals;
    meta: Meta;
    filters: AppliedFilters;
    auth?: {
        can?: Record<string, boolean>;
    };
}

function formatBsMinor(value?: number | null): string {
    return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0) / 100);
}

function scopeLabel(scope: Scope): string {
    return scope === 'local' ? 'Por local' : 'Por cesionario';
}

function debtTypeLabel(debtType: DebtType): string {
    return debtType === 'current' ? 'Por vencer' : 'Vencida';
}

export default function DelinquencyReport() {
    const { rows, totals, meta, filters, auth } = usePage<DelinquencyPageProps>().props;

    const { data, setData, processing } = useForm({
        scope: filters.scope ?? 'concessionaire',
        debt_type: filters.debt_type ?? 'overdue',
        per_page: meta.per_page ?? 25,
    });

    const breadcrumbs = [
        { title: 'Reportes', href: '' },
        { title: 'Morosidad', href: '' },
    ];

    const canExport = !!auth?.can?.['reports.delinquency.export'];
    const isOverdue = filters.debt_type === 'overdue';

    const buildParams = (page = 1): Record<string, unknown> => ({
        page,
        per_page: data.per_page,
        scope: data.scope,
        debt_type: data.debt_type,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        router.get(route('reports.delinquency'), buildParams(1) as any, {
            preserveState: true,
            preserveScroll: true,
            only: ['rows', 'meta', 'totals', 'filters'],
        });
    };

    const handleClear = () => {
        router.get(route('reports.delinquency'), {}, { preserveState: false, preserveScroll: true });
    };

    const handleExportPdf = () => {
        const params = new URLSearchParams();
        const exportPerPage = Math.min(Math.max(Number(data.per_page) || 25, 10), 100);
        params.set('page', String(meta.current_page));
        params.set('per_page', String(exportPerPage));
        params.set('scope', data.scope);
        params.set('debt_type', data.debt_type);
        window.open(route('reports.delinquency.export') + '?' + params.toString(), '_blank');
    };

    const hasActiveFilters = data.scope !== filters.scope || data.debt_type !== filters.debt_type;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reporte de morosidad" />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-8">
                            <IndexHeaderHero
                                icon={AlertTriangle}
                                title="Reporte de morosidad"
                                description="Ranking de deuda cobrable por antigüedad o próximo vencimiento; excluye cargos incobrables. El detalle se consulta en el Estado de Cuenta."
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
                                    <div className="grid gap-4 md:grid-cols-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="scope">Alcance</Label>
                                            <select
                                                id="scope"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.scope}
                                                onChange={(e) => setData('scope', e.target.value as Scope)}
                                            >
                                                <option value="concessionaire">Por cesionario</option>
                                                <option value="local">Por local</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="debt_type">Tipo de deuda</Label>
                                            <select
                                                id="debt_type"
                                                className="w-full rounded-md border px-3 py-2 text-sm"
                                                value={data.debt_type}
                                                onChange={(e) => setData('debt_type', e.target.value as DebtType)}
                                            >
                                                <option value="overdue">Vencida</option>
                                                <option value="current">Por vencer</option>
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="per_page">Registros</Label>
                                            <Input
                                                id="per_page"
                                                type="number"
                                                min={10}
                                                max={100}
                                                value={data.per_page}
                                                onChange={(e) => setData('per_page', Number(e.target.value) || 25)}
                                            />
                                            <p className="text-muted-foreground text-xs">
                                                El PDF exporta los registros visibles de la página actual.
                                            </p>
                                        </div>
                                        <div className="flex items-end gap-2">
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

                        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Alcance</div>
                                    <div className="mt-1 text-xl font-semibold">{scopeLabel(filters.scope)}</div>
                                    <div className="text-muted-foreground mt-1 text-xs">Corte: {filters.cutoff_date}</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Tipo</div>
                                    <div className="mt-1 text-xl font-semibold">{debtTypeLabel(filters.debt_type)}</div>
                                    <div className="text-muted-foreground mt-1 text-xs">{totals.charges_count} cargos incluidos</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Deudores</div>
                                    <div className="mt-1 text-xl font-semibold">{totals.debtors_count}</div>
                                    <div className="text-muted-foreground mt-1 text-xs">{totals.locals_count} locales referenciados</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <div className="text-muted-foreground text-sm">Deuda neta cobrable Bs</div>
                                    <div className="mt-1 text-xl font-semibold">{formatBsMinor(totals.final_due_bs_minor)}</div>
                                    <div className="text-muted-foreground mt-1 text-xs">Después de saldos del mismo alcance</div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resultados ({meta.total} deudores)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="text-muted-foreground border-b text-xs">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">Deudor</th>
                                                <th className="px-3 py-2 text-left font-medium">Referencia</th>
                                                <th className="px-3 py-2 text-left font-medium">Mercado</th>
                                                <th className="px-3 py-2 text-left font-medium">Locales</th>
                                                <th className="px-3 py-2 text-right font-medium">Cargos</th>
                                                <th className="px-3 py-2 text-right font-medium">{isOverdue ? 'Mora máx.' : 'Próximo'}</th>
                                                <th className="px-3 py-2 text-right font-medium">Bruto Bs</th>
                                                <th className="px-3 py-2 text-right font-medium">Neto Bs</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {rows.length === 0 ? (
                                                <tr>
                                                    <td colSpan={8} className="text-muted-foreground px-3 py-6 text-center text-sm">
                                                        No se encontraron deudores con los filtros aplicados.
                                                    </td>
                                                </tr>
                                            ) : (
                                                rows.map((row) => (
                                                    <tr key={`${row.debtor_type}-${row.debtor_id}`} className="hover:bg-muted/40">
                                                        <td className="px-3 py-2 text-xs">
                                                            <div className="font-medium">{row.debtor_name}</div>
                                                            {row.debtor_code ? <div className="text-muted-foreground">{row.debtor_code}</div> : null}
                                                        </td>
                                                        <td className="px-3 py-2 text-xs">
                                                            {filters.scope === 'local'
                                                                ? row.concessionaire_name || 'Sin cesionario'
                                                                : row.debtor_document || 'N/A'}
                                                        </td>
                                                        <td className="px-3 py-2 text-xs">{row.market_names || 'N/A'}</td>
                                                        <td className="px-3 py-2 text-xs">
                                                            <div>{row.local_codes || 'N/A'}</div>
                                                            {row.locals_count > 0 ? (
                                                                <div className="text-muted-foreground">{row.locals_count} local(es)</div>
                                                            ) : null}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs whitespace-nowrap">
                                                            {row.selected_charge_count}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs whitespace-nowrap">
                                                            {isOverdue ? `${row.max_days_overdue} días` : row.next_due_on || 'N/A'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs whitespace-nowrap">
                                                            {formatBsMinor(row.gross_selected_bs_minor)}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs font-semibold whitespace-nowrap">
                                                            {formatBsMinor(row.final_due_bs_minor)}
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
                                                router.get(route('reports.delinquency'), buildParams(meta.current_page - 1) as any, {
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
                                                router.get(route('reports.delinquency'), buildParams(meta.current_page + 1) as any, {
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
