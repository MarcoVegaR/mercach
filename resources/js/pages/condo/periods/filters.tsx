import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Combobox } from '@/components/ui/combobox';
import { DatePicker, type DatePickerValue, type DateRange } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { CalendarDays } from 'lucide-react';
import React from 'react';

export type Filters = {
    market_id?: string | number | null;
    period_year?: string | null; // YYYY
    period_month?: string | null; // YYYY-MM (single month)
    period_from?: string | null; // YYYY-MM
    period_to?: string | null; // YYYY-MM
};

export const defaultFilters: Filters = {
    market_id: null,
    period_year: null,
    period_month: null,
    period_from: null,
    period_to: null,
};

export type MarketOption = { id: number; code?: string | null; name?: string | null };

interface Props {
    value: Filters;
    onChange: (filters: Filters) => void;
    markets: MarketOption[];
}

export function CondoPeriodFilters({ value, onChange, markets }: Props) {
    const [local, setLocal] = React.useState<Filters>(value);
    const periodModeInitial: 'year' | 'month' | 'range' = ((): 'year' | 'month' | 'range' => {
        if (value.period_year) return 'year';
        if (value.period_from || value.period_to) return 'range';
        return 'month';
    })();
    const [periodMode, setPeriodMode] = React.useState<'year' | 'month' | 'range'>(periodModeInitial);

    React.useEffect(() => setLocal(value), [value]);

    // Clear period fields when switching modes to avoid confusion
    React.useEffect(() => {
        if (periodMode === 'year') {
            setLocal((prev) => ({ ...prev, period_month: null, period_from: null, period_to: null }));
        } else if (periodMode === 'month') {
            setLocal((prev) => ({ ...prev, period_year: null, period_from: null, period_to: null }));
        } else if (periodMode === 'range') {
            setLocal((prev) => ({ ...prev, period_year: null, period_month: null }));
        }
    }, [periodMode]);

    const activeCount = React.useMemo(() => {
        let c = 0;
        if (value.market_id) c++;
        if (value.period_year) c++;
        if (value.period_month) c++;
        if (value.period_from || value.period_to) c++;
        return c;
    }, [value]);

    const apply = () => {
        // Convert market_id to number if present
        const marketId = local.market_id ? Number(local.market_id) : null;
        const next: Filters = { market_id: marketId, period_year: null, period_month: null, period_from: null, period_to: null };
        if (periodMode === 'year') {
            next.period_year = local.period_year && /^\d{4}$/.test(local.period_year) ? local.period_year : null;
        } else if (periodMode === 'month') {
            next.period_month = local.period_month && /^\d{4}-(0[1-9]|1[0-2])$/.test(local.period_month) ? local.period_month : null;
        } else if (periodMode === 'range') {
            next.period_from = local.period_from && /^\d{4}-(0[1-9]|1[0-2])$/.test(local.period_from) ? local.period_from : null;
            next.period_to = local.period_to && /^\d{4}-(0[1-9]|1[0-2])$/.test(local.period_to) ? local.period_to : null;
        }
        onChange(next);
    };
    const clear = () => onChange({ market_id: null, period_year: null, period_month: null, period_from: null, period_to: null });

    const badges: Array<{ key: string; label: string; onRemove: () => void; icon?: React.ReactNode }> = [];
    if (value.market_id) {
        const opt = markets.find((m) => String(m.id) === String(value.market_id));
        badges.push({
            key: 'market_id',
            label: `Mercado: ${opt ? (opt.name ?? '') : value.market_id}`,
            onRemove: () => onChange({ ...value, market_id: null }),
            icon: <CalendarDays className="h-3 w-3 text-amber-600" />,
        });
    }
    if (value.period_year) {
        badges.push({
            key: 'period_year',
            label: `Año: ${value.period_year}`,
            onRemove: () => onChange({ ...value, period_year: null }),
            icon: <CalendarDays className="h-3 w-3 text-sky-600 dark:text-sky-400" />,
        });
    }
    if (value.period_month) {
        badges.push({
            key: 'period_month',
            label: `Mes: ${value.period_month}`,
            onRemove: () => onChange({ ...value, period_month: null }),
            icon: <CalendarDays className="h-3 w-3 text-sky-600 dark:text-sky-400" />,
        });
    }
    if (value.period_from || value.period_to) {
        badges.push({
            key: 'period_range',
            label: `Rango: ${value.period_from ?? '—'} a ${value.period_to ?? '—'}`,
            onRemove: () => onChange({ ...value, period_from: null, period_to: null }),
            icon: <CalendarDays className="h-3 w-3 text-sky-600 dark:text-sky-400" />,
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeCount}
                onApplyFilters={apply}
                onClearFilters={clear}
                title="Filtros de Períodos"
                description="Aplica filtros para el listado de períodos"
            >
                <div className="space-y-4">
                    {/* Mercado */}
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <Label htmlFor="market_id">Mercado</Label>
                        </div>
                        <Combobox
                            id="market_id"
                            options={markets.map((m) => ({ value: String(m.id), label: m.name ?? '' }))}
                            value={String(local.market_id ?? '')}
                            onChange={(v) => setLocal((prev) => ({ ...prev, market_id: Array.isArray(v) ? (v[0] ?? null) : v || null }))}
                            placeholder="Seleccionar mercado"
                            searchPlaceholder="Buscar mercado..."
                            emptyText="Sin resultados"
                        />
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <CalendarDays className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                <Label>Filtro de Período</Label>
                            </div>
                            <ToggleGroup type="single" value={periodMode} onValueChange={(v) => v && setPeriodMode(v as 'year' | 'month' | 'range')}>
                                <ToggleGroupItem value="year" className="px-2 py-1 text-xs">
                                    Año
                                </ToggleGroupItem>
                                <ToggleGroupItem value="month" className="px-2 py-1 text-xs">
                                    Mes
                                </ToggleGroupItem>
                                <ToggleGroupItem value="range" className="px-2 py-1 text-xs">
                                    Rango
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>
                        {(() => {
                            // Helpers
                            const parseMonth = (s?: string | null): Date | undefined => {
                                if (!s) return undefined;
                                const [y, m] = s.split('-').map((n) => parseInt(n, 10));
                                if (!y || !m) return undefined;
                                return new Date(y, m - 1, 1);
                            };
                            const toYearMonth = (d?: Date): string | null => {
                                if (!d) return null;
                                const y = d.getFullYear();
                                const m = String(d.getMonth() + 1).padStart(2, '0');
                                return `${y}-${m}`;
                            };

                            if (periodMode === 'year') {
                                return (
                                    <Input
                                        id="period_year"
                                        type="number"
                                        min={2000}
                                        max={2100}
                                        value={local.period_year ?? ''}
                                        onChange={(e) => setLocal((prev) => ({ ...prev, period_year: e.target.value || null }))}
                                        placeholder="Ej: 2025"
                                    />
                                );
                            }
                            if (periodMode === 'month') {
                                return (
                                    <Input
                                        id="period_month"
                                        type="month"
                                        value={local.period_month ?? ''}
                                        onChange={(e) => setLocal((prev) => ({ ...prev, period_month: e.target.value || null }))}
                                        placeholder="YYYY-MM"
                                    />
                                );
                            }
                            // range mode
                            const monthRange: DateRange | undefined =
                                local.period_from || local.period_to
                                    ? { from: parseMonth(local.period_from), to: parseMonth(local.period_to) }
                                    : undefined;

                            const handleRangeChange = (val: DatePickerValue) => {
                                const r = (val as DateRange) || undefined;
                                setLocal((prev) => ({
                                    ...prev,
                                    period_from: toYearMonth(r?.from) ?? null,
                                    period_to: toYearMonth(r?.to) ?? null,
                                }));
                            };

                            return (
                                <DatePicker
                                    id="period_between"
                                    mode="range"
                                    value={monthRange}
                                    onChange={handleRangeChange}
                                    placeholder="Seleccionar rango de meses"
                                    numberOfMonths={2}
                                />
                            );
                        })()}
                    </div>
                </div>
            </FilterSheet>
            <FilterBadges badges={badges} />
        </div>
    );
}
