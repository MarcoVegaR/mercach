import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import { formatDateShort } from '@/lib/date-utils';
import { cn } from '@/lib/utils';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, ChevronDown, Clock, CreditCard, Plus } from 'lucide-react';

type Item = {
    id: number;
    paid_on?: string;
    amount_bs_minor: number;
    applied_bs_minor: number;
    available_bs_minor: number;
    method?: string;
    status?: string;
    reference?: string;
    origin_bank_name?: string;
    payer_phone_e164?: string;
    payer_account_number?: string;
    allocations?: Array<{ local: string; amount: number }>;
};

type Props = {
    items: Item[];
};

function fmtMinor(minor?: number | null) {
    if (typeof minor !== 'number') return '—';
    return `Bs ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fmtDate(dateStr?: string) {
    return formatDateShort(dateStr);
}

function getMethod(method?: string) {
    const m = (method || '').toUpperCase();
    if (m.includes('TRF') || m.includes('TRANSF')) return 'Transferencia';
    if (m.includes('PMOV')) return 'Pago Móvil';
    if (m.includes('DEB')) return 'Débito';
    return 'Pago';
}

function fmtPhone(phone?: string) {
    if (!phone) return null;
    // Format: 0414-123-4567
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length >= 11) {
        return `${cleaned.slice(0, 4)}-${cleaned.slice(4, 7)}-${cleaned.slice(7, 11)}`;
    }
    return phone;
}

function fmtAccount(account?: string) {
    if (!account) return null;
    // Mask: •••• •••• •••• 1234
    const cleaned = account.replace(/\D/g, '');
    if (cleaned.length >= 4) {
        const last4 = cleaned.slice(-4);
        return `•••• •••• •••• ${last4}`;
    }
    return account;
}

export default function PortalPaymentsIndexModern() {
    const { items = [] } = usePage<Props>().props;

    const withBalance = items.filter((it) => it.status === 'CONFIRMED' && (it.available_bs_minor || 0) > 0);

    return (
        <AppLayout>
            <Head title="Mis pagos" />
            <div className="mx-auto w-full max-w-3xl px-4 py-6">
                {/* Header */}
                <div className="mb-6">
                    <Link href="/portal" className="text-muted-foreground hover:text-foreground mb-2 inline-flex items-center gap-1 text-sm">
                        ← Portal
                    </Link>
                    <h1 className="text-2xl font-bold tracking-tight">Mis pagos</h1>
                    <p className="text-muted-foreground text-sm">Registra y aplica tus pagos</p>
                </div>

                {/* Botón Registrar - Grande y claro */}
                <Link href="/portal/pagos/nuevo" className="group mb-6 block">
                    <Card className="border-2 border-blue-200 bg-blue-50/50 transition-all hover:border-blue-400 hover:bg-blue-50 hover:shadow-md dark:border-blue-900 dark:bg-blue-900/20 dark:hover:border-blue-700">
                        <CardContent className="flex items-center justify-between p-5">
                            <div className="flex items-center gap-4">
                                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 transition-transform group-hover:scale-105">
                                    <CreditCard className="h-7 w-7 text-white" />
                                </div>
                                <div>
                                    <p className="text-lg font-bold text-blue-900 dark:text-blue-100">Registrar nuevo pago</p>
                                    <p className="text-sm text-blue-700 dark:text-blue-300">Transferencia o Pago Móvil</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                {/* Alerta: Tienes saldo */}
                {withBalance.length > 0 && (
                    <Card className="mb-6 border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-900/20">
                        <CardContent className="p-4">
                            <div className="mb-3 flex items-center gap-2">
                                <AlertCircle className="h-5 w-5 text-green-600" />
                                <p className="font-semibold text-green-900 dark:text-green-100">Tienes saldo disponible</p>
                            </div>
                            <div className="space-y-2">
                                {withBalance.map((item) => (
                                    <div key={item.id} className="flex items-center justify-between rounded-lg bg-white p-3 dark:bg-slate-800">
                                        <div>
                                            <p className="font-bold text-green-700">{fmtMinor(item.available_bs_minor)}</p>
                                            <p className="text-xs text-slate-600 dark:text-slate-400">De tu pago del {fmtDate(item.paid_on)}</p>
                                        </div>
                                        <Link href={`/portal/pagos/${item.id}/aplicar`}>
                                            <Button size="sm" className="bg-green-600 hover:bg-green-700">
                                                Aplicar
                                            </Button>
                                        </Link>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Lista de pagos */}
                <div>
                    <h2 className="mb-3 text-sm font-medium text-slate-600 dark:text-slate-400">
                        {items.length === 0 ? 'Sin pagos' : `${items.length} ${items.length === 1 ? 'pago' : 'pagos'}`}
                    </h2>

                    {items.length > 0 ? (
                        <div className="space-y-2">
                            {items.map((item) => {
                                const isApplied = item.status === 'APPLIED';
                                const isVerified = item.status === 'CONFIRMED';
                                const isPending = !isApplied && !isVerified;
                                const hasAllocations = item.allocations && item.allocations.length > 0;

                                const isPmov = (item.method || '').toUpperCase().includes('PMOV');
                                const isTransfer =
                                    (item.method || '').toUpperCase().includes('TRF') || (item.method || '').toUpperCase().includes('TRANSF');

                                return (
                                    <Collapsible key={item.id}>
                                        <Card>
                                            <CardContent className="p-4">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="flex flex-1 items-start gap-3">
                                                        <div
                                                            className={cn(
                                                                'mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full',
                                                                isApplied && 'bg-blue-100 dark:bg-blue-900/30',
                                                                isVerified && 'bg-green-100 dark:bg-green-900/30',
                                                                isPending && 'bg-amber-100 dark:bg-amber-900/30',
                                                            )}
                                                        >
                                                            {isApplied && <CheckCircle2 className="h-5 w-5 text-blue-600" />}
                                                            {isVerified && <CheckCircle2 className="h-5 w-5 text-green-600" />}
                                                            {isPending && <Clock className="h-5 w-5 text-amber-600" />}
                                                        </div>
                                                        <div className="flex-1 space-y-2">
                                                            {/* Monto y método */}
                                                            <div>
                                                                <p className="font-bold">{fmtMinor(item.amount_bs_minor)}</p>
                                                                <p className="text-sm text-slate-600 dark:text-slate-400">{fmtDate(item.paid_on)}</p>
                                                            </div>

                                                            {/* Detalles del pago */}
                                                            <div className="space-y-1.5">
                                                                {/* Método y referencia */}
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <Badge
                                                                        variant="secondary"
                                                                        className={cn(
                                                                            'text-xs',
                                                                            isApplied && 'bg-blue-100 text-blue-700 dark:bg-blue-900/30',
                                                                            isVerified && 'bg-green-100 text-green-700 dark:bg-green-900/30',
                                                                            isPending && 'bg-amber-100 text-amber-700 dark:bg-amber-900/30',
                                                                        )}
                                                                    >
                                                                        {isApplied && 'Aplicado'}
                                                                        {isVerified && 'Verificado'}
                                                                        {isPending && 'En proceso'}
                                                                    </Badge>
                                                                    <Badge variant="outline" className="text-xs">
                                                                        {getMethod(item.method)}
                                                                    </Badge>
                                                                    {item.reference && (
                                                                        <Badge variant="outline" className="font-mono text-xs">
                                                                            Ref: {item.reference}
                                                                        </Badge>
                                                                    )}
                                                                </div>

                                                                {/* Origen del pago */}
                                                                {isPmov && item.payer_phone_e164 && (
                                                                    <p className="text-xs text-slate-600 dark:text-slate-400">
                                                                        <span className="font-medium">Desde:</span> {fmtPhone(item.payer_phone_e164)}
                                                                    </p>
                                                                )}
                                                                {isTransfer && item.payer_account_number && (
                                                                    <p className="text-xs text-slate-600 dark:text-slate-400">
                                                                        <span className="font-medium">Desde:</span>{' '}
                                                                        {fmtAccount(item.payer_account_number)}
                                                                    </p>
                                                                )}
                                                                {item.origin_bank_name && (
                                                                    <p className="text-xs text-slate-600 dark:text-slate-400">
                                                                        <span className="font-medium">Banco:</span> {item.origin_bank_name}
                                                                    </p>
                                                                )}

                                                                {/* Locales aplicados */}
                                                                {hasAllocations && (
                                                                    <p className="text-xs text-slate-500">
                                                                        Aplicado en {item.allocations!.length}{' '}
                                                                        {item.allocations!.length === 1 ? 'local' : 'locales'}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {hasAllocations && (
                                                        <CollapsibleTrigger asChild>
                                                            <Button variant="ghost" size="sm" className="mt-1 shrink-0">
                                                                <ChevronDown className="h-4 w-4" />
                                                            </Button>
                                                        </CollapsibleTrigger>
                                                    )}
                                                </div>

                                                {hasAllocations && (
                                                    <CollapsibleContent>
                                                        <div className="mt-3 space-y-2 border-t pt-3">
                                                            <p className="text-xs font-medium text-slate-600 dark:text-slate-400">
                                                                Detalle de aplicación:
                                                            </p>
                                                            {item.allocations!.map((alloc, idx) => (
                                                                <div
                                                                    key={idx}
                                                                    className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 dark:bg-slate-800/50"
                                                                >
                                                                    <span className="text-sm font-medium">{alloc.local}</span>
                                                                    <span className="text-sm text-slate-600 dark:text-slate-400">
                                                                        {fmtMinor(alloc.amount)}
                                                                    </span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </CollapsibleContent>
                                                )}
                                            </CardContent>
                                        </Card>
                                    </Collapsible>
                                );
                            })}
                        </div>
                    ) : (
                        <Card className="border-dashed">
                            <CardContent className="py-12 text-center">
                                <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                    <Plus className="h-6 w-6 text-slate-400" />
                                </div>
                                <p className="text-slate-600 dark:text-slate-400">No tienes pagos registrados</p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
