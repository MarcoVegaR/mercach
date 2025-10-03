import { Button } from '@/components/ui/button';
import { Combobox, type Option } from '@/components/ui/combobox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Building2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

export type ParticipantsExcludeDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    periodId: number;
    marketId: number;
    onSaved?: (totals?: { participants_count: number }) => void;
};

export default function ParticipantsExcludeDialog({ open, onOpenChange, periodId, marketId, onSaved }: ParticipantsExcludeDialogProps) {
    const [loading, setLoading] = React.useState(false);
    const [options, setOptions] = React.useState<Option[]>([]);
    const [selected, setSelected] = React.useState<string[]>([]);
    const [query, setQuery] = React.useState('');

    const loadOptions = React.useCallback(
        async (q: string) => {
            try {
                const usp = new URLSearchParams();
                usp.set('market_id', String(marketId));
                if (q) usp.set('q', q);
                // Load many by default and when typing to cover all (~700)
                usp.set('limit', '1000');
                const res = await fetch(`/condo/lookup/locals?${usp.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Cache-Control': 'no-store' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!res.ok) throw new Error('lookup_failed');
                const data = (await res.json()) as { items: Array<{ id: number; code: string; name: string }> };
                setOptions((data.items || []).map((i) => ({ value: String(i.id), label: i.code })));
            } catch {
                setOptions([]);
            }
        },
        [marketId],
    );

    React.useEffect(() => {
        if (open) void loadOptions('');
    }, [open, loadOptions]);

    const handleSearchChange = React.useCallback(
        (q: string) => {
            setQuery(q);
            void loadOptions(q);
        },
        [loadOptions],
    );

    const submit = React.useCallback(async () => {
        if (selected.length === 0) {
            toast.warning('Seleccione al menos un local');
            return;
        }
        setLoading(true);
        try {
            const res = await fetch(`/condo/periods/${periodId}/participants`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ local_ids: selected.map((v) => Number(v)) }),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => ({}) as any);
                throw new Error((body as any)?.message || 'No se pudo excluir');
            }
            const body = await res.json().catch(() => ({}) as any);
            toast.success('Locales excluidos');
            onOpenChange(false);
            setSelected([]);
            onSaved?.((body as any)?.totals);
        } catch (e: any) {
            toast.error(e?.message || 'No se pudo excluir');
        } finally {
            setLoading(false);
        }
    }, [periodId, selected, onOpenChange, onSaved]);

    const excludeAll = React.useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(`/condo/periods/${periodId}/participants/exclude-all`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                const body = await res.json().catch(() => ({}) as any);
                throw new Error((body as any)?.message || 'No se pudo excluir todos');
            }
            const body = await res.json().catch(() => ({}) as any);
            toast.success('Locales disponibles excluidos');
            onOpenChange(false);
            setSelected([]);
            onSaved?.((body as any)?.totals);
        } catch (e: any) {
            toast.error(e?.message || 'No se pudo excluir todos');
        } finally {
            setLoading(false);
        }
    }, [periodId, onOpenChange, onSaved]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl md:max-w-2xl" style={{ overflowY: 'visible', maxHeight: 'none' }}>
                <DialogHeader>
                    <DialogTitle>Excluir locales</DialogTitle>
                    <DialogDescription>Seleccione uno o más locales a excluir.</DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                    <div>
                        <Combobox
                            options={options}
                            value={selected}
                            onChange={(v) => setSelected(Array.isArray(v) ? v : v ? [v] : [])}
                            multiple
                            placeholder="Seleccionar locales a excluir"
                            searchPlaceholder="Buscar local por código..."
                            emptyText={query ? 'Sin coincidencias' : 'Escriba para buscar'}
                            leadingIcon={Building2}
                            leadingIconClassName="text-emerald-600"
                            onSearchChange={handleSearchChange}
                            withinDialog
                            maxPopoverHeight={360}
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={excludeAll} disabled={loading}>
                        Excluir disponibles
                    </Button>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
                        Cancelar
                    </Button>
                    <Button type="button" onClick={submit} disabled={loading}>
                        {loading ? 'Guardando...' : 'Excluir'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
