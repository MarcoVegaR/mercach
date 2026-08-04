import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import React from 'react';

type Summary = {
    open_bs_minor: number;
    overdue_bs_minor: number;
    payments_available_bs_minor: number;
    credits_open_bs_minor: number;
    net_due_after_credit_bs_minor: number;
    aging?: { '0_30': number; '31_60': number; '61_90': number; '90_plus': number };
};

type Header = {
    id: number;
    code?: string;
    name?: string;
    concessionaire?: { id: number; full_name: string } | null;
};

type ChargeRow = {
    charge_id: number;
    local_id?: number;
    local_label?: string | null;
    period: string;
    due_on?: string;
    amount_bs_minor: number | null;
    allocated_bs_minor: number;
    credited_bs_minor: number;
    outstanding_bs_minor: number;
    kind?: string;
};

type PaymentPartial = {
    payment_id: number;
    paid_on?: string;
    status?: string | null;
    applied_bs_minor: number;
    available_bs_minor: number;
};

type CreditOpen = {
    credit_id: number;
    balance_minor: number;
    source_payment_id?: number;
    created_at?: string;
};

type Props = {
    header: Header;
    summary_bs: Summary;
    summary_fx?: {
        condo?: { currency: 'USD'; open_minor: number; overdue_minor: number };
        rent?: { currency: 'EUR'; open_minor: number; overdue_minor: number };
        rent_m2?: { currency: 'EUR'; open_minor: number; overdue_minor: number };
        rent_fixed?: { currency: 'USD'; open_minor: number; overdue_minor: number };
    };
    by_local: Array<{
        local_id: number;
        local_label?: string | null;
        open_bs_minor: number;
        overdue_bs_minor: number;
        partial_applied_bs_minor: number;
        net_due_bs_minor: number;
    }>;
    tables: {
        charges_open: ChargeRow[];
        payments_partial: PaymentPartial[];
        credits_open: CreditOpen[];
    };
    recent?: Array<{ date: string; kind: string; description: string; amount_bs_minor: number; ref_id: number }>;
};

function fmtBs(n?: number | null) {
    if (typeof n !== 'number') return '-';
    return (n / 100).toLocaleString(undefined, { style: 'currency', currency: 'VES', minimumFractionDigits: 2 });
}

function fmtPaidOnDate(d?: string | null): string {
    if (!d) return '';
    try {
        const safe = /^\d{4}-\d{2}-\d{2}$/.test(d) ? new Date(`${d}T12:00:00Z`) : new Date(d);
        return new Intl.DateTimeFormat('es-VE', {
            timeZone: 'America/Caracas',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(safe);
    } catch {
        return String(d);
    }
}

function fmt(minor?: number | null, curr: 'USD' | 'EUR' | 'VES' = 'VES') {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: curr, minimumFractionDigits: 2 });
}

function formatPeriod(v?: string | null): string {
    if (!v) return '—';
    try {
        return new Date(v).toLocaleDateString('es-ES', { year: 'numeric', month: 'short' });
    } catch {
        return String(v);
    }
}

export default function EconomicProfileLocal(props: Props) {
    const { header, summary_bs, summary_fx, by_local: _by_local, tables, recent } = props;
    const atParam = React.useMemo(() => {
        if (typeof window === 'undefined') return new Date().toISOString().slice(0, 10);
        const p = new URLSearchParams(window.location.search);
        return p.get('at') || new Date().toISOString().slice(0, 10);
    }, []);
    const exportUrl = (format: 'csv' | 'json') =>
        `/admin/economic-profile/export?scope=local&id=${header.id}&format=${format}&at=${encodeURIComponent(atParam)}`;
    return (
        <ShowLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Perfil Económico — Local</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {header.code} · {header.name}
                    </p>
                </div>
            }
            actions={
                <div className="flex gap-2">
                    <a href={exportUrl('csv')} className="hover:bg-muted/40 inline-flex items-center rounded-md border px-3 py-2 text-sm">
                        Exportar CSV
                    </a>
                    <a href={exportUrl('json')} className="hover:bg-muted/40 inline-flex items-center rounded-md border px-3 py-2 text-sm">
                        Exportar JSON
                    </a>
                </div>
            }
        >
            <ShowSection id="resumen" title="Resumen">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-muted-foreground text-sm">Deuda abierta cobrable (VES)</div>
                            <div className="mt-1 text-2xl font-semibold">{fmtBs(summary_bs.open_bs_minor)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-muted-foreground text-sm">Vencida cobrable (VES)</div>
                            <div className="mt-1 text-2xl font-semibold">{fmtBs(summary_bs.overdue_bs_minor)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-muted-foreground text-sm">Pagos disponibles</div>
                            <div className="mt-1 text-2xl font-semibold">{fmtBs(summary_bs.payments_available_bs_minor)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-muted-foreground text-sm">Créditos (saldo a favor)</div>
                            <div className="mt-1 text-2xl font-semibold">{fmtBs(summary_bs.credits_open_bs_minor)}</div>
                        </CardContent>
                    </Card>
                    <Card className="lg:col-span-2">
                        <CardContent className="pt-6">
                            <div className="text-muted-foreground text-sm">Neto tras crédito</div>
                            <div className="mt-1 text-2xl font-semibold">{fmtBs(summary_bs.net_due_after_credit_bs_minor)}</div>
                        </CardContent>
                    </Card>
                </div>
                {(summary_fx?.condo || summary_fx?.rent_m2 || summary_fx?.rent_fixed || summary_fx?.rent) && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {summary_fx?.condo && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Condominio (USD)</CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-3 pt-2">
                                    <div>
                                        <div className="text-muted-foreground text-xs">Abierto</div>
                                        <div className="text-lg font-semibold">{fmt(summary_fx.condo.open_minor, 'USD')}</div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">Vencido cobrable</div>
                                        <div className="text-lg font-semibold">{fmt(summary_fx.condo.overdue_minor, 'USD')}</div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                        {(summary_fx?.rent_m2 || summary_fx?.rent) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Alquiler m² (EUR)</CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-3 pt-2">
                                    <div>
                                        <div className="text-muted-foreground text-xs">Abierto</div>
                                        <div className="text-lg font-semibold">
                                            {fmt(summary_fx?.rent_m2?.open_minor ?? summary_fx?.rent?.open_minor, 'EUR')}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">Vencido cobrable</div>
                                        <div className="text-lg font-semibold">
                                            {fmt(summary_fx?.rent_m2?.overdue_minor ?? summary_fx?.rent?.overdue_minor, 'EUR')}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {summary_fx?.rent_fixed && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Alquiler fijo (USD)</CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-3 pt-2">
                                    <div>
                                        <div className="text-muted-foreground text-xs">Abierto</div>
                                        <div className="text-lg font-semibold">{fmt(summary_fx.rent_fixed.open_minor, 'USD')}</div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">Vencido cobrable</div>
                                        <div className="text-lg font-semibold">{fmt(summary_fx.rent_fixed.overdue_minor, 'USD')}</div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </ShowSection>

            <ShowSection id="charges" title="Cargos abiertos cobrables">
                <Card>
                    <CardContent className="overflow-x-auto pt-6">
                        <table className="min-w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2 text-left">Periodo</th>
                                    <th className="px-3 py-2 text-left">Vence</th>
                                    <th className="px-3 py-2 text-right">Monto</th>
                                    <th className="px-3 py-2 text-right">Asignado</th>
                                    <th className="px-3 py-2 text-right">Crédito</th>
                                    <th className="px-3 py-2 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tables.charges_open.map((c) => (
                                    <tr key={c.charge_id} className="border-t">
                                        <td className="px-3 py-2">{formatPeriod(c.period)}</td>
                                        <td className="px-3 py-2">{c.due_on ? new Date(c.due_on).toLocaleDateString() : ''}</td>
                                        <td className="px-3 py-2 text-right">{fmtBs(c.amount_bs_minor)}</td>
                                        <td className="px-3 py-2 text-right">{fmtBs(c.allocated_bs_minor)}</td>
                                        <td className="px-3 py-2 text-right">{fmtBs(c.credited_bs_minor)}</td>
                                        <td className="px-3 py-2 text-right">{fmtBs(c.outstanding_bs_minor)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </ShowSection>

            <div className="grid gap-6 sm:grid-cols-2">
                <ShowSection id="payments" title="Pagos (parciales/disponibles)">
                    <Card>
                        <CardContent className="overflow-x-auto pt-6">
                            <table className="min-w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-3 py-2 text-left">Pago</th>
                                        <th className="px-3 py-2 text-left">Fecha</th>
                                        <th className="px-3 py-2 text-left">Status</th>
                                        <th className="px-3 py-2 text-right">Aplicado</th>
                                        <th className="px-3 py-2 text-right">Disponible</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tables.payments_partial.map((p) => (
                                        <tr key={p.payment_id} className="border-t">
                                            <td className="px-3 py-2">#{p.payment_id}</td>
                                            <td className="px-3 py-2">{fmtPaidOnDate(p.paid_on)}</td>
                                            <td className="px-3 py-2">{p.status}</td>
                                            <td className="px-3 py-2 text-right">{fmtBs(p.applied_bs_minor)}</td>
                                            <td className="px-3 py-2 text-right">{fmtBs(p.available_bs_minor)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </ShowSection>
                <ShowSection id="credits" title="Créditos abiertos">
                    <Card>
                        <CardContent className="overflow-x-auto pt-6">
                            <table className="min-w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-3 py-2 text-left">Crédito</th>
                                        <th className="px-3 py-2 text-left">Origen</th>
                                        <th className="px-3 py-2 text-right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tables.credits_open.map((c) => (
                                        <tr key={c.credit_id} className="border-t">
                                            <td className="px-3 py-2">#{c.credit_id}</td>
                                            <td className="px-3 py-2">{c.source_payment_id ? `Pago #${c.source_payment_id}` : ''}</td>
                                            <td className="px-3 py-2 text-right">{fmtBs(c.balance_minor)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </ShowSection>
            </div>

            {recent && recent.length > 0 && (
                <ShowSection id="recent" title="Reciente">
                    <Card>
                        <CardContent className="pt-4">
                            <ul className="space-y-1 text-sm">
                                {recent.map((e, i) => (
                                    <li key={i} className="flex items-center justify-between border-b py-1">
                                        <span>
                                            {e.date ? new Date(e.date).toLocaleDateString() : ''} · {e.kind} · {e.description}
                                        </span>
                                        <span>{fmtBs(e.amount_bs_minor)}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </ShowSection>
            )}
        </ShowLayout>
    );
}

EconomicProfileLocal.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
