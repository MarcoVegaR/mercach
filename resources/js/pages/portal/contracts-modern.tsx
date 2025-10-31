import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Building2, Calendar, CheckCircle2, Clock, FileText, MapPin, XCircle } from 'lucide-react';
import React from 'react';

interface Item {
    id: number;
    number: string;
    status: string;
    start_date: string;
    end_date: string;
    locals_label?: string;
    locals_count?: number;
}

type Props = { items: Item[] };

function fmtDate(dateStr?: string) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return dateStr;
    }
}

function getStatusConfig(status?: string) {
    switch (status?.toUpperCase()) {
        case 'ACTIVE':
        case 'ACTIVO':
            return { icon: CheckCircle2, label: 'Activo', color: 'text-green-600', bg: 'bg-green-50', border: 'border-green-200' };
        case 'PENDING':
        case 'PENDIENTE':
            return { icon: Clock, label: 'Pendiente', color: 'text-yellow-600', bg: 'bg-yellow-50', border: 'border-yellow-200' };
        case 'TERMINATED':
        case 'FINALIZADO':
            return { icon: XCircle, label: 'Finalizado', color: 'text-gray-600', bg: 'bg-gray-50', border: 'border-gray-200' };
        default:
            return { icon: FileText, label: status || '—', color: 'text-blue-600', bg: 'bg-blue-50', border: 'border-blue-200' };
    }
}

export default function PortalContractsModern({ items }: Props) {
    const activeContracts = items.filter((c) => ['ACTIVE', 'ACTIVO'].includes(c.status?.toUpperCase()));
    const inactiveContracts = items.filter((c) => !['ACTIVE', 'ACTIVO'].includes(c.status?.toUpperCase()));
    const totalLocals = items.reduce((sum, c) => sum + (c.locals_count || 0), 0);

    // State for showing more contracts
    const [showAllInactive, setShowAllInactive] = React.useState(false);
    const INITIAL_INACTIVE_LIMIT = 3;
    const displayedInactive = showAllInactive ? inactiveContracts : inactiveContracts.slice(0, INITIAL_INACTIVE_LIMIT);

    return (
        <AppLayout>
            <div className="container mx-auto max-w-6xl px-4 py-8">
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
                            <h1 className="text-4xl font-bold tracking-tight">Mis contratos</h1>
                            <p className="text-muted-foreground mt-2">Tus contratos de arrendamiento y locales</p>
                        </div>
                        <div className="flex gap-3">
                            <Badge variant="secondary" className="px-4 py-2 text-lg">
                                {items.length} contrato{items.length !== 1 ? 's' : ''}
                            </Badge>
                            <Badge variant="outline" className="px-4 py-2 text-lg">
                                {totalLocals} local{totalLocals !== 1 ? 'es' : ''}
                            </Badge>
                        </div>
                    </div>
                </div>

                {/* Active contracts */}
                {activeContracts.length > 0 && (
                    <div className="mb-8">
                        <div className="mb-4 flex items-center gap-3">
                            <CheckCircle2 className="h-5 w-5 text-green-600" />
                            <h2 className="text-2xl font-semibold">Contratos activos</h2>
                            <Badge className="bg-green-600">{activeContracts.length}</Badge>
                        </div>
                        <div className="grid gap-6 md:grid-cols-2">
                            {activeContracts.map((c) => {
                                const statusCfg = getStatusConfig(c.status);
                                const Icon = statusCfg.icon;
                                return (
                                    <Card key={c.id} className={`transition-shadow hover:shadow-lg ${statusCfg.border}`}>
                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className={`h-12 w-12 rounded-full ${statusCfg.bg} flex items-center justify-center`}>
                                                        <FileText className={`h-6 w-6 ${statusCfg.color}`} />
                                                    </div>
                                                    <div>
                                                        <CardTitle className="text-xl">
                                                            <Link href={`/portal/contratos/${c.id}`} className="hover:underline">
                                                                {c.number || `Contrato #${c.id}`}
                                                            </Link>
                                                        </CardTitle>
                                                        <CardDescription className="mt-1 flex items-center gap-2">
                                                            <Badge className={`${statusCfg.bg} ${statusCfg.color} border-0`}>
                                                                <Icon className="mr-1 h-3 w-3" />
                                                                {statusCfg.label}
                                                            </Badge>
                                                        </CardDescription>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <div className="text-muted-foreground mb-1 text-xs">Inicio</div>
                                                    <div className="flex items-center gap-2">
                                                        <Calendar className="text-muted-foreground h-3 w-3" />
                                                        <span className="text-sm font-medium">{fmtDate(c.start_date)}</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="text-muted-foreground mb-1 text-xs">Fin</div>
                                                    <div className="flex items-center gap-2">
                                                        <Calendar className="text-muted-foreground h-3 w-3" />
                                                        <span className="text-sm font-medium">{c.end_date ? fmtDate(c.end_date) : 'Indefinido'}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            {c.locals_label && (
                                                <div className={`rounded-lg p-3 ${statusCfg.bg} border ${statusCfg.border}`}>
                                                    <div className="text-muted-foreground mb-1 text-xs">Locales asociados</div>
                                                    <div className="flex items-center gap-2">
                                                        <MapPin className={`h-4 w-4 ${statusCfg.color}`} />
                                                        <span className="text-sm font-medium">{c.locals_label}</span>
                                                    </div>
                                                    {typeof c.locals_count === 'number' && c.locals_count > 0 && (
                                                        <Badge variant="outline" className="mt-2">
                                                            {c.locals_count} local{c.locals_count !== 1 ? 'es' : ''}
                                                        </Badge>
                                                    )}
                                                </div>
                                            )}

                                            <Link href={`/portal/contratos/${c.id}`}>
                                                <Button variant="outline" size="sm" className="w-full">
                                                    Ver detalles →
                                                </Button>
                                            </Link>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Inactive contracts */}
                {inactiveContracts.length > 0 && (
                    <div>
                        <div className="mb-4 flex items-center gap-3">
                            <Clock className="text-muted-foreground h-5 w-5" />
                            <h2 className="text-2xl font-semibold">Otros contratos</h2>
                            <Badge variant="outline">{inactiveContracts.length}</Badge>
                            {inactiveContracts.length > INITIAL_INACTIVE_LIMIT && !showAllInactive && (
                                <Badge variant="secondary" className="text-xs">
                                    Mostrando {INITIAL_INACTIVE_LIMIT}
                                </Badge>
                            )}
                        </div>
                        <div className="grid gap-4">
                            {displayedInactive.map((c) => {
                                const statusCfg = getStatusConfig(c.status);
                                const Icon = statusCfg.icon;
                                return (
                                    <Card key={c.id} className="transition-shadow hover:shadow-md">
                                        <CardContent className="pt-6">
                                            <div className="flex items-center justify-between">
                                                <div className="flex flex-1 items-center gap-4">
                                                    <div
                                                        className={`h-10 w-10 rounded-full ${statusCfg.bg} flex flex-shrink-0 items-center justify-center`}
                                                    >
                                                        <Icon className={`h-5 w-5 ${statusCfg.color}`} />
                                                    </div>
                                                    <div className="flex-1">
                                                        <div className="mb-1 flex items-center gap-3">
                                                            <Link href={`/portal/contratos/${c.id}`} className="font-semibold hover:underline">
                                                                {c.number || `Contrato #${c.id}`}
                                                            </Link>
                                                            <Badge className={`${statusCfg.bg} ${statusCfg.color} border-0 text-xs`}>
                                                                {statusCfg.label}
                                                            </Badge>
                                                        </div>
                                                        <div className="text-muted-foreground flex items-center gap-4 text-sm">
                                                            <span>
                                                                {fmtDate(c.start_date)} - {c.end_date ? fmtDate(c.end_date) : 'Indefinido'}
                                                            </span>
                                                            {c.locals_label && (
                                                                <span className="flex items-center gap-1">
                                                                    <MapPin className="h-3 w-3" />
                                                                    {c.locals_label}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                                <Link href={`/portal/contratos/${c.id}`}>
                                                    <Button variant="ghost" size="sm">
                                                        Ver →
                                                    </Button>
                                                </Link>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>

                        {/* Show more button */}
                        {inactiveContracts.length > INITIAL_INACTIVE_LIMIT && (
                            <div className="mt-6 text-center">
                                <Button variant="outline" size="lg" onClick={() => setShowAllInactive(!showAllInactive)} className="gap-2">
                                    {showAllInactive ? <>Ver menos</> : <>Ver todos ({inactiveContracts.length - INITIAL_INACTIVE_LIMIT} más)</>}
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {/* Empty state */}
                {items.length === 0 && (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <div className="flex flex-col items-center gap-4">
                                <div className="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100">
                                    <FileText className="text-muted-foreground h-10 w-10" />
                                </div>
                                <div>
                                    <h3 className="mb-2 text-xl font-semibold">No hay contratos registrados</h3>
                                    <p className="text-muted-foreground max-w-md">
                                        No se encontraron contratos asociados a tu cuenta. Si crees que esto es un error, contacta con administración.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Info card */}
                {items.length > 0 && (
                    <Card className="mt-8 border-blue-200 bg-blue-50/30">
                        <CardContent className="pt-6">
                            <div className="flex items-start gap-4">
                                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                                    <Building2 className="h-5 w-5 text-blue-600" />
                                </div>
                                <div className="flex-1">
                                    <h3 className="mb-2 font-semibold">Sobre tus contratos</h3>
                                    <p className="text-muted-foreground text-sm">
                                        Los contratos definen las condiciones de arrendamiento de tus locales. Los cargos mensuales (condominio y
                                        alquiler) se generan automáticamente en base a estos contratos. Si tienes dudas sobre los términos de tu
                                        contrato, contacta con administración.
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
