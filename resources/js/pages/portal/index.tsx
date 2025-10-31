import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { FileText, Handshake, Receipt, Wallet } from 'lucide-react';
import React from 'react';

type SummaryFx = {
    condo?: { currency: 'USD'; open_minor: number; overdue_minor: number };
    rent?: { currency: 'EUR'; open_minor: number; overdue_minor: number };
};
type Profile = {
    summary_bs: {
        open_bs_minor: number;
        net_due_after_credit_bs_minor: number;
        payments_available_bs_minor?: number;
        credits_open_bs_minor?: number;
    };
    summary_fx?: SummaryFx;
};

type Props = {
    user: { name: string; email: string };
    at: string;
    concessionaire?: { id: number; full_name: string } | null;
    profile?: Profile | null;
};

function fmtMinor(minor?: number | null, curr: 'USD' | 'EUR' | 'VES' = 'VES') {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: curr, minimumFractionDigits: 2 });
}

export default function PortalIndex({ user, at, concessionaire, profile }: Props) {
    const usdOpen = profile?.summary_fx?.condo?.open_minor ?? 0;
    const eurOpen = profile?.summary_fx?.rent?.open_minor ?? 0;
    const netDueBs = profile?.summary_bs?.net_due_after_credit_bs_minor ?? 0;

    return (
        <div className="container mx-auto max-w-5xl px-4 py-8">
            <div className="mb-8">
                <h1 className="text-3xl font-bold tracking-tight">Portal de Servicios</h1>
                <p className="text-muted-foreground mt-2">Bienvenido, {user?.name}</p>
                {concessionaire && (
                    <p className="mt-1 text-sm">
                        Concesionario: <span className="font-medium">{concessionaire.full_name}</span>
                    </p>
                )}
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Wallet className="h-4 w-4" /> Deuda
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="text-sm">
                            Condominio: <span className="font-medium">{fmtMinor(usdOpen, 'USD')}</span>
                        </div>
                        <div className="text-sm">
                            Alquiler: <span className="font-medium">{fmtMinor(eurOpen, 'EUR')}</span>
                        </div>
                        <div className="text-muted-foreground text-xs">Ref. VES (neto): {fmtMinor(netDueBs, 'VES')}</div>
                        <Link href="/portal/deuda" className="mt-2 inline-block">
                            <Button size="sm" variant="default">
                                Ver detalle
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Handshake className="h-4 w-4" /> Registrar/Cruzar Pagos
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Link href="/portal/pagos/nuevo" className="inline-block">
                            <Button size="sm" variant="outline">
                                Registrar Pago
                            </Button>
                        </Link>
                        <Link href="/portal/pagos" className="inline-block">
                            <Button size="sm" variant="secondary">
                                Mis Pagos
                            </Button>
                        </Link>
                        {typeof profile?.summary_bs?.payments_available_bs_minor === 'number' &&
                            profile!.summary_bs!.payments_available_bs_minor! > 0 && (
                                <div className="text-xs text-green-700">
                                    Tienes {fmtMinor(profile!.summary_bs!.payments_available_bs_minor!, 'VES')} disponibles para cruzar.
                                </div>
                            )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Receipt className="h-4 w-4" /> Recibos
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Link href="/portal/recibos" className="inline-block">
                            <Button size="sm" variant="outline">
                                Ver Recibos
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileText className="h-4 w-4" /> Contratos
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Link href="/portal/contratos" className="inline-block">
                            <Button size="sm" variant="outline">
                                Ver Contratos
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

PortalIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
