import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useQuery } from '@tanstack/react-query';
import { RefreshCcw } from 'lucide-react';
import * as React from 'react';
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type BankRow = {
    bank_id: number | null;
    bank_name: string;
    amount_bs_minor: number;
    count: number;
};

type MethodRow = {
    code: string;
    name: string;
    amount_bs_minor: number;
    count: number;
};

type ApiResponse = {
    from: string;
    to: string;
    by_destination_bank: BankRow[];
    by_method: MethodRow[];
    generated_at: string;
};

function todayIso(): string {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
}

function monthStartIso(): string {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-01`;
}

const bankChartConfig = {
    amount: { label: 'Recaudado', color: 'var(--chart-1)' },
} satisfies ChartConfig;

const methodChartConfig = {
    amount: { label: 'Recaudado', color: 'var(--chart-2)' },
} satisfies ChartConfig;

type ChartRow = {
    label: string;
    amount: number;
    count: number;
};

function normalizeTopN(rows: ChartRow[], topN: number): ChartRow[] {
    const sorted = [...rows].sort((a, b) => (b.amount ?? 0) - (a.amount ?? 0));
    const head = sorted.slice(0, topN);
    const tail = sorted.slice(topN);
    if (tail.length === 0) return head;

    const others = tail.reduce((acc, cur) => ({ label: 'Otros', amount: acc.amount + (cur.amount ?? 0), count: acc.count + (cur.count ?? 0) }), {
        label: 'Otros',
        amount: 0,
        count: 0,
    } as ChartRow);
    if (others.amount <= 0) return head;
    return [...head, others];
}

function fmtBs(amount: number): string {
    return amount.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function barColor(idx: number): string {
    const palette = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
    return palette[idx % palette.length];
}

function HorizontalAmountBar({ data, config }: { data: ChartRow[]; config: ChartConfig }) {
    const height = Math.max(220, Math.min(420, 52 + data.length * 34));

    return (
        <ChartContainer config={config} className="w-full">
            <ResponsiveContainer width="100%" height={height}>
                <BarChart data={data} layout="vertical" margin={{ left: 8, right: 16, top: 8, bottom: 8 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                    <YAxis type="category" dataKey="label" width={150} tick={{ fontSize: 12 }} tickLine={false} axisLine={false} />
                    <XAxis
                        type="number"
                        tick={{ fontSize: 12 }}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={(v) => `Bs. ${Number(v).toLocaleString('es-VE', { maximumFractionDigits: 0 })}`}
                    />
                    <Tooltip
                        cursor={{ fill: 'rgba(0,0,0,0.03)' }}
                        content={({ active, payload }) => {
                            if (!active || !payload?.length) return null;
                            const p = payload[0]?.payload as ChartRow;
                            return (
                                <div className="bg-background border-border rounded-lg border px-3 py-2 shadow-md">
                                    <div className="text-muted-foreground text-xs">{p.label}</div>
                                    <div className="text-sm font-semibold">Bs. {fmtBs(p.amount)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {p.count} pago{p.count !== 1 ? 's' : ''}
                                    </div>
                                </div>
                            );
                        }}
                    />
                    <Bar dataKey="amount" name="Recaudado" radius={[0, 6, 6, 0]}>
                        {data.map((_, idx) => (
                            <Cell key={idx} fill={barColor(idx)} />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </ChartContainer>
    );
}

export default function PaymentRevenueBreakdown() {
    const [draftFrom, setDraftFrom] = React.useState<string>(() => monthStartIso());
    const [draftTo, setDraftTo] = React.useState<string>(() => todayIso());
    const [from, setFrom] = React.useState<string>(() => monthStartIso());
    const [to, setTo] = React.useState<string>(() => todayIso());

    const canApply = draftFrom !== from || draftTo !== to;

    const { data, isLoading, isError, refetch, isFetching } = useQuery<ApiResponse>({
        queryKey: ['dashboard', 'payments', 'revenue-breakdown', { from, to }],
        staleTime: 180_000,
        queryFn: async () => {
            const params = new URLSearchParams();
            if (from) params.set('from', from);
            if (to) params.set('to', to);
            const res = await fetch(`/api/dashboard/payment/revenue-breakdown?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Failed to load payment revenue breakdown');
            return (await res.json()) as ApiResponse;
        },
    });

    const topN = 8;

    const byDestination = React.useMemo<ChartRow[]>(() => {
        const rows = data?.by_destination_bank ?? [];
        const mapped = rows.map((r) => ({
            label: r.bank_name,
            amount: (r.amount_bs_minor ?? 0) / 100,
            count: r.count ?? 0,
        }));
        return normalizeTopN(mapped, topN);
    }, [data]);

    const byMethod = React.useMemo<ChartRow[]>(() => {
        const rows = data?.by_method ?? [];
        const mapped = rows.map((r) => ({
            label: r.name || r.code,
            amount: (r.amount_bs_minor ?? 0) / 100,
            count: r.count ?? 0,
        }));
        return normalizeTopN(mapped, topN);
    }, [data]);

    const total = React.useMemo(() => {
        if (!data) return 0;
        return (data.by_method ?? []).reduce((acc, cur) => acc + (cur.amount_bs_minor ?? 0), 0) / 100;
    }, [data]);

    const onApply = React.useCallback(() => {
        setFrom(draftFrom);
        setTo(draftTo);
    }, [draftFrom, draftTo]);

    return (
        <Card className="flex flex-col">
            <CardHeader className="pb-0">
                <CardTitle>Recaudación por banco y método</CardTitle>
                <CardDescription>Rango por fecha de pago (paid_on)</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="bg-card/50 flex flex-col gap-3 rounded-lg border p-3 md:flex-row md:items-end md:justify-between">
                    <div className="flex flex-col gap-3 md:flex-row md:items-end">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <div className="text-muted-foreground text-xs">Desde</div>
                                <Input type="date" value={draftFrom} onChange={(e) => setDraftFrom(e.target.value)} />
                            </div>
                            <div className="space-y-1">
                                <div className="text-muted-foreground text-xs">Hasta</div>
                                <Input type="date" value={draftTo} onChange={(e) => setDraftTo(e.target.value)} />
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button size="sm" disabled={!canApply} onClick={onApply}>
                                Aplicar
                            </Button>
                            <Button size="sm" variant="outline" disabled={isFetching} onClick={() => refetch()}>
                                <RefreshCcw className={isFetching ? 'mr-2 h-4 w-4 animate-spin' : 'mr-2 h-4 w-4'} />
                                Actualizar
                            </Button>
                        </div>
                    </div>

                    <div className="text-right">
                        <div className="text-muted-foreground text-xs">Total período</div>
                        <div className="text-xl font-bold">Bs. {fmtBs(total)}</div>
                        <div className="text-muted-foreground text-xs">{data ? `${data.from} → ${data.to}` : `${from} → ${to}`}</div>
                    </div>
                </div>

                {isLoading && <Skeleton className="h-[320px] w-full" />}

                {isError && (
                    <div className="text-error text-sm">
                        No se pudo cargar el breakdown.{' '}
                        <button className="underline" onClick={() => refetch()}>
                            Reintentar
                        </button>
                    </div>
                )}

                {data && !isLoading && (
                    <Tabs defaultValue="destino" className="w-full">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <TabsList>
                                <TabsTrigger value="destino">Banco destino</TabsTrigger>
                                <TabsTrigger value="metodo">Método</TabsTrigger>
                            </TabsList>
                            <div className="text-muted-foreground text-xs">Top {topN} + Otros</div>
                        </div>

                        <TabsContent value="destino" className="mt-3">
                            <HorizontalAmountBar data={byDestination} config={bankChartConfig} />
                        </TabsContent>

                        <TabsContent value="metodo" className="mt-3">
                            <HorizontalAmountBar data={byMethod} config={methodChartConfig} />
                        </TabsContent>
                    </Tabs>
                )}
            </CardContent>
        </Card>
    );
}
