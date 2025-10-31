import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import React from 'react';

interface Item {
    id: number;
    number: string;
    status: string;
    start_date: string;
    end_date: string;
    locals_label?: string;
}

type Props = { items: Item[] };

export default function PortalContracts({ items }: Props) {
    return (
        <div className="container mx-auto max-w-5xl px-4 py-8">
            <div className="mb-8 flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Mis Contratos</h1>
                    <p className="text-muted-foreground mt-2">Listado de contratos y cantidad de locales asociados</p>
                </div>
                <Link href="/portal">
                    <Button variant="outline" size="sm">
                        Volver al Portal
                    </Button>
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Contratos</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b">
                                    <th className="py-2 pr-4 text-left">Número</th>
                                    <th className="py-2 pr-4 text-left">Estado</th>
                                    <th className="py-2 pr-4 text-left">Inicio</th>
                                    <th className="py-2 pr-4 text-left">Fin</th>
                                    <th className="py-2 pr-0 text-left">Locales</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(!items || items.length === 0) && (
                                    <tr>
                                        <td className="text-muted-foreground py-4" colSpan={5}>
                                            No hay contratos
                                        </td>
                                    </tr>
                                )}
                                {items?.map((c) => (
                                    <tr key={c.id} className="border-b/50">
                                        <td className="py-2 pr-4 font-medium">
                                            <Link href={`/portal/contratos/${c.id}`} className="underline">
                                                {c.number || `#${c.id}`}
                                            </Link>
                                        </td>
                                        <td className="py-2 pr-4">{c.status}</td>
                                        <td className="py-2 pr-4">{c.start_date}</td>
                                        <td className="py-2 pr-4">{c.end_date || '—'}</td>
                                        <td className="py-2 pr-0">{c.locals_label || '—'}</td>
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

PortalContracts.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
