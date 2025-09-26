import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tags, ToggleLeft } from 'lucide-react';
import React from 'react';

export type Filters = {
    concessionaire_type_id?: number;
    is_active?: boolean | null;
};

export const defaultFilters: Filters = {
    is_active: null,
};

export type FilterOptions = {
    concessionaire_types: Array<{ id: number; name: string }>;
};

interface ConcessionaireFiltersProps {
    value: Filters;
    onChange: (filters: Filters) => void;
    options?: FilterOptions;
}

export function ConcessionaireFilters({ value, onChange, options }: ConcessionaireFiltersProps) {
    const [local, setLocal] = React.useState<Filters>(value);

    React.useEffect(() => setLocal(value), [value]);

    const activeCount = React.useMemo(() => {
        let c = 0;
        if (value.concessionaire_type_id) c++;
        if (value.is_active !== null && value.is_active !== undefined) c++;
        return c;
    }, [value]);

    const apply = () => onChange(local);
    const clear = () => onChange({});

    const badges: Array<{ key: string; label: string; onRemove: () => void; icon?: React.ReactNode }> = [];
    if (value.concessionaire_type_id) {
        const m = options?.concessionaire_types.find((x) => x.id === value.concessionaire_type_id);
        badges.push({
            key: 'concessionaire_type_id',
            label: `Tipo: ${m?.name ?? value.concessionaire_type_id}`,
            onRemove: () => onChange({ ...value, concessionaire_type_id: undefined }),
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
                title="Filtros de Concesionarios"
                description="Aplica filtros para el listado de concesionarios"
            >
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {/* Tipo de concesionario */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Tags className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                            <Label htmlFor="concessionaire_type_id">Tipo</Label>
                        </div>
                        <Select
                            value={local.concessionaire_type_id ? String(local.concessionaire_type_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, concessionaire_type_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="concessionaire_type_id" className="w-full">
                                <SelectValue placeholder="Seleccionar tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {options?.concessionaire_types.map((m) => (
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
