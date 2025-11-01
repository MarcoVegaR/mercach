import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Calendar, CheckCircle2, ChevronDown, ChevronUp, Clock, CreditCard, TrendingDown, Wallet } from 'lucide-react';
import React from 'react';

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

function fmtDate(dateStr?: string) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { year: 'numeric', month: 'short' });
    } catch {
        return dateStr;
    }
}

function fmtDateFull(dateStr?: string) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { day: '2-digit', month: 'long', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

function fmtMinorCurrency(minor?: number | null, currency: string = 'VES') {
    const cur = (currency || 'VES').toUpperCase() as 'USD' | 'EUR' | 'VES';
    return fmtMinor(minor, cur);
}

function friendlyKind(kind?: string) {
    const k = (kind || '').toUpperCase();
    switch (k) {
        case 'RENT_EUR_M2':
            return 'Tasa de uso';
        case 'RENT_EUR_FIXED':
            return 'Alquiler fijo';
        case 'CONDO_USD':
            return 'Condominio';
        default:
            return k || 'Cargo';
    }
}

export default function PortalDebtModern({ header: _header, summary_bs, summary_fx, tables, at }: Props) {
    const charges = Array.isArray(tables?.charges_open) ? tables.charges_open : [];
    const usdOpen = summary_fx?.condo?.open_minor ?? 0;
    const usdOver = summary_fx?.condo?.overdue_minor ?? 0;
    const eurOpen = summary_fx?.rent?.open_minor ?? 0;
    const eurOver = summary_fx?.rent?.overdue_minor ?? 0;
    const paymentsAvail = Number(summary_bs?.payments_available_bs_minor ?? 0);
    const creditsAvail = Number(summary_bs?.credits_open_bs_minor ?? 0);
    const netAfterCredits = Number(summary_bs?.net_due_after_credit_bs_minor ?? 0);
    const netAfterAll = Math.max(0, netAfterCredits - paymentsAvail);
    const hasOverdue = summary_bs.overdue_bs_minor > 0;

    // Separate overdue and current charges
    const now = new Date();
    const overdueCharges = charges.filter((c) => {
        try {
            return c.due_on && new Date(c.due_on) < now;
        } catch {
            return false;
        }
    });
    const currentCharges = charges.filter((c) => {
        try {
            return !c.due_on || new Date(c.due_on) >= now;
        } catch {
            return true;
        }
    });

    // State for collapsible sections and limits
    const [overdueOpen, setOverdueOpen] = React.useState(true); // Always open if has overdue
    const [currentOpen, setCurrentOpen] = React.useState(false); // Collapsed by default
    const [showAllOverdue, setShowAllOverdue] = React.useState(false);
    const [showAllCurrent, setShowAllCurrent] = React.useState(false);

    const INITIAL_LIMIT = 5;
    const displayedOverdue = showAllOverdue ? overdueCharges : overdueCharges.slice(0, INITIAL_LIMIT);
    const displayedCurrent = showAllCurrent ? currentCharges : currentCharges.slice(0, INITIAL_LIMIT);

    return (
        <AppLayout>
            <div className="container mx-auto max-w-6xl px-4 py-8">
                {/* Header */}
                <div className="mb-8 flex items-start justify-between gap-4">
                    <div>
                        <div className="mb-2 flex items-center gap-3">
                            <Link href="/portal">
                                <Button variant="ghost" size="sm" className="gap-2">
                                    <ArrowLeft className="h-4 w-4" />
                                    Portal
                                </Button>
                            </Link>
                        </div>
                        <h1 className="text-4xl font-bold tracking-tight">Estado de cuenta</h1>
                        <p className="text-muted-foreground mt-2 flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            Actualizado al {fmtDateFull(at)}
                        </p>
                    </div>
                </div>

                {/* Alerts */}
                {hasOverdue && (
                    <Alert className="mb-6 border-red-200 bg-red-50">
                        <AlertTriangle className="h-4 w-4 text-red-600" />
                        <AlertDescription className="text-red-800">
                            <strong>Atención:</strong> Tienes {fmtMinor(summary_bs.overdue_bs_minor)} en deudas vencidas. Te recomendamos ponerte al
                            día lo antes posible.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Summary Cards */}
                <div className="mb-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className={hasOverdue ? 'border-red-200 dark:border-red-900/50' : 'border-blue-200 dark:border-blue-900/50'}>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Wallet className={`h-4 w-4 ${hasOverdue ? 'text-red-600' : 'text-blue-600'}`} />
                                Total adeudado
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className={`text-3xl font-bold ${hasOverdue ? 'text-red-600' : 'text-blue-600'}`}>
                                {fmtMinor(summary_bs.open_bs_minor)}
                            </div>
                            <div className="mt-3 space-y-1 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Condominio:</span>
                                    <span className="font-medium">{fmtMinor(usdOpen, 'USD')}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Alquiler:</span>
                                    <span className="font-medium">{fmtMinor(eurOpen, 'EUR')}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className={hasOverdue ? 'border-red-200 bg-red-50/30 dark:border-red-900/50 dark:bg-red-950/20' : ''}>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <AlertTriangle className="h-4 w-4 text-red-600" />
                                Vencida
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className={`text-3xl font-bold ${hasOverdue ? 'text-red-600' : 'text-muted-foreground'}`}>
                                {fmtMinor(summary_bs.overdue_bs_minor)}
                            </div>
                            {hasOverdue && (
                                <div className="mt-3 space-y-1 text-sm text-red-700">
                                    <div>Condominio: {fmtMinor(usdOver, 'USD')}</div>
                                    <div>Alquiler: {fmtMinor(eurOver, 'EUR')}</div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className={creditsAvail > 0 ? 'border-green-200 bg-green-50/30 dark:border-green-900/50 dark:bg-green-950/20' : ''}>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <TrendingDown className="h-4 w-4 text-green-600" />
                                Saldo a favor
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className={`text-3xl font-bold ${creditsAvail > 0 ? 'text-green-600' : 'text-muted-foreground'}`}>
                                {fmtMinor(creditsAvail)}
                            </div>
                            <p className="text-muted-foreground mt-2 text-sm">{creditsAvail > 0 ? 'Disponible para aplicar' : 'Sin crédito'}</p>
                        </CardContent>
                    </Card>

                    <Card className={paymentsAvail > 0 ? 'border-blue-200 bg-blue-50/30 dark:border-blue-900/50 dark:bg-blue-950/20' : ''}>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <CreditCard className="h-4 w-4 text-blue-600" />
                                Pagos sin aplicar
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className={`text-3xl font-bold ${paymentsAvail > 0 ? 'text-blue-600' : 'text-muted-foreground'}`}>
                                {fmtMinor(paymentsAvail)}
                            </div>
                            {paymentsAvail > 0 && (
                                <Link href="/portal/pagos">
                                    <Button variant="link" size="sm" className="mt-2 px-0">
                                        Aplicar ahora →
                                    </Button>
                                </Link>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Net to pay highlight */}
                <Card className="mb-8 border-2 border-blue-200 bg-blue-50/30 dark:border-blue-900/50 dark:bg-blue-950/20">
                    <CardContent className="pt-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="text-muted-foreground mb-1 text-sm font-medium">Total neto a pagar</div>
                                <div className="text-4xl font-bold text-blue-600">{fmtMinor(netAfterAll)}</div>
                                <p className="text-muted-foreground mt-2 text-sm">
                                    Después de aplicar créditos ({fmtMinor(creditsAvail)}) y pagos disponibles ({fmtMinor(paymentsAvail)})
                                </p>
                            </div>
                            <div className="flex flex-col gap-2">
                                <Link href="/portal/pagos/nuevo">
                                    <Button size="lg" className="gap-2">
                                        <CreditCard className="h-4 w-4" />
                                        Registrar pago
                                    </Button>
                                </Link>
                                {paymentsAvail > 0 && (
                                    <Link href="/portal/pagos">
                                        <Button variant="outline" size="lg" className="gap-2">
                                            Aplicar pagos pendientes
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Overdue charges - Collapsible */}
                {overdueCharges.length > 0 && (
                    <Collapsible open={overdueOpen} onOpenChange={setOverdueOpen} className="mb-6">
                        <Card className="border-red-200 dark:border-red-900/50">
                            <CardHeader className="pb-3">
                                <CollapsibleTrigger className="w-full">
                                    <div className="flex items-center justify-between transition-opacity hover:opacity-80">
                                        <div className="flex items-center gap-3">
                                            <AlertTriangle className="h-5 w-5 text-red-600" />
                                            <div className="text-left">
                                                <CardTitle className="flex items-center gap-2 text-red-600">
                                                    Deudas vencidas ({overdueCharges.length})
                                                    {overdueCharges.length > INITIAL_LIMIT && !showAllOverdue && (
                                                        <Badge variant="secondary" className="text-xs">
                                                            Mostrando {INITIAL_LIMIT}
                                                        </Badge>
                                                    )}
                                                </CardTitle>
                                                <CardDescription className="mt-1">Requieren atención prioritaria</CardDescription>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Badge variant="destructive">{fmtMinor(summary_bs.overdue_bs_minor)}</Badge>
                                            {overdueOpen ? (
                                                <ChevronUp className="h-5 w-5 text-slate-400" />
                                            ) : (
                                                <ChevronDown className="h-5 w-5 text-slate-400" />
                                            )}
                                        </div>
                                    </div>
                                </CollapsibleTrigger>
                            </CardHeader>

                            <CollapsibleContent>
                                <CardContent>
                                    <div className="space-y-2">
                                        {displayedOverdue.map((c: any, i: number) => {
                                            const outstanding = Number(c.outstanding_bs_minor ?? c.amount_bs_minor) || 0;
                                            return (
                                                <div
                                                    key={i}
                                                    className="rounded-lg border border-red-200 bg-red-50 p-3 transition-colors hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/30 dark:hover:bg-red-950/50"
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex-1">
                                                            <div className="mb-1 flex items-center gap-2">
                                                                <Badge variant="outline" className="text-xs">
                                                                    {friendlyKind(c.kind)}
                                                                </Badge>
                                                                <span className="text-sm font-semibold">{fmtDate(c.period)}</span>
                                                            </div>
                                                            <div className="flex items-center gap-1 text-xs text-red-700">
                                                                <Clock className="h-3 w-3" />
                                                                Venció {fmtDate(c.due_on)}
                                                            </div>
                                                            {c.local_label && (
                                                                <div className="text-muted-foreground mt-1 text-xs">Local: {c.local_label}</div>
                                                            )}
                                                        </div>
                                                        <div className="text-right">
                                                            <div className="text-lg font-bold text-red-600">{fmtMinor(outstanding)}</div>
                                                            <div className="text-muted-foreground text-xs">
                                                                Original: {fmtMinorCurrency(c.outstanding_minor, c.currency)}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {overdueCharges.length > INITIAL_LIMIT && (
                                        <div className="mt-4 text-center">
                                            <Button variant="outline" size="sm" onClick={() => setShowAllOverdue(!showAllOverdue)} className="gap-2">
                                                {showAllOverdue ? (
                                                    <>
                                                        Ver menos <ChevronUp className="h-4 w-4" />
                                                    </>
                                                ) : (
                                                    <>
                                                        Ver todas ({overdueCharges.length - INITIAL_LIMIT} más) <ChevronDown className="h-4 w-4" />
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>
                )}

                {/* Current charges - Collapsible (collapsed by default) */}
                {currentCharges.length > 0 && (
                    <Collapsible open={currentOpen} onOpenChange={setCurrentOpen}>
                        <Card>
                            <CardHeader className="pb-3">
                                <CollapsibleTrigger className="w-full">
                                    <div className="flex items-center justify-between transition-opacity hover:opacity-80">
                                        <div className="flex items-center gap-3">
                                            <CheckCircle2 className="h-5 w-5 text-green-600" />
                                            <div className="text-left">
                                                <CardTitle className="flex items-center gap-2">
                                                    Deudas al día ({currentCharges.length})
                                                    {currentCharges.length > INITIAL_LIMIT && !showAllCurrent && (
                                                        <Badge variant="secondary" className="text-xs">
                                                            Mostrando {INITIAL_LIMIT}
                                                        </Badge>
                                                    )}
                                                </CardTitle>
                                                <CardDescription className="mt-1">Cargos pendientes dentro del plazo</CardDescription>
                                            </div>
                                        </div>
                                        {currentOpen ? (
                                            <ChevronUp className="h-5 w-5 text-slate-400" />
                                        ) : (
                                            <ChevronDown className="h-5 w-5 text-slate-400" />
                                        )}
                                    </div>
                                </CollapsibleTrigger>
                            </CardHeader>

                            <CollapsibleContent>
                                <CardContent>
                                    <div className="space-y-2">
                                        {displayedCurrent.map((c: any, i: number) => {
                                            const outstanding = Number(c.outstanding_bs_minor ?? c.amount_bs_minor) || 0;
                                            return (
                                                <div
                                                    key={i}
                                                    className="bg-muted/50 dark:bg-muted/30 hover:bg-muted dark:hover:bg-muted/50 rounded-lg border p-3 transition-colors"
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex-1">
                                                            <div className="mb-1 flex items-center gap-2">
                                                                <Badge variant="outline" className="text-xs">
                                                                    {friendlyKind(c.kind)}
                                                                </Badge>
                                                                <span className="text-sm font-semibold">{fmtDate(c.period)}</span>
                                                            </div>
                                                            <div className="text-muted-foreground flex items-center gap-1 text-xs">
                                                                <Calendar className="h-3 w-3" />
                                                                Vence {fmtDate(c.due_on)}
                                                            </div>
                                                            {c.local_label && (
                                                                <div className="text-muted-foreground mt-1 text-xs">Local: {c.local_label}</div>
                                                            )}
                                                        </div>
                                                        <div className="text-right">
                                                            <div className="text-lg font-bold">{fmtMinor(outstanding)}</div>
                                                            <div className="text-muted-foreground text-xs">
                                                                Original: {fmtMinorCurrency(c.outstanding_minor, c.currency)}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {currentCharges.length > INITIAL_LIMIT && (
                                        <div className="mt-4 text-center">
                                            <Button variant="outline" size="sm" onClick={() => setShowAllCurrent(!showAllCurrent)} className="gap-2">
                                                {showAllCurrent ? (
                                                    <>
                                                        Ver menos <ChevronUp className="h-4 w-4" />
                                                    </>
                                                ) : (
                                                    <>
                                                        Ver todas ({currentCharges.length - INITIAL_LIMIT} más) <ChevronDown className="h-4 w-4" />
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>
                )}

                {charges.length === 0 && (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <div className="flex flex-col items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                                    <CheckCircle2 className="h-8 w-8 text-green-600" />
                                </div>
                                <div>
                                    <h3 className="mb-1 text-lg font-semibold">¡Todo al día!</h3>
                                    <p className="text-muted-foreground">No tienes deudas pendientes en este momento.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
