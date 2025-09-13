import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Badge } from '@/components/ui/badge';
import { Combobox } from '@/components/ui/combobox';
import { DatePicker, DateRange, type DatePickerValue } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Slider } from '@/components/ui/slider';
import { Calendar, Hash, Shield, ToggleLeft, Users } from 'lucide-react';
import React from 'react';

export type RoleFilterValue = {
    guard_name?: string;
    created_between?: {
        from?: string;
        to?: string;
    };
    permissions?: string[];
    users_count_min?: number;
    users_count_max?: number;
    is_active?: boolean;
};

interface RoleFiltersProps {
    value: RoleFilterValue;
    onChange: (filters: RoleFilterValue) => void;
    availablePermissions?: Array<{ id: number; name: string; description?: string }>;
}

export function RoleFilters({ value, onChange, availablePermissions = [] }: RoleFiltersProps) {
    const [localFilters, setLocalFilters] = React.useState<RoleFilterValue>(value);

    // Keep local filters in sync when parent-applied filters change
    React.useEffect(() => {
        setLocalFilters(value);
    }, [value]);

    const activeFiltersCount = React.useMemo(() => {
        let count = 0;
        if (value.guard_name) count++;
        if (value.created_between?.from || value.created_between?.to) count++;
        if (value.permissions && value.permissions.length > 0) count++;
        if (value.users_count_min || value.users_count_max) count++;
        if (value.is_active !== undefined) count++;
        return count;
    }, [value]);

    const handleGuardNameChange = (guardName: string) => {
        setLocalFilters({
            ...localFilters,
            guard_name: guardName === 'all' ? undefined : guardName,
        });
    };

    // Helpers to handle YYYY-MM-DD safely in local time to avoid timezone shifts
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

    const handleUsersCountChange = (values: number[]) => {
        setLocalFilters({
            ...localFilters,
            users_count_min: values[0] > 0 ? values[0] : undefined,
            users_count_max: values[1] < 100 ? values[1] : undefined,
        });
    };

    const handleActiveStatusChange = (checked: boolean | undefined) => {
        setLocalFilters({
            ...localFilters,
            is_active: checked,
        });
    };

    const applyFilters = () => {
        onChange(localFilters);
    };

    const clearFilters = () => {
        const emptyFilters: RoleFilterValue = {};
        setLocalFilters(emptyFilters);
        onChange(emptyFilters);
    };

    // Convert string dates to Date objects for DatePicker
    const dateRange: DateRange | undefined = localFilters.created_between
        ? {
              from: parseYMD(localFilters.created_between.from),
              to: parseYMD(localFilters.created_between.to),
          }
        : undefined;

    // Generate filter badges
    const filterBadges = [];

    if (value.guard_name) {
        filterBadges.push({
            key: 'guard_name',
            label: `Guard: ${value.guard_name}`,
            icon: <Shield className="h-3 w-3 text-indigo-600 dark:text-indigo-400" />,
            onRemove: () => onChange({ ...value, guard_name: undefined }),
        });
    }

    if (value.created_between && (value.created_between.from || value.created_between.to)) {
        filterBadges.push({
            key: 'created_between',
            label: `Fecha: ${value.created_between.from} - ${value.created_between.to}`,
            icon: <Calendar className="h-3 w-3 text-sky-600 dark:text-sky-400" />,
            onRemove: () => onChange({ ...value, created_between: undefined }),
        });
    }

    if (value.permissions && value.permissions.length > 0) {
        filterBadges.push({
            key: 'permissions',
            label: `Permisos: ${value.permissions.length} seleccionados`,
            icon: <Hash className="h-3 w-3 text-amber-600 dark:text-amber-400" />,
            onRemove: () => onChange({ ...value, permissions: undefined }),
        });
    }

    if (value.users_count_min || value.users_count_max) {
        filterBadges.push({
            key: 'users_count',
            label: `Usuarios: ${value.users_count_min || 0}-${value.users_count_max || 100}`,
            icon: <Users className="h-3 w-3 text-emerald-600 dark:text-emerald-400" />,
            onRemove: () => onChange({ ...value, users_count_min: undefined, users_count_max: undefined }),
        });
    }

    if (value.is_active !== undefined) {
        filterBadges.push({
            key: 'is_active',
            label: value.is_active ? 'Solo Activos' : 'Solo Inactivos',
            icon: <ToggleLeft className="h-3 w-3 text-violet-600 dark:text-violet-400" />,
            onRemove: () => onChange({ ...value, is_active: undefined }),
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeFiltersCount}
                onApplyFilters={applyFilters}
                onClearFilters={clearFilters}
                title="Filtros de Roles"
                description="Aplica filtros específicos para roles y permisos"
            >
                {/* Active Status Filter - First for better visibility */}
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <ToggleLeft className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        <Label htmlFor="is_active">Estado del Rol</Label>
                    </div>
                    <Select
                        key="status-select"
                        value={localFilters.is_active === undefined ? 'all' : localFilters.is_active ? 'active' : 'inactive'}
                        onValueChange={(value: string) => {
                            handleActiveStatusChange(value === 'all' ? undefined : value === 'active');
                        }}
                    >
                        <SelectTrigger id="is_active" className="w-full">
                            <SelectValue placeholder="Seleccionar estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-gray-400" />
                                    Todos los estados
                                </div>
                            </SelectItem>
                            <SelectItem value="active">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-green-500" />
                                    Solo Activos
                                </div>
                            </SelectItem>
                            <SelectItem value="inactive">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-red-500" />
                                    Solo Inactivos
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Separator />

                {/* Guard Filter */}
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Shield className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                        <Label htmlFor="guard_name">Guard de Autenticación</Label>
                    </div>
                    <Select key="guard-select" value={localFilters.guard_name || 'all'} onValueChange={handleGuardNameChange}>
                        <SelectTrigger id="guard_name" className="w-full">
                            <SelectValue placeholder="Seleccionar guard" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-gray-400" />
                                    Todos los guards
                                </div>
                            </SelectItem>
                            <SelectItem value="web">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-blue-500" />
                                    Web
                                </div>
                            </SelectItem>
                            <SelectItem value="api">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-green-500" />
                                    API
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Separator />

                {/* Date Range Filter */}
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Calendar className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                        <Label htmlFor="created_between">Fecha de Creación</Label>
                    </div>
                    <DatePicker mode="range" value={dateRange} onChange={handleDateRangeChange} placeholder="Seleccionar rango de fechas" />
                    {dateRange && (
                        <div className="text-muted-foreground text-xs">
                            {dateRange.from && !dateRange.to && <span>Desde {dateRange.from.toLocaleDateString()}</span>}
                            {dateRange.from && dateRange.to && (
                                <span>
                                    {dateRange.from.toLocaleDateString()} - {dateRange.to.toLocaleDateString()}
                                </span>
                            )}
                        </div>
                    )}
                </div>

                <Separator />

                {/* Permissions Filter with Searchable Multi-Select */}
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Hash className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        <Label>Filtrar por Permisos</Label>
                    </div>
                    <Combobox
                        id="permissions-filter"
                        options={availablePermissions.map((p) => ({
                            value: p.name,
                            label: p.description || p.name,
                        }))}
                        value={localFilters.permissions || []}
                        onChange={(value) => {
                            const newPermissions = Array.isArray(value) ? value : [];
                            const newFilters = {
                                ...localFilters,
                                permissions: newPermissions.length > 0 ? newPermissions : undefined,
                            };
                            setLocalFilters(newFilters);
                            // Do not auto-apply; apply on "Aplicar filtros"
                            // onChange(newFilters);
                        }}
                        multiple
                        placeholder="Seleccionar permisos..."
                        searchPlaceholder="Buscar permisos..."
                    />
                    {localFilters.permissions && localFilters.permissions.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                            {localFilters.permissions.slice(0, 3).map((permName) => {
                                const perm = availablePermissions.find((p) => p.name === permName);
                                return (
                                    <Badge key={permName} variant="secondary" className="text-xs">
                                        {perm?.description || permName}
                                    </Badge>
                                );
                            })}
                            {localFilters.permissions.length > 3 && (
                                <Badge variant="outline" className="text-xs">
                                    +{localFilters.permissions.length - 3} más
                                </Badge>
                            )}
                        </div>
                    )}
                </div>

                <Separator />

                {/* Users Count Filter */}
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Users className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        <Label htmlFor="users_count">
                            Rango de Usuarios: {localFilters.users_count_min || 0} - {localFilters.users_count_max || 100}
                        </Label>
                    </div>
                    <Slider
                        id="users_count"
                        min={0}
                        max={100}
                        step={1}
                        value={[localFilters.users_count_min || 0, localFilters.users_count_max || 100]}
                        onValueChange={handleUsersCountChange}
                        className="w-full"
                    />
                    <div className="text-muted-foreground flex justify-between text-xs">
                        <span>0 usuarios</span>
                        <span>100+ usuarios</span>
                    </div>
                </div>
            </FilterSheet>

            {/* Active Filters Display */}
            <FilterBadges badges={filterBadges} />
        </div>
    );
}
