import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Link } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, FileText } from 'lucide-react';
import { useMemo } from 'react';

type MonthItem = {
    month: string;
    month_label: string;
    count: number;
    declared_outstanding_bs_minor: number;
    current_outstanding_bs_minor: number;
};

type CurrencyItem = {
    currency: string;
    count: number;
    current_outstanding_amount_minor: number;
    current_outstanding_bs_minor: number;
};

type ApiResponse = {
    current_count: number;
    current_outstanding_bs_minor: number;
    declared_count: number;
    declared_outstanding_bs_minor: number;
    restored_count: number;
    by_month: MonthItem[];
    by_currency: CurrencyItem[];
    generated_at: string;
};

function formatBs(minor: number): string {
    return `Bs. ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatMinor(minor: number): string {
    return (minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function UncollectibleChargesMetrics() {
    const { data, isLoading, isError, refetch } = useQuery<ApiResponse>({
        queryKey: ['dashboard', 'charges', 'uncollectible-metrics'],
        staleTime: 180_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/charges/uncollectible-metrics?months=12', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('Failed to load uncollectible charges metrics');
            return (await res.json()) as ApiResponse;
        },
    });

    const { monthlyItems, maxMonthlyAmount } = useMemo(() => {
        const items = (data?.by_month ?? [])
            .filter((item) => item.count > 0 || item.declared_outstanding_bs_minor > 0 || item.current_outstanding_bs_minor > 0)
            .map((item) => item);

        return {
            monthlyItems: items,
            maxMonthlyAmount: Math.max(...items.flatMap((item) => [item.declared_outstanding_bs_minor, item.current_outstanding_bs_minor]), 1),
        };
    }, [data]);

    return (
        <Card className="flex flex-col">
            <CardHeader className="flex flex-col gap-3 pb-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-rose-600" /> Incobrables
                    </CardTitle>
                    <CardDescription>Saldo separado de la deuda cobrable activa</CardDescription>
                </div>
                <Button variant="outline" size="sm" asChild>
                    <Link href="/reports/uncollectible-charges">
                        <FileText className="mr-2 h-4 w-4" /> Ver reporte
                    </Link>
                </Button>
            </CardHeader>
            <CardContent className="space-y-4">
                {isLoading && <Skeleton className="h-[240px] w-full" />}
                {isError && (
                    <div className="text-error flex h-[220px] items-center justify-center text-sm">
                        No se pudieron cargar los incobrables.{' '}
                        <button className="underline" onClick={() => refetch()}>
                            Reintentar
                        </button>
                    </div>
                )}
                {data && (
                    <>
                        <div className="grid gap-3 lg:grid-cols-3">
                            <div className="rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-900/60 dark:bg-rose-950/30">
                                <div className="text-xs font-medium text-rose-700 dark:text-rose-300">Saldo incobrable actual</div>
                                <div className="mt-1 text-lg font-bold text-rose-900 dark:text-rose-100">
                                    {formatBs(data.current_outstanding_bs_minor)}
                                </div>
                                <div className="text-muted-foreground mt-1 text-xs">{data.current_count} cargo(s) actuales</div>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950/40">
                                <div className="text-muted-foreground text-xs font-medium">Declarado histórico</div>
                                <div className="mt-1 text-lg font-bold">{formatBs(data.declared_outstanding_bs_minor)}</div>
                                <div className="text-muted-foreground mt-1 text-xs">{data.declared_count} evento(s)</div>
                            </div>
                            <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                                <div className="text-xs font-medium text-emerald-700 dark:text-emerald-300">Restaurados</div>
                                <div className="mt-1 text-lg font-bold text-emerald-900 dark:text-emerald-100">{data.restored_count}</div>
                                <div className="text-muted-foreground mt-1 text-xs">Vuelven a deuda cobrable si tienen saldo</div>
                            </div>
                        </div>

                        <div className="rounded-lg border bg-white p-4 dark:bg-slate-950/40">
                            <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <div className="text-sm font-semibold">Evolución mensual</div>
                                    <div className="text-muted-foreground text-xs">
                                        Cada barra compara lo declarado contra lo que sigue separado como incobrable.
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-3 text-xs">
                                    <span className="inline-flex items-center gap-1">
                                        <span className="h-2 w-2 rounded-full bg-rose-500" /> Actual incobrable
                                    </span>
                                    <span className="inline-flex items-center gap-1">
                                        <span className="h-2 w-2 rounded-full bg-sky-200 ring-1 ring-sky-300" /> Declarado histórico
                                    </span>
                                </div>
                            </div>

                            <div className="mt-4 space-y-3">
                                {monthlyItems.length === 0 ? (
                                    <div className="text-muted-foreground rounded-md border border-dashed py-8 text-center text-sm">
                                        No hay cargos incobrables en el período consultado.
                                    </div>
                                ) : (
                                    monthlyItems.map((item) => {
                                        const currentWidth = Math.max((item.current_outstanding_bs_minor / maxMonthlyAmount) * 100, 0);
                                        const declaredWidth = Math.max((item.declared_outstanding_bs_minor / maxMonthlyAmount) * 100, 0);

                                        return (
                                            <div key={item.month} className="grid gap-2 sm:grid-cols-[8rem_1fr_10rem] sm:items-center">
                                                <div className="text-sm font-medium">{item.month_label}</div>
                                                <div className="relative h-5 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                                                    <div
                                                        className="absolute inset-y-0 left-0 rounded-full bg-sky-200 dark:bg-sky-900"
                                                        style={{ width: `${declaredWidth}%` }}
                                                    />
                                                    <div
                                                        className="absolute inset-y-1 left-0 rounded-full bg-rose-500"
                                                        style={{ width: `${currentWidth}%` }}
                                                    />
                                                </div>
                                                <div className="text-muted-foreground text-xs tabular-nums sm:text-right">
                                                    <span className="text-foreground font-medium">{formatBs(item.current_outstanding_bs_minor)}</span>
                                                    <span> / {formatBs(item.declared_outstanding_bs_minor)}</span>
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </div>

                        <div className="grid gap-2 sm:grid-cols-3">
                            {data.by_currency.length === 0 ? (
                                <div className="text-muted-foreground text-sm">No hay cargos incobrables actuales.</div>
                            ) : (
                                data.by_currency.map((item) => (
                                    <div key={item.currency} className="rounded-md border px-3 py-2 text-sm">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-semibold">{item.currency}</span>
                                            <span className="text-muted-foreground text-xs">{item.count} cargo(s)</span>
                                        </div>
                                        <div className="mt-1 font-medium">{formatBs(item.current_outstanding_bs_minor)}</div>
                                        {item.currency !== 'VES' && (
                                            <div className="text-muted-foreground mt-0.5 text-xs">
                                                {item.currency} {formatMinor(item.current_outstanding_amount_minor)} original
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
