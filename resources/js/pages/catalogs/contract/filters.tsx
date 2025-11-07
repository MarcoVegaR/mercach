export type Filters = {
    contract_type_id?: number;
    contract_status_id?: number;
    contract_modality_id?: number;
    trade_category_id?: number;
    signed?: boolean;
};

export const defaultFilters: Filters = {};

export type FilterOptions = {
    contract_types?: Array<{ id: number; name: string }>;
    contract_statuses: Array<{ id: number; name: string }>;
    contract_modalities: Array<{ id: number; name: string; code?: string }>;
    trade_categories: Array<{ id: number; name: string }>;
};

import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { BadgePercent, FileSpreadsheet, ListFilter } from 'lucide-react';
import React from 'react';

interface ContractFiltersProps {
    value: Filters;
    onChange: (filters: Filters) => void;
    options?: FilterOptions;
}

export function ContractFilters({ value, onChange, options }: ContractFiltersProps) {
    const [local, setLocal] = React.useState<Filters>(value);
    React.useEffect(() => setLocal(value), [value]);

    const activeCount = React.useMemo(() => {
        let c = 0;
        if (value.contract_type_id) c++;
        if (value.contract_status_id) c++;
        if (value.contract_modality_id) c++;
        if (value.trade_category_id) c++;
        if (value.signed !== undefined) c++;
        return c;
    }, [value]);

    const apply = () => onChange(local);
    const clear = () => onChange({});

    const badges: Array<{ key: string; label: string; onRemove: () => void; icon?: React.ReactNode }> = [];
    if (value.contract_type_id) {
        const t = options?.contract_types?.find((x) => x.id === value.contract_type_id);
        badges.push({
            key: 'contract_type_id',
            label: `Tipo: ${t?.name ?? value.contract_type_id}`,
            onRemove: () => onChange({ ...value, contract_type_id: undefined }),
        });
    }
    if (value.contract_status_id) {
        const m = options?.contract_statuses.find((x) => x.id === value.contract_status_id);
        badges.push({
            key: 'contract_status_id',
            label: `Estado: ${m?.name ?? value.contract_status_id}`,
            onRemove: () => onChange({ ...value, contract_status_id: undefined }),
        });
    }
    if (value.contract_modality_id) {
        const m = options?.contract_modalities.find((x) => x.id === value.contract_modality_id);
        badges.push({
            key: 'contract_modality_id',
            label: `Modalidad: ${m?.name ?? value.contract_modality_id}`,
            onRemove: () => onChange({ ...value, contract_modality_id: undefined }),
        });
    }
    if (value.trade_category_id) {
        const m = options?.trade_categories.find((x) => x.id === value.trade_category_id);
        badges.push({
            key: 'trade_category_id',
            label: `Rubro: ${m?.name ?? value.trade_category_id}`,
            onRemove: () => onChange({ ...value, trade_category_id: undefined }),
        });
    }
    if (value.signed !== undefined) {
        badges.push({
            key: 'signed',
            label: `Firma: ${value.signed ? 'Firmado' : 'Sin firmar'}`,
            onRemove: () => onChange({ ...value, signed: undefined }),
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeCount}
                onApplyFilters={apply}
                onClearFilters={clear}
                title="Filtros de Contratos"
                description="Aplica filtros para el listado de contratos"
            >
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {/* Tipo */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <ListFilter className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                            <Label htmlFor="contract_type_id">Tipo</Label>
                        </div>
                        <Select
                            value={local.contract_type_id ? String(local.contract_type_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, contract_type_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="contract_type_id" className="w-full">
                                <SelectValue placeholder="Seleccionar tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {options?.contract_types?.map((t) => (
                                    <SelectItem key={t.id} value={String(t.id)}>
                                        {t.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {/* Estado */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <ListFilter className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                            <Label htmlFor="contract_status_id">Estado</Label>
                        </div>
                        <Select
                            value={local.contract_status_id ? String(local.contract_status_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, contract_status_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="contract_status_id" className="w-full">
                                <SelectValue placeholder="Seleccionar estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {options?.contract_statuses.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Modalidad */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <BadgePercent className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                            <Label htmlFor="contract_modality_id">Modalidad</Label>
                        </div>
                        <Select
                            value={local.contract_modality_id ? String(local.contract_modality_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, contract_modality_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="contract_modality_id" className="w-full">
                                <SelectValue placeholder="Seleccionar modalidad" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todas</SelectItem>
                                {options?.contract_modalities.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Firma (signed) */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <ListFilter className="h-4 w-4 text-rose-600 dark:text-rose-400" />
                            <Label htmlFor="signed">Firma</Label>
                        </div>
                        <Select
                            value={local.signed === undefined ? 'all' : local.signed ? 'signed' : 'unsigned'}
                            onValueChange={(val) =>
                                setLocal({
                                    ...local,
                                    signed: val === 'all' ? undefined : val === 'signed',
                                })
                            }
                        >
                            <SelectTrigger id="signed" className="w-full">
                                <SelectValue placeholder="Seleccionar firma" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="signed">Firmados</SelectItem>
                                <SelectItem value="unsigned">Sin firmar</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Rubro */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <FileSpreadsheet className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            <Label htmlFor="trade_category_id">Rubro</Label>
                        </div>
                        <Select
                            value={local.trade_category_id ? String(local.trade_category_id) : 'all'}
                            onValueChange={(val) => setLocal({ ...local, trade_category_id: val === 'all' ? undefined : Number(val) })}
                        >
                            <SelectTrigger id="trade_category_id" className="w-full">
                                <SelectValue placeholder="Seleccionar rubro" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {options?.trade_categories.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </FilterSheet>
            <FilterBadges badges={badges} />
        </div>
    );
}
