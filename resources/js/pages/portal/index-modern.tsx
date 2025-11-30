import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Building2,
    CheckCircle2,
    Clock,
    Copy,
    CreditCard,
    FileText,
    HelpCircle,
    Phone,
    RefreshCw,
    Share2,
    Smartphone,
    Sparkles,
    User,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type SummaryFx = {
    condo?: { currency: 'USD'; open_minor: number; overdue_minor: number; rate_to_ves?: number | null };
    rent?: { currency: 'EUR'; open_minor: number; overdue_minor: number; rate_to_ves?: number | null };
};
type Profile = {
    summary_bs: {
        open_bs_minor: number;
        /** Optional recalculated totals for portal, based on FX aggregates with 2-decimal precision */
        open_bs_minor_from_fx?: number;
        net_due_after_credit_bs_minor: number;
        net_due_after_credit_bs_minor_from_fx?: number;
        payments_available_bs_minor?: number;
        credits_open_bs_minor?: number;
        overdue_bs_minor?: number;
        overdue_bs_minor_from_fx?: number;
    };
    summary_fx?: SummaryFx;
};

type FxRate = {
    rate_to_ves: number | null;
    rate_date: string | null;
    published_at: string | null;
};

type FxRates = {
    EUR: FxRate;
    USD: FxRate;
    fetched_at: string;
};

type BankAccount = {
    id: number;
    bank_name: string;
    bank_code: string;
    account_number: string;
    phone_number: string;
    account_holder_name: string;
    document_type: string;
    document_number: string;
    rif: string;
};

type PaymentItem = {
    id: number;
    paid_on: string;
    amount_bs_minor: number;
    applied_bs_minor: number;
    available_bs_minor: number;
    status: string;
    method: string;
    reference: string;
    gateway_resp_code: string;
    gateway_message: string;
};

type PaymentsStatus = {
    last_payment: {
        id: number;
        status: string;
        paid_on: string;
        amount_bs_minor: number;
        gateway_resp_code: string;
        gateway_message: string;
    } | null;
    recent: PaymentItem[];
    counts: {
        pending_review: number;
        confirmed: number;
    };
};

type Props = {
    at: string;
    concessionaire?: { id: number; full_name: string } | null;
    profile?: Profile | null;
    fxRates?: FxRates | null;
    bankAccounts?: BankAccount[];
    paymentsStatus?: PaymentsStatus | null;
};

function fmtMinor(minor?: number | null, curr: 'USD' | 'EUR' | 'VES' = 'VES') {
    if (typeof minor !== 'number') return '—';
    const formatter = new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const val = minor / 100;
    if (curr === 'VES') return `Bs ${formatter.format(val)}`;
    if (curr === 'EUR') return `€ ${formatter.format(val)}`;
    if (curr === 'USD') return `$ ${formatter.format(val)}`;
    return formatter.format(val);
}

function fmtDate(dateStr?: string) {
    if (!dateStr) return '—';

    const normalized = /^\d{4}-\d{2}-\d{2}$/.test(dateStr) ? `${dateStr}T00:00:00` : dateStr;
    const d = new Date(normalized);

    try {
        if (Number.isNaN(d.getTime())) {
            return dateStr;
        }

        return d.toLocaleDateString('es-VE', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

function fmtDateLong(dateStr?: string) {
    if (!dateStr) return '—';

    const normalized = /^\d{4}-\d{2}-\d{2}$/.test(dateStr) ? `${dateStr}T00:00:00` : dateStr;
    const d = new Date(normalized);

    try {
        if (Number.isNaN(d.getTime())) {
            return dateStr;
        }

        return d.toLocaleDateString('es-VE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

function copyToClipboard(text: string, label: string) {
    navigator.clipboard.writeText(text).then(() => {
        toast.success(`${label} copiado`, { description: text, duration: 2000 });
    });
}

async function shareData(text: string) {
    if (navigator.share) {
        try {
            await navigator.share({ text });
        } catch {
            // User cancelled
        }
    } else {
        navigator.clipboard.writeText(text).then(() => {
            toast.success('Datos copiados al portapapeles');
        });
    }
}

// Debt Status Chip
function DebtStatusChip({ hasOverdue, netDueBs }: { hasOverdue: boolean; netDueBs: number }) {
    if (netDueBs <= 0) {
        return (
            <Badge className="bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                Al día
            </Badge>
        );
    }
    if (hasOverdue) {
        return (
            <Badge variant="destructive" className="bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                <AlertCircle className="mr-1 h-3.5 w-3.5" />
                Deuda vencida
            </Badge>
        );
    }
    return (
        <Badge className="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
            <Clock className="mr-1 h-3.5 w-3.5" />
            Vence pronto
        </Badge>
    );
}

// Payment Status Badge
function PaymentStatusBadge({ status }: { status: string }) {
    switch (status) {
        case 'REG':
            return (
                <Badge
                    variant="outline"
                    className="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
                >
                    <RefreshCw className="mr-1 h-3 w-3" />
                    En revisión
                </Badge>
            );
        case 'CONF':
            return (
                <Badge
                    variant="outline"
                    className="border-green-300 bg-green-50 text-green-700 dark:border-green-700 dark:bg-green-900/20 dark:text-green-400"
                >
                    <CheckCircle2 className="mr-1 h-3 w-3" />
                    Confirmado
                </Badge>
            );
        case 'CONC':
            return (
                <Badge
                    variant="outline"
                    className="border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-400"
                >
                    <CheckCircle2 className="mr-1 h-3 w-3" />
                    Aplicado
                </Badge>
            );
        case 'RECH':
            return (
                <Badge variant="outline" className="border-red-300 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-400">
                    <XCircle className="mr-1 h-3 w-3" />
                    Rechazado
                </Badge>
            );
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

export default function PortalIndexModern({ at, concessionaire, profile, fxRates, bankAccounts = [], paymentsStatus }: Props) {
    const usdOpen = profile?.summary_fx?.condo?.open_minor ?? 0;
    const eurOpen = profile?.summary_fx?.rent?.open_minor ?? 0;

    const summaryBs = profile?.summary_bs;
    const netDueBs = summaryBs?.net_due_after_credit_bs_minor ?? 0;
    const overdueBS = summaryBs?.overdue_bs_minor ?? 0;
    const creditsBS = summaryBs?.credits_open_bs_minor ?? 0;
    const paymentsAvailBS = summaryBs?.payments_available_bs_minor ?? 0;

    const hasOverdue = overdueBS > 0;
    const hasCredits = creditsBS > 0;
    const hasPaymentsAvailable = paymentsAvailBS > 0;

    const [bankDetailsOpen, setBankDetailsOpen] = useState(false);

    // Format phone for Pago Móvil display (0424-1234567)
    const formatPhone = (phone: string) => {
        if (!phone) return phone;
        // Remove 58 prefix if present, then format as 0XXX-XXXXXXX
        let cleaned = phone.replace(/\D/g, '');
        if (cleaned.startsWith('58')) {
            cleaned = '0' + cleaned.slice(2);
        }
        if (cleaned.length === 11 && cleaned.startsWith('0')) {
            return `${cleaned.slice(1, 4)}-${cleaned.slice(4)}`;
        }
        return cleaned;
    };

    // Build share text for bank account
    const buildShareText = (acc: BankAccount) => {
        let text = `Datos para pago:\nBanco: ${acc.bank_name}\nTitular: ${acc.account_holder_name}\nRIF: ${acc.rif}\nCuenta: ${acc.account_number}`;
        if (acc.phone_number) {
            text += `\n\nPago Móvil:\nTeléfono: ${formatPhone(acc.phone_number)}\nCédula/RIF: ${acc.rif}`;
        }
        return text;
    };

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-4xl px-4 py-6">
                {/* Compact Header */}
                <div className="mb-6">
                    <div className="text-muted-foreground mb-1 text-sm">{fmtDateLong(at)}</div>
                    {concessionaire && (
                        <div className="text-foreground flex items-center gap-2 text-lg font-semibold">
                            <Building2 className="text-muted-foreground h-4 w-4" />
                            {concessionaire.full_name}
                        </div>
                    )}
                </div>

                {/* ===== CRITICAL ALERTS ===== */}
                {(hasOverdue || hasPaymentsAvailable) && (
                    <div className="mb-6 space-y-3">
                        {hasOverdue && (
                            <Alert className="border-l-4 border-l-red-600 bg-red-50 dark:bg-red-950/30">
                                <AlertCircle className="h-4 w-4 text-red-600" />
                                <AlertTitle className="text-red-800 dark:text-red-300">Tienes deuda vencida</AlertTitle>
                                <AlertDescription className="text-red-700 dark:text-red-400">
                                    Del total, <span className="font-bold">{fmtMinor(overdueBS)}</span> están vencidos y requieren pago inmediato.
                                </AlertDescription>
                            </Alert>
                        )}
                        {hasPaymentsAvailable && (
                            <Alert className="border-l-4 border-l-green-600 bg-green-50 dark:bg-green-950/30">
                                <Sparkles className="h-4 w-4 text-green-600" />
                                <AlertTitle className="text-green-800 dark:text-green-300">Pagos listos para aplicar</AlertTitle>
                                <AlertDescription className="flex items-center justify-between">
                                    <span className="text-green-700 dark:text-green-400">
                                        Tienes {fmtMinor(paymentsAvailBS)} disponible para aplicar a tus deudas.
                                    </span>
                                    <Button asChild size="sm" variant="outline" className="ml-2 border-green-300 text-green-700">
                                        <Link href="/portal/pagos">Aplicar ahora</Link>
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>
                )}

                {/* ===== CARD PRINCIPAL: Resumen Financiero ===== */}
                <Card className="mb-6 overflow-hidden border-0 shadow-lg ring-1 ring-slate-900/5">
                    <CardContent className="p-0">
                        <div className="flex flex-col md:flex-row">
                            {/* Left side: Total Debt */}
                            <div className="bg-white p-6 md:w-1/2 md:p-8 dark:bg-slate-950">
                                <div className="mb-4 flex items-center justify-between">
                                    <h2 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">Total a Pagar</h2>
                                    <DebtStatusChip hasOverdue={hasOverdue} netDueBs={netDueBs} />
                                </div>

                                <div className="mb-6">
                                    <div className="text-foreground text-5xl font-bold tracking-tight text-slate-900 dark:text-white">
                                        {fmtMinor(netDueBs)}
                                    </div>
                                    {/* Overdue breakdown if partial */}
                                    {hasOverdue && overdueBS < netDueBs && (
                                        <div className="mt-2 flex items-center gap-2 text-sm font-medium text-red-600 dark:text-red-400">
                                            <AlertCircle className="h-4 w-4" />
                                            <span>Incluye {fmtMinor(overdueBS)} vencidos</span>
                                        </div>
                                    )}
                                    {/* Credit breakdown */}
                                    {hasCredits && (
                                        <div className="mt-1 flex items-center gap-2 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                            <CheckCircle2 className="h-4 w-4" />
                                            <span>Descontado {fmtMinor(creditsBS)} a favor</span>
                                        </div>
                                    )}
                                </div>

                                <div className="flex flex-col gap-3 pt-2">
                                    <Button
                                        asChild
                                        size="lg"
                                        className="w-full bg-blue-600 font-semibold text-white shadow-sm hover:bg-blue-700 active:translate-y-0.5"
                                    >
                                        <Link href="/portal/pagos/nuevo">
                                            <CreditCard className="mr-2 h-5 w-5" />
                                            Registrar Pago
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="lg"
                                        className="w-full border-slate-200 font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900"
                                        onClick={() => setBankDetailsOpen(!bankDetailsOpen)}
                                    >
                                        <Building2 className="mr-2 h-4 w-4 text-slate-500" />
                                        {bankDetailsOpen ? 'Ocultar datos bancarios' : 'Ver datos bancarios'}
                                    </Button>
                                </div>
                            </div>

                            {/* Right side: Breakdown & Rates */}
                            <div className="bg-slate-50 p-6 md:w-1/2 md:border-l md:border-slate-100 md:p-8 dark:bg-slate-900/50 dark:md:border-slate-800">
                                <h3 className="text-muted-foreground mb-4 text-xs font-semibold tracking-wider uppercase">Detalle de cargos</h3>

                                {/* Breakdown List */}
                                <div className="mb-6 space-y-3">
                                    {eurOpen > 0 || usdOpen > 0 ? (
                                        <>
                                            {eurOpen > 0 && (
                                                <div className="flex items-center justify-between rounded-lg bg-white p-3 shadow-sm ring-1 ring-slate-900/5 dark:bg-slate-800 dark:ring-slate-700">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                                                            <Building2 className="h-4 w-4" />
                                                        </div>
                                                        <span className="font-medium text-slate-700 dark:text-slate-200">Tasa de Uso</span>
                                                    </div>
                                                    <span className="font-bold text-slate-900 dark:text-white">{fmtMinor(eurOpen, 'EUR')}</span>
                                                </div>
                                            )}
                                            {usdOpen > 0 && (
                                                <div className="flex items-center justify-between rounded-lg bg-white p-3 shadow-sm ring-1 ring-slate-900/5 dark:bg-slate-800 dark:ring-slate-700">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-sky-100 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400">
                                                            <Sparkles className="h-4 w-4" />
                                                        </div>
                                                        <span className="font-medium text-slate-700 dark:text-slate-200">Gastos Comunes</span>
                                                    </div>
                                                    <span className="font-bold text-slate-900 dark:text-white">{fmtMinor(usdOpen, 'USD')}</span>
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <div className="text-muted-foreground py-2 text-sm italic">Sin cargos pendientes</div>
                                    )}
                                </div>

                                {/* Exchange Rates */}
                                {fxRates && (fxRates.EUR?.rate_to_ves || fxRates.USD?.rate_to_ves) && (
                                    <div className="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                                        <div className="mb-2 text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                                            Tasa BCV del {fxRates.EUR?.rate_date ? fmtDate(fxRates.EUR.rate_date) : 'día'}
                                        </div>
                                        <div className="flex items-center gap-4">
                                            {fxRates.EUR?.rate_to_ves && (
                                                <div className="flex items-baseline gap-1.5">
                                                    <span className="text-lg font-bold text-slate-700 dark:text-slate-300">€</span>
                                                    <span className="text-lg font-medium text-slate-900 dark:text-white">
                                                        {fxRates.EUR.rate_to_ves.toLocaleString('es-VE', {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 2,
                                                        })}
                                                    </span>
                                                </div>
                                            )}
                                            {fxRates.EUR?.rate_to_ves && fxRates.USD?.rate_to_ves && (
                                                <div className="h-8 w-px bg-slate-200 dark:bg-slate-700"></div>
                                            )}
                                            {fxRates.USD?.rate_to_ves && (
                                                <div className="flex items-baseline gap-1.5">
                                                    <span className="text-lg font-bold text-slate-700 dark:text-slate-300">$</span>
                                                    <span className="text-lg font-medium text-slate-900 dark:text-white">
                                                        {fxRates.USD.rate_to_ves.toLocaleString('es-VE', {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 2,
                                                        })}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                <div className="mt-4 text-center md:text-right">
                                    <Link
                                        href="/portal/deuda"
                                        className="text-muted-foreground inline-flex items-center gap-1 text-sm transition-colors hover:text-blue-600 hover:underline"
                                    >
                                        Ver detalle completo
                                        <ArrowRight className="h-3.5 w-3.5" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ===== BANK DETAILS SECTION ===== */}
                <Collapsible open={bankDetailsOpen} onOpenChange={setBankDetailsOpen}>
                    <CollapsibleContent>
                        <Card className="mb-6 border-0 shadow-md">
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Building2 className="h-5 w-5 text-blue-600" />
                                    ¿A qué cuenta pagar?
                                </CardTitle>
                                <p className="text-muted-foreground text-sm">Copia estos datos para hacer tu pago</p>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {bankAccounts.length === 0 ? (
                                    <div className="text-muted-foreground py-4 text-center text-sm">No hay cuentas bancarias configuradas</div>
                                ) : (
                                    bankAccounts.map((acc) => (
                                        <div key={acc.id} className="bg-muted/30 rounded-xl p-4">
                                            {/* Bank header */}
                                            <div className="mb-4 flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                                        <Building2 className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                                    </div>
                                                    <div>
                                                        <div className="text-foreground font-semibold">{acc.bank_name}</div>
                                                        <div className="text-muted-foreground text-xs">Transferencia</div>
                                                    </div>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => shareData(buildShareText(acc))}
                                                    className="text-blue-600"
                                                >
                                                    <Share2 className="mr-1 h-4 w-4" />
                                                    <span className="hidden sm:inline">Compartir</span>
                                                </Button>
                                            </div>

                                            {/* Transfer details */}
                                            <div className="mb-4 space-y-2">
                                                <CopyableField label="Titular" value={acc.account_holder_name} icon={<User className="h-4 w-4" />} />
                                                <CopyableField label="RIF" value={acc.rif} icon={<FileText className="h-4 w-4" />} />
                                                <CopyableField label="Cuenta" value={acc.account_number} icon={<CreditCard className="h-4 w-4" />} />
                                            </div>

                                            {/* Pago Móvil section */}
                                            {acc.phone_number && (
                                                <div className="border-border border-t pt-4">
                                                    <div className="mb-3 flex items-center gap-2">
                                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                                                            <Smartphone className="h-4 w-4 text-green-600 dark:text-green-400" />
                                                        </div>
                                                        <span className="text-foreground text-sm font-medium">Pago Móvil</span>
                                                    </div>
                                                    <div className="space-y-2">
                                                        <CopyableField
                                                            label="Teléfono"
                                                            value={formatPhone(acc.phone_number)}
                                                            copyValue={acc.phone_number}
                                                            icon={<Phone className="h-4 w-4" />}
                                                        />
                                                        <CopyableField label="Cédula/RIF" value={acc.rif} icon={<FileText className="h-4 w-4" />} />
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </CollapsibleContent>
                </Collapsible>

                {/* ===== PAYMENTS STATUS ===== */}
                {paymentsStatus && (paymentsStatus.last_payment || paymentsStatus.counts.pending_review > 0) && (
                    <Card className="mb-6 border-0 shadow-md">
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <CreditCard className="h-5 w-5 text-purple-600" />
                                Estado de pagos
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {/* Payments in review alert */}
                            {paymentsStatus.counts.pending_review > 0 && (
                                <Alert className="mb-4 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30">
                                    <RefreshCw className="h-4 w-4 text-amber-600" />
                                    <AlertTitle className="text-amber-800 dark:text-amber-300">
                                        {paymentsStatus.counts.pending_review} pago
                                        {paymentsStatus.counts.pending_review > 1 ? 's' : ''} en revisión
                                    </AlertTitle>
                                    <AlertDescription className="text-amber-700 dark:text-amber-400">
                                        Tu pago está siendo verificado. Esto puede tomar unos minutos.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {/* Last payment */}
                            {paymentsStatus.last_payment && (
                                <div className="flex items-center justify-between rounded-lg border p-4">
                                    <div className="flex items-center gap-3">
                                        <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-lg">
                                            <CreditCard className="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="text-foreground font-medium">Último pago registrado</div>
                                            <div className="text-muted-foreground text-sm">
                                                {fmtMinor(paymentsStatus.last_payment.amount_bs_minor)} •{' '}
                                                {fmtDate(paymentsStatus.last_payment.paid_on)}
                                            </div>
                                        </div>
                                    </div>
                                    <PaymentStatusBadge status={paymentsStatus.last_payment.status} />
                                </div>
                            )}

                            {/* Register payment CTA */}
                            <div className="mt-4">
                                <Link href="/portal/pagos/nuevo" className="group flex items-center gap-2 text-sm text-blue-600 hover:underline">
                                    ¿Ya pagaste? Registrar pago
                                    <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* ===== HELP SECTION ===== */}
                <Card className="mt-6 border-0 bg-slate-50 shadow-sm dark:bg-slate-900/50">
                    <CardContent className="flex items-center gap-4 p-4">
                        <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                            <HelpCircle className="h-5 w-5 text-blue-600" />
                        </div>
                        <div className="flex-1">
                            <div className="text-foreground text-sm font-medium">¿Necesitas ayuda?</div>
                            <p className="text-muted-foreground text-sm">
                                Contacta a administración:{' '}
                                <a href="mailto:administracion@mercadodechacao.gob.ve" className="text-blue-600 hover:underline">
                                    administracion@mercadodechacao.gob.ve
                                </a>
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

// Copyable Field Component
function CopyableField({ label, value, copyValue, icon }: { label: string; value: string; copyValue?: string; icon?: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between rounded-lg bg-white p-2 dark:bg-slate-800">
            <div className="flex items-center gap-2">
                {icon && <span className="text-muted-foreground">{icon}</span>}
                <div>
                    <div className="text-muted-foreground text-xs">{label}</div>
                    <div className="text-foreground font-mono text-sm">{value}</div>
                </div>
            </div>
            <Button variant="ghost" size="sm" onClick={() => copyToClipboard(copyValue ?? value, label)} className="text-blue-600">
                <Copy className="h-4 w-4" />
            </Button>
        </div>
    );
}
