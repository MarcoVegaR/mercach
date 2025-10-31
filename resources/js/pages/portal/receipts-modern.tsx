import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, ChevronLeft, ChevronRight, Download, FileText, Filter, Search } from 'lucide-react';
import React from 'react';

type Item = { id: number; receipt_number: string; issued_at: string; status: string; amount_bs_minor?: number; payment_id?: number };

type Props = { items: Item[] };

function fmtDate(dateStr?: string) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch {
        return dateStr;
    }
}

function fmtMinor(minor?: number | null) {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: 'VES', minimumFractionDigits: 2 });
}

export default function PortalReceiptsModern({ items }: Props) {
    const [searchTerm, setSearchTerm] = React.useState('');
    const [periodFilter, setPeriodFilter] = React.useState<string>('3m'); // last 3 months by default
    const [currentPage, setCurrentPage] = React.useState(1);
    const ITEMS_PER_PAGE = 12; // 6 per column in 2-col grid

    // Filter by period
    const periodFilteredItems = React.useMemo(() => {
        const now = new Date();
        return items.filter((item) => {
            try {
                const itemDate = new Date(item.issued_at);
                switch (periodFilter) {
                    case '1m': {
                        // Last month
                        const oneMonthAgo = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
                        return itemDate >= oneMonthAgo;
                    }
                    case '3m': {
                        // Last 3 months
                        const threeMonthsAgo = new Date(now.getFullYear(), now.getMonth() - 3, now.getDate());
                        return itemDate >= threeMonthsAgo;
                    }
                    case '6m': {
                        // Last 6 months
                        const sixMonthsAgo = new Date(now.getFullYear(), now.getMonth() - 6, now.getDate());
                        return itemDate >= sixMonthsAgo;
                    }
                    case '1y': {
                        // Last year
                        const oneYearAgo = new Date(now.getFullYear() - 1, now.getMonth(), now.getDate());
                        return itemDate >= oneYearAgo;
                    }
                    case 'all': {
                        // All time
                        return true;
                    }
                    default: {
                        return true;
                    }
                }
            } catch {
                return true;
            }
        });
    }, [items, periodFilter]);

    // Filter by search
    const filteredItems = React.useMemo(() => {
        if (!searchTerm) return periodFilteredItems;
        const term = searchTerm.toLowerCase();
        return periodFilteredItems.filter(
            (r) => r.receipt_number.toLowerCase().includes(term) || r.status.toLowerCase().includes(term) || r.issued_at.toLowerCase().includes(term),
        );
    }, [periodFilteredItems, searchTerm]);

    // Pagination
    const totalPages = Math.ceil(filteredItems.length / ITEMS_PER_PAGE);
    const paginatedItems = React.useMemo(() => {
        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        return filteredItems.slice(start, start + ITEMS_PER_PAGE);
    }, [filteredItems, currentPage]);

    // Reset to page 1 when filters change
    React.useEffect(() => {
        setCurrentPage(1);
    }, [searchTerm, periodFilter]);

    const totalReceipts = items.length;

    return (
        <AppLayout>
            <div className="container mx-auto max-w-5xl px-4 py-8">
                {/* Header */}
                <div className="mb-8">
                    <div className="mb-3 flex items-center gap-3">
                        <Link href="/portal">
                            <Button variant="ghost" size="sm" className="gap-2">
                                <ArrowLeft className="h-4 w-4" />
                                Portal
                            </Button>
                        </Link>
                    </div>
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h1 className="text-4xl font-bold tracking-tight">Mis recibos</h1>
                            <p className="text-muted-foreground mt-2">Descarga tus comprobantes de pago</p>
                        </div>
                        <Badge variant="secondary" className="px-4 py-2 text-lg">
                            {totalReceipts} recibo{totalReceipts !== 1 ? 's' : ''}
                        </Badge>
                    </div>
                </div>

                {/* Filters and Search */}
                <div className="mb-6 space-y-4">
                    <div className="flex flex-col gap-4 sm:flex-row">
                        {/* Period Filter */}
                        <Select value={periodFilter} onValueChange={setPeriodFilter}>
                            <SelectTrigger className="w-full sm:w-[200px]">
                                <Filter className="mr-2 h-4 w-4" />
                                <SelectValue placeholder="Período" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1m">Último mes</SelectItem>
                                <SelectItem value="3m">Últimos 3 meses</SelectItem>
                                <SelectItem value="6m">Últimos 6 meses</SelectItem>
                                <SelectItem value="1y">Último año</SelectItem>
                                <SelectItem value="all">Todos</SelectItem>
                            </SelectContent>
                        </Select>

                        {/* Search */}
                        <div className="relative flex-1">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Buscar por número, fecha o estado..."
                                className="w-full rounded-lg border py-2 pr-4 pl-10 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Results info */}
                    <div className="text-muted-foreground flex items-center justify-between text-sm">
                        <div>
                            Mostrando {paginatedItems.length} de {filteredItems.length} recibos
                            {periodFilter !== 'all' && ` (total: ${totalReceipts})`}
                        </div>
                        {filteredItems.length > ITEMS_PER_PAGE && (
                            <div>
                                Página {currentPage} de {totalPages}
                            </div>
                        )}
                    </div>
                </div>

                {/* Receipts grid */}
                {filteredItems.length > 0 ? (
                    <>
                        <div className="mb-8 grid gap-4 md:grid-cols-2">
                            {paginatedItems.map((r) => (
                                <Card key={r.id} className="transition-shadow hover:shadow-lg">
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between">
                                            <div>
                                                <CardTitle className="flex items-center gap-2 text-base">
                                                    <FileText className="h-4 w-4 text-blue-600" />
                                                    {r.receipt_number}
                                                </CardTitle>
                                                <CardDescription className="mt-1">{fmtDate(r.issued_at)}</CardDescription>
                                            </div>
                                            <Badge
                                                variant={r.status === 'ISSUED' ? 'default' : 'secondary'}
                                                className={r.status === 'ISSUED' ? 'bg-green-600' : ''}
                                            >
                                                <CheckCircle2 className="mr-1 h-3 w-3" />
                                                {r.status}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        {typeof r.amount_bs_minor === 'number' && (
                                            <div className="mb-3">
                                                <div className="text-muted-foreground text-sm">Monto</div>
                                                <div className="text-2xl font-bold text-blue-600">{fmtMinor(r.amount_bs_minor)}</div>
                                            </div>
                                        )}
                                        <a href={`/portal/recibos/${r.id}/download`} target="_blank" rel="noopener noreferrer">
                                            <Button variant="outline" size="sm" className="w-full gap-2">
                                                <Download className="h-4 w-4" />
                                                Descargar PDF
                                            </Button>
                                        </a>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="mt-8 flex items-center justify-center gap-4">
                                <Button
                                    variant="outline"
                                    size="lg"
                                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                    disabled={currentPage === 1}
                                    className="gap-2"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                    Anterior
                                </Button>

                                <div className="text-muted-foreground text-sm">
                                    Página <span className="font-semibold text-slate-900">{currentPage}</span> de{' '}
                                    <span className="font-semibold text-slate-900">{totalPages}</span>
                                </div>

                                <Button
                                    variant="outline"
                                    size="lg"
                                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                    disabled={currentPage === totalPages}
                                    className="gap-2"
                                >
                                    Siguiente
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        )}
                    </>
                ) : items.length > 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <div className="flex flex-col items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                                    <Search className="text-muted-foreground h-8 w-8" />
                                </div>
                                <div>
                                    <h3 className="mb-1 text-lg font-semibold">No se encontraron resultados</h3>
                                    <p className="text-muted-foreground">Intenta con otros términos de búsqueda</p>
                                    <Button
                                        variant="link"
                                        size="sm"
                                        className="mt-2"
                                        onClick={() => {
                                            setSearchTerm('');
                                            setPeriodFilter('all');
                                        }}
                                    >
                                        Limpiar filtros
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <div className="flex flex-col items-center gap-4">
                                <div className="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100">
                                    <FileText className="h-10 w-10 text-blue-600" />
                                </div>
                                <div>
                                    <h3 className="mb-2 text-xl font-semibold">Aún no tienes recibos</h3>
                                    <p className="text-muted-foreground mb-4 max-w-md">
                                        Los recibos se generan automáticamente cuando aplicas tus pagos a tus deudas.
                                    </p>
                                    <div className="flex items-center justify-center gap-3">
                                        <Link href="/portal/pagos/nuevo">
                                            <Button size="lg" className="gap-2">
                                                Registrar un pago
                                            </Button>
                                        </Link>
                                        <Link href="/portal/pagos">
                                            <Button variant="outline" size="lg">
                                                Ver mis pagos
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Help card */}
                {items.length > 0 && (
                    <Card className="mt-8 border-blue-200 bg-blue-50/30">
                        <CardContent className="pt-6">
                            <div className="flex items-start gap-4">
                                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                                    <FileText className="h-5 w-5 text-blue-600" />
                                </div>
                                <div className="flex-1">
                                    <h3 className="mb-2 font-semibold">Sobre tus recibos</h3>
                                    <p className="text-muted-foreground text-sm">
                                        Cada recibo corresponde a un cargo específico que fue cubierto con tus pagos. Puedes descargar los PDF para
                                        tus registros personales o contables.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
