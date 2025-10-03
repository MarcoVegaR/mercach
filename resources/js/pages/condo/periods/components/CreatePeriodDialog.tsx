import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import React from 'react';

export type MarketOption = { id: number; code?: string | null; name?: string | null };

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    markets: MarketOption[];
}

export default function CreatePeriodDialog({ open, onOpenChange, markets }: Props) {
    const [marketId, setMarketId] = React.useState<string>('');
    const [periodMonth, setPeriodMonth] = React.useState<string>('');
    const [submitting, setSubmitting] = React.useState(false);

    const options = React.useMemo(() => markets.map((m) => ({ value: String(m.id), label: m.name ?? '' })), [markets]);

    const canSubmit = marketId !== '' && /^\d{4}-(0[1-9]|1[0-2])$/.test(periodMonth);

    const submit = async () => {
        if (!canSubmit) return;
        setSubmitting(true);
        try {
            await new Promise<void>((resolve, reject) => {
                router.post(
                    '/condo/periods/upsert',
                    { market_id: Number(marketId), period_month: periodMonth },
                    {
                        preserveScroll: true,
                        preserveState: false,
                        onSuccess: () => resolve(),
                        onError: () => reject(new Error('create_failed')),
                        onFinish: () => setSubmitting(false),
                    },
                );
            });
            onOpenChange(false);
            setMarketId('');
            setPeriodMonth('');
        } catch {
            setSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(o) => onOpenChange(o)}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nuevo período</DialogTitle>
                    <DialogDescription>Seleccione el mercado y el mes del período a crear o abrir.</DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div>
                        <label htmlFor="market_id_dialog" className="mb-1 block text-sm font-medium">
                            Mercado
                        </label>
                        <Select value={marketId || undefined} onValueChange={(v) => setMarketId(v)}>
                            <SelectTrigger id="market_id_dialog" className="w-full">
                                <SelectValue placeholder="Seleccionar mercado" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <label htmlFor="period_month" className="mb-1 block text-sm font-medium">
                            Mes
                        </label>
                        <Input
                            id="period_month"
                            type="month"
                            value={periodMonth}
                            onChange={(e) => setPeriodMonth(e.target.value)}
                            leadingIcon={CalendarDays}
                            leadingIconClassName="text-sky-600"
                            placeholder="AAAA-MM"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button type="button" onClick={submit} disabled={!canSubmit || submitting}>
                        Crear / Abrir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
