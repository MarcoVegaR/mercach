import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, FileBarChart, FileText, Filter, Search, X } from 'lucide-react';
import { FormEventHandler } from 'react';

interface BankValidation {
    id: number;
    paid_on: string;
    reference: string;
    origin_account: string | null;
    destination_account: string | null;
    amount_bs: number;
    payer_document: string | null;
    gateway_resp_code: string | null;
    gateway_message: string | null;
    req_id: string | null;
    status: string;
    method: string | null;
}

interface ResponseCode {
    code: string;
    label: string;
}

interface Filters {
    date_from: string | null;
    date_to: string | null;
    response_code: string | null;
    status: string | null;
    per_page: number;
}

export default function BankValidationsReport() {
    const { rows, meta, responseCodes, links } = usePage<{
        rows: BankValidation[];
        meta: { current_page: number; from: number | null; last_page: number; per_page: number; to: number | null; total: number };
        responseCodes: ResponseCode[];
        links?: { url: string | null; label: string; active: boolean }[];
    }>().props;
    const filters = { date_from: '', date_to: '', response_code: '', status: '', per_page: 15 } as Filters;
    const { data, setData, get, processing } = useForm({
        date_from: filters.date_from || '',
        date_to: filters.date_to || '',
        response_code: filters.response_code || '',
        status: filters.status || '',
        per_page: filters.per_page || 15,
    });

    const breadcrumbs = [
        { title: 'Reportes', href: '' },
        { title: 'Validaciones Bancarias', href: '' },
    ];

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        get(route('reports.bank-validations'), { preserveState: true });
    };

    const handleClear = () => {
        setData({
            date_from: '',
            date_to: '',
            response_code: '',
            status: '',
            per_page: 15,
        });
        router.get(route('reports.bank-validations'));
    };

    const handleExport = (format: 'csv' | 'json') => {
        const params = new URLSearchParams();
        if (data.date_from) params.append('date_from', data.date_from);
        if (data.date_to) params.append('date_to', data.date_to);
        if (data.response_code) params.append('response_code', data.response_code);
        if (data.status) params.append('status', data.status);
        params.append('format', format);

        window.open(route('reports.bank-validations.export') + '?' + params.toString(), '_blank');
    };

    const hasActiveFilters = data.date_from || data.date_to || data.response_code || data.status;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
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
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Filter className="h-5 w-5" />
                                    Filtros
                                </CardTitle>
                                <CardDescription>Filtra las validaciones por fecha, código de respuesta o estado</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="date_from">Fecha desde</Label>
                                            <Input
                                                id="date_from"
                                                type="date"
                                                value={data.date_from}
                                                onChange={(e) => setData('date_from', e.target.value)}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="date_to">Fecha hasta</Label>
                                            <Input
                                                id="date_to"
                                                type="date"
                                                value={data.date_to}
                                                onChange={(e) => setData('date_to', e.target.value)}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="response_code">Código de respuesta</Label>
                                            <Select
                                                value={data.response_code}
                                                onValueChange={(value) => setData('response_code', value === '__ALL__' ? '' : value)}
                                            >
                                                <SelectTrigger id="response_code">
                                                    <SelectValue placeholder="Todos los códigos" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="__ALL__">Todos los códigos</SelectItem>
                                                    {responseCodes.map((rc) => (
                                                        <SelectItem key={rc.code} value={rc.code}>
                                                            {rc.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="status">Estado del pago</Label>
                                            <Select
                                                value={data.status}
                                                onValueChange={(value) => setData('status', value === '__ALL__' ? '' : value)}
                                            >
                                                <SelectTrigger id="status">
                                                    <SelectValue placeholder="Todos los estados" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="__ALL__">Todos los estados</SelectItem>
                                                    <SelectItem value="REGISTERED">REGISTERED</SelectItem>
                                                    <SelectItem value="CONFIRMED">CONFIRMED</SelectItem>
                                                    <SelectItem value="APPLIED">APPLIED</SelectItem>
                                                </SelectContent>
                                            </Select>
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

                        {/* Results Table */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Resultados ({meta.total} validaciones)</CardTitle>
                                <CardDescription>
                                    Mostrando {meta.from || 0} - {meta.to || 0} de {meta.total} registros
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b">
                                            <tr>
                                                <th className="px-4 py-2 text-left font-medium">Fecha de pago</th>
                                                <th className="px-4 py-2 text-left font-medium">Nro. Referencia</th>
                                                <th className="px-4 py-2 text-left font-medium">Cuenta/Origen</th>
                                                <th className="px-4 py-2 text-left font-medium">Cuenta/Destino</th>
                                                <th className="px-4 py-2 text-right font-medium">Monto</th>
                                                <th className="px-4 py-2 text-left font-medium">Cedula/RIF</th>
                                                <th className="px-4 py-2 text-left font-medium">Código</th>
                                                <th className="px-4 py-2 text-left font-medium">Respuesta</th>
                                                <th className="px-4 py-2 text-left font-medium">ReqId</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {rows.length === 0 ? (
                                                <tr>
                                                    <td colSpan={9} className="text-muted-foreground px-4 py-8 text-center">
                                                        No se encontraron validaciones con los filtros aplicados
                                                    </td>
                                                </tr>
                                            ) : (
                                                rows.map((payment: BankValidation) => (
                                                    <tr key={payment.id} className="hover:bg-muted/50">
                                                        <td className="px-4 py-2 whitespace-nowrap">{payment.paid_on}</td>
                                                        <td className="px-4 py-2 font-mono text-xs">{payment.reference}</td>
                                                        <td className="max-w-[150px] truncate px-4 py-2 font-mono text-xs">
                                                            {payment.origin_account || '-'}
                                                        </td>
                                                        <td className="max-w-[150px] truncate px-4 py-2 text-xs">
                                                            {payment.destination_account || '-'}
                                                        </td>
                                                        <td className="px-4 py-2 text-right font-medium">
                                                            {new Intl.NumberFormat('es-VE', {
                                                                style: 'currency',
                                                                currency: 'VES',
                                                            }).format(payment.amount_bs)}
                                                        </td>
                                                        <td className="px-4 py-2 font-mono text-xs">{payment.payer_document || '-'}</td>
                                                        <td className="px-4 py-2">
                                                            <span className="font-mono text-xs">{payment.gateway_resp_code || '-'}</span>
                                                        </td>
                                                        <td className="max-w-[200px] px-4 py-2">
                                                            <span className="text-muted-foreground text-xs">{payment.gateway_message || '-'}</span>
                                                        </td>
                                                        <td className="text-muted-foreground px-4 py-2 font-mono text-xs">{payment.req_id || '-'}</td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Pagination */}
                                {links && links.length > 3 && (
                                    <div className="mt-4 flex items-center justify-between">
                                        <div className="text-muted-foreground text-sm">
                                            Página {meta.current_page} de {meta.last_page}
                                        </div>
                                        <div className="flex gap-1">
                                            {links.map((link: { url: string | null; label: string; active: boolean }, index: number) => {
                                                if (!link.url) {
                                                    return (
                                                        <Button
                                                            key={index}
                                                            variant="outline"
                                                            size="sm"
                                                            disabled
                                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                                        />
                                                    );
                                                }

                                                return (
                                                    <Link key={index} href={link.url} preserveState>
                                                        <Button
                                                            variant={link.active ? 'default' : 'outline'}
                                                            size="sm"
                                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                                        />
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
