import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';

type Item = {
    id: number;
    paid_on?: string;
    amount_bs_minor: number;
    applied_bs_minor: number;
    available_bs_minor: number;
    method?: string;
    status?: string;
};

type Props = {
    items: Item[];
};

function fmtMinor(minor?: number | null) {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: 'VES', minimumFractionDigits: 2 });
}

export default function PortalPaymentsIndex() {
    const { items = [] } = usePage<Props>().props;

    return (
        <AppLayout>
            <Head title="Mis pagos" />
            <div className="container mx-auto max-w-5xl px-4 py-8">
                <div className="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Mis pagos</h1>
                        <p className="text-muted-foreground mt-2">Registra y cruza tus pagos</p>
                    </div>
                    <Link href="/portal/pagos/nuevo">
                        <Button>Nuevo pago</Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Pagos recientes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b">
                                        <th className="py-2 pr-4 text-left">ID</th>
                                        <th className="py-2 pr-4 text-left">Fecha</th>
                                        <th className="py-2 pr-4 text-left">Método</th>
                                        <th className="py-2 pr-4 text-left">Estado</th>
                                        <th className="py-2 pr-4 text-right">Monto (Bs)</th>
                                        <th className="py-2 pr-4 text-right">Asignado</th>
                                        <th className="py-2 pr-4 text-right">Disponible</th>
                                        <th className="py-2 pr-0 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {Array.isArray(items) && items.length > 0 ? (
                                        items.map((it) => {
                                            const canApply = String(it.status || '') === 'CONFIRMED' && Number(it.available_bs_minor || 0) > 0;
                                            return (
                                                <tr key={it.id} className="border-b/50">
                                                    <td className="py-2 pr-4">{it.id}</td>
                                                    <td className="py-2 pr-4">{String(it.paid_on || '')}</td>
                                                    <td className="py-2 pr-4">{String(it.method || '')}</td>
                                                    <td className="py-2 pr-4">{String(it.status || '')}</td>
                                                    <td className="py-2 pr-4 text-right">{fmtMinor(it.amount_bs_minor)}</td>
                                                    <td className="py-2 pr-4 text-right">{fmtMinor(it.applied_bs_minor)}</td>
                                                    <td className="py-2 pr-4 text-right">{fmtMinor(it.available_bs_minor)}</td>
                                                    <td className="py-2 pr-0 text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <Link href={`/portal/pagos/${it.id}/cruzar`}>
                                                                <Button size="sm" disabled={!canApply}>
                                                                    Cruzar
                                                                </Button>
                                                            </Link>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td className="text-muted-foreground py-4" colSpan={8}>
                                                Sin pagos
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
