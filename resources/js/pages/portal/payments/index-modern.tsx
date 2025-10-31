import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Clock, Plus, Sparkles, XCircle } from 'lucide-react';

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
};

type Props = {
    items: Item[];
};

function fmtMinor(minor?: number | null) {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: 'VES', minimumFractionDigits: 2 });
}

function fmtDate(dateStr?: string) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return dateStr;
    }
}

function getStatusConfig(status?: string) {
    switch (status) {
        case 'CONFIRMED':
            return { icon: CheckCircle2, label: 'Verificado', color: 'text-green-600', bg: 'bg-green-50', border: 'border-green-200' };
        case 'APPLIED':
            return { icon: CheckCircle2, label: 'Aplicado', color: 'text-blue-600', bg: 'bg-blue-50', border: 'border-blue-200' };
        case 'REGISTERED':
            return { icon: Clock, label: 'Pendiente verificación', color: 'text-yellow-600', bg: 'bg-yellow-50', border: 'border-yellow-200' };
        default:
            return { icon: XCircle, label: status || 'Desconocido', color: 'text-gray-600', bg: 'bg-gray-50', border: 'border-gray-200' };
    }
}

function getMethodLabel(method?: string) {
    switch (method) {
        case 'TRANSFER':
            return 'Transferencia';
        case 'TRF':
            return 'Transferencia';
        case 'PMOV':
            return 'Pago Móvil';
        case 'DEB':
            return 'Débito';
        default:
            return method || '—';
    }
}

function fmtMaskedAccount(acct?: string) {
    if (!acct) return '—';
    const digits = acct.replace(/[^0-9]/g, '');
    if (digits.length < 4) return acct;
    return `•••• ${digits.slice(-4)}`;
}

export default function PortalPaymentsIndexModern() {
    const { items = [] } = usePage<Props>().props;

    const pendingPayments = items.filter((it) => String(it.status) === 'CONFIRMED' && Number(it.available_bs_minor || 0) > 0);
    const appliedPayments = items.filter((it) => String(it.status) === 'APPLIED');
    const otherPayments = items.filter((it) => String(it.status) !== 'CONFIRMED' && String(it.status) !== 'APPLIED');

    return (
        <AppLayout>
            <Head title="Mis pagos" />
            <div className="container mx-auto max-w-5xl px-4 py-8">
                <div className="mb-8 flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Mis pagos</h1>
                        <p className="text-muted-foreground mt-2">Gestiona tus pagos y aplícalos a tus deudas</p>
                    </div>
                    <Link href="/portal/pagos/nuevo">
                        <Button size="lg" className="gap-2">
                            <Plus className="h-4 w-4" />
                            Registrar nuevo pago
                        </Button>
                    </Link>
                </div>

                {pendingPayments.length > 0 && (
                    <Card className="animate-pulse-slow mb-6 border-2 border-green-400 bg-gradient-to-r from-green-50 to-blue-50 shadow-lg">
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 animate-bounce items-center justify-center rounded-full bg-green-500">
                                    <Sparkles className="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <CardTitle className="text-xl font-bold text-green-700">¡Acción requerida! Pagos listos para aplicar</CardTitle>
                                    <CardDescription className="text-base font-medium text-green-600">
                                        Tienes {pendingPayments.length} pago(s) verificado(s) que DEBES aplicar a tus deudas
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {pendingPayments.map((item) => {
                                const statusCfg = getStatusConfig(item.status);
                                const Icon = statusCfg.icon;
                                const appliedPercent = item.amount_bs_minor > 0 ? (item.applied_bs_minor / item.amount_bs_minor) * 100 : 0;
                                const availablePercent = 100 - appliedPercent;

                                return (
                                    <div
                                        key={item.id}
                                        className="rounded-lg border-2 border-green-300 bg-white p-4 shadow-md transition-all hover:scale-[1.01] hover:shadow-xl"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex-1 space-y-2">
                                                <div className="flex items-center gap-3">
                                                    <Badge variant="outline" className="font-mono text-xs">
                                                        #{item.id}
                                                    </Badge>
                                                    <Badge className={`${statusCfg.bg} ${statusCfg.color} border-0`}>
                                                        <Icon className="mr-1 h-3 w-3" />
                                                        {statusCfg.label}
                                                    </Badge>
                                                    <span className="text-muted-foreground text-sm">{getMethodLabel(item.method)}</span>
                                                </div>

                                                <div className="grid grid-cols-3 gap-4">
                                                    <div>
                                                        <div className="text-muted-foreground text-xs">Fecha</div>
                                                        <div className="text-sm font-medium">{fmtDate(item.paid_on)}</div>
                                                    </div>
                                                    <div>
                                                        <div className="text-muted-foreground text-xs">Monto total</div>
                                                        <div className="text-sm font-semibold">{fmtMinor(item.amount_bs_minor)}</div>
                                                    </div>
                                                    <div>
                                                        <div className="text-xs text-green-700">Disponible para aplicar</div>
                                                        <div className="text-sm font-bold text-green-700">{fmtMinor(item.available_bs_minor)}</div>
                                                    </div>
                                                </div>

                                                {item.applied_bs_minor > 0 && (
                                                    <div className="space-y-1">
                                                        <div className="flex items-center justify-between text-xs">
                                                            <span className="text-muted-foreground">Progreso de aplicación</span>
                                                            <span className="font-medium">{Math.round(appliedPercent)}%</span>
                                                        </div>
                                                        <Progress value={appliedPercent} className="h-2" />
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex flex-col gap-2">
                                                <Link href={`/portal/pagos/${item.id}/aplicar`}>
                                                    <Button
                                                        size="lg"
                                                        className="animate-pulse-subtle transform gap-2 bg-gradient-to-r from-green-600 to-green-700 px-6 py-6 text-base font-bold text-white shadow-lg transition-all hover:scale-105 hover:from-green-700 hover:to-green-800 hover:shadow-xl"
                                                    >
                                                        <Sparkles className="h-5 w-5" />
                                                        Aplicar a deudas
                                                        <ArrowRight className="h-5 w-5" />
                                                    </Button>
                                                </Link>
                                                <p className="text-center text-xs font-semibold text-green-700">¡Hazlo ahora!</p>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}

                {appliedPayments.length > 0 && (
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle className="text-lg">Pagos aplicados ({appliedPayments.length})</CardTitle>
                            <CardDescription>Historial de pagos aplicados con detalles</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Accordion type="single" collapsible>
                                {appliedPayments.map((item) => {
                                    const statusCfg = getStatusConfig(item.status);
                                    const Icon = statusCfg.icon;
                                    return (
                                        <AccordionItem key={item.id} value={`applied-${item.id}`}>
                                            <AccordionTrigger className="px-2">
                                                <div className="flex flex-1 items-center gap-3 text-left">
                                                    <Icon className={`h-5 w-5 ${statusCfg.color}`} />
                                                    <Badge variant="outline" className="font-mono text-xs">
                                                        #{item.id}
                                                    </Badge>
                                                    <span className="text-sm font-medium">{fmtDate(item.paid_on)}</span>
                                                    <Badge variant="secondary" className="text-xs">
                                                        {getMethodLabel(item.method)}
                                                    </Badge>
                                                </div>
                                                <div className="text-sm font-semibold">{fmtMinor(item.amount_bs_minor)}</div>
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <div className="space-y-1">
                                                        <div className="text-muted-foreground text-xs">Fecha</div>
                                                        <div className="text-sm font-medium">{fmtDate(item.paid_on)}</div>
                                                    </div>
                                                    <div className="space-y-1">
                                                        <div className="text-muted-foreground text-xs">Método</div>
                                                        <div className="text-sm font-medium">{getMethodLabel(item.method)}</div>
                                                    </div>
                                                    <div className="space-y-1">
                                                        <div className="text-muted-foreground text-xs">Monto total</div>
                                                        <div className="text-sm font-semibold">{fmtMinor(item.amount_bs_minor)}</div>
                                                    </div>
                                                    <div className="space-y-1">
                                                        <div className="text-muted-foreground text-xs">Aplicado</div>
                                                        <div className="text-sm font-semibold text-blue-600">{fmtMinor(item.applied_bs_minor)}</div>
                                                    </div>
                                                    {item.available_bs_minor > 0 && (
                                                        <div className="space-y-1">
                                                            <div className="text-muted-foreground text-xs">Disponible</div>
                                                            <div className="text-sm font-semibold text-green-700">
                                                                {fmtMinor(item.available_bs_minor)}
                                                            </div>
                                                        </div>
                                                    )}
                                                    <div className="space-y-1">
                                                        <div className="text-muted-foreground text-xs">Referencia</div>
                                                        <div className="text-sm font-medium">{item.reference || '—'}</div>
                                                    </div>
                                                    <div className="space-y-1">
                                                        <div className="text-muted-foreground text-xs">Banco de origen</div>
                                                        <div className="text-sm font-medium">{item.origin_bank_name || '—'}</div>
                                                    </div>
                                                    {item.method === 'PMOV' && (
                                                        <div className="space-y-1">
                                                            <div className="text-muted-foreground text-xs">Teléfono del pagador</div>
                                                            <div className="text-sm font-medium">{item.payer_phone_e164 || '—'}</div>
                                                        </div>
                                                    )}
                                                    {item.method !== 'PMOV' && (
                                                        <div className="space-y-1">
                                                            <div className="text-muted-foreground text-xs">Cuenta del pagador</div>
                                                            <div className="text-sm font-medium">{fmtMaskedAccount(item.payer_account_number)}</div>
                                                        </div>
                                                    )}
                                                </div>
                                            </AccordionContent>
                                        </AccordionItem>
                                    );
                                })}
                            </Accordion>
                        </CardContent>
                    </Card>
                )}

                {otherPayments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Otros pagos ({otherPayments.length})</CardTitle>
                            <CardDescription>Pagos en proceso de verificación u otros estados</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {otherPayments.map((item) => {
                                const statusCfg = getStatusConfig(item.status);
                                const Icon = statusCfg.icon;

                                return (
                                    <div key={item.id} className={`rounded-lg border p-4 ${statusCfg.bg} ${statusCfg.border}`}>
                                        <div className="flex items-center justify-between">
                                            <div className="flex flex-1 items-center gap-3">
                                                <Icon className={`h-5 w-5 ${statusCfg.color}`} />
                                                <div className="flex-1">
                                                    <div className="mb-1 flex items-center gap-2">
                                                        <Badge variant="outline" className="font-mono text-xs">
                                                            #{item.id}
                                                        </Badge>
                                                        <span className="text-sm font-medium">{fmtDate(item.paid_on)}</span>
                                                        <Badge className={`${statusCfg.bg} ${statusCfg.color} border-0 text-xs`}>
                                                            {statusCfg.label}
                                                        </Badge>
                                                    </div>
                                                    <div className="flex items-center gap-4 text-sm">
                                                        <div>
                                                            <span className="text-muted-foreground">Monto: </span>
                                                            <span className="font-semibold">{fmtMinor(item.amount_bs_minor)}</span>
                                                        </div>
                                                        <div>
                                                            <span className="text-muted-foreground">Método: </span>
                                                            <span>{getMethodLabel(item.method)}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}

                {items.length === 0 && (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <div className="flex flex-col items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                                    <Clock className="text-muted-foreground h-8 w-8" />
                                </div>
                                <div>
                                    <h3 className="mb-1 text-lg font-semibold">No tienes pagos registrados</h3>
                                    <p className="text-muted-foreground mb-4">Comienza registrando tu primer pago</p>
                                    <Link href="/portal/pagos/nuevo">
                                        <Button size="lg" className="gap-2">
                                            <Plus className="h-4 w-4" />
                                            Registrar mi primer pago
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
