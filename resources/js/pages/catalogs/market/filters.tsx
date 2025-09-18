import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ToggleLeft } from 'lucide-react';
import React from 'react';

export type Filters = {
    q?: string | null;
    is_active?: boolean | null;
};

export const defaultFilters: Filters = {
    q: null,
    is_active: null,
};

interface MarketFiltersProps {
    value: Filters;
    onChange: (filters: Filters) => void;
}

export function MarketFilters({ value, onChange }: MarketFiltersProps) {
    const [local, setLocal] = React.useState<Filters>(value);

    React.useEffect(() => {
        setLocal(value);
    }, [value]);

    const activeCount = React.useMemo(() => {
        let c = 0;
        if (value.is_active !== null && value.is_active !== undefined) c++;
        return c;
    }, [value]);

    const apply = () => onChange(local);
    const clear = () => onChange({ q: value.q ?? null, is_active: null });

    const badges: Array<{ key: string; label: string; onRemove: () => void; icon?: React.ReactNode }> = [];
    if (value.is_active !== null && value.is_active !== undefined) {
        badges.push({
            key: 'is_active',
            label: value.is_active ? 'Solo Activos' : 'Solo Inactivos',
            onRemove: () => onChange({ ...value, is_active: null }),
            icon: <ToggleLeft className="h-3 w-3 text-violet-600 dark:text-violet-400" />,
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeCount}
                onApplyFilters={apply}
                onClearFilters={clear}
                title="Filtros de Mercados"
                description="Aplica filtros para el listado de mercados"
            >
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <ToggleLeft className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        <Label htmlFor="is_active">Estado</Label>
                    </div>
                    <Select
                        key="status-select"
                        value={local.is_active === null || local.is_active === undefined ? 'all' : local.is_active ? 'active' : 'inactive'}
                        onValueChange={(value: string) => {
                            const next: Filters = {
                                ...local,
                                is_active: value === 'all' ? null : value === 'active',
                            };
                            setLocal(next);
                        }}
                    >
                        <SelectTrigger id="is_active" className="w-full">
                            <SelectValue placeholder="Seleccionar estado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-gray-400" />
                                    Todos
                                </div>
                            </SelectItem>
                            <SelectItem value="active">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-green-500" />
                                    Activos
                                </div>
                            </SelectItem>
                            <SelectItem value="inactive">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-red-500" />
                                    Inactivos
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </FilterSheet>
            <FilterBadges badges={badges} />
        </div>
    );
}
