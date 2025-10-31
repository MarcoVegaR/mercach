import { ShowLayout } from '@/components/show-base/ShowLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, CreditCard, DollarSign, Euro } from 'lucide-react';
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
    full_name: string;
    document?: { type_code?: string; number?: string };
    contracts_count?: number;
    locals_count?: number;
};

type ChargeRow = {
    charge_id: number;
    local_id?: number;
    local_label?: string | null;
    period: string;
    due_on?: string;
    currency: string;
    amount_minor: number;
    amount_bs_minor: number | null;
    allocated_bs_minor: number;
    credited_bs_minor: number;
    outstanding_bs_minor: number;
    outstanding_minor: number;
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
    };
    by_local: Array<{
        local_id: number;
        local_label?: string | null;
        currency: string;
        open_bs_minor: number;
        overdue_bs_minor: number;
        partial_applied_bs_minor: number;
        net_due_bs_minor: number;
        open_minor: number;
        overdue_minor: number;
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

function fmt(minor?: number | null, curr: 'USD' | 'EUR' | 'VES' = 'VES') {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: curr, minimumFractionDigits: 2 });
}

export default function EconomicProfileConcessionaire(props: Props) {
    const { header, summary_bs, summary_fx, by_local, tables, recent } = props;
    const [activeTab, setActiveTab] = React.useState('resumen');
    const [kindFilter, setKindFilter] = React.useState<'all' | 'CONDO' | 'RENT'>('all');
    const [overdueFilter, setOverdueFilter] = React.useState(false);

    const atParam = React.useMemo(() => {
        if (typeof window === 'undefined') return new Date().toISOString().slice(0, 10);
        const p = new URLSearchParams(window.location.search);
        return p.get('at') || new Date().toISOString().slice(0, 10);
    }, []);
    const exportUrl = (format: 'csv' | 'json') =>
        `/admin/economic-profile/export?scope=concessionaire&id=${header.id}&format=${format}&at=${encodeURIComponent(atParam)}`;

    const condoDebt = summary_fx?.condo?.open_minor ?? 0;
    const rentDebt = summary_fx?.rent?.open_minor ?? 0;
    const hasDebt = condoDebt > 0 || rentDebt > 0;

    const filteredCharges = React.useMemo(() => {
        let filtered = tables.charges_open;
        if (kindFilter !== 'all') {
            filtered = filtered.filter((c) => (c.kind ?? '').toUpperCase().startsWith(kindFilter));
        }
        if (overdueFilter) {
            filtered = filtered.filter((c) => c.due_on && new Date(c.due_on) < new Date());
        }
        return filtered;
    }, [tables.charges_open, kindFilter, overdueFilter]);

    return (
        <ShowLayout
            header={
                <div className="flex items-center gap-4">
                    <Link href="/admin/economic-profile" className="text-muted-foreground hover:text-foreground transition-colors">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Perfil Económico</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {header.full_name} · {header.document?.type_code}
                            {header.document?.number ? header.document.number : ''}
                        </p>
                    </div>
                </div>
            }
            actions={
                <div className="flex gap-2">
                    {hasDebt && (
                        <Button onClick={() => router.visit('/payments/create')} className="gap-2">
                            <CreditCard className="h-4 w-4" />
                            Registrar Pago
                        </Button>
                    )}
                    <a
                        href={exportUrl('csv')}
                        className="bg-background hover:bg-muted/40 inline-flex items-center rounded-md border px-3 py-2 text-sm"
                    >
                        CSV
                    </a>
                    <a
                        href={exportUrl('json')}
                        className="bg-background hover:bg-muted/40 inline-flex items-center rounded-md border px-3 py-2 text-sm"
                    >
                        JSON
                    </a>
                </div>
            }
            aside={
                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Deuda Total</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {summary_fx?.condo && summary_fx.condo.open_minor > 0 && (
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <DollarSign className="text-muted-foreground h-4 w-4" />
                                        <span className="text-muted-foreground text-sm">Condominio</span>
                                    </div>
                                    <span className="text-lg font-semibold">{fmt(summary_fx.condo.open_minor, 'USD')}</span>
                                </div>
                            )}
                            {summary_fx?.rent && summary_fx.rent.open_minor > 0 && (
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Euro className="text-muted-foreground h-4 w-4" />
                                        <span className="text-muted-foreground text-sm">Alquiler</span>
                                    </div>
                                    <span className="text-lg font-semibold">{fmt(summary_fx.rent.open_minor, 'EUR')}</span>
                                </div>
                            )}
                            {!hasDebt && <p className="text-muted-foreground text-sm">Sin deuda abierta</p>}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Info</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">Locales</span>
                                <span>{header.locals_count ?? '—'}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">Contratos</span>
                                <span>{header.contracts_count ?? '—'}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">Créditos</span>
                                <span>{fmtBs(summary_bs.credits_open_bs_minor)}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            }
        >
            <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
                <TabsList className="grid w-full grid-cols-4">
                    <TabsTrigger value="resumen">Resumen</TabsTrigger>
                    <TabsTrigger value="cargos">Cargos</TabsTrigger>
                    <TabsTrigger value="pagos">Pagos</TabsTrigger>
                    <TabsTrigger value="locales">Por Local</TabsTrigger>
                </TabsList>

                <TabsContent value="resumen" className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {summary_fx?.condo && summary_fx.condo.open_minor > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Condominio</CardTitle>
                                    <DollarSign className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{fmt(summary_fx.condo.open_minor, 'USD')}</div>
                                    <p className="text-muted-foreground text-xs">Vencido: {fmt(summary_fx.condo.overdue_minor, 'USD')}</p>
                                </CardContent>
                            </Card>
                        )}
                        {summary_fx?.rent && summary_fx.rent.open_minor > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Alquiler</CardTitle>
                                    <Euro className="text-muted-foreground h-4 w-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{fmt(summary_fx.rent.open_minor, 'EUR')}</div>
                                    <p className="text-muted-foreground text-xs">Vencido: {fmt(summary_fx.rent.overdue_minor, 'EUR')}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Métricas Adicionales (VES)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">Pagos disponibles</dt>
                                    <dd className="font-semibold">{fmtBs(summary_bs.payments_available_bs_minor)}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Créditos</dt>
                                    <dd className="font-semibold">{fmtBs(summary_bs.credits_open_bs_minor)}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="locales">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Totales por Local</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-3 py-2 text-left">Local</th>
                                        <th className="px-3 py-2 text-left">Moneda</th>
                                        <th className="px-3 py-2 text-right">Abierto</th>
                                        <th className="px-3 py-2 text-right">Vencido</th>
                                        <th className="px-3 py-2 text-right">Ref. VES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {by_local.map((r) => {
                                        const currency = (r.currency || 'VES').toUpperCase() as 'USD' | 'EUR' | 'VES';
                                        return (
                                            <tr key={r.local_id} className="border-t">
                                                <td className="px-3 py-2 font-medium">{r.local_label ?? r.local_id}</td>
                                                <td className="px-3 py-2">
                                                    <span
                                                        className={`rounded px-2 py-1 text-xs ${currency === 'USD' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : currency === 'EUR' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-700'}`}
                                                    >
                                                        {currency}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold">{fmt(r.open_minor, currency)}</td>
                                                <td className="px-3 py-2 text-right">{fmt(r.overdue_minor, currency)}</td>
                                                <td className="text-muted-foreground px-3 py-2 text-right text-xs">{fmtBs(r.open_bs_minor)}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="cargos">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base">Cargos abiertos</CardTitle>
                                <div className="flex gap-2">
                                    <Button size="sm" variant={kindFilter === 'all' ? 'default' : 'outline'} onClick={() => setKindFilter('all')}>
                                        Todos
                                    </Button>
                                    <Button size="sm" variant={kindFilter === 'CONDO' ? 'default' : 'outline'} onClick={() => setKindFilter('CONDO')}>
                                        Condominio
                                    </Button>
                                    <Button size="sm" variant={kindFilter === 'RENT' ? 'default' : 'outline'} onClick={() => setKindFilter('RENT')}>
                                        Alquiler
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant={overdueFilter ? 'destructive' : 'outline'}
                                        onClick={() => setOverdueFilter(!overdueFilter)}
                                    >
                                        Solo vencidos
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-3 py-2 text-left">Tipo</th>
                                        <th className="px-3 py-2 text-left">Periodo</th>
                                        <th className="px-3 py-2 text-left">Vence</th>
                                        <th className="px-3 py-2 text-right">Monto</th>
                                        <th className="px-3 py-2 text-right">Ref. VES</th>
                                        <th className="px-3 py-2 text-right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredCharges.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-muted-foreground px-3 py-8 text-center">
                                                No hay cargos con los filtros seleccionados
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredCharges.map((c) => {
                                            const kind = (c.kind ?? '').toUpperCase();
                                            const isCondo = kind.startsWith('CONDO');
                                            const isRent = kind.startsWith('RENT');
                                            const currency = (c.currency || 'VES').toUpperCase() as 'USD' | 'EUR' | 'VES';
                                            const isOverdue = c.due_on && new Date(c.due_on) < new Date();
                                            return (
                                                <tr key={c.charge_id} className={`border-t ${isOverdue ? 'bg-red-50 dark:bg-red-950/10' : ''}`}>
                                                    <td className="px-3 py-2">
                                                        <span
                                                            className={`rounded px-2 py-1 text-xs ${isCondo ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : isRent ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-700'}`}
                                                        >
                                                            {kind.replace('_', ' ')}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2">{c.period}</td>
                                                    <td className="px-3 py-2">{c.due_on ? new Date(c.due_on).toLocaleDateString() : ''}</td>
                                                    <td className="px-3 py-2 text-right font-medium">{fmt(c.outstanding_minor, currency)}</td>
                                                    <td className="text-muted-foreground px-3 py-2 text-right text-xs">
                                                        {fmtBs(c.outstanding_bs_minor)}
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-semibold">{fmt(c.outstanding_minor, currency)}</td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="pagos" className="space-y-4">
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
                                            <td className="px-3 py-2">{p.paid_on ? new Date(p.paid_on).toLocaleDateString() : ''}</td>
                                            <td className="px-3 py-2">{p.status}</td>
                                            <td className="px-3 py-2 text-right">{fmtBs(p.applied_bs_minor)}</td>
                                            <td className="px-3 py-2 text-right">{fmtBs(p.available_bs_minor)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Créditos abiertos</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
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
                </TabsContent>
            </Tabs>
        </ShowLayout>
    );
}

EconomicProfileConcessionaire.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
