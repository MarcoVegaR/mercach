import { KpiCard } from '@/components/analytics/KpiCard';
import LocalsAvailableDonut from '@/components/analytics/LocalsAvailableDonut';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';

type AuthCan = Record<string, boolean>;

type DashboardKpis = {
    users: { total: number };
    locals: { available: number };
    concessionaires: { active: number };
    contracts: { vigentes: number };
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

    const { data, isLoading, isError, refetch } = useQuery<DashboardKpis>({
        queryKey: ['dashboard', 'kpis'],
        staleTime: 60_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/kpis', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load KPIs');
            return (await res.json()) as DashboardKpis;
        },
        enabled: canCards && canView,
    });

    if (!canView) return null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {canCards && (
                    <section className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h2 className="text-muted-foreground text-base font-medium">Métricas Principales</h2>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    queryClient.invalidateQueries({ queryKey: ['dashboard', 'kpis'] });
                                    queryClient.invalidateQueries({ queryKey: ['dashboard', 'distributions', 'local_type', 'available'] });
                                    queryClient.invalidateQueries({ queryKey: ['dashboard', 'locals', 'by-type'] });
                                    queryClient.invalidateQueries({ queryKey: ['dashboard', 'locals', 'available-by-type'] });
                                }}
                            >
                                Refrescar
                            </Button>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <KpiCard
                                title="Cantidad de usuarios"
                                description="Usuarios totales"
                                isLoading={isLoading}
                                value={data?.users.total}
                                href={can['users.view'] ? '/users' : undefined}
                            />
                            <KpiCard
                                title="Locales disponibles"
                                description="Sin contrato vigente"
                                isLoading={isLoading}
                                value={data?.locals.available}
                                href={'/catalogs/local?filters%5Blocal_status_id%5D=2&page=1&per_page=15'}
                            />
                            <KpiCard
                                title="Concesionarios activos"
                                description={'Con ≥1 contrato vigente'}
                                isLoading={isLoading}
                                value={data?.concessionaires.active}
                                href={'/catalogs/concessionaire?filters%5Bhas_active_contract%5D=1&page=1&per_page=15'}
                            />
                            <KpiCard
                                title="Contratos vigentes"
                                description="En curso hoy"
                                isLoading={isLoading}
                                value={data?.contracts.vigentes}
                                href={'/catalogs/contract?filters%5Bcontract_status_id%5D=2&page=1&per_page=15'}
                            />
                        </div>

                        {isError && (
                            <div className="flex items-center justify-between rounded-lg border p-4">
                                <p className="text-muted-foreground text-sm">No se pudieron cargar las métricas.</p>
                                <Button size="sm" onClick={() => refetch()}>
                                    Reintentar
                                </Button>
                            </div>
                        )}
                    </section>
                )}

                {canCharts && (
                    <section>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <LocalsAvailableDonut />
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
