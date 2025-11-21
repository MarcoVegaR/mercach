import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { AlertCircle } from 'lucide-react';
import { useMemo } from 'react';
import { Label, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

export type StatusItem = { id: number; code: string; label: string; value: number };

type ApiResponse = {
    items: StatusItem[];
    total: number;
    generated_at: string;
};

type VigBreakdown = {
    total: number;
    signed: number;
    unsigned: number;
    generated_at: string;
};

export default function ContractsByStatusDonutEnhanced() {
    const { data, isLoading, isError, refetch } = useQuery<ApiResponse>({
        queryKey: ['dashboard', 'contracts', 'by-status'],
        staleTime: 180_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/contracts/by-status', {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load contracts by status');
            return (await res.json()) as ApiResponse;
        },
    });

    const { data: vigBreakdown } = useQuery<VigBreakdown>({
        queryKey: ['dashboard', 'contracts', 'vigentes-breakdown'],
        staleTime: 180_000,
        queryFn: async () => {
            const res = await fetch('/api/dashboard/contracts/vigentes-breakdown', {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load VIG breakdown');
            return (await res.json()) as VigBreakdown;
        },
        enabled: !!data,
    });

    const { chartData, chartConfig } = useMemo(() => {
        if (!data)
            return {
                chartData: [] as Array<{ id: number; code: string; label: string; value: number; fill: string; _key: string }>,
                chartConfig: {} as ChartConfig,
            };

        const cfg: ChartConfig = {};
        const items = data.items.map((it, i) => {
            const tokenIndex = (i % 5) + 1;
            const key = `cs${it.id}`;
            cfg[key] = { label: it.label, color: `var(--chart-${tokenIndex})` };
            return {
                id: it.id,
                code: it.code,
                label: it.label,
                value: it.value,
                fill: `var(--color-${key})`,
                _key: key,
            };
        });
        return { chartData: items, chartConfig: cfg };
    }, [data]);

    const vigItem = data?.items.find((it) => it.code === 'VIG');
    const vigentesTotal = vigBreakdown?.total ?? vigItem?.value ?? 0;
    const showBreakdown = !!vigItem && !!vigBreakdown && vigentesTotal > 0;

    return (
        <Card aria-label={`Contratos por estado, total ${data?.total ?? 0}`}>
            <CardHeader className="items-center pb-0">
                <CardTitle>Contratos por estado</CardTitle>
                <CardDescription>Total de contratos</CardDescription>
            </CardHeader>
            <CardContent className="min-h-[340px] flex-1 pb-0">
                {isLoading && <Skeleton className="mx-auto h-[250px] w-[250px] rounded-full" />}
                {isError && (
                    <div className="text-destructive text-sm">
                        No se pudo cargar el gráfico.{' '}
                        <button className="underline" onClick={() => refetch()}>
                            Reintentar
                        </button>
                    </div>
                )}
                {data && (
                    <div className="space-y-4">
                        <ChartContainer config={chartConfig} className="mx-auto aspect-square max-h-[250px]">
                            <ResponsiveContainer>
                                <PieChart>
                                    <Tooltip
                                        cursor={false}
                                        content={(props) => <ChartTooltipContent {...props} suffix="contratos" locale="es-VE" />}
                                    />
                                    <Pie
                                        data={chartData}
                                        dataKey="value"
                                        nameKey="label"
                                        innerRadius={60}
                                        strokeWidth={5}
                                        onClick={(slice) => {
                                            const id = (slice?.payload as { id?: number })?.id;
                                            if (!id) return;
                                            router.visit(route('catalogs.contract.index'), {
                                                method: 'get',
                                                data: {
                                                    filters: { contract_status_id: id },
                                                    page: 1,
                                                    per_page: 15,
                                                },
                                                preserveScroll: true,
                                            });
                                        }}
                                    >
                                        <Label
                                            // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                            content={(props: any) => {
                                                const cx = props?.viewBox?.cx as number | undefined;
                                                const cy = props?.viewBox?.cy as number | undefined;
                                                if (typeof cx !== 'number' || typeof cy !== 'number') return null;
                                                return (
                                                    <text x={cx} y={cy} textAnchor="middle" dominantBaseline="middle">
                                                        <tspan x={cx} y={cy} className="fill-foreground text-3xl font-bold">
                                                            {data.total.toLocaleString('es-VE')}
                                                        </tspan>
                                                        <tspan x={cx} y={(cy || 0) + 24} className="fill-muted-foreground">
                                                            Contratos
                                                        </tspan>
                                                    </text>
                                                );
                                            }}
                                        />
                                    </Pie>
                                </PieChart>
                            </ResponsiveContainer>
                        </ChartContainer>

                        {showBreakdown && vigBreakdown && vigItem && (
                            <div className="bg-muted/30 rounded-lg border p-3">
                                <div className="mb-2 flex items-center gap-2 text-sm font-medium">
                                    <span className="text-muted-foreground">De los {vigentesTotal.toLocaleString('es-VE')} vigentes:</span>
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <div className="bg-muted h-3 w-full overflow-hidden rounded-full">
                                            <div
                                                className="h-full bg-emerald-500 transition-all"
                                                style={{
                                                    width: `${vigBreakdown.total > 0 ? (vigBreakdown.signed / vigBreakdown.total) * 100 : 0}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                    <a
                                        className="hover:text-foreground flex w-full cursor-pointer items-center justify-between text-xs transition-colors"
                                        href={`/catalogs/contract?filters%5Bcontract_status_id%5D=${vigItem.id}&filters%5Bsigned%5D=true&page=1&per_page=15`}
                                    >
                                        <span className="flex items-center gap-1.5 font-medium text-emerald-600 dark:text-emerald-400">
                                            ✓ {vigBreakdown.signed.toLocaleString('es-VE')} firmados
                                        </span>
                                        <span className="text-muted-foreground">
                                            ({vigBreakdown.total > 0 ? Math.round((vigBreakdown.signed / vigBreakdown.total) * 100) : 0}%)
                                        </span>
                                    </a>
                                    <a
                                        className="hover:text-foreground flex w-full cursor-pointer items-center justify-between text-xs transition-colors"
                                        href={`/catalogs/contract?filters%5Bcontract_status_id%5D=${vigItem.id}&filters%5Bsigned%5D=false&page=1&per_page=15`}
                                    >
                                        <span className="flex items-center gap-1.5 font-medium text-amber-600 dark:text-amber-400">
                                            <AlertCircle className="h-3 w-3" />
                                            {vigBreakdown.unsigned.toLocaleString('es-VE')} sin firmar
                                        </span>
                                        <span className="text-muted-foreground">
                                            ({vigBreakdown.total > 0 ? Math.round((vigBreakdown.unsigned / vigBreakdown.total) * 100) : 0}%)
                                        </span>
                                    </a>
                                </div>
                            </div>
                        )}
                    </div>
                )}
                {data && data.items.length === 0 && <div className="text-muted-foreground text-sm">Sin datos disponibles.</div>}
            </CardContent>
        </Card>
    );
}
