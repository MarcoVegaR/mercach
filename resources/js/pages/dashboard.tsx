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
import React from 'react';

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
    total_overdue_usd_minor?: number;
    total_overdue_bs_minor_eur?: number;
    total_overdue_bs_minor_usd?: number;
    total_overdue_usd_condo_minor?: number;
    total_overdue_bs_minor_usd_condo?: number;
    total_overdue_usd_rent_fixed_minor?: number;
    total_overdue_bs_minor_usd_rent_fixed?: number;
    total_debt_eur_minor: number;
    total_debt_bs_minor: number;
    total_debt_usd_minor?: number;
    total_debt_bs_minor_eur?: number;
    total_debt_bs_minor_usd?: number;
    total_debt_usd_condo_minor?: number;
    total_debt_bs_minor_usd_condo?: number;
    total_debt_usd_rent_fixed_minor?: number;
    total_debt_bs_minor_usd_rent_fixed?: number;
    fx_rate_ves_per_eur: number;
    fx_rate_date: string;
    fx_rate_ves_per_usd?: number;
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
    const canFinance = !!can['dashboard.view.finance'];
    const canChartsContracts = !!(can['dashboard.view.charts.contracts'] ?? canCharts);
    const canChartsLocals = !!(can['dashboard.view.charts.locals'] ?? canCharts);
    const canChartsConcessionaires = !!(can['dashboard.view.charts.concessionaires'] ?? canCharts);
    const canChartsDebt = !!(can['dashboard.view.charts.debt'] ?? canCharts);
    const canChartsPayments = !!(can['dashboard.view.charts.payments'] ?? canCharts);

    const queryClient = useQueryClient();

    const forceDebtRef = React.useRef(false);

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
        enabled: canFinance && canView,
    });

    const { data: debtMetrics, isLoading: debtLoading } = useQuery<DebtMetrics>({
        queryKey: ['dashboard', 'debt-metrics'],
        staleTime: 120_000,
        queryFn: async () => {
            const force = forceDebtRef.current;
            const url = force ? '/api/dashboard/debt/metrics?force=1' : '/api/dashboard/debt/metrics';
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load debt metrics');
            const data = (await res.json()) as DebtMetrics;
            if (force) {
                forceDebtRef.current = false;
            }
            return data;
        },
        enabled: canFinance && canView,
    });

    const refreshAll = async () => {
        // Forzar que la próxima consulta de métricas de deuda invalide la caché del backend
        forceDebtRef.current = true;

        // Pedir al backend que recalcule caches de deuda y distribuciones relacionadas
        try {
            await Promise.all([
                fetch('/api/dashboard/debt/metrics?force=1', { headers: { Accept: 'application/json' } }),
                fetch('/api/dashboard/debt/overdue-counts?force=1', { headers: { Accept: 'application/json' } }),
                fetch('/api/dashboard/charges/by-kind?force=1', { headers: { Accept: 'application/json' } }),
                fetch('/api/dashboard/charges/by-status?force=1', { headers: { Accept: 'application/json' } }),
                fetch('/api/debt-analysis/distributions?force=1', { headers: { Accept: 'application/json' } }),
            ]);
        } catch {
            // Si alguno falla, igual invalidamos para que las queries reintenten
        }

        // Invalidar todas las queries relacionadas al dashboard y análisis de deuda
        queryClient.invalidateQueries({ queryKey: ['dashboard'] });
        queryClient.invalidateQueries({ queryKey: ['debt-analysis'] });
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
                            {canFinance && <TabsTrigger value="finanzas">Finanzas</TabsTrigger>}
                            <TabsTrigger value="operaciones">Operaciones</TabsTrigger>
                            <TabsTrigger value="concesionarios">Cesionarios</TabsTrigger>
                        </TabsList>
                        <Button variant="outline" size="sm" onClick={refreshAll}>
                            Refrescar
                        </Button>
                    </div>

                    {/* TAB 1: RESUMEN EJECUTIVO - Vista compacta sin scroll */}
                    <TabsContent value="resumen" className="space-y-4">
                        {canCards && (
                            <section className="space-y-3">
                                <h2 className="text-foreground text-base font-semibold">📊 Métricas Clave</h2>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    {canFinance && (
                                        <KpiCard
                                            title="Deuda total (Renta/Tasa)"
                                            icon={AlertTriangle}
                                            isLoading={debtLoading}
                                            value={`€ ${((debtMetrics?.total_debt_eur_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                            subtitle={
                                                debtMetrics
                                                    ? `Bs. ${((debtMetrics.total_debt_bs_minor_eur ?? debtMetrics.total_debt_bs_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`
                                                    : undefined
                                            }
                                            borderVariant="neutral"
                                            href={canFinance ? '/dashboard/debt-analysis' : undefined}
                                        />
                                    )}

                                    {canFinance && (
                                        <KpiCard
                                            title="Deuda total (Gastos Comunes)"
                                            icon={AlertTriangle}
                                            isLoading={debtLoading}
                                            value={`$ ${(((debtMetrics?.total_debt_usd_condo_minor ?? debtMetrics?.total_debt_usd_minor ?? 0) as number) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                            subtitle={
                                                debtMetrics
                                                    ? `Bs. ${(((debtMetrics.total_debt_bs_minor_usd_condo ?? debtMetrics.total_debt_bs_minor_usd ?? 0) as number) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`
                                                    : undefined
                                            }
                                            borderVariant="neutral"
                                            href={canFinance ? '/dashboard/debt-analysis' : undefined}
                                        />
                                    )}

                                    {canFinance && (
                                        <KpiCard
                                            title="Deuda total (Alquiler fijo)"
                                            icon={AlertTriangle}
                                            isLoading={debtLoading}
                                            value={`$ ${(((debtMetrics?.total_debt_usd_rent_fixed_minor ?? 0) as number) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                            subtitle={
                                                debtMetrics
                                                    ? `Bs. ${(((debtMetrics.total_debt_bs_minor_usd_rent_fixed ?? 0) as number) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`
                                                    : undefined
                                            }
                                            borderVariant="neutral"
                                            href={canFinance ? '/dashboard/debt-analysis' : undefined}
                                        />
                                    )}
                                    {canFinance && (
                                        <KpiCard
                                            title="Renta mensual proyectada"
                                            icon={CreditCard}
                                            isLoading={revenueLoading}
                                            value={`€ ${((revenueProj?.total_eur_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                            subtitle={revenueProj?.period_label}
                                            borderVariant="primary"
                                        />
                                    )}
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
                                        title="Cesionarios activos"
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

                        {canFinance && (canChartsDebt || canChartsPayments) && (
                            <section className="space-y-3">
                                <h2 className="text-foreground text-base font-semibold">📈 Indicadores Principales</h2>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    {canChartsPayments && <ChargesByStatusDonut />}
                                    {canChartsDebt && <DebtByLocalTypeDonut />}
                                    {canChartsPayments && <PaymentTrendLine />}
                                </div>
                            </section>
                        )}
                    </TabsContent>

                    {/* TAB 2: FINANZAS - Deudas, Ingresos y Pagos */}
                    {canFinance && (
                        <TabsContent value="finanzas" className="space-y-6">
                            {canCards && (
                                <section className="space-y-3">
                                    <h2 className="text-muted-foreground text-base font-medium">Métricas de Riesgo</h2>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <KpiCard
                                            title="Deuda total (Renta/Tasa)"
                                            icon={AlertTriangle}
                                            isLoading={debtLoading}
                                            value={`€ ${((debtMetrics?.total_debt_eur_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                            subtitle={
                                                debtMetrics
                                                    ? `Bs. ${((debtMetrics.total_debt_bs_minor_eur ?? debtMetrics.total_debt_bs_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`
                                                    : undefined
                                            }
                                            borderVariant="neutral"
                                        />

                                        <KpiCard
                                            title="Deuda vencida (Renta/Tasa)"
                                            icon={AlertTriangle}
                                            isLoading={debtLoading}
                                            value={`€ ${((debtMetrics?.total_overdue_eur_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                            subtitle={
                                                debtMetrics
                                                    ? `Bs. ${((debtMetrics.total_overdue_bs_minor_eur ?? debtMetrics.total_overdue_bs_minor ?? 0) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })} • ${debtMetrics.delinquent_count} morosos`
                                                    : undefined
                                            }
                                            borderVariant="destructive"
                                        />

                                        <KpiCard
                                            title="Deuda vencida (Gastos Comunes)"
                                            icon={AlertTriangle}
                                            isLoading={debtLoading}
                                            value={`$ ${(((debtMetrics?.total_overdue_usd_condo_minor ?? debtMetrics?.total_overdue_usd_minor ?? 0) as number) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                            subtitle={
                                                debtMetrics
                                                    ? `Bs. ${(((debtMetrics.total_overdue_bs_minor_usd_condo ?? debtMetrics.total_overdue_bs_minor_usd ?? 0) as number) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`
                                                    : undefined
                                            }
                                            borderVariant="destructive"
                                        />

                                        <KpiCard
                                            title="Deuda vencida (Alquiler fijo)"
                                            icon={AlertTriangle}
                                            isLoading={debtLoading}
                                            value={`$ ${(((debtMetrics?.total_overdue_usd_rent_fixed_minor ?? 0) as number) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`}
                                            subtitle={
                                                debtMetrics
                                                    ? `Bs. ${(((debtMetrics.total_overdue_bs_minor_usd_rent_fixed ?? 0) as number) / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`
                                                    : undefined
                                            }
                                            borderVariant="destructive"
                                        />
                                        <KpiCard
                                            title="Cesionarios morosos"
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
                                            title="Cesionarios solventes"
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

                            {(canChartsDebt || canChartsPayments) && (
                                <>
                                    {/* SECCIÓN DEUDAS */}
                                    {canChartsDebt && (
                                        <section className="space-y-3">
                                            <h2 className="text-foreground text-base font-semibold">💸 Análisis de Deudas</h2>
                                            <DebtRankingBar />
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                                <DebtByLocalTypeDonut />
                                                <ChargesByKindDonut />
                                                <ChargesByStatusDonut />
                                            </div>
                                        </section>
                                    )}

                                    {/* SECCIÓN INGRESOS */}
                                    {canChartsPayments && (
                                        <section className="space-y-3">
                                            <h2 className="text-foreground text-base font-semibold">💵 Proyección de Ingresos</h2>
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <ProjectedRevenueByLocalTypeDonut />
                                                <TopRevenueLocalsBar />
                                            </div>
                                        </section>
                                    )}

                                    {/* SECCIÓN PAGOS */}
                                    {canChartsPayments && (
                                        <section className="space-y-3">
                                            <h2 className="text-foreground text-base font-semibold">💰 Estadísticas de Pagos</h2>
                                            <PaymentTrendLine />
                                        </section>
                                    )}
                                </>
                            )}
                        </TabsContent>
                    )}

                    {/* TAB 3: OPERACIONES - Contratos y Locales */}
                    <TabsContent value="operaciones" className="space-y-6">
                        {canCards && (
                            <section className="space-y-3">
                                <h2 className="text-foreground text-base font-semibold">📊 Métricas Operativas</h2>
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

                        {(canChartsContracts || canChartsLocals) && (
                            <>
                                {/* SECCIÓN CONTRATOS */}
                                {canChartsContracts && (
                                    <section className="space-y-3">
                                        <h2 className="text-foreground text-base font-semibold">📄 Gestión de Contratos</h2>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <ContractsByStatusDonutEnhanced />
                                            <ContractsByTypeDonut />
                                        </div>
                                        <ContractsTimelineTable />
                                    </section>
                                )}

                                {/* SECCIÓN LOCALES */}
                                {canChartsLocals && (
                                    <section className="space-y-3">
                                        <h2 className="text-foreground text-base font-semibold">🏢 Infraestructura de Locales</h2>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <LocalsAvailableDonut />
                                            <LocalsByLocationBar />
                                        </div>
                                    </section>
                                )}
                            </>
                        )}
                    </TabsContent>

                    {/* TAB 4: CONCESIONARIOS */}
                    <TabsContent value="concesionarios" className="space-y-6">
                        {canCards && (
                            <section className="space-y-3">
                                <h2 className="text-foreground text-base font-semibold">📊 Métricas de Cesionarios</h2>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <KpiCard
                                        title="Cesionarios activos"
                                        icon={Users}
                                        isLoading={kpisLoading}
                                        value={kpis?.concessionaires.active}
                                        borderVariant="primary"
                                    />
                                    {canFinance && (
                                        <KpiCard
                                            title="Cesionarios solventes"
                                            icon={Users}
                                            isLoading={debtLoading}
                                            value={debtMetrics?.solvent_count}
                                            subtitle="Sin deuda vencida"
                                            borderVariant="success"
                                            href="/admin/economic-profile"
                                        />
                                    )}
                                    {canFinance && (
                                        <KpiCard
                                            title="Cesionarios morosos"
                                            icon={Users}
                                            iconClassName="bg-red-500/10"
                                            isLoading={debtLoading}
                                            value={debtMetrics?.delinquent_count}
                                            subtitle={`${debtMetrics?.morosidad_rate ?? 0}% del total`}
                                            borderVariant="destructive"
                                            href="/admin/economic-profile"
                                        />
                                    )}
                                </div>
                            </section>
                        )}

                        {canChartsConcessionaires && (
                            <>
                                {/* SECCIÓN RANKING */}
                                <section className="space-y-3">
                                    <h2 className="text-foreground text-base font-semibold">🏆 Top Cesionarios</h2>
                                    <ConcessionairesRankingBar />
                                </section>

                                {/* SECCIÓN ANÁLISIS */}
                                <section className="space-y-3">
                                    <h2 className="text-foreground text-base font-semibold">📈 Análisis Demográfico</h2>
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
