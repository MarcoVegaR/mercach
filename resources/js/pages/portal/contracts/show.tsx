import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import React from 'react';

interface HistoryItem {
    from_code: string;
    to_code: string;
    occurred_at: string;
}

interface Item {
    id: number;
    number: string;
    contract_status: string;
    contract_status_code: string;
    contract_modality: string;
    start_date: string;
    end_date: string | null;
    monthly_price_eur?: number | null;
    locals_count: number;
    locals: string[];
    pdf_path?: string;
    status_history: HistoryItem[];
}

type Props = { item: Item };

export default function PortalContractShow({ item }: Props) {
    const statusColor = (code: string) => {
        const c = (code || '').toUpperCase();
        if (c === 'VIG' || c === 'EXT') return 'bg-emerald-100 text-emerald-800';
        if (c === 'VENC') return 'bg-amber-100 text-amber-800';
        if (c === 'TERM') return 'bg-rose-100 text-rose-800';
        return 'bg-slate-100 text-slate-800';
    };
    const priceFmt = (minor?: number | null) =>
        typeof minor === 'number' ? (minor / 100).toLocaleString(undefined, { style: 'currency', currency: 'EUR' }) : '—';

    return (
        <div className="container mx-auto max-w-5xl px-4 py-8">
            <div className="mb-8 flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Contrato {item.number || `#${item.id}`}</h1>
                    <p className="text-muted-foreground mt-2 flex items-center gap-2">
                        Estado: <Badge className={statusColor(item.contract_status_code)}>{item.contract_status}</Badge>
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Link href="/portal/contratos">
                        <Button variant="outline" size="sm">
                            Volver a Contratos
                        </Button>
                    </Link>
                    <Link href="/portal">
                        <Button variant="outline" size="sm">
                            Volver al Portal
                        </Button>
                    </Link>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Detalles</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="text-sm">
                            Modalidad: <span className="font-medium">{item.contract_modality || '—'}</span>
                        </div>
                        <div className="text-sm">
                            Inicio: <span className="font-medium">{item.start_date}</span>
                        </div>
                        <div className="text-sm">
                            Fin: <span className="font-medium">{item.end_date || '—'}</span>
                        </div>
                        <div className="text-sm">
                            Precio mensual (EUR): <span className="font-medium">{priceFmt(item.monthly_price_eur as any)}</span>
                        </div>
                        {item.pdf_path && (
                            <div className="text-sm">
                                Documento:{' '}
                                <a className="underline" href={`/${item.pdf_path}`} target="_blank" rel="noopener noreferrer">
                                    Descargar PDF
                                </a>
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Locales ({item.locals_count})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {item.locals?.length ? (
                            <ul className="list-disc space-y-1 pl-5 text-sm">
                                {item.locals.map((l, i) => (
                                    <li key={i}>{l}</li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">Sin locales asociados</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle className="text-base">Historial de estatus</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b">
                                    <th className="py-2 pr-4 text-left">Desde</th>
                                    <th className="py-2 pr-4 text-left">Hacia</th>
                                    <th className="py-2 pr-0 text-left">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                {item.status_history?.length ? (
                                    item.status_history.map((h, i) => (
                                        <tr key={i} className="border-b/50">
                                            <td className="py-2 pr-4">{h.from_code || '—'}</td>
                                            <td className="py-2 pr-4">{h.to_code}</td>
                                            <td className="py-2 pr-0">{new Date(h.occurred_at).toLocaleString()}</td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td className="text-muted-foreground py-4" colSpan={3}>
                                            Sin historial
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

PortalContractShow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
