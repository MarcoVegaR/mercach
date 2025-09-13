import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { DatePicker, DateRange, type DatePickerValue } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Calendar, History, Monitor } from 'lucide-react';
import React from 'react';

export type AuditFilterValue = {
    event?: string;
    auditable_type?: string;
    // Additional scalar filters supported via URL query parsing (not all exposed in UI)
    user_id?: string;
    auditable_id?: string;
    ip_address?: string;
    url?: string;
    tags?: string;
    created_between?: {
        from?: string;
        to?: string;
    };
};

interface AuditFiltersProps {
    value: AuditFilterValue;
    onChange: (filters: AuditFilterValue) => void;
}

const commonEvents = [
    { value: 'created', label: 'Creado' },
    { value: 'updated', label: 'Actualizado' },
    { value: 'deleted', label: 'Eliminado' },
    { value: 'login', label: 'Inicio de sesión' },
    { value: 'logout', label: 'Cierre de sesión' },
    { value: 'permissions_sync', label: 'Cambios de permisos' },
];

const commonEntityTypes = [
    { value: 'App\\Models\\User', label: 'Usuario' },
    { value: 'App\\Models\\Role', label: 'Rol' },
];

export function AuditFilters({ value, onChange }: AuditFiltersProps) {
    const [localFilters, setLocalFilters] = React.useState<AuditFilterValue>(value);

    // Keep local filters in sync when parent-applied filters change
    React.useEffect(() => {
        setLocalFilters(value);
    }, [value]);

    const activeFiltersCount = React.useMemo(() => {
        let count = 0;
        if (value.event) count++;
        if (value.auditable_type) count++;
        if (value.created_between?.from || value.created_between?.to) count++;
        return count;
    }, [value]);

    const handleEventChange = (value: string) => {
        setLocalFilters({
            ...localFilters,
            event: value === 'all' ? undefined : value,
        });
    };

    const handleEntityTypeChange = (value: string) => {
        setLocalFilters({
            ...localFilters,
            auditable_type: value === 'all' ? undefined : value,
        });
    };

    // Helpers to handle Y-m-d safely in local time (avoid timezone shifts)
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

    // Date range derived for DatePicker value
    const dateRange: DateRange | undefined = localFilters.created_between
        ? {
              from: parseYMD(localFilters.created_between.from),
              to: parseYMD(localFilters.created_between.to),
          }
        : undefined;

    const handleDateRangeChange = (dateRange: DatePickerValue) => {
        const range = dateRange as DateRange | undefined;
        setLocalFilters({
            ...localFilters,
            created_between:
                range && (range.from || range.to)
                    ? {
                          from: toYMD(range.from),
                          to: toYMD(range.to),
                      }
                    : undefined,
        });
    };

    const applyFilters = () => {
        onChange(localFilters);
    };

    const clearFilters = () => {
        setLocalFilters({});
        onChange({});
    };

    const filterBadges = React.useMemo(() => {
        const badges = [];

        if (value.event) {
            badges.push({
                key: 'event',
                label: `Evento: ${commonEvents.find((e) => e.value === value.event)?.label || value.event}`,
                onRemove: () => onChange({ ...value, event: undefined }),
            });
        }

        if (value.auditable_type) {
            badges.push({
                key: 'auditable_type',
                label: `Entidad: ${commonEntityTypes.find((e) => e.value === value.auditable_type)?.label || value.auditable_type}`,
                onRemove: () => onChange({ ...value, auditable_type: undefined }),
            });
        }

        if (value.created_between?.from || value.created_between?.to) {
            const from = value.created_between?.from;
            const to = value.created_between?.to;
            badges.push({
                key: 'created_between',
                label: `Fecha: ${from || 'Inicio'} - ${to || 'Fin'}`,
                onRemove: () => onChange({ ...value, created_between: undefined }),
            });
        }

        return badges;
    }, [value, onChange]);

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                title="Filtros de Auditoría"
                description="Filtra los registros de auditoría por diferentes criterios"
                activeFiltersCount={activeFiltersCount}
                onApplyFilters={applyFilters}
                onClearFilters={clearFilters}
            >
                <div className="space-y-6">
                    {/* Event */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <History className="h-4 w-4 text-orange-500 dark:text-orange-400" />
                            <Label className="text-sm font-medium">Evento</Label>
                        </div>
                        <Select value={localFilters.event || 'all'} onValueChange={handleEventChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Selecciona un evento" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los eventos</SelectItem>
                                {commonEvents.map((event) => (
                                    <SelectItem key={event.value} value={event.value}>
                                        {event.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <Separator />

                    {/* Entity Type */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Monitor className="h-4 w-4 text-blue-500 dark:text-blue-400" />
                            <Label className="text-sm font-medium">Tipo de Entidad</Label>
                        </div>
                        <Select value={localFilters.auditable_type || 'all'} onValueChange={handleEntityTypeChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Selecciona un tipo de entidad" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todas las entidades</SelectItem>
                                {commonEntityTypes.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <Separator />

                    {/* Date Range */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Calendar className="h-4 w-4 text-green-500 dark:text-green-400" />
                            <Label className="text-sm font-medium">Rango de Fechas</Label>
                        </div>
                        <DatePicker mode="range" value={dateRange} onChange={handleDateRangeChange} placeholder="Selecciona un rango de fechas" />
                    </div>
                </div>
            </FilterSheet>
            <FilterBadges badges={filterBadges} />
        </div>
    );
}
