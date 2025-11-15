import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { Building2, Calendar, ChevronRight, Search, Store, TrendingUp } from 'lucide-react';
import React from 'react';

type Suggestion = { id: number; label: string; metadata?: string };

export default function EconomicProfileIndexModern() {
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

    const formattedDate = React.useMemo(() => {
        try {
            return new Date(at).toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
        } catch {
            return at;
        }
    }, [at]);

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
            <div className="container mx-auto max-w-5xl px-4 py-12">
                {/* Hero Section */}
                <div className="mb-8">
                    <div className="mb-4 flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 shadow-lg">
                            <TrendingUp className="h-6 w-6 text-white" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50">Perfil Económico</h1>
                            <p className="mt-1 text-slate-600 dark:text-slate-400">
                                Consulta situación financiera completa de concesionarios y locales
                            </p>
                        </div>
                    </div>
                </div>

                {/* Type Selection Cards */}
                <div className="mb-6 grid gap-4 sm:grid-cols-2">
                    <button
                        onClick={() => setType('concessionaire')}
                        className={`group relative overflow-hidden rounded-xl border-2 p-6 text-left transition-all ${
                            type === 'concessionaire'
                                ? 'border-blue-500 bg-gradient-to-br from-blue-50 to-blue-100 shadow-lg dark:from-blue-950 dark:to-blue-900'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600'
                        }`}
                    >
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <div
                                    className={`mb-3 flex h-10 w-10 items-center justify-center rounded-lg ${
                                        type === 'concessionaire'
                                            ? 'bg-blue-500 text-white'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                                    }`}
                                >
                                    <Building2 className="h-5 w-5" />
                                </div>
                                <h3 className="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-50">Cesionario</h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Vista consolidada de todos los locales, contratos y deudas
                                </p>
                            </div>
                            {type === 'concessionaire' && (
                                <div className="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-blue-500 text-white">
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                            )}
                        </div>
                    </button>

                    <button
                        onClick={() => setType('local')}
                        className={`group relative overflow-hidden rounded-xl border-2 p-6 text-left transition-all ${
                            type === 'local'
                                ? 'border-green-500 bg-gradient-to-br from-green-50 to-green-100 shadow-lg dark:from-green-950 dark:to-green-900'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600'
                        }`}
                    >
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <div
                                    className={`mb-3 flex h-10 w-10 items-center justify-center rounded-lg ${
                                        type === 'local'
                                            ? 'bg-green-500 text-white'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                                    }`}
                                >
                                    <Store className="h-5 w-5" />
                                </div>
                                <h3 className="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-50">Local</h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Situación específica de un local individual con detalle de cargos
                                </p>
                            </div>
                            {type === 'local' && (
                                <div className="mt-1 flex h-6 w-6 items-center justify-center rounded-full bg-green-500 text-white">
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                            )}
                        </div>
                    </button>
                </div>

                {/* Search Card */}
                <Card className="mb-6 overflow-hidden shadow-lg">
                    <CardHeader className="bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-700">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Buscar y consultar</CardTitle>
                                <CardDescription className="mt-1">
                                    {type === 'concessionaire'
                                        ? 'Busca por nombre o documento del concesionario'
                                        : 'Busca por código o nombre del local'}
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <Calendar className="h-4 w-4" />
                                <span>Corte: {formattedDate}</span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-6">
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-slate-700 dark:text-slate-300" htmlFor="date-input">
                                Fecha de corte
                            </label>
                            <div className="relative">
                                <Calendar className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    id="date-input"
                                    type="date"
                                    value={at}
                                    onChange={(e) => setAt(e.target.value)}
                                    className="h-11 w-full rounded-lg border border-slate-300 bg-white py-2 pr-3 pl-10 text-sm shadow-sm transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-600 dark:bg-slate-800"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium text-slate-700 dark:text-slate-300" htmlFor="search-input">
                                Buscar
                            </label>
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                <input
                                    id="search-input"
                                    type="text"
                                    placeholder={
                                        type === 'concessionaire' ? 'Ej: "Juan Pérez", "J-12345678"...' : 'Ej: "LC-001", "Local comercial"...'
                                    }
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    className="h-12 w-full rounded-lg border border-slate-300 bg-white py-2 pr-3 pl-11 text-sm shadow-sm transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-600 dark:bg-slate-800"
                                    autoFocus
                                />
                                {loading && (
                                    <div className="absolute top-1/2 right-3 -translate-y-1/2">
                                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-blue-500"></div>
                                    </div>
                                )}
                            </div>
                            {q.trim().length > 0 && q.trim().length < 2 && (
                                <p className="text-xs text-slate-500">Escribe al menos 2 caracteres para buscar</p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Results */}
                {q.trim().length >= 2 && (
                    <Card className="overflow-hidden shadow-lg">
                        <CardHeader className="bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-700">
                            <CardTitle className="flex items-center gap-2 text-base">
                                Resultados
                                {items.length > 0 && (
                                    <span className="ml-auto rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                        {items.length}
                                    </span>
                                )}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {error && (
                                <div className="px-6 py-8 text-center">
                                    <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
                                </div>
                            )}
                            {loading && (
                                <div className="px-6 py-12 text-center">
                                    <div className="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-blue-500"></div>
                                    <p className="text-sm text-slate-500">Buscando...</p>
                                </div>
                            )}
                            {!loading && items.length === 0 && !error && (
                                <div className="px-6 py-12 text-center">
                                    <Search className="mx-auto mb-3 h-12 w-12 text-slate-300" />
                                    <p className="text-sm text-slate-500">No se encontraron resultados</p>
                                    <p className="mt-1 text-xs text-slate-400">Intenta con otros términos de búsqueda</p>
                                </div>
                            )}
                            {!loading && items.length > 0 && (
                                <ul className="divide-y divide-slate-100 dark:divide-slate-700">
                                    {items.map((it) => (
                                        <li key={`${type}-${it.id}`}>
                                            <button
                                                onClick={() => goTo(it.id)}
                                                className="group flex w-full items-center justify-between px-6 py-4 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800"
                                            >
                                                <div className="flex items-center gap-4">
                                                    <div
                                                        className={`flex h-10 w-10 items-center justify-center rounded-lg transition-colors ${
                                                            type === 'concessionaire'
                                                                ? 'bg-blue-100 text-blue-600 group-hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-400'
                                                                : 'bg-green-100 text-green-600 group-hover:bg-green-200 dark:bg-green-900 dark:text-green-400'
                                                        }`}
                                                    >
                                                        {type === 'concessionaire' ? (
                                                            <Building2 className="h-5 w-5" />
                                                        ) : (
                                                            <Store className="h-5 w-5" />
                                                        )}
                                                    </div>
                                                    <div>
                                                        <div className="font-medium text-slate-900 dark:text-slate-50">{it.label}</div>
                                                        <div className="text-xs text-slate-500">ID: {it.id}</div>
                                                    </div>
                                                </div>
                                                <ChevronRight className="h-5 w-5 text-slate-400 transition-transform group-hover:translate-x-1" />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Help Section */}
                {q.trim().length === 0 && (
                    <Card className="mt-6 border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/50">
                        <CardContent className="flex items-start gap-3 pt-6">
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-500 text-white">
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <div className="flex-1">
                                <h4 className="mb-1 font-medium text-blue-900 dark:text-blue-100">¿Qué puedes consultar?</h4>
                                <ul className="space-y-1 text-sm text-blue-700 dark:text-blue-300">
                                    <li>• Deudas abiertas y vencidas por moneda (USD, EUR, VES)</li>
                                    <li>• Pagos parcialmente aplicados y disponibles</li>
                                    <li>• Créditos a favor (saldo positivo)</li>
                                    <li>• Detalle completo de cargos, períodos y saldos</li>
                                    <li>• Exportación de datos a CSV/JSON</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </div>
    );
}

EconomicProfileIndexModern.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
