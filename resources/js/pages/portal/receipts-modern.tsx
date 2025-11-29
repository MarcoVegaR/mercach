import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { CheckCircle2, ChevronLeft, ChevronRight, Download, FileText, Search } from 'lucide-react';
import React from 'react';

type Item = { id: number; receipt_number: string; issued_at: string; status: string; amount_bs_minor?: number; payment_id?: number };

type Props = { items: Item[] };

function fmtDate(dateStr?: string) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return dateStr;
    }
}

function getStatusLabel(status: string) {
    switch (status?.toUpperCase()) {
        case 'ISSUED':
        case 'ACTIVE':
            return 'Emitido';
        case 'VOID':
        case 'CANCELLED':
            return 'Anulado';
        default:
            return status;
    }
}

function getReceiptDisplayLabel(receiptNumber: string) {
    if (!receiptNumber) {
        return 'Recibo';
    }

    const match = receiptNumber.match(/(\d{3,})$/);
    const sequence = match ? match[1] : receiptNumber;

    return `Recibo #${sequence}`;
}

export default function PortalReceiptsModern({ items }: Props) {
    const [searchTerm, setSearchTerm] = React.useState('');
    const [periodFilter, setPeriodFilter] = React.useState<string>('3m');
    const [currentPage, setCurrentPage] = React.useState(1);
    const ITEMS_PER_PAGE = 10;

    // Filter by period
    const periodFilteredItems = React.useMemo(() => {
        const now = new Date();
        return items.filter((item) => {
            try {
                const itemDate = new Date(item.issued_at);
                switch (periodFilter) {
                    case '1m':
                        return itemDate >= new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
                    case '3m':
                        return itemDate >= new Date(now.getFullYear(), now.getMonth() - 3, now.getDate());
                    case '6m':
                        return itemDate >= new Date(now.getFullYear(), now.getMonth() - 6, now.getDate());
                    case '1y':
                        return itemDate >= new Date(now.getFullYear() - 1, now.getMonth(), now.getDate());
                    default:
                        return true;
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
        return periodFilteredItems.filter((r) => r.receipt_number.toLowerCase().includes(term) || r.issued_at.toLowerCase().includes(term));
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

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-3xl px-4 py-6">
                {/* Header */}
                <div className="mb-6">
                    <Link href="/portal" className="text-muted-foreground hover:text-foreground mb-2 inline-flex items-center gap-1 text-sm">
                        ← Portal
                    </Link>
                    <h1 className="text-2xl font-bold tracking-tight">Mis recibos</h1>
                    <p className="text-muted-foreground text-sm">Comprobantes de pago</p>
                </div>

                {/* Stats */}
                <Card className="mb-6">
                    <CardContent className="flex items-center gap-3 p-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <FileText className="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold">{items.length}</p>
                            <p className="text-muted-foreground text-sm">{items.length === 1 ? 'Recibo emitido' : 'Recibos emitidos'}</p>
                        </div>
                    </CardContent>
                </Card>

                {/* Filters */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            type="text"
                            placeholder="Buscar recibo..."
                            className="pl-9"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                    <Select value={periodFilter} onValueChange={setPeriodFilter}>
                        <SelectTrigger className="w-full sm:w-[180px]">
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
                </div>

                {/* Results info */}
                <p className="text-muted-foreground mb-4 text-sm">
                    {filteredItems.length === 0 ? 'Sin resultados' : `Mostrando ${paginatedItems.length} de ${filteredItems.length} recibos`}
                </p>

                {/* Receipts list */}
                {filteredItems.length > 0 ? (
                    <div className="space-y-2">
                        {paginatedItems.map((r) => (
                            <Card key={r.id} title={`${r.receipt_number} · ${r.status}`}>
                                <CardContent className="flex items-center justify-between p-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                                            <CheckCircle2 className="h-5 w-5 text-green-600" />
                                        </div>
                                        <div>
                                            <p className="font-semibold">{getReceiptDisplayLabel(r.receipt_number)}</p>
                                            <p className="text-muted-foreground text-sm">
                                                {fmtDate(r.issued_at)} · {getStatusLabel(r.status)}
                                            </p>
                                        </div>
                                    </div>
                                    <a href={`/portal/recibos/${r.id}/download`} target="_blank" rel="noopener noreferrer">
                                        <Button variant="outline" size="sm" className="gap-1.5">
                                            <Download className="h-4 w-4" />
                                            <span className="hidden sm:inline">PDF</span>
                                        </Button>
                                    </a>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : items.length > 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="py-12 text-center">
                            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                <Search className="text-muted-foreground h-6 w-6" />
                            </div>
                            <h3 className="mb-1 font-semibold">Sin resultados</h3>
                            <p className="text-muted-foreground mb-3 text-sm">Intenta con otros términos</p>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setSearchTerm('');
                                    setPeriodFilter('all');
                                }}
                            >
                                Limpiar filtros
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-dashed">
                        <CardContent className="py-12 text-center">
                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                <FileText className="text-muted-foreground h-8 w-8" />
                            </div>
                            <h3 className="mb-2 text-lg font-semibold">Sin recibos</h3>
                            <p className="text-muted-foreground mb-4 text-sm">Los recibos se generan al aplicar pagos a tus deudas.</p>
                            <Link href="/portal/pagos">
                                <Button>Ver mis pagos</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}

                {/* Pagination */}
                {totalPages > 1 && (
                    <div className="mt-6 flex items-center justify-center gap-3">
                        <Button variant="outline" size="sm" onClick={() => setCurrentPage((p) => Math.max(1, p - 1))} disabled={currentPage === 1}>
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <span className="text-muted-foreground text-sm">
                            {currentPage} / {totalPages}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                            disabled={currentPage === totalPages}
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
