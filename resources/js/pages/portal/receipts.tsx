import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import React from 'react';

type Item = { id: number; receipt_number: string; issued_at: string; status: string };

type Props = { items: Item[] };

export default function PortalReceipts({ items }: Props) {
    return (
        <div className="container mx-auto max-w-5xl px-4 py-8">
            <div className="mb-8 flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Mis Recibos</h1>
                    <p className="text-muted-foreground mt-2">Descarga tus recibos emitidos</p>
                </div>
                <Link href="/portal">
                    <Button variant="outline" size="sm">
                        Volver al Portal
                    </Button>
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Recibos</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b">
                                    <th className="py-2 pr-4 text-left">Número</th>
                                    <th className="py-2 pr-4 text-left">Emitido</th>
                                    <th className="py-2 pr-4 text-left">Estado</th>
                                    <th className="py-2 pr-0 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(!items || items.length === 0) && (
                                    <tr>
                                        <td className="text-muted-foreground py-4" colSpan={4}>
                                            No hay recibos
                                        </td>
                                    </tr>
                                )}
                                {items?.map((r) => (
                                    <tr key={r.id} className="border-b/50">
                                        <td className="py-2 pr-4 font-medium">{r.receipt_number}</td>
                                        <td className="py-2 pr-4">{new Date(r.issued_at).toLocaleString()}</td>
                                        <td className="py-2 pr-4">{r.status}</td>
                                        <td className="py-2 pr-0 text-right">
                                            <a href={`/portal/recibos/${r.id}/download`} className="underline">
                                                Ver/Descargar
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

PortalReceipts.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
