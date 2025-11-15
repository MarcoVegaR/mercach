import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowDownUp, Calendar, CalendarCheck, RefreshCw, Timer } from 'lucide-react';
import * as React from 'react';

// Types from API
export type TimelineItem = {
    id: number;
    code: string;
    type_code?: 'CONTR' | 'CONV' | string;
    start_date: string;
    end_date: string | null;
    duration_total_days: number;
    elapsed_days: number;
    remaining_days: number | null;
    concessionaire_names: string;
};

export type TimelineResponse = {
    sort_by: 'start_date' | 'end_date';
    order: 'asc' | 'desc';
    items: TimelineItem[];
    generated_at: string;
};

type SortBy = 'start_date' | 'end_date';
type Order = 'asc' | 'desc';

function formatDate(dateStr: string | null): string {
    if (!dateStr) return 'Indefinido';
    const [year, month, day] = dateStr.split('-');
    return `${day}/${month}/${year}`;
}

function RemainingBadge({ remaining }: { remaining: number | null }) {
    const color =
        remaining === null
            ? 'bg-muted text-foreground'
            : remaining <= 30
              ? 'bg-destructive/15 text-destructive'
              : remaining <= 90
                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-300'
                : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300';

    const label = remaining === null ? 'Indefinido' : `${remaining.toLocaleString('es-VE')} días`;

    return (
        <span className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium ${color}`}>
            <Timer className="h-3 w-3" />
            {label}
        </span>
    );
}

export function ContractsTimelineTable() {
    const [activeSortBy, setActiveSortBy] = React.useState<SortBy>('start_date');
    const [order, setOrder] = React.useState<Order>('asc');
    const [range, setRange] = React.useState<'all' | '30' | '90'>('all');
    const [activeType, setActiveType] = React.useState<'CONTR' | 'CONV'>('CONTR');
    const limit = 20;

    const queryClient = useQueryClient();

    const { data, isLoading, isError, refetch } = useQuery<TimelineResponse>({
        queryKey: ['dashboard', 'contracts-timeline', { sort_by: activeSortBy, order, limit, type: activeType }],
        staleTime: 120_000,
        queryFn: async () => {
            const params = new URLSearchParams({ sort_by: activeSortBy, order, limit: String(limit), type: activeType });
            const res = await fetch(`/api/dashboard/contracts/timeline?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load timeline');
            return (await res.json()) as TimelineResponse;
        },
    });

    const items = React.useMemo(() => data?.items ?? [], [data?.items]);

    const isEndDate = activeSortBy === 'end_date';

    const filteredItems = React.useMemo(() => {
        if (!items.length) return [] as TimelineItem[];
        if (!isEndDate || range === 'all') return items;
        const maxDays = range === '30' ? 30 : 90;
        // Exclude indefinite (remaining_days === null) and keep only remaining <= threshold
        return items.filter((it) => it.remaining_days !== null && it.remaining_days <= maxDays);
    }, [items, range, isEndDate]);

    const countFor = React.useCallback(
        (key: SortBy) => {
            if (key === 'end_date' && range !== 'all') return filteredItems.length;
            return items.length;
        },
        [filteredItems.length, items.length, range],
    );

    const handleRefresh = React.useCallback(() => {
        queryClient.invalidateQueries({ queryKey: ['dashboard', 'contracts-timeline'] });
        refetch();
    }, [queryClient, refetch]);

    const toggleOrder = React.useCallback(() => {
        setOrder((prev) => (prev === 'asc' ? 'desc' : 'asc'));
    }, []);

    const isConvenioView = activeType === 'CONV';
    const title = isConvenioView ? 'Convenios vigentes – Lista' : 'Contratos vigentes – Lista';
    const description =
        activeSortBy === 'start_date'
            ? `Ordenado por Inicio (${order === 'asc' ? 'más viejo → más nuevo' : 'más nuevo → más viejo'})`
            : `Ordenado por Fin (${order === 'asc' ? 'más próximo → más lejano' : 'más lejano → más próximo'})`;

    if (isLoading) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="space-y-2">
                        {[...Array(6)].map((_, i) => (
                            <Skeleton key={i} className="h-10 w-full" />
                        ))}
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (isError) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="text-error flex min-h-[120px] items-center justify-between gap-3 text-sm">
                        <span>No se pudo cargar la lista.</span>
                        <Button variant="outline" size="sm" onClick={handleRefresh}>
                            Reintentar
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!items.length) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="text-muted-foreground flex min-h-[120px] items-center justify-center text-sm">Sin contratos vigentes.</div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="py-0">
            <CardHeader className="flex flex-col items-stretch border-b !p-0 sm:flex-row">
                <div className="flex flex-1 flex-col justify-center gap-1 px-6 pt-5 pb-3 sm:py-6">
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>
                        {description}
                        {isEndDate && range !== 'all' ? ` · Próximos ≤ ${range} días (excluye indefinidos)` : ''}
                    </CardDescription>
                </div>
                <div className="flex">
                    <div className="flex items-center gap-1 border-t px-4 py-2 sm:border-t-0 sm:border-l">
                        <Button variant={activeType === 'CONTR' ? 'secondary' : 'outline'} size="sm" onClick={() => setActiveType('CONTR')}>
                            Contratos
                        </Button>
                        <Button variant={activeType === 'CONV' ? 'secondary' : 'outline'} size="sm" onClick={() => setActiveType('CONV')}>
                            Convenios
                        </Button>
                    </div>
                    {(['start_date', 'end_date'] as const).map((key) => {
                        const label = key === 'start_date' ? 'Fecha Inicio' : 'Fecha Fin';
                        const Icon = key === 'start_date' ? Calendar : CalendarCheck;
                        return (
                            <button
                                key={key}
                                data-active={activeSortBy === key}
                                className="data-[active=true]:bg-muted/50 relative z-30 flex flex-1 flex-col justify-center gap-1 border-t px-6 py-4 text-left even:border-l sm:border-t-0 sm:border-l sm:px-8 sm:py-6"
                                onClick={() => setActiveSortBy(key)}
                            >
                                <span className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                    <Icon className="h-3 w-3" /> {label}
                                </span>
                                <span className="text-lg leading-none font-bold sm:text-3xl">
                                    {activeSortBy === key ? countFor(key).toLocaleString('es-VE') : '—'}
                                </span>
                            </button>
                        );
                    })}
                </div>
                <div className="flex items-center gap-2 border-t px-4 py-2 sm:border-t-0 sm:border-l">
                    <Button variant="ghost" size="icon" onClick={toggleOrder} title={order === 'asc' ? 'Invertir orden' : 'Ver ascendente'}>
                        <ArrowDownUp className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={handleRefresh} title="Refrescar datos">
                        <RefreshCw className="h-4 w-4" />
                    </Button>
                    <div className="ml-2 hidden gap-1 sm:flex">
                        <Button
                            variant={range === 'all' ? 'secondary' : 'outline'}
                            size="sm"
                            onClick={() => setRange('all')}
                            title="Ver todos"
                            disabled={!isEndDate || isConvenioView}
                        >
                            Todos
                        </Button>
                        <Button
                            variant={range === '30' ? 'secondary' : 'outline'}
                            size="sm"
                            onClick={() => setRange('30')}
                            title="Próximos ≤ 30 días (solo en Fecha Fin)"
                            disabled={!isEndDate || isConvenioView}
                        >
                            ≤ 30 días
                        </Button>
                        <Button
                            variant={range === '90' ? 'secondary' : 'outline'}
                            size="sm"
                            onClick={() => setRange('90')}
                            title="Próximos ≤ 90 días (solo en Fecha Fin)"
                            disabled={!isEndDate || isConvenioView}
                        >
                            ≤ 90 días
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse text-left text-xs sm:text-sm">
                        <thead className="bg-muted/50 sticky top-0 z-10">
                            <tr className="text-muted-foreground">
                                <th className="px-2 py-2 font-medium sm:px-3">Código</th>
                                <th className="px-2 py-2 font-medium sm:px-3">Cesionario(s)</th>
                                <th className="px-2 py-2 font-medium sm:px-3">Inicio</th>
                                {!isConvenioView && <th className="px-2 py-2 font-medium sm:px-3">Fin</th>}
                                {!isConvenioView && <th className="hidden px-2 py-2 sm:table-cell sm:px-3">Duración</th>}
                                {!isConvenioView && <th className="hidden px-2 py-2 sm:table-cell sm:px-3">Transcurridos</th>}
                                {!isConvenioView && <th className="px-2 py-2 font-medium sm:px-3">Progreso</th>}
                                {!isConvenioView && <th className="px-2 py-2 font-medium sm:px-3">Restantes</th>}
                                {isConvenioView && <th className="px-2 py-2 font-medium sm:px-3">Estado</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {filteredItems.map((item) => {
                                const total = Math.max(0, item.duration_total_days || 0);
                                const elapsed = Math.max(0, item.elapsed_days || 0);
                                const pct = total > 0 ? Math.min(100, Math.round((elapsed / total) * 100)) : 0;
                                const showProgress = item.remaining_days !== null && total > 0;
                                return (
                                    <tr
                                        key={item.id}
                                        className="hover:bg-muted/30 cursor-pointer border-b"
                                        onClick={() =>
                                            router.visit(`/catalogs/contract/${item.id}`, {
                                                preserveScroll: false,
                                            })
                                        }
                                    >
                                        <td className="text-foreground px-2 py-2 font-medium sm:px-3">{item.code}</td>
                                        <td className="text-foreground/90 px-2 py-2 sm:px-3">
                                            <span className="block max-w-[160px] truncate sm:max-w-none">{item.concessionaire_names}</span>
                                        </td>
                                        <td className="px-2 py-2 whitespace-nowrap sm:px-3">{formatDate(item.start_date)}</td>
                                        {!isConvenioView && <td className="px-2 py-2 whitespace-nowrap sm:px-3">{formatDate(item.end_date)}</td>}
                                        {!isConvenioView && (
                                            <td className="hidden px-2 py-2 sm:table-cell sm:px-3">
                                                {item.duration_total_days.toLocaleString('es-VE')} días
                                            </td>
                                        )}
                                        {!isConvenioView && (
                                            <td className="hidden px-2 py-2 sm:table-cell sm:px-3">
                                                {item.elapsed_days.toLocaleString('es-VE')} días
                                            </td>
                                        )}
                                        {!isConvenioView && (
                                            <td className="px-2 py-2 sm:px-3">
                                                {showProgress ? (
                                                    <div className="bg-muted relative h-2 w-28 overflow-hidden rounded sm:w-40">
                                                        <div
                                                            className="bg-primary absolute top-0 left-0 h-full"
                                                            style={{ width: `${pct}%` }}
                                                            aria-label="progreso"
                                                            role="progressbar"
                                                            aria-valuemin={0}
                                                            aria-valuemax={100}
                                                            aria-valuenow={pct}
                                                        />
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">Indefinido</span>
                                                )}
                                            </td>
                                        )}
                                        {!isConvenioView && (
                                            <td className="px-2 py-2 sm:px-3">
                                                <RemainingBadge remaining={item.remaining_days} />
                                            </td>
                                        )}
                                        {isConvenioView && <td className="px-2 py-2 sm:px-3">Indefinido</td>}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    {filteredItems.length === 0 && (
                        <div className="text-muted-foreground border-t px-3 py-4 text-sm">Sin contratos en este rango.</div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
