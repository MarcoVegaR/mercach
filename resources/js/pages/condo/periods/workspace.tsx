import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { CheckCircle2, RotateCcw, ShieldCheck } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';
import ExpensesTab from './tabs/ExpensesTab';
import ParticipantsTab from './tabs/ParticipantsTab';

interface PeriodItem {
    id: number;
    market_id: number;
    period: string; // YYYY-MM-DD
    status: 'DRAFT' | 'FINAL';
    expenses?: Array<any>;
    participants?: Array<any>;
    total_usd_minor?: number;
    expenses_count?: number;
    participants_count?: number;
}

interface ShowProps extends PageProps {
    item: PeriodItem;
    meta?: { loaded_relations?: string[]; loaded_counts?: string[] };
    options?: {
        expense_types?: Array<{ id: number; name: string; code?: string }>;
        locals?: Array<{ id: number; code: string }>;
    };
    flash?: { success?: string; error?: string; warning?: string; info?: string };
    auth?: { can?: Record<string, boolean> };
}

export default function CondoPeriodWorkspacePage() {
    const { item, meta: _meta, auth, flash, options } = usePage<ShowProps>().props;

    const canFinalize = !!auth?.can?.['condo_period.finalize'];
    const canReopen = !!auth?.can?.['condo_period.reopen'];
    const canUpdate = !!auth?.can?.['condo_period.update'];

    const d = new Date(item.period);
    const periodMonth = format(d, 'yyyy-MM', { locale: es });

    // Reactive KPIs
    const [kpi, setKpi] = React.useState<{ expenses_count: number; participants_count: number; total_usd_minor: number }>(() => ({
        expenses_count: item.expenses_count ?? item.expenses?.length ?? 0,
        participants_count: item.participants_count ?? item.participants?.length ?? 0,
        total_usd_minor: item.total_usd_minor ?? 0,
    }));

    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);

    const breadcrumbs = [
        { title: 'Condominio', href: '/condo' },
        { title: 'Períodos', href: '/condo/periods' },
        { title: `${periodMonth}`, href: `/condo/periods/${item.id}/show` },
    ];

    const finalize = () => {
        router.post(`/condo/periods/${item.id}/finalize`);
    };

    const reopen = () => {
        router.post(`/condo/periods/${item.id}/reopen`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Período ${periodMonth}`} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {/* Header */}
                        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-4">
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Período {periodMonth}</h1>
                                <Badge variant={item.status === 'FINAL' ? 'secondary' : 'default'} className="font-medium">
                                    {item.status}
                                </Badge>
                                <div className="text-muted-foreground text-sm">
                                    ID {item.id} · Market #{item.market_id}
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                {item.status === 'DRAFT' && canFinalize && (
                                    <Button onClick={finalize} className="flex items-center gap-2">
                                        <ShieldCheck className="h-4 w-4" /> Confirmar (FINAL)
                                    </Button>
                                )}
                                {item.status === 'FINAL' && canReopen && (
                                    <Button onClick={reopen} variant="secondary" className="flex items-center gap-2">
                                        <RotateCcw className="h-4 w-4" /> Reabrir (DRAFT)
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* KPIs */}
                        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <Card className="p-4">
                                <div className="text-muted-foreground text-sm">Gastos</div>
                                <div className="text-2xl font-bold">{kpi.expenses_count}</div>
                            </Card>
                            <Card className="p-4">
                                <div className="text-muted-foreground text-sm">Excluidos</div>
                                <div className="text-2xl font-bold">{kpi.participants_count}</div>
                            </Card>
                            <Card className="p-4">
                                <div className="text-muted-foreground text-sm">Total USD</div>
                                <div className="text-2xl font-bold">
                                    {new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'USD' }).format((kpi.total_usd_minor || 0) / 100)}
                                </div>
                            </Card>
                        </div>

                        {/* Workspace Tabs */}
                        <Tabs defaultValue="expenses" className="space-y-4">
                            <TabsList>
                                <TabsTrigger value="expenses">Gastos</TabsTrigger>
                                <TabsTrigger value="participants">Excluidos</TabsTrigger>
                            </TabsList>
                            <TabsContent value="expenses" className="space-y-4">
                                <ExpensesTab
                                    period={item}
                                    canUpdate={canUpdate}
                                    options={{ expense_types: options?.expense_types ?? [] }}
                                    onTotalsUpdate={(totals) => {
                                        if (!totals) return;
                                        setKpi((prev) => ({
                                            ...prev,
                                            expenses_count: totals.expenses_count,
                                            total_usd_minor: totals.total_usd_minor,
                                        }));
                                    }}
                                />
                            </TabsContent>
                            <TabsContent value="participants" className="space-y-4">
                                <ParticipantsTab
                                    period={item}
                                    canUpdate={canUpdate}
                                    onTotalsUpdate={(totals) => {
                                        if (!totals) return;
                                        setKpi((prev) => ({ ...prev, participants_count: totals.participants_count }));
                                    }}
                                />
                            </TabsContent>
                        </Tabs>

                        <div className="text-muted-foreground mt-8 flex items-center gap-2 text-sm">
                            <CheckCircle2 className="h-4 w-4" />
                            <span>
                                Cambios en gastos/participantes requieren estado DRAFT. Adjuntos aceptan PDF/JPG/PNG. Actualice la página para ver los
                                cambios.
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
