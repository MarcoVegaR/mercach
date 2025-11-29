import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { AlertCircle, Calendar, CheckCircle2, ChevronDown, ChevronUp, Clock, FileText, Home, XCircle } from 'lucide-react';
import React from 'react';

interface Local {
    id: number;
    code: string;
    name: string;
    type: string;
    area_m2: number | null;
}

interface Item {
    id: number;
    number: string;
    status: string;
    status_name?: string;
    start_date: string;
    end_date: string;
    modality_code: string;
    charge_type: string | null;
    monthly_eur: number | null;
    locals: Local[];
    locals_count: number;
}

type Props = { items: Item[] };

// Format date in friendly format
function fmtDate(dateStr?: string) {
    if (!dateStr) return null;
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return dateStr;
    }
}

// Format EUR amount
function fmtEur(amount?: number | null): string {
    if (typeof amount !== 'number') return '—';
    return `€ ${amount.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Status configuration with friendly labels
function getStatusConfig(status?: string, statusName?: string) {
    const code = status?.toUpperCase();
    switch (code) {
        case 'VIG':
        case 'ACTIVE':
        case 'ACTIVO':
            return { icon: CheckCircle2, label: 'Vigente', color: 'text-green-600', bg: 'bg-green-100', border: 'border-green-200', isActive: true };
        case 'EXT':
            return { icon: Clock, label: 'Extendido', color: 'text-blue-600', bg: 'bg-blue-100', border: 'border-blue-200', isActive: true };
        case 'VENC':
            return { icon: AlertCircle, label: 'Vencido', color: 'text-amber-600', bg: 'bg-amber-100', border: 'border-amber-200', isActive: true };
        case 'TERM':
        case 'TERMINATED':
        case 'FINALIZADO':
            return { icon: XCircle, label: 'Terminado', color: 'text-slate-500', bg: 'bg-slate-100', border: 'border-slate-200', isActive: false };
        case 'BOR':
            return { icon: FileText, label: 'Borrador', color: 'text-slate-400', bg: 'bg-slate-50', border: 'border-slate-200', isActive: false };
        default:
            return {
                icon: FileText,
                label: statusName || status || '—',
                color: 'text-blue-600',
                bg: 'bg-blue-50',
                border: 'border-blue-200',
                isActive: false,
            };
    }
}

export default function PortalContractsModern({ items }: Props) {
    // Separate contracts by active status
    const activeContracts = items.filter((c) => getStatusConfig(c.status, c.status_name).isActive);
    const historicalContracts = items.filter((c) => !getStatusConfig(c.status, c.status_name).isActive);

    // Total locals from active contracts
    const totalLocals = activeContracts.reduce((sum, c) => sum + (c.locals_count || 0), 0);

    // State for expanding contracts
    const [expandedId, setExpandedId] = React.useState<number | null>(activeContracts[0]?.id ?? null);
    const [showHistorical, setShowHistorical] = React.useState(false);

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-3xl px-4 py-6">
                {/* Header */}
                <div className="mb-6">
                    <Link href="/portal" className="text-muted-foreground hover:text-foreground mb-2 inline-flex items-center gap-1 text-sm">
                        ← Portal
                    </Link>
                    <h1 className="text-2xl font-bold tracking-tight">Mis locales</h1>
                    <p className="text-muted-foreground text-sm">Locales asignados y condiciones de pago</p>
                </div>

                {/* Quick stats */}
                <div className="mb-6 grid grid-cols-2 gap-3">
                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                                <Home className="h-5 w-5 text-green-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">{totalLocals}</p>
                                <p className="text-muted-foreground text-sm">{totalLocals === 1 ? 'Local activo' : 'Locales activos'}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                <FileText className="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">{activeContracts.length}</p>
                                <p className="text-muted-foreground text-sm">
                                    {activeContracts.length === 1 ? 'Contrato vigente' : 'Contratos vigentes'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Active contracts - Focus on locals */}
                {activeContracts.length > 0 && (
                    <div className="mb-6 space-y-3">
                        {activeContracts.map((contract) => {
                            const cfg = getStatusConfig(contract.status, contract.status_name);
                            const isExpanded = expandedId === contract.id;

                            return (
                                <Card
                                    key={contract.id}
                                    className={cn('overflow-hidden transition-all', isExpanded ? 'ring-2 ring-green-500/20' : '', cfg.border)}
                                >
                                    <CardContent className="p-0">
                                        {/* Contract header - clickable */}
                                        <button
                                            onClick={() => setExpandedId(isExpanded ? null : contract.id)}
                                            className="flex w-full items-center justify-between p-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className={cn('flex h-11 w-11 items-center justify-center rounded-lg', cfg.bg)}>
                                                    <Home className={cn('h-5 w-5', cfg.color)} />
                                                </div>
                                                <div>
                                                    {/* Show locals prominently */}
                                                    <p className="font-semibold">
                                                        {contract.locals.length > 0
                                                            ? contract.locals.map((l) => `${l.type} ${l.code}`).join(', ')
                                                            : 'Sin locales'}
                                                    </p>
                                                    <div className="mt-1 flex items-center gap-1.5">
                                                        <Badge className={cn('border-0 text-xs', cfg.bg, cfg.color)}>{cfg.label}</Badge>
                                                        {contract.charge_type && (
                                                            <Badge variant="outline" className="text-muted-foreground text-xs font-normal">
                                                                {contract.charge_type}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {contract.monthly_eur && (
                                                    <span className="text-lg font-bold text-green-600">{fmtEur(contract.monthly_eur)}</span>
                                                )}
                                                {isExpanded ? (
                                                    <ChevronUp className="h-5 w-5 text-slate-400" />
                                                ) : (
                                                    <ChevronDown className="h-5 w-5 text-slate-400" />
                                                )}
                                            </div>
                                        </button>

                                        {/* Expanded details */}
                                        {isExpanded && (
                                            <div className="border-t p-4">
                                                {/* Locals detail */}
                                                {contract.locals.length > 0 && (
                                                    <div className="mb-4">
                                                        <p className="text-muted-foreground mb-2 text-xs font-medium tracking-wide uppercase">
                                                            Locales incluidos
                                                        </p>
                                                        <div className="space-y-2">
                                                            {contract.locals.map((local) => (
                                                                <div
                                                                    key={local.id}
                                                                    className="flex items-center justify-between rounded-md border p-3"
                                                                >
                                                                    <div className="flex items-center gap-2">
                                                                        <Home className="text-muted-foreground h-4 w-4" />
                                                                        <span className="font-medium">
                                                                            {local.type} {local.code}
                                                                        </span>
                                                                    </div>
                                                                    {local.area_m2 && (
                                                                        <span className="text-muted-foreground text-sm">{local.area_m2} m²</span>
                                                                    )}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Payment info */}
                                                <div className="mb-4 rounded-md border p-3">
                                                    <p className="text-muted-foreground mb-2 text-xs font-medium tracking-wide uppercase">
                                                        Condiciones de pago
                                                    </p>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-sm">{contract.charge_type || 'Tipo de cargo'}</span>
                                                        {contract.monthly_eur ? (
                                                            <span className="font-semibold text-green-600">{fmtEur(contract.monthly_eur)} /mes</span>
                                                        ) : contract.modality_code === 'M2' ? (
                                                            <span className="text-muted-foreground text-sm">Según m²</span>
                                                        ) : (
                                                            <span className="text-muted-foreground text-sm">—</span>
                                                        )}
                                                    </div>
                                                </div>

                                                {/* Dates */}
                                                <div className="grid grid-cols-2 gap-3 text-sm">
                                                    <div className="rounded-md border p-3">
                                                        <p className="text-muted-foreground text-xs">Inicio</p>
                                                        <div className="mt-1 flex items-center gap-1">
                                                            <Calendar className="text-muted-foreground h-3 w-3" />
                                                            <span className="font-medium">{fmtDate(contract.start_date)}</span>
                                                        </div>
                                                    </div>
                                                    <div className="rounded-md border p-3">
                                                        <p className="text-muted-foreground text-xs">Vencimiento</p>
                                                        <div className="mt-1 flex items-center gap-1">
                                                            <Calendar className="text-muted-foreground h-3 w-3" />
                                                            <span className="font-medium">
                                                                {contract.end_date ? fmtDate(contract.end_date) : 'Sin fecha'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Contract reference (subtle) */}
                                                <p className="text-muted-foreground mt-3 text-center text-xs">Contrato: {contract.number}</p>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}

                {/* Historical contracts (collapsed) */}
                {historicalContracts.length > 0 && (
                    <div className="mb-6">
                        <button
                            onClick={() => setShowHistorical(!showHistorical)}
                            className="text-muted-foreground hover:text-foreground mb-3 flex w-full items-center justify-between text-sm font-medium"
                        >
                            <span>Contratos anteriores ({historicalContracts.length})</span>
                            {showHistorical ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        </button>

                        {showHistorical && (
                            <div className="space-y-2">
                                {historicalContracts.map((c) => {
                                    const cfg = getStatusConfig(c.status, c.status_name);
                                    const Icon = cfg.icon;
                                    const localsText =
                                        c.locals.length > 0 ? c.locals.map((l) => `${l.type} ${l.code}`).join(', ') : `${c.locals_count} locales`;

                                    return (
                                        <Card key={c.id}>
                                            <CardContent className="flex items-center justify-between p-4">
                                                <div className="flex items-center gap-3">
                                                    <div className={cn('flex h-10 w-10 items-center justify-center rounded-lg', cfg.bg)}>
                                                        <Icon className={cn('h-5 w-5', cfg.color)} />
                                                    </div>
                                                    <div>
                                                        <p className="font-medium">{localsText}</p>
                                                        <p className="text-muted-foreground text-sm">
                                                            {fmtDate(c.start_date)} — {c.end_date ? fmtDate(c.end_date) : 'Sin fecha'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <Badge className={cn('border-0 text-xs', cfg.bg, cfg.color)}>{cfg.label}</Badge>
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}

                {/* Empty state */}
                {items.length === 0 && (
                    <Card className="border-dashed">
                        <CardContent className="py-12 text-center">
                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                <Home className="text-muted-foreground h-8 w-8" />
                            </div>
                            <h3 className="mb-2 text-lg font-semibold">Sin locales asignados</h3>
                            <p className="text-muted-foreground text-sm">
                                No tienes locales registrados. Contacta a administración si crees que es un error.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
