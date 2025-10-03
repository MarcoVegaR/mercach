import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import React from 'react';

export type ParticipantsFilterValue = {
    q?: string;
    local_ids?: string[];
    included?: boolean | null;
};

interface Props {
    marketId: number;
    locals?: Array<{ id: number; code: string }>;
    value: ParticipantsFilterValue;
    onChange: (val: ParticipantsFilterValue) => void;
}

export default function ParticipantsFilters({ marketId, locals = [], value, onChange }: Props) {
    const [local, setLocal] = React.useState<ParticipantsFilterValue>(value);
    const [optionsRemote, setOptionsRemote] = React.useState<Array<{ value: string; label: string }>>([]);
    const [_loading, setLoading] = React.useState(false);

    React.useEffect(() => setLocal(value), [value]);

    const activeCount = React.useMemo(() => {
        let c = 0;
        if ((local.local_ids?.length ?? 0) > 0) c++;
        if (local.included !== null && local.included !== undefined) c++;
        return c;
    }, [local]);

    // Remote suggestions for locals based on q
    React.useEffect(() => {
        let alive = true;
        const controller = new AbortController();
        const q = (local.q ?? '').trim();
        const doFetch = async () => {
            try {
                setLoading(true);
                const params = new URLSearchParams({ market_id: String(marketId), limit: '20' });
                if (q !== '') params.set('q', q);
                const res = await fetch(`/condo/lookup/locals?${params.toString()}`, { signal: controller.signal });
                if (!alive) return;
                if (!res.ok) throw new Error('lookup_failed');
                const data = await res.json();
                const items = Array.isArray(data.items) ? data.items : [];
                setOptionsRemote(items.map((it: any) => ({ value: String(it.id), label: it.code || String(it.id) })));
            } catch {
                if (!alive) return;
                setOptionsRemote([]);
            } finally {
                if (alive) setLoading(false);
            }
        };
        const handle = setTimeout(doFetch, 300);
        return () => {
            alive = false;
            controller.abort();
            clearTimeout(handle);
        };
    }, [marketId, local.q]);

    const options = React.useMemo(() => {
        const base = optionsRemote.length > 0 ? optionsRemote : locals.map((l) => ({ value: String(l.id), label: l.code }));
        return base;
    }, [optionsRemote, locals]);

    const badges: Array<{ key: string; label: string; onRemove: () => void }> = [];
    if ((value.q ?? '').trim() !== '') {
        badges.push({ key: 'q', label: `Buscar: ${(value.q ?? '').trim()}`, onRemove: () => onChange({ ...value, q: '' }) });
    }
    if ((value.local_ids?.length ?? 0) > 0) {
        badges.push({ key: 'local_ids', label: `${value.local_ids?.length} locales`, onRemove: () => onChange({ ...value, local_ids: [] }) });
    }
    if (value.included !== null && value.included !== undefined) {
        badges.push({
            key: 'included',
            label: value.included ? 'Solo incluidos' : 'Solo excluidos',
            onRemove: () => onChange({ ...value, included: null }),
        });
    }

    const apply = () => onChange(local);
    const clear = () => onChange({ local_ids: [], included: null });

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeCount}
                onApplyFilters={apply}
                onClearFilters={clear}
                title="Filtros de participantes"
                description="Filtra por locales y estado de inclusión"
            >
                <div className="space-y-4">
                    <div className="space-y-1">
                        <Label>Búsqueda (código o nombre)</Label>
                        <Input
                            value={local.q ?? ''}
                            onChange={(e) => setLocal((prev) => ({ ...prev, q: e.target.value }))}
                            placeholder="Ej: 1A, 2-B, PERFUMERÍA..."
                        />
                    </div>
                    <div className="space-y-1">
                        <Label>Locales</Label>
                        <Combobox
                            options={options}
                            value={local.local_ids ?? []}
                            onChange={(v) => setLocal((prev) => ({ ...prev, local_ids: Array.isArray(v) ? v : [v] }))}
                            multiple
                            placeholder="Seleccionar locales..."
                            searchPlaceholder="Buscar local..."
                            emptyText="Sin resultados"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label>Estado</Label>
                        <Select
                            value={local.included === null || local.included === undefined ? 'all' : local.included ? 'included' : 'excluded'}
                            onValueChange={(v) => setLocal((prev) => ({ ...prev, included: v === 'all' ? null : v === 'included' }))}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Seleccionar estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="included">Incluidos</SelectItem>
                                <SelectItem value="excluded">Excluidos</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </FilterSheet>
            <FilterBadges badges={badges} />
        </div>
    );
}
