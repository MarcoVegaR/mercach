import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CalendarCheck, Tags, ToggleLeft } from 'lucide-react';
import React from 'react';

export type Filters = {
    concessionaire_type_id?: number;
    is_active?: boolean | null;
    has_active_contract?: boolean | null;
    life_proof_status?: 'current' | 'requires_citation' | 'missing';
};

export const defaultFilters: Filters = {
    is_active: null,
    has_active_contract: null,
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
        if (value.has_active_contract !== null && value.has_active_contract !== undefined) c++;
        if (value.life_proof_status) c++;
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
    if (value.has_active_contract !== null && value.has_active_contract !== undefined) {
        badges.push({
            key: 'has_active_contract',
            label: value.has_active_contract ? 'Con contrato vigente' : 'Sin contrato vigente',
            onRemove: () => onChange({ ...value, has_active_contract: null }),
        });
    }
    if (value.life_proof_status) {
        const labels = {
            current: 'Fe de vida vigente',
            requires_citation: 'Requiere citación',
            missing: 'Sin fe de vida registrada',
        };
        badges.push({
            key: 'life_proof_status',
            label: labels[value.life_proof_status],
            onRemove: () => onChange({ ...value, life_proof_status: undefined }),
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeCount}
                onApplyFilters={apply}
                onClearFilters={clear}
                title="Filtros de Cesionarios"
                description="Aplica filtros para el listado de cesionarios"
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

                    {/* Con contrato vigente */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <ToggleLeft className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            <Label htmlFor="has_active_contract">Contrato vigente</Label>
                        </div>
                        <Select
                            value={
                                local.has_active_contract === null || local.has_active_contract === undefined
                                    ? 'all'
                                    : local.has_active_contract
                                      ? 'yes'
                                      : 'no'
                            }
                            onValueChange={(val) => setLocal({ ...local, has_active_contract: val === 'all' ? null : val === 'yes' })}
                        >
                            <SelectTrigger id="has_active_contract" className="w-full">
                                <SelectValue placeholder="Seleccionar" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="yes">Con contrato vigente</SelectItem>
                                <SelectItem value="no">Sin contrato vigente</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <CalendarCheck className="h-4 w-4 text-rose-600 dark:text-rose-400" />
                            <Label htmlFor="life_proof_status">Fe de vida</Label>
                        </div>
                        <Select
                            value={local.life_proof_status ?? 'all'}
                            onValueChange={(val) =>
                                setLocal({
                                    ...local,
                                    life_proof_status: val === 'all' ? undefined : (val as 'current' | 'requires_citation' | 'missing'),
                                })
                            }
                        >
                            <SelectTrigger id="life_proof_status" className="w-full">
                                <SelectValue placeholder="Seleccionar estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="current">Vigente</SelectItem>
                                <SelectItem value="requires_citation">Requiere citación</SelectItem>
                                <SelectItem value="missing">Sin registro</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </FilterSheet>
            <FilterBadges badges={badges} />
        </div>
    );
}
