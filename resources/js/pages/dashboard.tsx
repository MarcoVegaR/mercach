import { AlertBanner, type DashboardAlert } from '@/components/analytics/AlertBanner';
import ChargesByKindDonut from '@/components/analytics/ChargesByKindDonut';
import ChargesByStatusDonut from '@/components/analytics/ChargesByStatusDonut';
import { ConcessionairesRankingBar } from '@/components/analytics/ConcessionairesRankingBar';
import ContractsByStatusDonutEnhanced from '@/components/analytics/ContractsByStatusDonutEnhanced';
import ContractsByTypeDonut from '@/components/analytics/ContractsByTypeDonut';
import { ContractsTimelineTable } from '@/components/analytics/ContractsTimelineTable';
import DebtByLocalTypeDonut from '@/components/analytics/DebtByLocalTypeDonut';
import { DebtRankingBar } from '@/components/analytics/DebtRankingBar';
import { KpiStatCard, type SparkPoint } from '@/components/analytics/KpiStatCard';
import LocalsAvailableDonut from '@/components/analytics/LocalsAvailableDonut';
import LocalsByLocationBar from '@/components/analytics/LocalsByLocationBar';
import PaymentRevenueBreakdown from '@/components/analytics/PaymentRevenueBreakdown';
import { PaymentTrendLine } from '@/components/analytics/PaymentTrendLine';
import ProjectedRevenueByLocalTypeDonut from '@/components/analytics/ProjectedRevenueByLocalTypeDonut';
import TopRevenueLocalsBar from '@/components/analytics/TopRevenueLocalsBar';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Banknote, Building2, Clock, FileText, RefreshCw, Shield, TrendingUp, Users } from 'lucide-react';
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
    total_bs_minor: number;
    by_local_type: Array<{
        local_type_id: number;
        local_type_name: string;
        amount_eur_minor: number;
        amount_bs_minor: number;
        locals_count: number;
    }>;
    fx_rate_ves_per_eur: number;
    fx_rate_date?: string | null;
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

type AlertsResponse = {
    alerts: DashboardAlert[];
    generated_at: string;
};

type SparklineResponse = {
    items: SparkPoint[];
    generated_at: string;
};

function timeAgo(isoStr?: string): string {
    if (!isoStr) return '';
    const diff = Math.floor((Date.now() - new Date(isoStr).getTime()) / 1000);
    if (diff < 60) return 'hace unos segundos';
    if (diff < 3600) return `hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `hace ${Math.floor(diff / 3600)}h`;
    return `hace ${Math.floor(diff / 86400)}d`;
}

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
    const [isRefreshing, setIsRefreshing] = React.useState(false);

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
            if (force) forceDebtRef.current = false;
            return data;
        },
        enabled: canFinance && canView,
    });

    const { data: alertsData } = useQuery<AlertsResponse>({
        queryKey: ['dashboard', 'alerts'],
        staleTime: 120_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/alerts', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load alerts');
            return (await res.json()) as AlertsResponse;
        },
        enabled: canCards && canView,
    });

    const { data: sparklineData } = useQuery<SparklineResponse>({
        queryKey: ['dashboard', 'revenue', 'sparkline'],
        staleTime: 300_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/revenue/sparkline?months=6', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load sparkline');
            return (await res.json()) as SparklineResponse;
        },
        enabled: canFinance && canView,
    });

    const latestTimestamp = React.useMemo(() => {
        const timestamps = [kpis?.generated_at, debtMetrics?.generated_at, revenueProj?.generated_at].filter(Boolean);
        if (timestamps.length === 0) return undefined;
        return timestamps.sort().pop();
    }, [kpis, debtMetrics, revenueProj]);

    const refreshAll = async () => {
        setIsRefreshing(true);
        forceDebtRef.current = true;
        try {
            await Promise.all([
                fetch('/api/dashboard/debt/metrics?force=1', { headers: { Accept: 'application/json' } }),
                fetch('/api/dashboard/debt/overdue-counts?force=1', { headers: { Accept: 'application/json' } }),
                fetch('/api/dashboard/charges/by-kind?force=1', { headers: { Accept: 'application/json' } }),
                fetch('/api/dashboard/charges/by-status?force=1', { headers: { Accept: 'application/json' } }),
                fetch('/api/debt-analysis/distributions?force=1', { headers: { Accept: 'application/json' } }),
            ]);
        } catch {
            // best-effort invalidation
        }
        queryClient.invalidateQueries({ queryKey: ['dashboard'] });
        queryClient.invalidateQueries({ queryKey: ['debt-analysis'] });
        setIsRefreshing(false);
    };

    const fmtBs = (minor: number) => `Bs. ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`;
    const fmtEur = (minor: number) => `€ ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`;
    const fmtUsd = (minor: number) => `$ ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`;

    if (!canView) return null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <Tabs defaultValue="panorama" className="w-full">
                    {/* ── HEADER ── */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <TabsList>
                            <TabsTrigger value="panorama">Panorama</TabsTrigger>
                            {canFinance && <TabsTrigger value="finanzas">Finanzas</TabsTrigger>}
                            <TabsTrigger value="operaciones">Operaciones</TabsTrigger>
                        </TabsList>
                        <div className="flex items-center gap-3">
                            {latestTimestamp && <span className="text-muted-foreground text-xs">Actualizado {timeAgo(latestTimestamp)}</span>}
                            <Button variant="outline" size="sm" onClick={refreshAll} disabled={isRefreshing}>
                                <RefreshCw className={`mr-1.5 h-3.5 w-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
                                Refrescar
                            </Button>
                        </div>
                    </div>

                    {/* ══════════════════════════════════════════════════ */}
                    {/* TAB 1: PANORAMA — Estado del negocio en 3 segundos */}
                    {/* ══════════════════════════════════════════════════ */}
                    <TabsContent value="panorama" className="space-y-6">
                        {canCards && (
                            <section className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    {canFinance && (
                                        <KpiStatCard
                                            title="Deuda total"
                                            icon={Banknote}
                                            isLoading={debtLoading}
                                            value={fmtBs(debtMetrics?.total_debt_bs_minor ?? 0)}
                                            subtitle={
                                                debtMetrics
                                                    ? `${fmtEur(debtMetrics.total_debt_eur_minor)} · ${fmtUsd(debtMetrics.total_debt_usd_minor ?? 0)}`
                                                    : undefined
                                            }
                                            deltaLabel={debtMetrics ? `${debtMetrics.morosidad_rate}% morosidad` : undefined}
                                            deltaVariant={debtMetrics && debtMetrics.morosidad_rate > 50 ? 'down' : 'neutral'}
                                            tintVariant="destructive"
                                            sparkColor="var(--chart-debt)"
                                            href="/dashboard/debt-analysis"
                                        />
                                    )}
                                    {canFinance && (
                                        <KpiStatCard
                                            title="Proyección mensual"
                                            icon={TrendingUp}
                                            isLoading={revenueLoading}
                                            value={revenueProj ? fmtBs(revenueProj.total_bs_minor) : '0'}
                                            subtitle={
                                                revenueProj ? `${revenueProj.period_label} · ${fmtEur(revenueProj.total_eur_minor)}` : undefined
                                            }
                                            tintVariant="success"
                                            sparkColor="var(--chart-revenue)"
                                            series={sparklineData?.items}
                                        />
                                    )}
                                    <KpiStatCard
                                        title="Contratos vigentes"
                                        icon={FileText}
                                        isLoading={kpisLoading}
                                        value={kpis?.contracts.vigentes}
                                        subtitle="En curso hoy"
                                        tintVariant="info"
                                        sparkColor="var(--chart-info)"
                                        href={
                                            can['contracts.view']
                                                ? '/catalogs/contract?filters%5Bcontract_status_id%5D=2&page=1&per_page=15'
                                                : undefined
                                        }
                                    />
                                    <KpiStatCard
                                        title="Cesionarios activos"
                                        icon={Users}
                                        isLoading={kpisLoading}
                                        value={kpis?.concessionaires.active}
                                        subtitle={`${debtMetrics?.solvent_count ?? 0} solventes`}
                                        tintVariant="neutral"
                                        href="/catalogs/concessionaire?filters%5Bhas_active_contract%5D=1&page=1&per_page=15"
                                    />
                                </div>
                            </section>
                        )}

                        {alertsData && alertsData.alerts.length > 0 && <AlertBanner alerts={alertsData.alerts} />}

                        {canFinance && canChartsPayments && (
                            <section>
                                <PaymentTrendLine />
                            </section>
                        )}

                        {canFinance && (canChartsDebt || canChartsPayments) && (
                            <section className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                {canChartsDebt && <DebtByLocalTypeDonut />}
                                {canChartsPayments && <ChargesByStatusDonut />}
                            </section>
                        )}
                    </TabsContent>

                    {/* ══════════════════════════════════════════════════ */}
                    {/* TAB 2: FINANZAS — Deep dive financiero */}
                    {/* ══════════════════════════════════════════════════ */}
                    {canFinance && (
                        <TabsContent value="finanzas" className="space-y-6">
                            {canCards && (
                                <section className="space-y-4">
                                    <h2 className="text-foreground border-border/50 border-b pb-2 text-lg font-semibold tracking-tight">
                                        Métricas de riesgo
                                    </h2>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                        <KpiStatCard
                                            title="Deuda total"
                                            icon={Banknote}
                                            isLoading={debtLoading}
                                            value={fmtBs(debtMetrics?.total_debt_bs_minor ?? 0)}
                                            subtitle={debtMetrics ? fmtEur(debtMetrics.total_debt_eur_minor) : undefined}
                                            tintVariant="destructive"
                                            sparkColor="var(--chart-debt)"
                                            href="/dashboard/debt-analysis"
                                        />
                                        <KpiStatCard
                                            title="Deuda vencida"
                                            icon={Banknote}
                                            isLoading={debtLoading}
                                            value={fmtBs(debtMetrics?.total_overdue_bs_minor ?? 0)}
                                            subtitle={
                                                debtMetrics
                                                    ? `${fmtEur(debtMetrics.total_overdue_eur_minor)} · ${debtMetrics.delinquent_count} morosos`
                                                    : undefined
                                            }
                                            tintVariant="destructive"
                                            sparkColor="var(--chart-debt)"
                                        />
                                        <KpiStatCard
                                            title="Cesionarios morosos"
                                            icon={Users}
                                            isLoading={debtLoading}
                                            value={debtMetrics?.delinquent_count}
                                            deltaLabel={debtMetrics ? `${debtMetrics.morosidad_rate}%` : undefined}
                                            deltaVariant={debtMetrics && debtMetrics.morosidad_rate > 50 ? 'down' : 'neutral'}
                                            tintVariant="destructive"
                                            href="/admin/economic-profile"
                                        />
                                        <KpiStatCard
                                            title="Promedio días atraso"
                                            icon={Clock}
                                            isLoading={debtLoading}
                                            value={debtMetrics?.average_days_overdue}
                                            subtitle="Días vencidos promedio"
                                            tintVariant="warning"
                                            sparkColor="var(--chart-warning)"
                                        />
                                    </div>
                                </section>
                            )}

                            {canChartsDebt && (
                                <section className="space-y-4">
                                    <h2 className="text-foreground border-border/50 border-b pb-2 text-lg font-semibold tracking-tight">
                                        Top morosos
                                    </h2>
                                    <DebtRankingBar />
                                </section>
                            )}

                            {canChartsPayments && (
                                <section className="space-y-4">
                                    <h2 className="text-foreground border-border/50 border-b pb-2 text-lg font-semibold tracking-tight">
                                        Proyección de ingresos
                                    </h2>
                                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <ProjectedRevenueByLocalTypeDonut />
                                        <TopRevenueLocalsBar />
                                    </div>
                                </section>
                            )}

                            {canChartsPayments && (
                                <section className="space-y-4">
                                    <h2 className="text-foreground border-border/50 border-b pb-2 text-lg font-semibold tracking-tight">
                                        Recaudación
                                    </h2>
                                    <PaymentRevenueBreakdown />
                                    <PaymentTrendLine />
                                </section>
                            )}

                            {(canChartsDebt || canChartsPayments) && (
                                <section className="space-y-4">
                                    <h2 className="text-foreground border-border/50 border-b pb-2 text-lg font-semibold tracking-tight">
                                        Distribución de cargos y deuda
                                    </h2>
                                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                                        {canChartsPayments && <ChargesByKindDonut />}
                                        {canChartsPayments && <ChargesByStatusDonut />}
                                        {canChartsDebt && <DebtByLocalTypeDonut />}
                                    </div>
                                </section>
                            )}
                        </TabsContent>
                    )}

                    {/* ══════════════════════════════════════════════════ */}
                    {/* TAB 3: OPERACIONES — Contratos + Locales + Cesionarios */}
                    {/* ══════════════════════════════════════════════════ */}
                    <TabsContent value="operaciones" className="space-y-6">
                        {canCards && (
                            <section className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <KpiStatCard
                                        title="Contratos vigentes"
                                        icon={FileText}
                                        isLoading={kpisLoading}
                                        value={kpis?.contracts.vigentes}
                                        subtitle="En curso hoy"
                                        tintVariant="info"
                                        sparkColor="var(--chart-info)"
                                        href={
                                            can['contracts.view']
                                                ? '/catalogs/contract?filters%5Bcontract_status_id%5D=2&page=1&per_page=15'
                                                : undefined
                                        }
                                    />
                                    <KpiStatCard
                                        title="Locales disponibles"
                                        icon={Building2}
                                        isLoading={kpisLoading}
                                        value={kpis?.locals.available}
                                        subtitle="Sin contrato vigente"
                                        tintVariant="neutral"
                                        href="/catalogs/local"
                                    />
                                    <KpiStatCard
                                        title="Cesionarios activos"
                                        icon={Users}
                                        isLoading={kpisLoading}
                                        value={kpis?.concessionaires.active}
                                        tintVariant="info"
                                        href="/catalogs/concessionaire?filters%5Bhas_active_contract%5D=1&page=1&per_page=15"
                                    />
                                    {canFinance && (
                                        <KpiStatCard
                                            title="Cesionarios solventes"
                                            icon={Shield}
                                            isLoading={debtLoading}
                                            value={debtMetrics?.solvent_count}
                                            subtitle="Sin deuda vencida"
                                            tintVariant="success"
                                            sparkColor="var(--chart-revenue)"
                                            href="/admin/economic-profile"
                                        />
                                    )}
                                </div>
                            </section>
                        )}

                        {canChartsContracts && (
                            <section className="space-y-4">
                                <h2 className="text-foreground border-border/50 border-b pb-2 text-lg font-semibold tracking-tight">
                                    Gestión de contratos
                                </h2>
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <ContractsByStatusDonutEnhanced />
                                    <ContractsByTypeDonut />
                                </div>
                                <ContractsTimelineTable />
                            </section>
                        )}

                        {canChartsLocals && (
                            <section className="space-y-4">
                                <h2 className="text-foreground border-border/50 border-b pb-2 text-lg font-semibold tracking-tight">
                                    Infraestructura
                                </h2>
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <LocalsAvailableDonut />
                                    <LocalsByLocationBar />
                                </div>
                            </section>
                        )}

                        {canChartsConcessionaires && (
                            <section className="space-y-4">
                                <h2 className="text-foreground border-border/50 border-b pb-2 text-lg font-semibold tracking-tight">
                                    Top cesionarios
                                </h2>
                                <ConcessionairesRankingBar />
                            </section>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
