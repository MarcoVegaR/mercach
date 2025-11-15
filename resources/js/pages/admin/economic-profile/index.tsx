import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { Building2, Search, Store } from 'lucide-react';
import React from 'react';

type Suggestion = { id: number; label: string };

export default function EconomicProfileIndex() {
    const [type, setType] = React.useState<'concessionaire' | 'local'>('concessionaire');
    const [at, setAt] = React.useState<string>(() => new Date().toISOString().slice(0, 10));
    const [q, setQ] = React.useState('');
    const [items, setItems] = React.useState<Suggestion[]>([]);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const abortRef = React.useRef<AbortController | null>(null);

    const fetchSuggest = React.useCallback(async (query: string, kind: 'concessionaire' | 'local') => {
        if (abortRef.current) abortRef.current.abort();
        const ctrl = new AbortController();
        abortRef.current = ctrl;
        setLoading(true);
        setError(null);
        try {
            const usp = new URLSearchParams({ type: kind, q: query, limit: '10' });
            const res = await fetch(`/admin/economic-profile/search?${usp.toString()}`, { signal: ctrl.signal, credentials: 'same-origin' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            setItems(Array.isArray(data.items) ? data.items : []);
        } catch (e) {
            if (!(e instanceof DOMException && e.name === 'AbortError')) setError('Error buscando...');
        } finally {
            setLoading(false);
        }
    }, []);

    React.useEffect(() => {
        if (q.trim().length < 2) {
            setItems([]);
            return;
        }
        const t = setTimeout(() => fetchSuggest(q.trim(), type), 250);
        return () => clearTimeout(t);
    }, [q, type, fetchSuggest]);

    const goTo = (id: number) => {
        const base = type === 'concessionaire' ? `/admin/economic-profile/concessionaires/${id}` : `/admin/economic-profile/locals/${id}`;
        router.visit(`${base}?at=${encodeURIComponent(at)}`);
    };

    return (
        <div className="container mx-auto max-w-4xl px-4 py-8">
            <div className="mb-8">
                <h1 className="text-3xl font-bold tracking-tight">Perfil Económico</h1>
                <p className="text-muted-foreground mt-2">Consulta la situación económica de concesionarios y locales</p>
            </div>

            <Card className="mb-6">
                <CardHeader>
                    <CardTitle>Búsqueda</CardTitle>
                    <CardDescription>Selecciona el tipo de consulta y la fecha de corte</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Tipo de búsqueda</label>
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    variant={type === 'concessionaire' ? 'default' : 'outline'}
                                    onClick={() => setType('concessionaire')}
                                    className="justify-start gap-2"
                                >
                                    <Building2 className="h-4 w-4" />
                                    Cesionario
                                </Button>
                                <Button
                                    variant={type === 'local' ? 'default' : 'outline'}
                                    onClick={() => setType('local')}
                                    className="justify-start gap-2"
                                >
                                    <Store className="h-4 w-4" />
                                    Local
                                </Button>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium" htmlFor="date-input">
                                Fecha de corte
                            </label>
                            <input
                                id="date-input"
                                type="date"
                                value={at}
                                onChange={(e) => setAt(e.target.value)}
                                className="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium" htmlFor="search-input">
                            Buscar
                        </label>
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <input
                                id="search-input"
                                type="text"
                                placeholder={type === 'concessionaire' ? 'Nombre o documento del concesionario' : 'Código o nombre del local'}
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                className="border-input bg-background flex h-10 w-full rounded-md border py-2 pr-3 pl-10 text-sm"
                                autoFocus
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {q.trim().length >= 2 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Resultados</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {error && <p className="text-destructive text-sm">{error}</p>}
                        {loading && <p className="text-muted-foreground text-sm">Buscando...</p>}
                        {!loading && items.length === 0 && <p className="text-muted-foreground text-sm">Sin resultados</p>}
                        {!loading && items.length > 0 && (
                            <ul className="space-y-2">
                                {items.map((it) => (
                                    <li key={`${type}-${it.id}`}>
                                        <button
                                            onClick={() => goTo(it.id)}
                                            className="hover:bg-muted/50 w-full rounded-md border p-3 text-left transition-colors"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <div className="font-medium">{it.label}</div>
                                                    <div className="text-muted-foreground text-xs">ID: {it.id}</div>
                                                </div>
                                                {type === 'concessionaire' ? (
                                                    <Building2 className="text-muted-foreground h-5 w-5" />
                                                ) : (
                                                    <Store className="text-muted-foreground h-5 w-5" />
                                                )}
                                            </div>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

EconomicProfileIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
