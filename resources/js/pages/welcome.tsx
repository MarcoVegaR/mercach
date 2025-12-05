import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CheckCircle2,
    CreditCard,
    FileText,
    HelpCircle,
    LayoutDashboard,
    Lock,
    Mail,
    MapPin,
    Phone,
    Shield,
    ShoppingBag,
    Store,
    Users,
} from 'lucide-react';

// Market Icon Component
function MarketIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="8" y="24" width="48" height="36" rx="2" fill="currentColor" fillOpacity="0.1" stroke="currentColor" strokeWidth="2" />
            <path d="M4 24L32 8L60 24" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
            <rect x="16" y="36" width="12" height="24" rx="1" fill="currentColor" fillOpacity="0.2" stroke="currentColor" strokeWidth="1.5" />
            <rect x="36" y="36" width="12" height="12" rx="1" fill="currentColor" fillOpacity="0.2" stroke="currentColor" strokeWidth="1.5" />
            <rect x="36" y="52" width="12" height="8" rx="1" fill="currentColor" fillOpacity="0.2" stroke="currentColor" strokeWidth="1.5" />
            <circle cx="32" cy="18" r="3" fill="currentColor" />
        </svg>
    );
}

// Feature Card Component
function FeatureCard({ icon: Icon, title, description }: { icon: React.ElementType; title: string; description: string }) {
    return (
        <div className="group flex items-start gap-4 rounded-xl p-4 transition-all duration-300 hover:bg-slate-50 dark:hover:bg-slate-800/50">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/25 transition-transform duration-300 group-hover:scale-110">
                <Icon className="h-6 w-6" />
            </div>
            <div>
                <h3 className="font-semibold text-slate-900 dark:text-white">{title}</h3>
                <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">{description}</p>
            </div>
        </div>
    );
}

// Stats Card Component
function StatCard({ value, label, icon: Icon }: { value: string; label: string; icon: React.ElementType }) {
    return (
        <div className="text-center">
            <div className="mb-2 flex justify-center">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-white/20">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
            <div className="text-3xl font-bold">{value}</div>
            <div className="mt-1 text-sm opacity-80">{label}</div>
        </div>
    );
}

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const canDashboard = Boolean(auth?.can?.['dashboard.view']);
    const canPortal = Boolean(auth?.can?.['portal.access']);

    return (
        <>
            <Head title="Portal de Servicios - Mercado de Chacao" />
            <div className="relative min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
                {/* Decorative Background Elements */}
                <div className="pointer-events-none absolute inset-0 overflow-hidden">
                    <div className="absolute -top-40 right-0 h-[500px] w-[500px] rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-500/20 blur-3xl" />
                    <div className="absolute -bottom-40 left-0 h-[500px] w-[500px] rounded-full bg-gradient-to-tr from-blue-400/20 to-indigo-500/20 blur-3xl" />
                    <div className="absolute top-1/2 left-1/2 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-gradient-to-r from-emerald-400/10 to-blue-500/10 blur-3xl" />
                </div>

                {/* Header */}
                <header className="relative z-20 border-b border-slate-200/50 bg-white/70 backdrop-blur-xl dark:border-slate-800/50 dark:bg-slate-900/70">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 sm:py-4">
                        {/* Logo & Brand */}
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/25">
                                <MarketIcon className="h-6 w-6 text-white" />
                            </div>
                            <div>
                                <div className="font-bold text-slate-900 dark:text-white">Mercado de Chacao</div>
                                <div className="text-xs text-slate-500 dark:text-slate-400">Portal de Servicios</div>
                            </div>
                        </div>

                        {/* Navigation */}
                        <nav className="flex w-full items-center justify-end gap-3 sm:w-auto">
                            {auth.user ? (
                                <div className="flex flex-wrap gap-2">
                                    {canPortal && (
                                        <Button asChild className="bg-emerald-600 hover:bg-emerald-700">
                                            <Link href={route('portal.index')}>
                                                <Users className="mr-2 h-4 w-4" />
                                                Ir al Portal
                                            </Link>
                                        </Button>
                                    )}
                                    {canDashboard && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="border-slate-300 hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                                        >
                                            <Link href={route('dashboard')}>
                                                <LayoutDashboard className="mr-2 h-4 w-4" />
                                                Panel Administrativo
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <>
                                    <Button
                                        asChild
                                        variant="ghost"
                                        className="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                                    >
                                        <Link href={route('login')}>Iniciar Sesión</Link>
                                    </Button>
                                    <Button asChild className="bg-emerald-600 hover:bg-emerald-700">
                                        <Link href={route('login')}>
                                            Acceder al Portal
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero Section */}
                <section className="relative z-10 px-6 pt-16 pb-20 lg:pt-24 lg:pb-32">
                    <div className="mx-auto max-w-7xl">
                        <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                            {/* Hero Content */}
                            <div className="text-center lg:text-left">
                                {/* Badge */}
                                <div className="mb-6 inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                    <Store className="h-4 w-4" />
                                    <span>Sistema Oficial de Gestión</span>
                                </div>

                                {/* Title */}
                                <h1 className="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">
                                    <span className="block">Portal de Servicios</span>
                                    <span className="mt-2 block bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                                        Mercado de Chacao
                                    </span>
                                </h1>

                                {/* Description */}
                                <p className="mx-auto mt-6 max-w-xl text-lg text-slate-600 lg:mx-0 dark:text-slate-400">
                                    Plataforma integral para la gestión de locales comerciales, contratos, pagos y servicios del Mercado Municipal de
                                    Chacao.
                                </p>

                                {/* CTA Buttons */}
                                <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row lg:justify-start">
                                    {auth.user ? (
                                        <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                                            {canPortal && (
                                                <Button asChild size="lg" className="w-full bg-emerald-600 px-8 hover:bg-emerald-700 sm:w-auto">
                                                    <Link href={route('portal.index')}>
                                                        <Users className="mr-2 h-5 w-5" />
                                                        Ir al Portal
                                                    </Link>
                                                </Button>
                                            )}
                                            {canDashboard && (
                                                <Button
                                                    asChild
                                                    size="lg"
                                                    variant="outline"
                                                    className="w-full border-slate-300 px-8 hover:bg-slate-100 sm:w-auto dark:border-slate-700 dark:hover:bg-slate-800"
                                                >
                                                    <Link href={route('dashboard')}>
                                                        <LayoutDashboard className="mr-2 h-5 w-5" />
                                                        Panel Administrativo
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    ) : (
                                        <>
                                            <Button asChild size="lg" className="w-full bg-emerald-600 px-8 hover:bg-emerald-700 sm:w-auto">
                                                <Link href={route('login')}>
                                                    <Users className="mr-2 h-5 w-5" />
                                                    Portal de Concesionarios
                                                    <ArrowRight className="ml-2 h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                size="lg"
                                                variant="outline"
                                                className="w-full border-slate-300 px-8 hover:bg-slate-100 sm:w-auto dark:border-slate-700 dark:hover:bg-slate-800"
                                            >
                                                <Link href={route('login')}>
                                                    <Shield className="mr-2 h-5 w-5 text-slate-500" />
                                                    Acceso Administrativo
                                                </Link>
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>

                            {/* Hero Visual */}
                            <div className="relative hidden lg:block">
                                <div className="relative mx-auto w-full max-w-lg">
                                    {/* Main Card */}
                                    <Card className="relative overflow-hidden border-0 bg-white/80 shadow-2xl shadow-slate-900/10 backdrop-blur-sm dark:bg-slate-800/80">
                                        <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500" />
                                        <CardContent className="p-8">
                                            {/* Stats Grid */}
                                            <div className="mb-8 grid grid-cols-3 gap-4">
                                                <div className="rounded-xl bg-emerald-50 p-4 text-center dark:bg-emerald-900/20">
                                                    <Building2 className="mx-auto h-6 w-6 text-emerald-600" />
                                                    <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">200+</div>
                                                    <div className="text-xs text-slate-600 dark:text-slate-400">Locales</div>
                                                </div>
                                                <div className="rounded-xl bg-blue-50 p-4 text-center dark:bg-blue-900/20">
                                                    <Users className="mx-auto h-6 w-6 text-blue-600" />
                                                    <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">150+</div>
                                                    <div className="text-xs text-slate-600 dark:text-slate-400">Concesionarios</div>
                                                </div>
                                                <div className="rounded-xl bg-purple-50 p-4 text-center dark:bg-purple-900/20">
                                                    <ShoppingBag className="mx-auto h-6 w-6 text-purple-600" />
                                                    <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">10+</div>
                                                    <div className="text-xs text-slate-600 dark:text-slate-400">Años de servicio</div>
                                                </div>
                                            </div>

                                            {/* Quick Actions Preview */}
                                            <div className="space-y-3">
                                                <div className="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                                                        <CreditCard className="h-5 w-5 text-emerald-600" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <div className="text-sm font-medium text-slate-900 dark:text-white">Registro de Pagos</div>
                                                        <div className="text-xs text-slate-500">Transferencia y Pago Móvil</div>
                                                    </div>
                                                    <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                                                </div>
                                                <div className="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                                        <FileText className="h-5 w-5 text-blue-600" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <div className="text-sm font-medium text-slate-900 dark:text-white">Consulta de Deudas</div>
                                                        <div className="text-xs text-slate-500">Estado de cuenta en tiempo real</div>
                                                    </div>
                                                    <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                                                </div>
                                                <div className="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                                                        <FileText className="h-5 w-5 text-purple-600" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <div className="text-sm font-medium text-slate-900 dark:text-white">Recibos Digitales</div>
                                                        <div className="text-xs text-slate-500">Descarga y comparte</div>
                                                    </div>
                                                    <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    {/* Floating Elements */}
                                    <div className="absolute -top-4 -right-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/30">
                                        <Lock className="h-8 w-8 text-white" />
                                    </div>
                                    <div className="absolute -bottom-4 -left-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 shadow-lg shadow-blue-500/30">
                                        <Shield className="h-7 w-7 text-white" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section className="relative z-10 bg-white/50 px-6 py-20 backdrop-blur-sm dark:bg-slate-900/50">
                    <div className="mx-auto max-w-7xl">
                        <div className="mb-16 text-center">
                            <h2 className="text-3xl font-bold text-slate-900 dark:text-white">Servicios Disponibles</h2>
                            <p className="mt-4 text-lg text-slate-600 dark:text-slate-400">
                                Accede a todos los servicios del mercado desde un solo lugar
                            </p>
                        </div>

                        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            <FeatureCard
                                icon={CreditCard}
                                title="Registro de Pagos"
                                description="Registra tus pagos por transferencia bancaria o Pago Móvil con verificación automática."
                            />
                            <FeatureCard
                                icon={FileText}
                                title="Estado de Cuenta"
                                description="Consulta tu saldo, deudas pendientes y pagos realizados en tiempo real."
                            />
                            <FeatureCard
                                icon={Building2}
                                title="Gestión de Contratos"
                                description="Visualiza la información de tus contratos activos y locales asignados."
                            />
                            <FeatureCard
                                icon={FileText}
                                title="Recibos Digitales"
                                description="Descarga tus recibos de pago en formato PDF para tu archivo personal."
                            />
                            <FeatureCard
                                icon={Shield}
                                title="Seguridad Garantizada"
                                description="Toda la información está protegida y cada acción es registrada para tu seguridad."
                            />
                            <FeatureCard
                                icon={HelpCircle}
                                title="Soporte Directo"
                                description="Contacta a la administración del mercado para resolver cualquier consulta."
                            />
                        </div>
                    </div>
                </section>

                {/* Trust Section */}
                <section className="relative z-10 overflow-hidden bg-gradient-to-br from-emerald-600 to-teal-700 px-6 py-16 text-white">
                    <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDM0djZoLTZ2LTZoNnptMC0zMHY2aC02VjRoNnptMzAgMzB2Nmg2di02aC02em0tNjAgMHY2aDZ2LTZINnptMzAtMzB2Nmg2VjRoLTZ6bS0zMCAzMHY2aDZ2LTZINnptMCAzMHY2aDZ2LTZINnptMzAgMHY2aDZ2LTZoLTZ6Ii8+PC9nPjwvZz48L3N2Zz4=')] opacity-50" />
                    <div className="relative mx-auto max-w-7xl">
                        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard value="24/7" label="Disponibilidad" icon={Store} />
                            <StatCard value="100%" label="Verificación Bancaria" icon={CheckCircle2} />
                            <StatCard value="SSL" label="Conexión Segura" icon={Lock} />
                            <StatCard value="Auditoría" label="Registro Completo" icon={Shield} />
                        </div>
                    </div>
                </section>

                {/* Security Notice */}
                <section className="relative z-10 px-6 py-16">
                    <div className="mx-auto max-w-4xl">
                        <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-800/50 dark:bg-amber-900/10">
                            <CardContent className="flex flex-col items-start gap-4 p-6 sm:flex-row">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
                                    <Shield className="h-6 w-6 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-amber-900 dark:text-amber-300">Aviso de Seguridad</h3>
                                    <ul className="mt-2 space-y-1 text-sm text-amber-800 dark:text-amber-400">
                                        <li className="flex items-center gap-2">
                                            <CheckCircle2 className="h-4 w-4" />
                                            Acceso exclusivo para usuarios autorizados del Mercado de Chacao.
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <CheckCircle2 className="h-4 w-4" />
                                            Toda actividad es registrada y auditada para garantizar la transparencia.
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <CheckCircle2 className="h-4 w-4" />
                                            Para obtener acceso, contacte a la administración del mercado.
                                        </li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                {/* Footer */}
                <footer className="relative z-10 border-t border-slate-200 bg-white px-6 py-12 dark:border-slate-800 dark:bg-slate-900">
                    <div className="mx-auto max-w-7xl">
                        <div className="grid gap-8 md:grid-cols-3">
                            {/* Brand */}
                            <div>
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600">
                                        <MarketIcon className="h-6 w-6 text-white" />
                                    </div>
                                    <div>
                                        <div className="font-bold text-slate-900 dark:text-white">Mercado de Chacao</div>
                                        <div className="text-xs text-slate-500">Municipio Chacao, Caracas</div>
                                    </div>
                                </div>
                                <p className="mt-4 text-sm text-slate-600 dark:text-slate-400">
                                    Sistema de gestión integral para el mercado municipal más emblemático de Caracas.
                                </p>
                            </div>

                            {/* Contact */}
                            <div>
                                <h4 className="mb-4 font-semibold text-slate-900 dark:text-white">Contacto</h4>
                                <ul className="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                                    <li className="flex items-center gap-2">
                                        <Mail className="h-4 w-4 text-slate-400" />
                                        <a href="mailto:mercado@chacao.gob.ve" className="hover:text-emerald-600">
                                            mercado@chacao.gob.ve
                                        </a>
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <Phone className="h-4 w-4 text-slate-400" />
                                        <span>(0212) 265-4342</span>
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <MapPin className="h-4 w-4 text-slate-400" />
                                        <span>Av Mohedano, Chacao, Caracas</span>
                                    </li>
                                </ul>
                            </div>

                            {/* Quick Links */}
                            <div>
                                <h4 className="mb-4 font-semibold text-slate-900 dark:text-white">Accesos</h4>
                                <ul className="space-y-3 text-sm">
                                    <li>
                                        <Link
                                            href={route('login')}
                                            className="flex items-center gap-2 text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-400"
                                        >
                                            <ArrowRight className="h-4 w-4" />
                                            Portal de Concesionarios
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={route('login')}
                                            className="flex items-center gap-2 text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-400"
                                        >
                                            <ArrowRight className="h-4 w-4" />
                                            Panel Administrativo
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {/* Bottom Bar */}
                        <div className="mt-12 flex flex-col items-center justify-between gap-4 border-t border-slate-200 pt-8 md:flex-row dark:border-slate-800">
                            <p className="text-sm text-slate-500">© {new Date().getFullYear()} Mercado de Chacao. Todos los derechos reservados.</p>
                            <p className="text-xs text-slate-400">
                                Desarrollado por{' '}
                                <a
                                    href="https://caracoders.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="font-medium text-slate-500 hover:text-emerald-600"
                                >
                                    Caracoders Pro Services
                                </a>
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
