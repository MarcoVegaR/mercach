import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { MapPin, Store, Tags, ToggleLeft } from 'lucide-react';
import React from 'react';

export type Filters = {
    market_id?: number;
    local_type_id?: number;
    local_status_id?: number;
    local_location_id?: number;
    is_active?: boolean | null;
};

export const defaultFilters: Filters = {};

export type FilterOptions = {
    markets: Array<{ id: number; name: string }>;
    local_types: Array<{ id: number; name: string }>;
    local_statuses: Array<{ id: number; name: string }>;
    local_locations: Array<{ id: number; name: string }>;
};

interface LocalFiltersProps {
    value: Filters;
    onChange: (filters: Filters) => void;
    options?: FilterOptions;
}

export function LocalFilters({ value, onChange, options }: LocalFiltersProps) {
    const [local, setLocal] = React.useState<Filters>(value);

    React.useEffect(() => setLocal(value), [value]);

    const activeCount = React.useMemo(() => {
        let c = 0;
        if (value.market_id) c++;
        if (value.local_type_id) c++;
        if (value.local_status_id) c++;
        if (value.local_location_id) c++;
        if (value.is_active !== null && value.is_active !== undefined) c++;
        return c;
    }, [value]);

    const apply = () => onChange(local);
    const clear = () => onChange({});

    const badges: Array<{ key: string; label: string; onRemove: () => void; icon?: React.ReactNode }> = [];
    if (value.market_id) {
        const m = options?.markets.find((x) => x.id === value.market_id);
        badges.push({
            key: 'market_id',
            label: `Mercado: ${m?.name ?? value.market_id}`,
            onRemove: () => onChange({ ...value, market_id: undefined }),
        });
    }
    if (value.local_type_id) {
        const m = options?.local_types.find((x) => x.id === value.local_type_id);
        badges.push({
            key: 'local_type_id',
            label: `Tipo: ${m?.name ?? value.local_type_id}`,
            onRemove: () => onChange({ ...value, local_type_id: undefined }),
        });
    }
    if (value.local_status_id) {
        const m = options?.local_statuses.find((x) => x.id === value.local_status_id);
        badges.push({
            key: 'local_status_id',
            label: `Estado: ${m?.name ?? value.local_status_id}`,
            onRemove: () => onChange({ ...value, local_status_id: undefined }),
        });
    }
    if (value.local_location_id) {
        const m = options?.local_locations.find((x) => x.id === value.local_location_id);
        badges.push({
            key: 'local_location_id',
            label: `Ubicación: ${m?.name ?? value.local_location_id}`,
            onRemove: () => onChange({ ...value, local_location_id: undefined }),
        });
    }
    if (value.is_active !== null && value.is_active !== undefined) {
        badges.push({
            key: 'is_active',
            label: value.is_active ? 'Solo Activos' : 'Solo Inactivos',
            onRemove: () => onChange({ ...value, is_active: null }),
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeCount}
                onApplyFilters={apply}
                onClearFilters={clear}
                title="Filtros de Locales"
                description="Aplica filtros para el listado de locales"
            >
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {/* Mercado */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Store className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            <Label htmlFor="market_id">Mercado</Label>
                        </div>
                        <Select
                            value={local.market_id ? String(local.market_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, market_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="market_id" className="w-full">
                                <SelectValue placeholder="Seleccionar mercado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {options?.markets.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Tipo de local */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Tags className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                            <Label htmlFor="local_type_id">Tipo</Label>
                        </div>
                        <Select
                            value={local.local_type_id ? String(local.local_type_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, local_type_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="local_type_id" className="w-full">
                                <SelectValue placeholder="Seleccionar tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {options?.local_types.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Estado de local */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <ToggleLeft className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                            <Label htmlFor="local_status_id">Estado de local</Label>
                        </div>
                        <Select
                            value={local.local_status_id ? String(local.local_status_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, local_status_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="local_status_id" className="w-full">
                                <SelectValue placeholder="Seleccionar estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {options?.local_statuses.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {null /* Rubro removed from Local filters; association will be at Contract level */}

                    {/* Ubicación */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <MapPin className="h-4 w-4 text-rose-600 dark:text-rose-400" />
                            <Label htmlFor="local_location_id">Ubicación</Label>
                        </div>
                        <Select
                            value={local.local_location_id ? String(local.local_location_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, local_location_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="local_location_id" className="w-full">
                                <SelectValue placeholder="Seleccionar ubicación" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {options?.local_locations.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Estado */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <ToggleLeft className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                            <Label htmlFor="is_active">Estado</Label>
                        </div>
                        <Select
                            value={local.is_active === null || local.is_active === undefined ? 'all' : local.is_active ? 'active' : 'inactive'}
                            onValueChange={(val) => setLocal({ ...local, is_active: val === 'all' ? null : val === 'active' })}
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
                </div>
            </FilterSheet>
            <FilterBadges badges={badges} />
        </div>
    );
}
