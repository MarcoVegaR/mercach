import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Combobox } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { BadgeCheck, Building2, ListFilter, UserSquare2 } from 'lucide-react';
import React from 'react';

export type Filters = {
    charge_status_id?: number;
    local_id?: number;
    concessionaire_id?: number;
    kind?: string;
};

export const defaultFilters: Filters = {};

export type FilterOptions = {
    statuses: Array<{ id: number; name: string; code?: string }>; // ISSUED/CANCELED
    locals: Array<{ id: number; name: string }>;
    concessionaires: Array<{ id: number; name: string }>;
    types: Array<{ value: string; label: string }>; // RENT_EUR_M2, ...
};

interface Props {
    value: Filters;
    onChange: (filters: Filters) => void;
    options?: FilterOptions;
}

export function ChargesFilters({ value, onChange, options }: Props) {
    const [local, setLocal] = React.useState<Filters>(value);
    React.useEffect(() => setLocal(value), [value]);

    const activeCount = React.useMemo(() => {
        let c = 0;
        if (value.charge_status_id) c++;
        if (value.local_id) c++;
        if (value.concessionaire_id) c++;
        if (value.kind) c++;
        return c;
    }, [value]);

    const apply = () => onChange(local);
    const clear = () => onChange({});

    const badges: Array<{ key: string; label: string; onRemove: () => void; icon?: React.ReactNode }> = [];
    if (value.charge_status_id) {
        const st = options?.statuses.find((s) => s.id === value.charge_status_id);
        badges.push({
            key: 'charge_status_id',
            label: `Estado: ${st?.name ?? value.charge_status_id}`,
            onRemove: () => onChange({ ...value, charge_status_id: undefined }),
        });
    }
    if (value.local_id) {
        const l = options?.locals.find((x) => x.id === value.local_id);
        badges.push({
            key: 'local_id',
            label: `Local: ${l?.name ?? value.local_id}`,
            onRemove: () => onChange({ ...value, local_id: undefined }),
            icon: <Building2 className="h-3 w-3 text-sky-600" />,
        });
    }
    if (value.concessionaire_id) {
        const c = options?.concessionaires.find((x) => x.id === value.concessionaire_id);
        badges.push({
            key: 'concessionaire_id',
            label: `Concesionario: ${c?.name ?? value.concessionaire_id}`,
            onRemove: () => onChange({ ...value, concessionaire_id: undefined }),
            icon: <UserSquare2 className="h-3 w-3 text-emerald-600" />,
        });
    }
    if (value.kind) {
        const k = options?.types.find((x) => x.value === value.kind);
        badges.push({
            key: 'kind',
            label: `Tipo: ${k?.label ?? value.kind}`,
            onRemove: () => onChange({ ...value, kind: undefined }),
            icon: <ListFilter className="h-3 w-3 text-violet-600" />,
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeCount}
                onApplyFilters={apply}
                onClearFilters={clear}
                title="Filtros de Cargos"
                description="Aplica filtros para el listado de cargos"
            >
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {/* Estado */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <BadgeCheck className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                            <Label htmlFor="charge_status_id">Estado</Label>
                        </div>
                        <Select
                            value={local.charge_status_id ? String(local.charge_status_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, charge_status_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="charge_status_id" className="w-full">
                                <SelectValue placeholder="Seleccionar estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {(options?.statuses ?? []).map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Tipo */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <ListFilter className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                            <Label htmlFor="kind">Tipo</Label>
                        </div>
                        <Select
                            value={local.kind ? String(local.kind) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, kind: val === 'all' ? undefined : val })}
                        >
                            <SelectTrigger id="kind" className="w-full">
                                <SelectValue placeholder="Seleccionar tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {(options?.types ?? []).map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Local (Combobox, solo nombre) */}
                    <div className="space-y-3 sm:col-span-2">
                        <div className="flex items-center gap-2">
                            <Building2 className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                            <Label htmlFor="local_id">Local</Label>
                        </div>
                        <Combobox
                            id="local_id"
                            options={(options?.locals ?? []).map((l) => ({ value: String(l.id), label: l.name }))}
                            value={local.local_id ? String(local.local_id) : ''}
                            onChange={(v) => setLocal((prev) => ({ ...prev, local_id: v ? Number(Array.isArray(v) ? v[0] : v) : undefined }))}
                            placeholder="Seleccionar local"
                            searchPlaceholder="Buscar local..."
                            emptyText="Sin resultados"
                        />
                    </div>

                    {/* Concesionario (Combobox) */}
                    <div className="space-y-3 sm:col-span-2">
                        <div className="flex items-center gap-2">
                            <UserSquare2 className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            <Label htmlFor="concessionaire_id">Concesionario</Label>
                        </div>
                        <Combobox
                            id="concessionaire_id"
                            options={(options?.concessionaires ?? []).map((c) => ({ value: String(c.id), label: c.name }))}
                            value={local.concessionaire_id ? String(local.concessionaire_id) : ''}
                            onChange={(v) =>
                                setLocal((prev) => ({ ...prev, concessionaire_id: v ? Number(Array.isArray(v) ? v[0] : v) : undefined }))
                            }
                            placeholder="Seleccionar concesionario"
                            searchPlaceholder="Buscar concesionario..."
                            emptyText="Sin resultados"
                        />
                    </div>
                </div>
            </FilterSheet>
            <FilterBadges badges={badges} />
        </div>
    );
}
