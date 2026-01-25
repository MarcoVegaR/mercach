import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { PaymentApplyAdmin } from '@/components/payments/PaymentApplyAdmin';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Ban, Calendar, CheckCircle2, CreditCard, FileText, Pencil, RotateCcw, Trash2 } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

// -----------------------------------------------------------------------------
// Types
// -----------------------------------------------------------------------------
interface Item {
    id: number | string;
    [key: string]: unknown;
}

interface AllocationRow {
    charge_id: number;
    amount_bs_minor: number;
    created_at?: string;
    currency?: string;
    amount_minor?: number;
    period?: string;
    due_on?: string;
    local_label?: string | null;
    kind?: string;
}

interface ShowProps extends PageProps {
    item: Item;
    hasEditRoute?: boolean;
    can_edit?: boolean;
    customer_credit_bs_minor?: number;
    allocations?: AllocationRow[];
    receipt?: {
        id: number | string;
        receipt_number?: string;
        issued_at?: string;
        download_url?: string;
        verify_url?: string;
    };
}

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------
function formatDate(date?: string | null): string {
    if (!date) return '—';
    try {
        // Parse as local noon to avoid timezone shift (YYYY-MM-DD interpreted as UTC midnight)
        const d = date.includes('T') ? new Date(date) : new Date(date + 'T12:00:00');
        return d.toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
    } catch {
        return '—';
    }
}

function formatMinor(v?: number | null): string {
    const n = Number(v ?? 0);
    return (n / 100).toFixed(2);
}

function formatShortDate(s?: string | null): string {
    if (!s) return '—';
    try {
        const str = String(s);
        // Parse as local noon to avoid timezone shift (YYYY-MM-DD interpreted as UTC midnight)
        const d = str.includes('T') ? new Date(str) : new Date(str + 'T12:00:00');
        return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch {
        return String(s);
    }
}

function getStatusColor(status: string): string {
    switch (status) {
        case 'APPLIED':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'CONFIRMED':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'REGISTERED':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'VOID':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        default:
            return 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300';
    }
}

function getStatusLabel(status: string): string {
    switch (status) {
        case 'APPLIED':
            return 'Conciliado';
        case 'CONFIRMED':
            return 'Confirmado';
        case 'REGISTERED':
            return 'Registrado';
        case 'VOID':
            return 'Anulado';
        default:
            return status;
    }
}

/** Formatea tipo de cargo a texto amigable */
function formatChargeKind(kind?: string | null): string {
    if (!kind) return '—';
    const map: Record<string, string> = {
        RENT_EUR_M2: 'Alquiler (m²)',
        RENT_EUR_FIXED: 'Alquiler fijo',
        CONDO_USD: 'Condominio',
        MULTA: 'Multa',
        MORA: 'Mora',
        EXTRA: 'Cargo extra',
    };
    return (
        map[kind] ||
        kind
            .replace(/_/g, ' ')
            .toLowerCase()
            .replace(/\b\w/g, (c) => c.toUpperCase())
    );
}

/** Limpia etiqueta de local duplicada (ej: "J-09 • J-09" -> "J-09") */
function cleanLocalLabel(label?: string | null): string {
    if (!label) return '—';
    const parts = label
        .split(/\s*[•·-]\s*/)
        .map((p) => p.trim())
        .filter(Boolean);
    const unique = [...new Set(parts)];
    return unique.join(' - ') || label;
}

/** Formatea periodo a texto corto (ej: "2025-05" -> "May 2025") */
function formatPeriod(period?: string | null): string {
    if (!period) return '—';
    try {
        const [year, month] = period.split('-');
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return `${months[parseInt(month, 10) - 1]} ${year}`;
    } catch {
        return period;
    }
}

// -----------------------------------------------------------------------------
// Component
// -----------------------------------------------------------------------------
export default function ShowPage() {
    const { item, hasEditRoute, can_edit, customer_credit_bs_minor, allocations = [], receipt } = usePage<ShowProps>().props;
    const { flash } = usePage<{ flash?: { success?: string; error?: string; warning?: string; info?: string } }>().props;
    const { auth } = usePage<{ auth?: { can?: Record<string, boolean> } }>().props;

    // Flash messages
    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);

    const payment = item as any;
    const status = String(payment.status ?? '');
    const isConfirmed = status === 'CONFIRMED';
    const isApplied = status === 'APPLIED';
    const isVoid = status === 'VOID';
    const method = String(payment.method ?? '').toUpperCase();
    const canVoid = Boolean(auth?.can?.['catalogs.payment.void']);
    const canUpdate = Boolean(auth?.can?.['catalogs.payment.update']);
    const canDelete = Boolean(auth?.can?.['catalogs.payment.delete']);
    const isVoidEligible = isApplied && (method === 'DEB' || method === 'EXO');
    const appliedMinor = Number(payment.applied_bs_minor ?? 0);
    const availableMinor = Number(payment.available_bs_minor ?? 0);
    const creditMinor = Number(customer_credit_bs_minor ?? 0);

    const [rebookOpen, setRebookOpen] = React.useState(false);
    const [rebookPaidOn, setRebookPaidOn] = React.useState<string>(String(payment.paid_on ?? ''));
    const [rebookReason, setRebookReason] = React.useState<string>('');
    const [rebookPending, setRebookPending] = React.useState(false);

    React.useEffect(() => {
        setRebookPaidOn(String(payment.paid_on ?? ''));
    }, [payment.paid_on]);

    // Tab state
    const initialTab = React.useMemo(() => {
        const p = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
        const t = p?.get('tab') || '';
        if (t === 'apply') {
            return isConfirmed ? 'apply' : isApplied ? 'allocations' : 'details';
        }
        if (t === 'allocations') return 'allocations';
        return isApplied ? 'allocations' : 'details';
    }, [isConfirmed, isApplied]);
    const [tab, setTab] = React.useState<string>(initialTab);

    const breadcrumbs = [
        { title: 'Pagos', href: '/payments' },
        { title: String(payment.id), href: '' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Pago #${String(payment.id)}`} />

            <ShowLayout
                header={
                    <div className="flex items-center gap-4">
                        <Link href="/payments" className="text-muted-foreground hover:text-foreground transition-colors">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div className="flex-1">
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight">Pago #{String(payment.id)}</h1>
                                <Badge className={getStatusColor(status)}>{getStatusLabel(status)}</Badge>
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {payment.debtor_name ?? payment.debtor_type} • {formatDate(payment.paid_on)}
                            </p>
                        </div>
                    </div>
                }
                actions={
                    <div className="flex gap-2">
                        {hasEditRoute && can_edit && canUpdate && (
                            <Button variant="outline" onClick={() => router.visit(`/payments/${item.id}/edit`)}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Editar
                            </Button>
                        )}
                        {canVoid && isVoidEligible && (
                            <ConfirmAlert
                                trigger={
                                    <Button variant="destructive" type="button">
                                        <Ban className="mr-2 h-4 w-4" />
                                        Anular
                                    </Button>
                                }
                                title={`Anular pago #${String(payment.id)}`}
                                description={
                                    'Esta acción revierte recibos, cruces y créditos asociados. Luego deberás registrar/aplicar nuevamente con la fecha correcta.'
                                }
                                confirmLabel="Anular pago"
                                requireReason
                                reasonLabel="Motivo de anulación"
                                reasonPlaceholder="Ej: Fecha de pago incorrecta (tasa cambiaria)."
                                reasonMinLength={6}
                                onConfirm={async (reason) => {
                                    await new Promise<void>((resolve, reject) => {
                                        router.post(
                                            `/payments/${item.id}/void`,
                                            { reason: reason ?? '' },
                                            {
                                                preserveState: false,
                                                preserveScroll: true,
                                                onSuccess: () => resolve(),
                                                onError: () => reject(new Error('void_failed')),
                                            },
                                        );
                                    });
                                }}
                                toastMessages={{
                                    loading: 'Anulando pago…',
                                    success: 'Pago anulado (VOID).',
                                    error: 'No se pudo anular el pago.',
                                }}
                            />
                        )}
                        {canVoid && isVoidEligible && (
                            <>
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => {
                                        setRebookReason('');
                                        setRebookPaidOn(String(payment.paid_on ?? ''));
                                        setRebookOpen(true);
                                    }}
                                >
                                    <RotateCcw className="mr-2 h-4 w-4" />
                                    Anular + Re-registrar
                                </Button>

                                <AlertDialog open={rebookOpen} onOpenChange={(open) => !rebookPending && setRebookOpen(open)}>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>{`Anular + Re-registrar pago #${String(payment.id)}`}</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Esta acción anula el pago, revierte recibos/cruces/créditos y crea un nuevo pago con la fecha
                                                corregida.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>

                                        <div className="space-y-4 py-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="rebook_paid_on">Nueva fecha de pago</Label>
                                                <Input
                                                    id="rebook_paid_on"
                                                    type="date"
                                                    value={rebookPaidOn}
                                                    onChange={(e) => setRebookPaidOn(e.target.value)}
                                                    disabled={rebookPending}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="rebook_reason">Motivo</Label>
                                                <Input
                                                    id="rebook_reason"
                                                    value={rebookReason}
                                                    onChange={(e) => setRebookReason(e.target.value)}
                                                    placeholder="Ej: Fecha de pago incorrecta (tasa cambiaria)."
                                                    disabled={rebookPending}
                                                />
                                            </div>
                                            <div className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                                                Confirma que la nueva fecha corresponde al movimiento real en banco. Este proceso genera un nuevo pago
                                                en estado CONFIRMED listo para aplicar.
                                            </div>
                                        </div>

                                        <AlertDialogFooter>
                                            <AlertDialogCancel asChild>
                                                <Button variant="secondary" disabled={rebookPending}>
                                                    Cancelar
                                                </Button>
                                            </AlertDialogCancel>
                                            <AlertDialogAction asChild>
                                                <Button
                                                    variant="destructive"
                                                    disabled={rebookPending || !rebookPaidOn}
                                                    isLoading={rebookPending}
                                                    loadingText="Procesando…"
                                                    onClick={async (e) => {
                                                        e.preventDefault();
                                                        if (!rebookPaidOn) {
                                                            toast.error('Debes indicar la nueva fecha de pago.');
                                                            return;
                                                        }
                                                        setRebookPending(true);
                                                        const t = toast.loading('Anulando y re-registrando…');
                                                        try {
                                                            await new Promise<void>((resolve, reject) => {
                                                                router.post(
                                                                    `/payments/${item.id}/void-rebook`,
                                                                    { paid_on: rebookPaidOn, reason: rebookReason },
                                                                    {
                                                                        preserveState: false,
                                                                        preserveScroll: true,
                                                                        onSuccess: () => resolve(),
                                                                        onError: () => reject(new Error('void_rebook_failed')),
                                                                    },
                                                                );
                                                            });
                                                            toast.success('Pago anulado y re-registrado.', { id: t });
                                                            setRebookOpen(false);
                                                        } catch {
                                                            toast.error('No se pudo anular y re-registrar.', { id: t });
                                                        } finally {
                                                            setRebookPending(false);
                                                        }
                                                    }}
                                                >
                                                    Anular + crear nuevo pago
                                                </Button>
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </>
                        )}
                        {status === 'REGISTERED' && canUpdate && (
                            <Button
                                variant="secondary"
                                onClick={async () => {
                                    await new Promise<void>((resolve, reject) => {
                                        router.post(
                                            `/payments/${item.id}/verify`,
                                            {},
                                            {
                                                preserveState: false,
                                                preserveScroll: true,
                                                onSuccess: () => resolve(),
                                                onError: () => reject(new Error('verify_failed')),
                                            },
                                        );
                                    });
                                }}
                            >
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                Verificar
                            </Button>
                        )}
                        {canDelete && (
                            <ConfirmAlert
                                trigger={
                                    <Button variant="destructive" type="button">
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Eliminar
                                    </Button>
                                }
                                title="Eliminar pago"
                                description={`¿Está seguro de eliminar el pago #${String(payment.id)}? Esta acción no se puede deshacer.`}
                                confirmLabel="Eliminar"
                                onConfirm={async () => {
                                    await new Promise<void>((resolve, reject) => {
                                        router.delete(`/payments/${item.id}`, {
                                            preserveState: false,
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                resolve();
                                                router.visit('/payments');
                                            },
                                            onError: () => reject(new Error('delete_failed')),
                                        });
                                    });
                                }}
                            />
                        )}
                    </div>
                }
                aside={
                    <Card className="sticky top-4">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Resumen</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Status */}
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Estado</span>
                                <Badge className={getStatusColor(status)}>{getStatusLabel(status)}</Badge>
                            </div>

                            {/* Gateway response */}
                            {payment.gateway_resp_code && (
                                <div className="space-y-1">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground text-sm">Código banco</span>
                                        <Badge variant={payment.gateway_resp_code === '00' ? 'default' : 'destructive'} className="font-mono">
                                            {String(payment.gateway_resp_code)}
                                        </Badge>
                                    </div>
                                    {payment.gateway_message && (
                                        <p className="text-xs text-slate-500 dark:text-slate-400">{String(payment.gateway_message)}</p>
                                    )}
                                </div>
                            )}

                            <div className="border-t pt-3" />

                            {/* Amounts */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Monto</span>
                                    <span className="font-medium">Bs {formatMinor(payment.amount_bs_minor)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Aplicado</span>
                                    <span className="text-sm">Bs {formatMinor(appliedMinor)}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Disponible</span>
                                    <span className={`font-medium ${availableMinor > 0 ? 'text-green-600' : ''}`}>
                                        Bs {formatMinor(availableMinor)}
                                    </span>
                                </div>
                                {creditMinor > 0 && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground text-sm">Crédito a favor</span>
                                        <span className="font-medium text-amber-600">Bs {formatMinor(creditMinor)}</span>
                                    </div>
                                )}
                            </div>

                            {/* Receipt info */}
                            {receipt && receipt.receipt_number && (
                                <>
                                    <div className="border-t pt-3" />
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <FileText className="h-4 w-4 text-green-600" />
                                            <span className="text-sm font-medium">Recibo #{String(receipt.receipt_number)}</span>
                                        </div>
                                        <div className="text-muted-foreground text-xs">Emitido: {formatShortDate(receipt.issued_at)}</div>
                                        <div className="flex gap-2">
                                            {receipt.download_url && (
                                                <a
                                                    href={receipt.download_url}
                                                    className="text-xs text-blue-600 underline hover:text-blue-700"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Descargar PDF
                                                </a>
                                            )}
                                            {receipt.verify_url && (
                                                <a
                                                    href={receipt.verify_url}
                                                    className="text-xs text-blue-600 underline hover:text-blue-700"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Verificar
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                </>
                            )}

                            {isVoid && (
                                <>
                                    <div className="border-t pt-3" />
                                    <div className="space-y-1">
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground text-sm">Anulado</span>
                                            <span className="text-sm font-medium">{formatShortDate(payment.voided_at)}</span>
                                        </div>
                                        {payment.void_reason && (
                                            <p className="text-xs text-slate-500 dark:text-slate-400">{String(payment.void_reason)}</p>
                                        )}
                                    </div>
                                </>
                            )}

                            {/* Status message */}
                            {isApplied && (
                                <div className="rounded-lg bg-green-50 p-3 text-center text-sm text-green-700 dark:bg-green-900/20 dark:text-green-300">
                                    ✓ Pago conciliado
                                </div>
                            )}
                            {isVoid && (
                                <div className="rounded-lg bg-red-50 p-3 text-center text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                                    ✕ Pago anulado (VOID)
                                </div>
                            )}
                            {isConfirmed && (
                                <div className="rounded-lg bg-blue-50 p-3 text-center text-sm text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">
                                    Listo para aplicar
                                </div>
                            )}
                            {status === 'REGISTERED' && (
                                <div className="rounded-lg bg-amber-50 p-3 text-center text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                    Pendiente de verificación
                                </div>
                            )}
                        </CardContent>
                    </Card>
                }
            >
                <Tabs value={tab} onValueChange={(v) => setTab(v)}>
                    <TabsList className="mb-4">
                        <TabsTrigger value="details">
                            <CreditCard className="mr-2 h-4 w-4" />
                            Detalle
                        </TabsTrigger>
                        <TabsTrigger value="apply" disabled={!isConfirmed || !canUpdate}>
                            <CheckCircle2 className="mr-2 h-4 w-4" />
                            Aplicar
                        </TabsTrigger>
                        <TabsTrigger value="allocations">
                            <FileText className="mr-2 h-4 w-4" />
                            Asignaciones
                            {allocations.length > 0 && (
                                <Badge variant="secondary" className="ml-2">
                                    {allocations.length}
                                </Badge>
                            )}
                        </TabsTrigger>
                    </TabsList>

                    {/* Details Tab */}
                    <TabsContent value="details">
                        <ShowSection id="overview" title="Información del Pago">
                            <Card>
                                <CardContent className="pt-6">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Tipo de deudor</dt>
                                            <dd className="mt-1 text-sm">{String(payment.debtor_type ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Deudor</dt>
                                            <dd className="mt-1 text-sm font-medium">{String(payment.debtor_name ?? payment.debtor_id ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Método</dt>
                                            <dd className="mt-1 text-sm">{String(payment.method ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Cuenta receptora</dt>
                                            <dd className="mt-1 text-sm">{String(payment.company_bank_account_id ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Banco origen</dt>
                                            <dd className="mt-1 text-sm">{String(payment.origin_bank_name ?? payment.origin_bank_id ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Referencia</dt>
                                            <dd className="mt-1 font-mono text-sm">{String(payment.reference ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Documento pagador</dt>
                                            <dd className="mt-1 text-sm">
                                                {(() => {
                                                    const t = payment.document_type_code || (payment.payer_document_type ?? '').toString();
                                                    const n = (payment.payer_document_number ?? '').toString();
                                                    const sep = t ? '-' : '';
                                                    return t || n ? `${t}${sep}${n}` : '—';
                                                })()}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Fecha de pago</dt>
                                            <dd className="mt-1 text-sm">{formatDate(payment.paid_on)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Tasa FX</dt>
                                            <dd className="mt-1 text-sm">{String(payment.fx_rate_id ?? '—')}</dd>
                                        </div>
                                    </dl>
                                </CardContent>
                            </Card>
                        </ShowSection>

                        <ShowSection id="metadata" title="Metadatos">
                            <Card>
                                <CardContent className="pt-6">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                                Creado
                                            </dt>
                                            <dd className="mt-1 text-sm">{formatDate(payment.created_at)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Calendar className="mr-1 inline h-4 w-4 text-blue-500" />
                                                Última actualización
                                            </dt>
                                            <dd className="mt-1 text-sm">{formatDate(payment.updated_at)}</dd>
                                        </div>
                                    </dl>
                                </CardContent>
                            </Card>
                        </ShowSection>
                    </TabsContent>

                    {/* Apply Tab - New Modern Component */}
                    <TabsContent value="apply">
                        <PaymentApplyAdmin
                            payment={{
                                id: Number(payment.id),
                                paid_on: String(payment.paid_on ?? ''),
                                debtor_type: String(payment.debtor_type ?? ''),
                                debtor_id: Number(payment.debtor_id ?? 0),
                                local_id: payment.local_id ? Number(payment.local_id) : undefined,
                                status: status,
                                amount_bs_minor: Number(payment.amount_bs_minor ?? 0),
                                applied_bs_minor: appliedMinor,
                                available_bs_minor: availableMinor,
                            }}
                            customerCreditBsMinor={creditMinor}
                        />
                    </TabsContent>

                    {/* Allocations Tab */}
                    <TabsContent value="allocations">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <FileText className="h-5 w-5" />
                                    Asignaciones realizadas
                                    {allocations.length > 0 && <Badge variant="secondary">{allocations.length}</Badge>}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {allocations.length > 0 ? (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="text-muted-foreground p-3 text-left font-medium">Local</th>
                                                    <th className="text-muted-foreground p-3 text-left font-medium">Tipo</th>
                                                    <th className="text-muted-foreground p-3 text-left font-medium">Periodo</th>
                                                    <th className="text-muted-foreground p-3 text-left font-medium">Vence</th>
                                                    <th className="text-muted-foreground p-3 text-left font-medium">Moneda</th>
                                                    <th className="text-muted-foreground p-3 text-right font-medium">Monto</th>
                                                    <th className="text-muted-foreground p-3 text-right font-medium">Aplicado (Bs)</th>
                                                    <th className="text-muted-foreground p-3 text-left font-medium">Fecha</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {allocations.map((a, idx) => (
                                                    <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                                        <td className="p-3 font-medium">{cleanLocalLabel(a.local_label)}</td>
                                                        <td className="p-3 text-slate-600 dark:text-slate-400">{formatChargeKind(a.kind)}</td>
                                                        <td className="p-3">{formatPeriod(a.period)}</td>
                                                        <td className="p-3">{a.due_on ? formatShortDate(a.due_on) : '—'}</td>
                                                        <td className="p-3">{a.currency ?? '—'}</td>
                                                        <td className="p-3 text-right font-mono">{((a.amount_minor ?? 0) / 100).toFixed(2)}</td>
                                                        <td className="p-3 text-right font-mono font-medium text-green-600">
                                                            Bs {formatMinor(a.amount_bs_minor)}
                                                        </td>
                                                        <td className="p-3 text-slate-500">{a.created_at ? formatShortDate(a.created_at) : '—'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                            <tfoot className="border-t bg-slate-50 dark:bg-slate-800/50">
                                                <tr>
                                                    <td colSpan={6} className="p-3 text-right font-medium">
                                                        Total aplicado:
                                                    </td>
                                                    <td className="p-3 text-right font-mono text-lg font-bold text-green-600">
                                                        Bs {formatMinor(appliedMinor)}
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="py-12 text-center">
                                        <FileText className="mx-auto mb-4 h-12 w-12 text-slate-300" />
                                        <p className="text-muted-foreground">No hay asignaciones realizadas</p>
                                        {isConfirmed && (
                                            <Button variant="outline" className="mt-4" onClick={() => setTab('apply')}>
                                                Ir a aplicar pago
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </ShowLayout>
        </AppLayout>
    );
}
