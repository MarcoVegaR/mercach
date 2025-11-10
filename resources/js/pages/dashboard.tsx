import ChargesByKindDonut from '@/components/analytics/ChargesByKindDonut';
import ChargesByStatusDonut from '@/components/analytics/ChargesByStatusDonut';
import ConcessionairesByTypeDonut from '@/components/analytics/ConcessionairesByTypeDonut';
import ConcessionairesNaturalByDocBar from '@/components/analytics/ConcessionairesNaturalByDocBar';
import { ConcessionairesRankingBar } from '@/components/analytics/ConcessionairesRankingBar';
import ContractsByStatusDonutEnhanced from '@/components/analytics/ContractsByStatusDonutEnhanced';
import ContractsByTypeDonut from '@/components/analytics/ContractsByTypeDonut';
import { ContractsTimelineTable } from '@/components/analytics/ContractsTimelineTable';
import DebtByLocalTypeDonut from '@/components/analytics/DebtByLocalTypeDonut';
import { DebtRankingBar } from '@/components/analytics/DebtRankingBar';
import { KpiCard } from '@/components/analytics/KpiCard';
import LocalsAvailableDonut from '@/components/analytics/LocalsAvailableDonut';
import LocalsByLocationBar from '@/components/analytics/LocalsByLocationBar';
import { PaymentTrendLine } from '@/components/analytics/PaymentTrendLine';
import ProjectedRevenueByLocalTypeDonut from '@/components/analytics/ProjectedRevenueByLocalTypeDonut';
import TopRevenueLocalsBar from '@/components/analytics/TopRevenueLocalsBar';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Building2, CreditCard, FileText, TrendingDown, Users } from 'lucide-react';

type AuthCan = Record<string, boolean>;

type DashboardKpis = {
    users: { total: number };
    locals: { available: number };
    concessionaires: { active: number };
    contracts: { vigentes: number };
    generated_at: string;
};

type RevenueProjection = {
    period_start: string;
    period_label: string;
    total_eur_minor: number;
    by_local_type: Array<{ local_type_id: number; local_type_name: string; amount_eur_minor: number; locals_count: number }>;
    generated_at: string;
};

type DebtMetrics = {
    total_overdue_eur_minor: number;
    total_overdue_bs_minor: number;
    fx_rate_ves_per_eur: number;
    fx_rate_date: string;
    delinquent_count: number;
    average_days_overdue: number;
    solvent_count: number;
    morosidad_rate: number;
    generated_at: string;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

export default function Dashboard() {
    const page = usePage<{ auth: { can: AuthCan } }>();
    const can = page.props.auth?.can ?? {};
    const canView = !!can['dashboard.view'];
    const canCards = !!can['dashboard.view.cards'];
    const canCharts = !!can['dashboard.view.charts'];

    const queryClient = useQueryClient();

    const { data: kpis, isLoading: kpisLoading } = useQuery<DashboardKpis>({
        queryKey: ['dashboard', 'kpis'],
        staleTime: 60_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/kpis', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load KPIs');
            return (await res.json()) as DashboardKpis;
        },
        enabled: canCards && canView,
    });

    const { data: revenueProj, isLoading: revenueLoading } = useQuery<RevenueProjection>({
        queryKey: ['dashboard', 'revenue', 'projection'],
        staleTime: 180_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/revenue/projection', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load revenue projection');
            return (await res.json()) as RevenueProjection;
        },
        enabled: canCharts && canView,
    });

    const { data: debtMetrics, isLoading: debtLoading } = useQuery<DebtMetrics>({
        queryKey: ['dashboard', 'debt-metrics'],
        staleTime: 120_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/debt/metrics', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load debt metrics');
            return (await res.json()) as DebtMetrics;
        },
        enabled: canCharts && canView,
    });

    const refreshAll = () => {
        queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    };

    if (!canView) return null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Tabs defaultValue="resumen" className="w-full">
                    <div className="flex items-center justify-between">
                        <TabsList>
                            <TabsTrigger value="resumen">Resumen Ejecutivo</TabsTrigger>
                            <TabsTrigger value="finanzas">Finanzas</TabsTrigger>
                            <TabsTrigger value="operaciones">Operaciones</TabsTrigger>
                            <TabsTrigger value="concesionarios">Concesionarios</TabsTrigger>
                        </TabsList>
                        <Button variant="outline" size="sm" onClick={refreshAll}>
                            Refrescar
                        </Button>
                    </div>

                    {/* TAB 1: RESUMEN EJECUTIVO - Vista compacta sin scroll */}
                    <TabsContent value="resumen" className="space-y-4">
                        {canCards && (
                            <section className="space-y-3">
                                <h2 className="text-base font-semibold text-slate-900">📊 Métricas Clave</h2>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <KpiCard
                                        title="Deuda total vencida"
                                        icon={AlertTriangle}
                                        iconClassName="bg-red-500/10"
                                        isLoading={debtLoading}
                                        value={`€ ${((debtMetrics?.total_overdue_eur_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                        subtitle={`${debtMetrics?.morosidad_rate ?? 0}% morosidad`}
                                        borderVariant="destructive"
                                        href="/dashboard/debt-analysis"
                                    />
                                    <KpiCard
                                        title="Renta mensual proyectada"
                                        icon={CreditCard}
                                        isLoading={revenueLoading}
                                        value={`€ ${((revenueProj?.total_eur_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                        subtitle={revenueProj?.period_label}
                                        borderVariant="primary"
                                    />
                                    <KpiCard
                                        title="Contratos vigentes"
                                        icon={FileText}
                                        isLoading={kpisLoading}
                                        value={kpis?.contracts.vigentes}
                                        subtitle="En curso hoy"
                                        href={
                                            can['contracts.view']
                                                ? '/catalogs/contract?filters%5Bcontract_status_id%5D=2&page=1&per_page=15'
                                                : undefined
                                        }
                                        borderVariant="primary"
                                    />
                                    <KpiCard
                                        title="Concesionarios activos"
                                        icon={Users}
                                        isLoading={kpisLoading}
                                        value={kpis?.concessionaires.active}
                                        subtitle={`${debtMetrics?.solvent_count ?? 0} solventes`}
                                        href={'/catalogs/concessionaire?filters%5Bhas_active_contract%5D=1&page=1&per_page=15'}
                                        borderVariant="neutral"
                                    />
                                </div>
                            </section>
                        )}

                        {canCharts && (
                            <section className="space-y-3">
                                <h2 className="text-base font-semibold text-slate-900">📈 Indicadores Principales</h2>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <ChargesByStatusDonut />
                                    <DebtByLocalTypeDonut />
                                    <PaymentTrendLine />
                                </div>
                            </section>
                        )}
                    </TabsContent>

                    {/* TAB 2: FINANZAS - Deudas, Ingresos y Pagos */}
                    <TabsContent value="finanzas" className="space-y-6">
                        {canCards && (
                            <section className="space-y-3">
                                <h2 className="text-muted-foreground text-base font-medium">Métricas de Riesgo</h2>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <KpiCard
                                        title="Deuda total vencida"
                                        icon={AlertTriangle}
                                        isLoading={debtLoading}
                                        value={`€ ${((debtMetrics?.total_overdue_eur_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                        subtitle={
                                            debtMetrics
                                                ? `Bs. ${((debtMetrics.total_overdue_bs_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })} • ${debtMetrics.delinquent_count} morosos`
                                                : undefined
                                        }
                                        borderVariant="destructive"
                                    />
                                    <KpiCard
                                        title="Concesionarios morosos"
                                        icon={Users}
                                        isLoading={debtLoading}
                                        value={debtMetrics?.delinquent_count}
                                        subtitle={`${debtMetrics?.morosidad_rate ?? 0}% del total`}
                                        borderVariant="destructive"
                                        href="/admin/economic-profile"
                                    />
                                    <KpiCard
                                        title="Promedio días atraso"
                                        icon={TrendingDown}
                                        isLoading={debtLoading}
                                        value={debtMetrics?.average_days_overdue}
                                        subtitle="Días vencidos"
                                        borderVariant="neutral"
                                    />
                                    <KpiCard
                                        title="Concesionarios solventes"
                                        icon={Users}
                                        isLoading={debtLoading}
                                        value={debtMetrics?.solvent_count}
                                        subtitle="Sin deuda vencida"
                                        borderVariant="success"
                                        href="/admin/economic-profile"
                                    />
                                </div>
                            </section>
                        )}

                        {canCharts && (
                            <>
                                {/* SECCIÓN DEUDAS */}
                                <section className="space-y-3">
                                    <h2 className="text-base font-semibold text-slate-900">💸 Análisis de Deudas</h2>
                                    <DebtRankingBar />
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <DebtByLocalTypeDonut />
                                        <ChargesByKindDonut />
                                        <ChargesByStatusDonut />
                                    </div>
                                </section>

                                {/* SECCIÓN INGRESOS */}
                                <section className="space-y-3">
                                    <h2 className="text-base font-semibold text-slate-900">💵 Proyección de Ingresos</h2>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <ProjectedRevenueByLocalTypeDonut />
                                        <TopRevenueLocalsBar />
                                    </div>
                                </section>

                                {/* SECCIÓN PAGOS */}
                                <section className="space-y-3">
                                    <h2 className="text-base font-semibold text-slate-900">💰 Estadísticas de Pagos</h2>
                                    <PaymentTrendLine />
                                </section>
                            </>
                        )}
                    </TabsContent>

                    {/* TAB 3: OPERACIONES - Contratos y Locales */}
                    <TabsContent value="operaciones" className="space-y-6">
                        {canCards && (
                            <section className="space-y-3">
                                <h2 className="text-base font-semibold text-slate-900">📊 Métricas Operativas</h2>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <KpiCard
                                        title="Contratos vigentes"
                                        icon={FileText}
                                        isLoading={kpisLoading}
                                        value={kpis?.contracts.vigentes}
                                        subtitle="En curso hoy"
                                        href={
                                            can['contracts.view']
                                                ? '/catalogs/contract?filters%5Bcontract_status_id%5D=2&page=1&per_page=15'
                                                : undefined
                                        }
                                        borderVariant="primary"
                                    />
                                    <KpiCard
                                        title="Locales disponibles"
                                        icon={Building2}
                                        isLoading={kpisLoading}
                                        value={kpis?.locals.available}
                                        subtitle="Sin contrato vigente"
                                        href={'/catalogs/local'}
                                        borderVariant="neutral"
                                    />
                                </div>
                            </section>
                        )}

                        {canCharts && (
                            <>
                                {/* SECCIÓN CONTRATOS */}
                                <section className="space-y-3">
                                    <h2 className="text-base font-semibold text-slate-900">📄 Gestión de Contratos</h2>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <ContractsByStatusDonutEnhanced />
                                        <ContractsByTypeDonut />
                                    </div>
                                    <ContractsTimelineTable />
                                </section>

                                {/* SECCIÓN LOCALES */}
                                <section className="space-y-3">
                                    <h2 className="text-base font-semibold text-slate-900">🏢 Infraestructura de Locales</h2>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <LocalsAvailableDonut />
                                        <LocalsByLocationBar />
                                    </div>
                                </section>
                            </>
                        )}
                    </TabsContent>

                    {/* TAB 4: CONCESIONARIOS */}
                    <TabsContent value="concesionarios" className="space-y-6">
                        {canCards && (
                            <section className="space-y-3">
                                <h2 className="text-base font-semibold text-slate-900">📊 Métricas de Concesionarios</h2>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <KpiCard
                                        title="Concesionarios activos"
                                        icon={Users}
                                        isLoading={kpisLoading}
                                        value={kpis?.concessionaires.active}
                                        subtitle="Con ≥1 contrato vigente"
                                        href={'/catalogs/concessionaire?filters%5Bhas_active_contract%5D=1&page=1&per_page=15'}
                                        borderVariant="primary"
                                    />
                                    <KpiCard
                                        title="Concesionarios solventes"
                                        icon={Users}
                                        isLoading={debtLoading}
                                        value={debtMetrics?.solvent_count}
                                        subtitle="Sin deuda vencida"
                                        borderVariant="success"
                                        href="/admin/economic-profile"
                                    />
                                    <KpiCard
                                        title="Concesionarios morosos"
                                        icon={Users}
                                        iconClassName="bg-red-500/10"
                                        isLoading={debtLoading}
                                        value={debtMetrics?.delinquent_count}
                                        subtitle={`${debtMetrics?.morosidad_rate ?? 0}% del total`}
                                        borderVariant="destructive"
                                        href="/admin/economic-profile"
                                    />
                                </div>
                            </section>
                        )}

                        {canCharts && (
                            <>
                                {/* SECCIÓN RANKING */}
                                <section className="space-y-3">
                                    <h2 className="text-base font-semibold text-slate-900">🏆 Top Concesionarios</h2>
                                    <ConcessionairesRankingBar />
                                </section>

                                {/* SECCIÓN ANÁLISIS */}
                                <section className="space-y-3">
                                    <h2 className="text-base font-semibold text-slate-900">📈 Análisis Demográfico</h2>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <ConcessionairesByTypeDonut />
                                        <ConcessionairesNaturalByDocBar />
                                    </div>
                                </section>
                            </>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
