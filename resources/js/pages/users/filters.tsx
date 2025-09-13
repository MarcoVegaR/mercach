import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { DatePicker, DateRange, type DatePickerValue } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Calendar, Shield, ToggleLeft } from 'lucide-react';
import React from 'react';

export type UserFilterValue = {
    role_id?: number;
    is_active?: boolean;
    created_between?: {
        from?: string;
        to?: string;
    };
};

interface UserFiltersProps {
    value: UserFilterValue;
    onChange: (filters: UserFilterValue) => void;
    availableRoles?: Array<{ id: number; name: string }>;
}

export function UserFilters({ value, onChange, availableRoles = [] }: UserFiltersProps) {
    const [localFilters, setLocalFilters] = React.useState<UserFilterValue>(value);

    React.useEffect(() => {
        setLocalFilters(value);
    }, [value]);

    const activeFiltersCount = React.useMemo(() => {
        let count = 0;
        if (value.role_id) count++;
        if (value.is_active !== undefined) count++;
        if (value.created_between?.from || value.created_between?.to) count++;
        return count;
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
        const empty: UserFilterValue = {};
        setLocalFilters(empty);
        onChange(empty);
    };

    const dateRange: DateRange | undefined = localFilters.created_between
        ? {
              from: parseYMD(localFilters.created_between.from),
              to: parseYMD(localFilters.created_between.to),
          }
        : undefined;

    const badges = [] as Array<{
        key: string;
        label: string;
        icon?: React.ReactNode;
        onRemove: () => void;
    }>;

    // Removed name/email badges: global search input handles these filters

    if (value.role_id) {
        const role = availableRoles.find((r) => r.id === value.role_id);
        badges.push({
            key: 'role_id',
            label: `Rol: ${role?.name ?? value.role_id}`,
            icon: <Shield className="h-3 w-3 text-emerald-600 dark:text-emerald-400" />,
            onRemove: () => onChange({ ...value, role_id: undefined }),
        });
    }

    if (value.is_active !== undefined) {
        badges.push({
            key: 'is_active',
            label: value.is_active ? 'Solo Activos' : 'Solo Inactivos',
            icon: <ToggleLeft className="h-3 w-3 text-violet-600 dark:text-violet-400" />,
            onRemove: () => onChange({ ...value, is_active: undefined }),
        });
    }

    if (value.created_between && (value.created_between.from || value.created_between.to)) {
        badges.push({
            key: 'created_between',
            label: `Fecha: ${value.created_between.from ?? ''} - ${value.created_between.to ?? ''}`,
            icon: <Calendar className="h-3 w-3 text-sky-600 dark:text-sky-400" />,
            onRemove: () => onChange({ ...value, created_between: undefined }),
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeFiltersCount}
                onApplyFilters={applyFilters}
                onClearFilters={clearFilters}
                title="Filtros de Usuarios"
                description="Aplica filtros específicos para usuarios"
            >
                {/* Active Status */}
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <ToggleLeft className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        <Label htmlFor="is_active">Estado</Label>
                    </div>
                    <Select
                        value={localFilters.is_active === undefined ? 'all' : localFilters.is_active ? 'active' : 'inactive'}
                        onValueChange={(val) => setLocalFilters({ ...localFilters, is_active: val === 'all' ? undefined : val === 'active' })}
                    >
                        <SelectTrigger id="is_active" className="w-full">
                            <SelectValue placeholder="Seleccionar estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            <SelectItem value="active">Solo Activos</SelectItem>
                            <SelectItem value="inactive">Solo Inactivos</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Separator />

                {/* Role Filter */}
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Shield className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        <Label htmlFor="role_id">Rol</Label>
                    </div>
                    <Select
                        value={localFilters.role_id ? String(localFilters.role_id) : 'all'}
                        onValueChange={(val) => setLocalFilters({ ...localFilters, role_id: val === 'all' ? undefined : Number(val) })}
                    >
                        <SelectTrigger id="role_id" className="w-full">
                            <SelectValue placeholder="Seleccionar rol" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Todos</SelectItem>
                            {availableRoles.map((r) => (
                                <SelectItem key={r.id} value={String(r.id)}>
                                    {r.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <Separator />

                {/* Created Between */}
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Calendar className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                        <Label htmlFor="created_between">Fecha de Creación</Label>
                    </div>
                    <DatePicker mode="range" value={dateRange} onChange={handleDateRangeChange} placeholder="Seleccionar rango de fechas" />
                </div>
            </FilterSheet>

            <FilterBadges badges={badges} />
        </div>
    );
}
