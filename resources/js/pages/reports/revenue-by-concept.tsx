import { IndexHeaderHero } from '@/components/index/IndexHeaderHero';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, BarChart3, Building2, CalendarDays, Check, Download, FileText, House, RotateCcw, Shield, Users, Wrench } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Props {
    filters: { from: string; to: string; concepts: string[] };
    conceptOptions: { value: string; label: string }[];
    datePresets: { value: string; label: string; from: string; to: string }[];
}

const conceptStyles = {
    RENT_EUR_M2: { icon: House, color: 'bg-violet-50 text-violet-600 dark:bg-violet-950 dark:text-violet-300' },
    CONDO_USD: { icon: Users, color: 'bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300' },
    RENT_EUR_FIXED: { icon: Building2, color: 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300' },
    ADJ: { icon: Wrench, color: 'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300' },
    CESION_DERECHOS: { icon: FileText, color: 'bg-rose-50 text-rose-600 dark:bg-rose-950 dark:text-rose-300' },
    FINE: { icon: Shield, color: 'bg-violet-50 text-violet-600 dark:bg-violet-950 dark:text-violet-300' },
};

export default function RevenueByConcept({ filters, conceptOptions, datePresets }: Props) {
    const allConcepts = conceptOptions.map((option) => option.value);
    const month = datePresets.find((preset) => preset.value === 'month')!;
    const initialPeriod = [month, ...datePresets].find((preset) => preset.from === filters.from && preset.to === filters.to)?.value ?? 'custom';
    const [period, setPeriod] = useState(initialPeriod);
    const { data, setData } = useForm(filters);
    const allSelected = data.concepts.length === allConcepts.length;

    const selectPeriod = (value: string) => {
        setPeriod(value);
        const preset = datePresets.find((option) => option.value === value);
        if (preset) {
            setData({ ...data, from: preset.from, to: preset.to });
        }
    };

    const reset = () => {
        setPeriod('month');
        setData({ from: month.from, to: month.to, concepts: [] });
    };

    const generatePdf = (event: FormEvent) => {
        event.preventDefault();
        if (!data.concepts.length) {
            return;
        }
        const params = new URLSearchParams({ from: data.from, to: data.to });
        data.concepts.forEach((concept) => params.append('concepts[]', concept));
        window.open(`${route('reports.revenue-by-concept.export')}?${params.toString()}`, '_blank', 'noopener,noreferrer');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Reportes', href: '' },
                { title: 'Recaudación por concepto', href: route('reports.revenue-by-concept') },
            ]}
        >
            <Head title="Recaudación por concepto" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
                <IndexHeaderHero
                    icon={BarChart3}
                    title="Recaudación por concepto"
                    description="Configure los filtros y genere el reporte de recaudación en PDF."
                />
                <Card className="rounded-2xl shadow-sm">
                    <CardContent className="p-5 sm:p-8">
                        <form onSubmit={generatePdf} className="flex flex-col gap-7">
                            <section aria-labelledby="period-title" className="flex flex-col gap-5">
                                <div className="flex items-start gap-3">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-green-50 font-semibold text-green-800 dark:bg-green-950 dark:text-green-300">
                                        1
                                    </span>
                                    <div className="flex flex-col gap-1">
                                        <h2 id="period-title" className="font-semibold">
                                            Período del pago
                                        </h2>
                                        <p className="text-muted-foreground text-sm">
                                            Se consideran los pagos registrados dentro de las fechas seleccionadas.
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-col gap-5 sm:pl-11">
                                    <div role="group" aria-label="Seleccionar período" className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                        {[...datePresets, { value: 'custom', label: 'Personalizado' }].map((preset) => (
                                            <Button
                                                key={preset.value}
                                                type="button"
                                                variant="outline"
                                                aria-pressed={period === preset.value}
                                                onClick={() => selectPeriod(preset.value)}
                                                className={cn(
                                                    'h-11 gap-2',
                                                    period === preset.value &&
                                                        'border-green-700 bg-green-50 text-green-800 hover:bg-green-100 dark:border-green-700 dark:bg-green-950 dark:text-green-200 dark:hover:bg-green-900',
                                                )}
                                            >
                                                <CalendarDays className="size-4" />
                                                {preset.label}
                                            </Button>
                                        ))}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="from">Desde</Label>
                                            <Input
                                                id="from"
                                                type="date"
                                                required
                                                disabled={period !== 'custom'}
                                                value={data.from}
                                                onChange={(event) => setData('from', event.target.value)}
                                                className="h-11 disabled:opacity-70"
                                            />
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="to">Hasta</Label>
                                            <Input
                                                id="to"
                                                type="date"
                                                required
                                                min={data.from}
                                                disabled={period !== 'custom'}
                                                value={data.to}
                                                onChange={(event) => setData('to', event.target.value)}
                                                className="h-11 disabled:opacity-70"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section aria-labelledby="concepts-title" className="flex flex-col gap-5 border-t pt-6">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                            2
                                        </span>
                                        <div className="flex flex-col gap-1">
                                            <h2 id="concepts-title" className="font-semibold">
                                                Conceptos a incluir
                                            </h2>
                                            <p className="text-muted-foreground text-sm">
                                                Puede incluir uno, varios o todos los conceptos del reporte.
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setData('concepts', allConcepts)}
                                        className={cn('gap-2', allSelected && 'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200')}
                                    >
                                        <Check className="size-4" />
                                        Todos los conceptos
                                    </Button>
                                </div>
                                <div className="grid gap-3 sm:pl-11 md:grid-cols-2 lg:grid-cols-3">
                                    {conceptOptions.map((option) => {
                                        const style = conceptStyles[option.value as keyof typeof conceptStyles];
                                        const Icon = style?.icon ?? FileText;
                                        return (
                                            <label
                                                key={option.value}
                                                className="hover:bg-muted/40 has-[:focus-visible]:ring-ring flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors has-[:focus-visible]:ring-2"
                                            >
                                                <span className={cn('flex size-11 shrink-0 items-center justify-center rounded-lg', style?.color)}>
                                                    <Icon className="size-5" />
                                                </span>
                                                <span className="flex-1 text-sm font-medium">{option.label}</span>
                                                <input
                                                    type="checkbox"
                                                    className="size-4 shrink-0 accent-green-700"
                                                    checked={data.concepts.includes(option.value)}
                                                    onChange={() =>
                                                        setData(
                                                            'concepts',
                                                            data.concepts.includes(option.value)
                                                                ? data.concepts.filter((concept) => concept !== option.value)
                                                                : [...data.concepts, option.value],
                                                        )
                                                    }
                                                />
                                            </label>
                                        );
                                    })}
                                </div>
                                {!data.concepts.length && (
                                    <p role="alert" className="text-destructive text-sm sm:pl-11">
                                        Seleccione al menos un concepto para generar el PDF.
                                    </p>
                                )}
                            </section>

                            <div className="flex flex-col gap-4">
                                <p className="text-muted-foreground flex items-center gap-3 rounded-lg bg-indigo-50/70 px-4 py-3 text-sm dark:bg-indigo-950/40">
                                    <FileText className="size-4 shrink-0 text-indigo-500" />
                                    El documento incluirá el resumen, detalle y totales en Bs y USD al momento del pago.
                                </p>
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <Button type="button" variant="outline" onClick={reset} className="gap-2">
                                        <RotateCcw className="size-4" />
                                        Restablecer
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={!data.concepts.length}
                                        className="h-11 gap-3 bg-green-800 px-6 text-white hover:bg-green-900 dark:bg-green-700 dark:hover:bg-green-600"
                                    >
                                        <Download className="size-4" />
                                        Generar PDF
                                        <ArrowRight className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
