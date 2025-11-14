import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { DatePicker, DateRange, type DatePickerValue } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { BadgeCheck, Calendar, FileWarning } from 'lucide-react';
import React from 'react';

export type BankValidationsFilterValue = {
    paid_between?: { from?: string; to?: string };
    response_code?: string;
    status?: string;
};

export type ResponseCodeOption = { code: string; label: string };

export function BankValidationsFilters({
    value,
    onChange,
    responseCodes = [],
}: {
    value: BankValidationsFilterValue;
    onChange: (filters: BankValidationsFilterValue) => void;
    responseCodes?: ResponseCodeOption[];
}) {
    const [localFilters, setLocalFilters] = React.useState<BankValidationsFilterValue>(value);

    React.useEffect(() => setLocalFilters(value), [value]);

    const activeFiltersCount = React.useMemo(() => {
        let c = 0;
        if (value.paid_between?.from || value.paid_between?.to) c++;
        if (value.response_code) c++;
        if (value.status) c++;
        return c;
    }, [value]);

    const parseYMD = (s?: string): Date | undefined => {
        if (!s) return undefined;
        const [y, m, d] = s.split('-').map((n) => parseInt(n, 10));
        if (!y || !m || !d) return undefined;
        return new Date(y, m - 1, d);
    };
    const toYMD = (d?: Date): string | undefined => {
        if (!d) return undefined;
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };

    const handleDateRangeChange = (val: DatePickerValue) => {
        const range = val as DateRange | undefined;
        setLocalFilters({
            ...localFilters,
            paid_between: range && (range.from || range.to) ? { from: toYMD(range.from), to: toYMD(range.to) } : undefined,
        });
    };

    const applyFilters = () => onChange(localFilters);
    const clearFilters = () => {
        const empty: BankValidationsFilterValue = {};
        setLocalFilters(empty);
        onChange(empty);
    };

    const dateRange: DateRange | undefined = localFilters.paid_between
        ? { from: parseYMD(localFilters.paid_between.from), to: parseYMD(localFilters.paid_between.to) }
        : undefined;

    const badges: Array<{ key: string; label: string; icon?: React.ReactNode; onRemove: () => void }> = [];
    if (value.paid_between && (value.paid_between.from || value.paid_between.to)) {
        badges.push({
            key: 'paid_between',
            label: `Fecha: ${value.paid_between.from ?? ''} - ${value.paid_between.to ?? ''}`,
            icon: <Calendar className="h-3 w-3 text-sky-600 dark:text-sky-400" />,
            onRemove: () => onChange({ ...value, paid_between: undefined }),
        });
    }
    if (value.response_code) {
        const rc = responseCodes.find((r) => r.code === value.response_code)?.label ?? value.response_code;
        badges.push({
            key: 'response_code',
            label: `Código: ${rc}`,
            icon: <BadgeCheck className="h-3 w-3 text-emerald-600 dark:text-emerald-400" />,
            onRemove: () => onChange({ ...value, response_code: undefined }),
        });
    }
    if (value.status) {
        badges.push({
            key: 'status',
            label: `Estado: ${value.status}`,
            icon: <FileWarning className="h-3 w-3 text-violet-600 dark:text-violet-400" />,
            onRemove: () => onChange({ ...value, status: undefined }),
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeFiltersCount}
                onApplyFilters={applyFilters}
                onClearFilters={clearFilters}
                title="Filtros de Validaciones Bancarias"
                description="Aplica filtros específicos para el reporte"
            >
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Calendar className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                        <Label htmlFor="paid_between">Fecha de pago</Label>
                    </div>
                    <DatePicker mode="range" value={dateRange} onChange={handleDateRangeChange} placeholder="Seleccionar rango de fechas" />
                </div>

                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <BadgeCheck className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        <Label htmlFor="response_code">Código de respuesta</Label>
                    </div>
                    <Select
                        value={localFilters.response_code ? String(localFilters.response_code) : 'all'}
                        onValueChange={(val) => setLocalFilters({ ...localFilters, response_code: val === 'all' ? undefined : String(val) })}
                    >
                        <SelectTrigger id="response_code" className="w-full">
                            <SelectValue placeholder="Seleccionar código" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            {responseCodes.map((rc) => (
                                <SelectItem key={rc.code} value={rc.code}>
                                    {rc.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <FileWarning className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        <Label htmlFor="status">Estado del pago</Label>
                    </div>
                    <Select
                        value={localFilters.status ? String(localFilters.status) : 'all'}
                        onValueChange={(val) => setLocalFilters({ ...localFilters, status: val === 'all' ? undefined : String(val) })}
                    >
                        <SelectTrigger id="status" className="w-full">
                            <SelectValue placeholder="Seleccionar estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            <SelectItem value="REGISTERED">REGISTERED</SelectItem>
                            <SelectItem value="CONFIRMED">CONFIRMED</SelectItem>
                            <SelectItem value="APPLIED">APPLIED</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </FilterSheet>

            <FilterBadges badges={badges} />
        </div>
    );
}
