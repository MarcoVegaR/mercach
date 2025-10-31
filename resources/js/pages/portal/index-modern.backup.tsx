import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { AlertCircle, ArrowRight, Building2, CheckCircle2, Clock, CreditCard, FileText, Receipt, Sparkles, TrendingDown, Wallet } from 'lucide-react';

type SummaryFx = {
    condo?: { currency: 'USD'; open_minor: number; overdue_minor: number };
    rent?: { currency: 'EUR'; open_minor: number; overdue_minor: number };
};
type Profile = {
    summary_bs: {
        open_bs_minor: number;
        net_due_after_credit_bs_minor: number;
        payments_available_bs_minor?: number;
        credits_open_bs_minor?: number;
        overdue_bs_minor?: number;
    };
    summary_fx?: SummaryFx;
};

type Props = {
    user: { name: string; email: string };
    at: string;
    concessionaire?: { id: number; full_name: string } | null;
    profile?: Profile | null;
};

function fmtMinor(minor?: number | null, curr: 'USD' | 'EUR' | 'VES' = 'VES') {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: curr, minimumFractionDigits: 2 });
}

function fmtDate(dateStr?: string) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    } catch {
        return dateStr;
    }
}

export default function PortalIndexModern({ user, at, concessionaire, profile }: Props) {
    const usdOpen = profile?.summary_fx?.condo?.open_minor ?? 0;
    const eurOpen = profile?.summary_fx?.rent?.open_minor ?? 0;
    const netDueBs = profile?.summary_bs?.net_due_after_credit_bs_minor ?? 0;
    const overdueBS = profile?.summary_bs?.overdue_bs_minor ?? 0;
    const creditsBS = profile?.summary_bs?.credits_open_bs_minor ?? 0;
    const paymentsAvailBS = profile?.summary_bs?.payments_available_bs_minor ?? 0;
    const totalOpenBS = profile?.summary_bs?.open_bs_minor ?? 0;

    const hasOverdue = overdueBS > 0;
    const hasCredits = creditsBS > 0;
    const hasPaymentsAvailable = paymentsAvailBS > 0;
    const debtHealthPercent = totalOpenBS > 0 ? Math.min(100, ((totalOpenBS - overdueBS) / totalOpenBS) * 100) : 100;

    return (
        <AppLayout>
            <div className="container mx-auto max-w-6xl px-4 py-8">
                {/* Header with greeting */}
                <div className="mb-8">
                    <h1 className="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-4xl font-bold tracking-tight text-transparent">
                        Bienvenido, {user?.name?.split(' ')[0] || 'Usuario'}
                    </h1>
                    <p className="text-muted-foreground mt-2 text-lg">{fmtDate(at)}</p>
                    {concessionaire && (
                        <div className="mt-3 flex items-center gap-2">
                            <Building2 className="text-muted-foreground h-4 w-4" />
                            <span className="text-sm font-medium">{concessionaire.full_name}</span>
                        </div>
                    )}
                </div>

                {/* Alerts section */}
                <div className="mb-8 space-y-3">
                    {hasOverdue && (
                        <Alert className="border-red-200 bg-red-50">
                            <AlertCircle className="h-4 w-4 text-red-600" />
                            <AlertDescription className="text-red-800">
                                <strong>Atención:</strong> Tienes {fmtMinor(overdueBS)} en deudas vencidas.
                                <Link href="/portal/deuda" className="ml-2 font-medium underline">
                                    Ver detalles →
                                </Link>
                            </AlertDescription>
                        </Alert>
                    )}

                    {hasPaymentsAvailable && (
                        <Alert className="border-green-200 bg-green-50">
                            <Sparkles className="h-4 w-4 text-green-600" />
                            <AlertDescription className="text-green-800">
                                ¡Tienes {fmtMinor(paymentsAvailBS)} en pagos listos para aplicar!
                                <Link href="/portal/pagos" className="ml-2 font-medium underline">
                                    Aplicar ahora →
                                </Link>
                            </AlertDescription>
                        </Alert>
                    )}

                    {hasCredits && (
                        <Alert className="border-blue-200 bg-blue-50">
                            <CheckCircle2 className="h-4 w-4 text-blue-600" />
                            <AlertDescription className="text-blue-800">Tienes {fmtMinor(creditsBS)} en saldo a favor disponible.</AlertDescription>
                        </Alert>
                    )}
                </div>

                {/* Financial summary */}
                <div className="mb-8 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <Card className={hasOverdue ? 'border-red-200' : ''}>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Wallet className="text-muted-foreground h-4 w-4" />
                                Deuda total
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold">{fmtMinor(totalOpenBS)}</div>
                            <div className="mt-3 space-y-2 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Condominio</span>
                                    <span className="font-semibold">{fmtMinor(usdOpen, 'USD')}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Alquiler</span>
                                    <span className="font-semibold">{fmtMinor(eurOpen, 'EUR')}</span>
                                </div>
                            </div>
                            <div className="mt-4">
                                <div className="mb-1 flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">Estado de deuda</span>
                                    <span className={`font-medium ${hasOverdue ? 'text-red-600' : 'text-green-600'}`}>
                                        {hasOverdue ? 'Con mora' : 'Al día'}
                                    </span>
                                </div>
                                <Progress value={debtHealthPercent} className={`h-2 ${hasOverdue ? '[&>div]:bg-red-500' : '[&>div]:bg-green-500'}`} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className={hasOverdue ? 'border-red-200 bg-red-50/30' : ''}>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <AlertCircle className="h-4 w-4 text-red-600" />
                                Deuda vencida
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className={`text-3xl font-bold ${hasOverdue ? 'text-red-600' : 'text-muted-foreground'}`}>{fmtMinor(overdueBS)}</div>
                            {hasOverdue ? (
                                <p className="mt-2 text-sm text-red-700">Requiere atención inmediata</p>
                            ) : (
                                <p className="text-muted-foreground mt-2 text-sm">¡Todo al día!</p>
                            )}
                            <Link href="/portal/deuda">
                                <Button variant="outline" size="sm" className="mt-4 w-full">
                                    Ver detalle
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>

                    <Card className={hasCredits ? 'border-green-200 bg-green-50/30' : ''}>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <TrendingDown className="h-4 w-4 text-green-600" />
                                Saldo a favor
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className={`text-3xl font-bold ${hasCredits ? 'text-green-600' : 'text-muted-foreground'}`}>
                                {fmtMinor(creditsBS)}
                            </div>
                            <p className="text-muted-foreground mt-2 text-sm">{hasCredits ? 'Disponible para aplicar' : 'Sin saldo disponible'}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <CheckCircle2 className="h-4 w-4 text-blue-600" />
                                Neto a pagar
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold text-blue-600">{fmtMinor(netDueBs)}</div>
                            <p className="text-muted-foreground mt-2 text-sm">Después de créditos y pagos</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick actions */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Card className="transition-shadow hover:shadow-lg">
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                                    <CreditCard className="h-6 w-6 text-blue-600" />
                                </div>
                                <div>
                                    <CardTitle className="text-lg">Registrar pago</CardTitle>
                                    <CardDescription>Transferencia o Pago Móvil</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-muted-foreground text-sm">
                                Registra tu pago bancario para que podamos verificarlo y aplicarlo a tus deudas.
                            </p>
                            <Link href="/portal/pagos/nuevo">
                                <Button className="w-full gap-2" size="lg">
                                    Registrar nuevo pago
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            </Link>
                            <Link href="/portal/pagos">
                                <Button variant="outline" className="w-full" size="sm">
                                    Ver mis pagos
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>

                    <Card className="transition-shadow hover:shadow-lg">
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100">
                                    <Wallet className="h-6 w-6 text-purple-600" />
                                </div>
                                <div>
                                    <CardTitle className="text-lg">Mi deuda</CardTitle>
                                    <CardDescription>Estado de cuenta completo</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="space-y-2 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Total:</span>
                                    <span className="font-semibold">{fmtMinor(totalOpenBS)}</span>
                                </div>
                                {hasOverdue && (
                                    <div className="flex items-center justify-between text-red-600">
                                        <span>Vencida:</span>
                                        <span className="font-semibold">{fmtMinor(overdueBS)}</span>
                                    </div>
                                )}
                            </div>
                            <Link href="/portal/deuda">
                                <Button variant="outline" className="w-full gap-2" size="lg">
                                    Ver estado de cuenta
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>

                    <Card className="transition-shadow hover:shadow-lg">
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                                    <Receipt className="h-6 w-6 text-green-600" />
                                </div>
                                <div>
                                    <CardTitle className="text-lg">Mis recibos</CardTitle>
                                    <CardDescription>Historial de pagos</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-muted-foreground text-sm">Consulta y descarga tus recibos de pago emitidos.</p>
                            <Link href="/portal/recibos">
                                <Button variant="outline" className="w-full gap-2" size="lg">
                                    Ver recibos
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>

                    <Card className="transition-shadow hover:shadow-lg">
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100">
                                    <FileText className="h-6 w-6 text-orange-600" />
                                </div>
                                <div>
                                    <CardTitle className="text-lg">Mis contratos</CardTitle>
                                    <CardDescription>Contratos y locales</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-muted-foreground text-sm">Revisa tus contratos activos y locales asociados.</p>
                            <Link href="/portal/contratos">
                                <Button variant="outline" className="w-full gap-2" size="lg">
                                    Ver contratos
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>

                    {hasPaymentsAvailable && (
                        <Card className="border-green-200 bg-green-50/30 transition-shadow hover:shadow-lg md:col-span-2">
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                                        <Sparkles className="h-6 w-6 text-green-600" />
                                    </div>
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            Pagos listos para aplicar
                                            <Badge variant="secondary" className="bg-green-600 text-white">
                                                Acción requerida
                                            </Badge>
                                        </CardTitle>
                                        <CardDescription>Tienes saldo sin asignar</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Saldo disponible:</span>
                                    <span className="text-2xl font-bold text-green-600">{fmtMinor(paymentsAvailBS)}</span>
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    Aplica este saldo a tus deudas pendientes para generar recibos de pago.
                                </p>
                                <Link href="/portal/pagos">
                                    <Button className="w-full gap-2 bg-green-600 hover:bg-green-700" size="lg">
                                        <Sparkles className="h-4 w-4" />
                                        Aplicar pagos ahora
                                        <ArrowRight className="h-4 w-4" />
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Help section */}
                <Card className="mt-8 border-blue-200 bg-blue-50/30">
                    <CardContent className="pt-6">
                        <div className="flex items-start gap-4">
                            <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                                <Clock className="h-5 w-5 text-blue-600" />
                            </div>
                            <div className="flex-1">
                                <h3 className="mb-2 text-lg font-semibold">¿Necesitas ayuda?</h3>
                                <p className="text-muted-foreground mb-3 text-sm">
                                    Si tienes preguntas sobre tus pagos, deudas o contratos, estamos aquí para ayudarte.
                                </p>
                                <div className="flex items-center gap-3 text-sm">
                                    <span className="text-muted-foreground">Contacto:</span>
                                    <span className="font-medium">administracion@mercadochacao.com</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
