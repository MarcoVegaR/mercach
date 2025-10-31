import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Building2,
    CheckCircle2,
    Clock,
    CreditCard,
    FileText,
    HelpCircle,
    Receipt,
    Sparkles,
    TrendingDown,
    Wallet,
} from 'lucide-react';

type SummaryFx = {
    condo?: { currency: 'USD'; open_minor: number; overdue_minor: number; rate_to_ves?: number | null };
    rent?: { currency: 'EUR'; open_minor: number; overdue_minor: number; rate_to_ves?: number | null };
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

    // Contextual greeting based on time of day
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches';

    return (
        <AppLayout>
            <div className="from-background to-muted/20 dark:from-background dark:to-muted/10 min-h-screen bg-gradient-to-b">
                <div className="container mx-auto max-w-7xl px-4 py-12">
                    {/* Hero Section */}
                    <div className="mb-12">
                        <div className="mb-6 flex items-center justify-between">
                            <div>
                                <h1 className="text-foreground mb-2 text-5xl font-bold tracking-tight">
                                    {greeting}, {user?.name?.split(' ')[0] || 'Usuario'}
                                </h1>
                                <p className="text-muted-foreground mb-1 text-lg">Gestiona tus pagos y consulta tu estado de cuenta</p>
                                <p className="text-muted-foreground text-sm">
                                    {new Date(at).toLocaleDateString('es-VE', { weekday: 'long', day: 'numeric', month: 'long' })}
                                </p>
                            </div>
                            {concessionaire && (
                                <div className="bg-card border-border hidden items-center gap-3 rounded-2xl border px-6 py-4 shadow-sm md:flex">
                                    <Building2 className="text-muted-foreground h-5 w-5" />
                                    <div>
                                        <div className="text-muted-foreground text-xs font-medium">Concesionario</div>
                                        <div className="text-foreground text-sm font-semibold">{concessionaire.full_name}</div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Concessionaire info on mobile */}
                    {concessionaire && (
                        <div className="bg-card border-border mb-6 flex items-center gap-3 rounded-xl border px-4 py-3 shadow-sm md:hidden">
                            <Building2 className="text-muted-foreground h-5 w-5" />
                            <div>
                                <div className="text-muted-foreground text-xs">Concesionario</div>
                                <div className="text-foreground text-sm font-semibold">{concessionaire.full_name}</div>
                            </div>
                        </div>
                    )}

                    {/* Quick Actions - No title, moved to top */}
                    <div className="mb-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {/* Registrar pago */}
                        <Link href="/portal/pagos/nuevo">
                            <Card className="group cursor-pointer border-0 bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-md transition-all hover:scale-[1.02] hover:shadow-xl">
                                <CardContent className="pt-6 pb-6">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm transition-transform group-hover:scale-110">
                                        <CreditCard className="h-7 w-7" />
                                    </div>
                                    <div className="mb-1 text-lg font-semibold">Registrar pago</div>
                                    <div className="text-sm text-blue-100">Transferencia o Pago Móvil</div>
                                </CardContent>
                            </Card>
                        </Link>

                        {/* Ver deuda */}
                        <Link href="/portal/deuda">
                            <Card className="group cursor-pointer border-0 shadow-md transition-all hover:scale-[1.02] hover:shadow-xl">
                                <CardContent className="pt-6 pb-6">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-purple-100 transition-transform group-hover:scale-110">
                                        <Wallet className="h-7 w-7 text-purple-600" />
                                    </div>
                                    <div className="text-foreground mb-1 text-lg font-semibold">Mi deuda</div>
                                    <div className="text-muted-foreground text-sm">Estado de cuenta</div>
                                </CardContent>
                            </Card>
                        </Link>

                        {/* Recibos */}
                        <Link href="/portal/recibos">
                            <Card className="group cursor-pointer border-0 shadow-md transition-all hover:scale-[1.02] hover:shadow-xl">
                                <CardContent className="pt-6 pb-6">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-green-100 transition-transform group-hover:scale-110">
                                        <Receipt className="h-7 w-7 text-green-600" />
                                    </div>
                                    <div className="text-foreground mb-1 text-lg font-semibold">Mis recibos</div>
                                    <div className="text-muted-foreground text-sm">Descargar comprobantes</div>
                                </CardContent>
                            </Card>
                        </Link>

                        {/* Contratos */}
                        <Link href="/portal/contratos">
                            <Card className="group cursor-pointer border-0 shadow-md transition-all hover:scale-[1.02] hover:shadow-xl">
                                <CardContent className="pt-6 pb-6">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 transition-transform group-hover:scale-110">
                                        <FileText className="h-7 w-7 text-amber-600" />
                                    </div>
                                    <div className="text-foreground mb-1 text-lg font-semibold">Mis contratos</div>
                                    <div className="text-muted-foreground text-sm">Contratos y locales</div>
                                </CardContent>
                            </Card>
                        </Link>
                    </div>

                    {/* Critical Alerts */}
                    <div className="mb-10 space-y-4">
                        {/* Overdue debt alert */}
                        {hasOverdue && (
                            <Alert role="alert" aria-live="polite" className="border-l-4 border-l-red-600 bg-red-50 shadow-md dark:bg-red-950/30">
                                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                                    <div className="flex flex-1 items-center gap-3">
                                        <div
                                            className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40"
                                            aria-hidden="true"
                                        >
                                            <AlertCircle className="h-5 w-5 text-red-700 dark:text-red-400" />
                                        </div>
                                        <div className="flex-1">
                                            <div className="mb-1 font-bold text-red-900 dark:text-red-300">Tienes deuda vencida</div>
                                            <div className="text-sm font-medium text-red-800 dark:text-red-400">
                                                {fmtMinor(overdueBS)} requiere pago inmediato
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex w-full gap-2 sm:w-auto">
                                        <Button asChild className="flex-1 bg-red-600 text-white hover:bg-red-700 sm:flex-initial">
                                            <Link href="/portal/pagos/nuevo">Pagar ahora</Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400"
                                        >
                                            <Link href="/portal/deuda">Ver detalles</Link>
                                        </Button>
                                    </div>
                                </div>
                            </Alert>
                        )}

                        {/* Payments available to apply alert */}
                        {hasPaymentsAvailable && (
                            <Alert role="alert" aria-live="polite" className="border-l-4 border-l-blue-600 bg-blue-50 shadow-md dark:bg-blue-950/30">
                                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                                    <div className="flex flex-1 items-center gap-3">
                                        <div
                                            className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40"
                                            aria-hidden="true"
                                        >
                                            <Sparkles className="h-5 w-5 text-blue-700 dark:text-blue-400" />
                                        </div>
                                        <div className="flex-1">
                                            <div className="mb-1 font-bold text-blue-900 dark:text-blue-300">Tienes pagos pendientes de aplicar</div>
                                            <div className="text-sm font-medium text-blue-800 dark:text-blue-400">
                                                {fmtMinor(paymentsAvailBS)} disponible para aplicar a tus deudas
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex w-full gap-2 sm:w-auto">
                                        <Button asChild className="flex-1 bg-blue-600 text-white hover:bg-blue-700 sm:flex-initial">
                                            <Link href="/portal/pagos">Aplicar ahora</Link>
                                        </Button>
                                    </div>
                                </div>
                            </Alert>
                        )}
                    </div>

                    {/* Financial Overview - Limpio y visual */}
                    <div className="mb-12">
                        <h2 className="text-foreground mb-6 text-2xl font-bold">Resumen financiero</h2>

                        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {/* Balance principal - Consolidated view */}
                            <Card className="border-0 bg-gradient-to-br from-blue-50 to-indigo-50 shadow-md transition-shadow hover:shadow-lg dark:from-blue-950/30 dark:to-indigo-950/30">
                                <CardContent className="pt-6">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div>
                                            <div className="text-muted-foreground text-sm font-medium">Total a pagar</div>
                                            {hasPaymentsAvailable && (
                                                <Badge
                                                    variant="secondary"
                                                    className="mt-1 bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                                >
                                                    <Sparkles className="mr-1 h-3 w-3" />
                                                    {fmtMinor(paymentsAvailBS)} para aplicar
                                                </Badge>
                                            )}
                                        </div>
                                        <CheckCircle2 className="h-5 w-5 text-blue-500" aria-hidden="true" />
                                    </div>
                                    <div className="mb-2 text-4xl font-bold text-blue-600 dark:text-blue-400">{fmtMinor(netDueBs)}</div>
                                    <div className="text-muted-foreground mb-3 text-xs">Ya descontados créditos y pagos</div>
                                    {hasOverdue && (
                                        <Badge variant="destructive" className="bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                            <AlertCircle className="mr-1 h-3 w-3" />
                                            {fmtMinor(overdueBS)} vencido
                                        </Badge>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Deuda total - With breakdown and rates */}
                            <Card
                                className="bg-card group border-0 shadow-md transition-shadow hover:shadow-lg"
                                title="Deuda total en bolívares con desglose por moneda"
                            >
                                <CardContent className="pt-6">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div className="text-muted-foreground text-sm font-medium">Deuda Total</div>
                                        <div className="flex items-center gap-1">
                                            <Wallet className="text-muted-foreground h-5 w-5" aria-hidden="true" />
                                        </div>
                                    </div>
                                    <div className="text-foreground mb-2 text-3xl font-bold">{fmtMinor(totalOpenBS)}</div>
                                    {(usdOpen > 0 || eurOpen > 0) && (
                                        <>
                                            <div className="text-muted-foreground mb-3 text-xs">Desglose por moneda</div>
                                            <div className="border-border space-y-2 border-t pt-3">
                                                {eurOpen > 0 && (
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-2 text-sm">
                                                            <span className="text-muted-foreground">🏪 Alquiler</span>
                                                        </div>
                                                        <div className="text-right">
                                                            <div className="text-foreground font-semibold">{fmtMinor(eurOpen, 'EUR')}</div>
                                                            {profile?.summary_fx?.rent?.rate_to_ves && (
                                                                <div className="text-muted-foreground text-xs">
                                                                    Tasa: {profile.summary_fx.rent.rate_to_ves.toFixed(2)} VES
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                                {usdOpen > 0 && (
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-2 text-sm">
                                                            <span className="text-muted-foreground">🏢 Condominio</span>
                                                        </div>
                                                        <div className="text-right">
                                                            <div className="text-foreground font-semibold">{fmtMinor(usdOpen, 'USD')}</div>
                                                            {profile?.summary_fx?.condo?.rate_to_ves && (
                                                                <div className="text-muted-foreground text-xs">
                                                                    Tasa: {profile.summary_fx.condo.rate_to_ves.toFixed(2)} VES
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Saldo a favor - Only show if > 0 */}
                            {hasCredits && (
                                <Card className="border-0 bg-gradient-to-br from-green-50 to-green-100/50 shadow-md transition-shadow hover:shadow-lg dark:from-green-950/30 dark:to-green-900/20">
                                    <CardContent className="pt-6">
                                        <div className="mb-4 flex items-center justify-between">
                                            <div className="text-muted-foreground text-sm font-medium">Saldo a favor</div>
                                            <TrendingDown className="h-5 w-5 text-green-500" aria-hidden="true" />
                                        </div>
                                        <div className="mb-2 text-3xl font-bold text-green-600 dark:text-green-400">{fmtMinor(creditsBS)}</div>
                                        <div className="text-muted-foreground text-sm">✓ Disponible para aplicar</div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>

                    {/* Secondary action - Ver pagos */}
                    <Link href="/portal/pagos">
                        <Card className="group bg-card mb-6 cursor-pointer border-0 shadow-sm transition-shadow hover:shadow-md">
                            <CardContent className="px-5 py-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-xl">
                                            <Clock className="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="text-foreground font-medium">Ver mis pagos</div>
                                            <div className="text-muted-foreground text-xs">Historial completo</div>
                                        </div>
                                    </div>
                                    <ArrowRight className="text-muted-foreground group-hover:text-foreground h-5 w-5 transition-all group-hover:translate-x-1" />
                                </div>
                            </CardContent>
                        </Card>
                    </Link>

                    {/* Help card - At the end */}
                    <Card
                        className="border-blue-200 bg-blue-50/30 dark:border-blue-900/50 dark:bg-blue-950/20"
                        role="complementary"
                        aria-label="Información de ayuda"
                    >
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-4">
                                <div
                                    className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
                                    aria-hidden="true"
                                >
                                    <HelpCircle className="h-6 w-6 text-blue-600" />
                                </div>
                                <div className="flex-1">
                                    <h3 className="text-foreground mb-1 font-semibold">¿Necesitas ayuda?</h3>
                                    <p className="text-muted-foreground text-sm">
                                        Contacta a administración:{' '}
                                        <a
                                            href="mailto:administracion@mercadochacao.com"
                                            className="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                        >
                                            administracion@mercadochacao.com
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
