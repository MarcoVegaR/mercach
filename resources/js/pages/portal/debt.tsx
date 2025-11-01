import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import React from 'react';

// Data contract from EconomicProfileService::forConcessionaire
// header, summary_bs, summary_fx, by_local, tables { charges_open, credits_open, payments_partial }, recent

type SummaryFx = {
    condo?: { currency: 'USD'; open_minor: number; overdue_minor: number };
    rent?: { currency: 'EUR'; open_minor: number; overdue_minor: number };
};

type Props = {
    header: { id: number; full_name: string };
    summary_bs: {
        open_bs_minor: number;
        overdue_bs_minor: number;
        credits_open_bs_minor: number;
        payments_available_bs_minor?: number;
        net_due_after_credit_bs_minor: number;
    };
    summary_fx?: SummaryFx;
    tables: { charges_open: Array<Record<string, any>> };
    at: string;
};

function fmtMinor(minor?: number | null, curr: 'USD' | 'EUR' | 'VES' = 'VES') {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: curr, minimumFractionDigits: 2 });
}

export default function PortalDebt({ header, summary_bs, summary_fx, tables, at }: Props) {
    const _charges = Array.isArray(tables?.charges_open) ? tables.charges_open.slice(0, 20) : [];
    const usdOpen = summary_fx?.condo?.open_minor ?? 0;
    const usdOver = summary_fx?.condo?.overdue_minor ?? 0;
    const eurOpen = summary_fx?.rent?.open_minor ?? 0;
    const eurOver = summary_fx?.rent?.overdue_minor ?? 0;
    const paymentsAvail = Number(summary_bs?.payments_available_bs_minor ?? 0);
    const netAfterCredits = Number(summary_bs?.net_due_after_credit_bs_minor ?? 0);
    const netAfterAll = Math.max(0, netAfterCredits - paymentsAvail);

    return (
        <div className="container mx-auto max-w-5xl px-4 py-8">
            <div className="mb-8 flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Mi Deuda</h1>
                    <p className="text-muted-foreground mt-2">
                        {header?.full_name} • al {at}
                    </p>
                </div>
                <Link href="/portal">
                    <Button variant="outline" size="sm">
                        Volver al Portal
                    </Button>
                </Link>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-muted-foreground text-sm">Abierta (VES)</div>
                        <div className="mt-1 text-2xl font-semibold">{fmtMinor(summary_bs.open_bs_minor)}</div>
                        <div className="text-muted-foreground mt-2 space-y-1 text-xs">
                            <div>
                                Condominio: <span className="font-medium">{fmtMinor(usdOpen, 'USD')}</span>
                            </div>
                            <div>
                                Alquiler: <span className="font-medium">{fmtMinor(eurOpen, 'EUR')}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-muted-foreground text-sm">Vencida (VES)</div>
                        <div className="mt-1 text-2xl font-semibold">{fmtMinor(summary_bs.overdue_bs_minor)}</div>
                        <div className="text-muted-foreground mt-2 space-y-1 text-xs">
                            <div>
                                Condominio: <span className="font-medium">{fmtMinor(usdOver, 'USD')}</span>
                            </div>
                            <div>
                                Alquiler: <span className="font-medium">{fmtMinor(eurOver, 'EUR')}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-muted-foreground text-sm">Créditos (VES)</div>
                        <div className="mt-1 text-2xl font-semibold">{fmtMinor(summary_bs.credits_open_bs_minor)}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-muted-foreground text-sm">Pagos disponibles (VES)</div>
                        <div className="mt-1 text-2xl font-semibold">{fmtMinor(paymentsAvail)}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-muted-foreground text-sm">Neto a pagar (VES)</div>
                        <div className="mt-1 text-2xl font-semibold">{fmtMinor(netAfterAll)}</div>
                    </CardContent>
                </Card>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Condominio (USD)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-semibold">{fmtMinor(usdOpen, 'USD')}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Alquiler (EUR)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-semibold">{fmtMinor(eurOpen, 'EUR')}</div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Cargos abiertos</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b">
                                    <th className="py-2 pr-4 text-left">Período</th>
                                    <th className="py-2 pr-4 text-left">Vence</th>
                                    <th className="py-2 pr-4 text-left">Moneda</th>
                                    <th className="py-2 pr-4 text-right">Pendiente (moneda)</th>
                                    <th className="py-2 pr-0 text-right">Pendiente (VES)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Array.isArray(tables?.charges_open) && tables.charges_open.length > 0 ? (
                                    tables.charges_open.map((c: any, i: number) => (
                                        <tr key={i} className="border-b/50">
                                            <td className="py-2 pr-4">{String(c.period ?? '')}</td>
                                            <td className="py-2 pr-4">{String(c.due_on ?? '')}</td>
                                            <td className="py-2 pr-4">{String(c.currency ?? '')}</td>
                                            <td className="py-2 pr-4 text-right">
                                                {fmtMinor(Number(c.outstanding_minor ?? c.amount_minor) || 0, String(c.currency || 'VES') as any)}
                                            </td>
                                            <td className="py-2 pr-0 text-right">
                                                {fmtMinor(Number(c.outstanding_bs_minor ?? c.amount_bs_minor) || 0, 'VES')}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td className="text-muted-foreground py-4" colSpan={5}>
                                            Sin cargos abiertos
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

PortalDebt.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
