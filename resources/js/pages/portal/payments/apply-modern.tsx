import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowRight, Check, CheckCircle2, ChevronDown, ChevronUp, CreditCard, DollarSign, Loader2, MapPin, Wallet } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

// -----------------------------------------------------------------------------
// Types
// -----------------------------------------------------------------------------
type Charge = {
    charge_id: number;
    period: string;
    due_on: string;
    outstanding_bs_minor: number;
    kind: string;
    local_id?: number;
    local_label?: string | null;
};

type PaymentVM = {
    id: number;
    paid_on?: string;
    available_bs_minor: number;
};

type Props = {
    payment: PaymentVM;
    customer_credit_bs_minor?: number;
    flash?: { success?: string; error?: string; warning?: string; info?: string };
};

type LocalGroup = {
    id: number;
    label: string;
    charges: Charge[];
    totalDebt: number;
    amountToApply: number;
    isSelected: boolean;
    coveragePercent: number;
    status: 'full' | 'partial' | 'none';
};

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------
function fmtMinor(minor?: number | null): string {
    if (typeof minor !== 'number') return '—';
    return `Bs ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fmtDate(dateStr?: string): string {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

function formatPeriod(period: string): string {
    try {
        const [year, month] = period.split('-');
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return `${months[parseInt(month, 10) - 1]} ${year}`;
    } catch {
        return period;
    }
}

function formatChargeKind(kind: string): string {
    const k = kind.toUpperCase();
    // Match common patterns
    if (k.includes('RENT') || k.includes('ALQUILER')) return 'Alquiler';
    if (k.includes('CONDO') || k.includes('CONDOMINIO')) return 'Condominio';
    if (k.includes('SERVICE') || k.includes('SERVICIO')) return 'Servicio';
    if (k.includes('MORA') || k.includes('LATE') || k.includes('INTEREST')) return 'Intereses';
    if (k.includes('PARKING') || k.includes('ESTACION')) return 'Estacionamiento';
    if (k.includes('WATER') || k.includes('AGUA')) return 'Agua';
    if (k.includes('ELECTRIC') || k.includes('LUZ')) return 'Electricidad';
    if (k.includes('MAINT') || k.includes('MANT')) return 'Mantenimiento';
    // Fallback: clean up technical codes
    return (
        kind
            .replace(/_/g, ' ')
            .replace(/EUR|USD|M2|FIXED/gi, '')
            .trim() || 'Cargo'
    );
}

function cleanLocalLabel(label: string | null | undefined): string {
    if (!label) return 'Sin local';
    // Remove duplicates like "D-34 • D-34" → "D-34"
    const parts = label
        .split(/\s*[•·-]\s*/)
        .map((p) => p.trim())
        .filter(Boolean);
    const unique = [...new Set(parts)];
    return unique.join(' - ') || label;
}

// -----------------------------------------------------------------------------
// Component
// -----------------------------------------------------------------------------
export default function PortalPaymentsApplyModern() {
    const { payment, customer_credit_bs_minor = 0, flash } = usePage<Props>().props;

    // State
    const [loading, setLoading] = React.useState(true);
    const [submitting, setSubmitting] = React.useState(false);
    const [charges, setCharges] = React.useState<Charge[]>([]);
    const [selectedLocalIds, setSelectedLocalIds] = React.useState<Set<number>>(new Set());
    const [useCredit, setUseCredit] = React.useState(customer_credit_bs_minor > 0);
    const [formErrors, setFormErrors] = React.useState<string[]>([]);
    const [expandedLocals, setExpandedLocals] = React.useState<Set<number>>(new Set());

    // Flash messages
    const successMessage = flash?.success;
    const errorMessage = flash?.error;

    React.useEffect(() => {
        if (successMessage) {
            toast.success(successMessage);
        }
    }, [successMessage]);

    // Calculate available funds
    const totalAvailable = payment.available_bs_minor + (useCredit ? customer_credit_bs_minor : 0);

    // Fetch charges on mount
    React.useEffect(() => {
        const fetchCharges = async () => {
            setLoading(true);
            try {
                const res = await fetch(`/portal/pagos/${payment.id}/open-charges?overdue_only=0`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const json = await res.json();
                const chargesData: Charge[] = Array.isArray(json.items) ? json.items : [];
                setCharges(chargesData);

                // Select all locals by default
                const localsSet = new Set<number>();
                for (const c of chargesData) {
                    localsSet.add(c.local_id ?? 0);
                }
                setSelectedLocalIds(localsSet);
            } catch {
                setFormErrors(['No se pudieron cargar las deudas. Intenta de nuevo.']);
            } finally {
                setLoading(false);
            }
        };
        fetchCharges();
    }, [payment.id]);

    // Group charges by local and calculate FIFO allocations
    const localGroups = React.useMemo((): LocalGroup[] => {
        const groups = new Map<number, LocalGroup>();

        // Group charges by local
        for (const charge of charges) {
            const lid = charge.local_id ?? 0;
            const existing = groups.get(lid);
            if (existing) {
                existing.charges.push(charge);
                existing.totalDebt += charge.outstanding_bs_minor;
            } else {
                groups.set(lid, {
                    id: lid,
                    label: cleanLocalLabel(charge.local_label) || (lid ? `Local ${lid}` : 'Otros cargos'),
                    charges: [charge],
                    totalDebt: charge.outstanding_bs_minor,
                    amountToApply: 0,
                    isSelected: selectedLocalIds.has(lid),
                    coveragePercent: 0,
                    status: 'none',
                });
            }
        }

        // Sort charges within each group by due_on (FIFO)
        for (const group of groups.values()) {
            group.charges.sort((a, b) => {
                const da = (a.due_on || a.period || '').toString();
                const db = (b.due_on || b.period || '').toString();
                return da.localeCompare(db);
            });
            group.isSelected = selectedLocalIds.has(group.id);
        }

        // Calculate FIFO allocation for selected locals
        let remaining = totalAvailable;

        // Get selected locals sorted by oldest debt first
        const selectedGroups = Array.from(groups.values())
            .filter((g) => g.isSelected)
            .sort((a, b) => {
                const oldestA = a.charges[0]?.due_on || a.charges[0]?.period || '';
                const oldestB = b.charges[0]?.due_on || b.charges[0]?.period || '';
                return oldestA.localeCompare(oldestB);
            });

        for (const group of selectedGroups) {
            if (remaining <= 0) {
                group.amountToApply = 0;
                group.status = 'none';
                group.coveragePercent = 0;
                continue;
            }

            const toApply = Math.min(remaining, group.totalDebt);
            group.amountToApply = toApply;
            remaining -= toApply;

            if (toApply >= group.totalDebt) {
                group.status = 'full';
                group.coveragePercent = 100;
            } else if (toApply > 0) {
                group.status = 'partial';
                group.coveragePercent = Math.round((toApply / group.totalDebt) * 100);
            } else {
                group.status = 'none';
                group.coveragePercent = 0;
            }
        }

        // Set unselected groups
        for (const group of groups.values()) {
            if (!group.isSelected) {
                group.amountToApply = 0;
                group.status = 'none';
                group.coveragePercent = 0;
            }
        }

        // Sort: locals receiving payment first (full > partial > none), then by label
        return Array.from(groups.values()).sort((a, b) => {
            const statusOrder = { full: 0, partial: 1, none: 2 };
            const aOrder = a.isSelected ? statusOrder[a.status] : 3;
            const bOrder = b.isSelected ? statusOrder[b.status] : 3;
            if (aOrder !== bOrder) return aOrder - bOrder;
            return a.label.localeCompare(b.label);
        });
    }, [charges, selectedLocalIds, totalAvailable]);

    // Calculate totals
    const totalDebtSelected = localGroups.filter((g) => g.isSelected).reduce((sum, g) => sum + g.totalDebt, 0);
    const totalToApply = localGroups.reduce((sum, g) => sum + g.amountToApply, 0);
    const remainingUnassigned = totalAvailable - totalToApply;

    // Toggle local selection
    const handleToggleLocal = (localId: number) => {
        setSelectedLocalIds((prev) => {
            const next = new Set(prev);
            if (next.has(localId)) {
                next.delete(localId);
            } else {
                next.add(localId);
            }
            return next;
        });
    };

    // Toggle local expansion
    const handleToggleExpand = (localId: number) => {
        setExpandedLocals((prev) => {
            const next = new Set(prev);
            if (next.has(localId)) {
                next.delete(localId);
            } else {
                next.add(localId);
            }
            return next;
        });
    };

    // Submit allocation
    const handleSubmit = () => {
        if (totalToApply <= 0) {
            toast.error('Selecciona al menos un local para aplicar el pago');
            return;
        }

        setSubmitting(true);
        setFormErrors([]);

        // Build allocation items (FIFO within each selected local)
        const items: Array<{ charge_id: number; amount_bs_minor: number }> = [];
        let remainingToAllocate = totalAvailable;

        for (const group of localGroups.filter((g) => g.isSelected)) {
            for (const charge of group.charges) {
                if (remainingToAllocate <= 0) break;
                const toApply = Math.min(remainingToAllocate, charge.outstanding_bs_minor);
                if (toApply > 0) {
                    items.push({ charge_id: charge.charge_id, amount_bs_minor: toApply });
                    remainingToAllocate -= toApply;
                }
            }
        }

        router.post(
            `/portal/pagos/${payment.id}/allocations`,
            {
                items,
                use_credit: useCredit,
            },
            {
                onError: (errors) => {
                    setFormErrors(Object.values(errors).flat() as string[]);
                    setSubmitting(false);
                },
                onSuccess: () => {
                    toast.success('Pago aplicado correctamente');
                },
            },
        );
    };

    // Loading state
    if (loading) {
        return (
            <AppLayout>
                <Head title="Aplicar pago" />
                <div className="flex min-h-[60vh] items-center justify-center">
                    <div className="text-center">
                        <Loader2 className="mx-auto mb-4 h-8 w-8 animate-spin text-blue-600" />
                        <p className="text-slate-600 dark:text-slate-400">Cargando deudas...</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    // No charges
    if (charges.length === 0) {
        return (
            <AppLayout>
                <Head title="Aplicar pago" />
                <div className="mx-auto w-full max-w-2xl px-4 py-6">
                    <Link href="/portal/pagos" className="text-muted-foreground hover:text-foreground mb-4 inline-flex items-center gap-1 text-sm">
                        ← Mis pagos
                    </Link>
                    <Card className="border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-900/20">
                        <CardContent className="py-12 text-center">
                            <CheckCircle2 className="mx-auto mb-4 h-12 w-12 text-green-600" />
                            <p className="text-lg font-bold text-green-900 dark:text-green-100">¡No tienes deudas pendientes!</p>
                            <p className="mt-2 text-sm text-green-700 dark:text-green-300">
                                Tu saldo de {fmtMinor(payment.available_bs_minor)} quedará disponible para futuras deudas.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title="Aplicar pago" />
            <div className="mx-auto w-full max-w-2xl px-4 py-6">
                {/* Header */}
                <Link href="/portal/pagos" className="text-muted-foreground hover:text-foreground mb-4 inline-flex items-center gap-1 text-sm">
                    ← Mis pagos
                </Link>
                <h1 className="mb-1 text-2xl font-bold tracking-tight">Aplicar pago a mis deudas</h1>
                <p className="text-muted-foreground mb-6 text-sm">Selecciona los locales donde quieres aplicar tu pago</p>

                {/* Error alert */}
                {(errorMessage || formErrors.length > 0) && (
                    <Alert variant="destructive" className="mb-6">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{errorMessage || formErrors.join('. ')}</AlertDescription>
                    </Alert>
                )}

                {/* Payment summary card */}
                <Card className="mb-6 border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100/50 dark:border-blue-900 dark:from-blue-900/30 dark:to-blue-800/20">
                    <CardContent className="p-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-600">
                                <Wallet className="h-6 w-6 text-white" />
                            </div>
                            <div className="flex-1">
                                <p className="text-sm text-blue-700 dark:text-blue-300">Disponible para aplicar</p>
                                <p className="text-2xl font-bold text-blue-900 dark:text-blue-100">{fmtMinor(totalAvailable)}</p>
                                {customer_credit_bs_minor > 0 && (
                                    <div className="mt-1 flex items-center gap-2">
                                        <label className="flex cursor-pointer items-center gap-2 text-xs text-blue-700 dark:text-blue-300">
                                            <input
                                                type="checkbox"
                                                checked={useCredit}
                                                onChange={(e) => setUseCredit(e.target.checked)}
                                                className="rounded border-blue-400"
                                            />
                                            Incluir crédito: {fmtMinor(customer_credit_bs_minor)}
                                        </label>
                                    </div>
                                )}
                            </div>
                            {payment.paid_on && (
                                <div className="text-right text-sm text-blue-700 dark:text-blue-300">
                                    <p>Pago del</p>
                                    <p className="font-medium">{fmtDate(payment.paid_on)}</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Local selection cards */}
                <div className="mb-6 space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-medium text-slate-600 dark:text-slate-400">Tus locales ({localGroups.length})</h2>
                        <button
                            type="button"
                            onClick={() => {
                                if (selectedLocalIds.size === localGroups.length) {
                                    setSelectedLocalIds(new Set());
                                } else {
                                    setSelectedLocalIds(new Set(localGroups.map((g) => g.id)));
                                }
                            }}
                            className="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400"
                        >
                            {selectedLocalIds.size === localGroups.length ? 'Deseleccionar todos' : 'Seleccionar todos'}
                        </button>
                    </div>

                    {localGroups.map((group) => {
                        const isExpanded = expandedLocals.has(group.id);

                        return (
                            <Card
                                key={group.id}
                                className={cn(
                                    'cursor-pointer overflow-hidden transition-all',
                                    group.isSelected &&
                                        group.status === 'full' &&
                                        'border-green-400 bg-green-50 dark:border-green-700 dark:bg-green-900/20',
                                    group.isSelected &&
                                        group.status === 'partial' &&
                                        'border-amber-400 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/20',
                                    group.isSelected &&
                                        group.status === 'none' &&
                                        'border-slate-300 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50',
                                    !group.isSelected && 'border-slate-200 bg-white opacity-60 dark:border-slate-800 dark:bg-slate-900',
                                )}
                            >
                                <CardContent className="p-0">
                                    {/* Main clickable area */}
                                    <div className="flex items-start gap-4 p-4" onClick={() => handleToggleLocal(group.id)}>
                                        {/* Selection indicator */}
                                        <div
                                            className={cn(
                                                'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md border-2 transition-colors',
                                                group.isSelected
                                                    ? 'border-green-500 bg-green-500 text-white'
                                                    : 'border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-800',
                                            )}
                                        >
                                            {group.isSelected && <Check className="h-4 w-4" />}
                                        </div>

                                        {/* Local info */}
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <MapPin className="h-4 w-4 text-slate-400" />
                                                <span className="font-semibold">{group.label}</span>
                                            </div>

                                            <div className="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">
                                                {fmtMinor(group.totalDebt)}
                                            </div>
                                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                                {group.charges.length} {group.charges.length === 1 ? 'cargo pendiente' : 'cargos pendientes'}
                                            </div>

                                            {/* Status indicator when selected */}
                                            {group.isSelected && (
                                                <div className="mt-3">
                                                    {group.status === 'full' && (
                                                        <div className="flex items-center gap-2">
                                                            <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                            <span className="text-sm font-medium text-green-700 dark:text-green-400">
                                                                Deuda cubierta totalmente
                                                            </span>
                                                        </div>
                                                    )}
                                                    {group.status === 'partial' && (
                                                        <div>
                                                            <div className="mb-1 flex items-center justify-between text-sm">
                                                                <span className="text-amber-700 dark:text-amber-400">
                                                                    Se aplicarán: {fmtMinor(group.amountToApply)}
                                                                </span>
                                                                <span className="text-amber-600">{group.coveragePercent}%</span>
                                                            </div>
                                                            <Progress value={group.coveragePercent} className="h-2 bg-amber-200" />
                                                            <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                                                Quedará pendiente: {fmtMinor(group.totalDebt - group.amountToApply)}
                                                            </p>
                                                        </div>
                                                    )}
                                                    {group.status === 'none' && group.isSelected && (
                                                        <div className="rounded-md bg-slate-100 p-2 dark:bg-slate-700/50">
                                                            <p className="text-xs text-slate-600 dark:text-slate-400">
                                                                <span className="font-medium">En espera:</span> Tu pago cubrirá primero deudas más
                                                                antiguas de otros locales. Si queda saldo, se aplicará aquí.
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Expand button */}
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                handleToggleExpand(group.id);
                                            }}
                                            className="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                                        >
                                            {isExpanded ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </button>
                                    </div>

                                    {/* Expanded charges list */}
                                    {isExpanded && (
                                        <div className="border-t bg-slate-50/50 p-3 dark:bg-slate-800/30">
                                            <p className="mb-2 text-xs font-medium text-slate-500">Detalle de cargos:</p>
                                            <div className="space-y-2">
                                                {group.charges.map((charge) => (
                                                    <div
                                                        key={charge.charge_id}
                                                        className="flex items-center justify-between rounded-md bg-white p-2 text-sm dark:bg-slate-800"
                                                    >
                                                        <div>
                                                            <span className="font-medium">{formatChargeKind(charge.kind)}</span>
                                                            <span className="mx-2 text-slate-400">•</span>
                                                            <span className="text-slate-600 dark:text-slate-400">{formatPeriod(charge.period)}</span>
                                                        </div>
                                                        <span className="font-medium">{fmtMinor(charge.outstanding_bs_minor)}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {/* Allocation summary */}
                <Card className="mb-6 bg-slate-50 dark:bg-slate-800/50">
                    <CardContent className="p-4">
                        <h3 className="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">Resumen de aplicación</h3>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Disponible</span>
                                <span className="font-medium">{fmtMinor(totalAvailable)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Deuda seleccionada</span>
                                <span className="font-medium">{fmtMinor(totalDebtSelected)}</span>
                            </div>
                            <div className="my-2 border-t dark:border-slate-700" />
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Se aplicará</span>
                                <span className="font-bold text-green-600">{fmtMinor(totalToApply)}</span>
                            </div>
                            {remainingUnassigned > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-slate-600 dark:text-slate-400">Quedará sin asignar</span>
                                    <span className="font-medium text-amber-600">{fmtMinor(remainingUnassigned)}</span>
                                </div>
                            )}
                        </div>

                        {/* Visual progress */}
                        <div className="mt-4">
                            <div className="mb-1 flex justify-between text-xs text-slate-500">
                                <span>Uso del pago</span>
                                <span>{totalAvailable > 0 ? Math.round((totalToApply / totalAvailable) * 100) : 0}%</span>
                            </div>
                            <Progress value={totalAvailable > 0 ? (totalToApply / totalAvailable) * 100 : 0} className="h-3" />
                        </div>

                        {/* Warnings */}
                        {remainingUnassigned > 0 && selectedLocalIds.size > 0 && (
                            <div className="mt-3 flex items-start gap-2 rounded-md bg-amber-100 p-2 text-xs text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                                <DollarSign className="mt-0.5 h-3 w-3 shrink-0" />
                                <span>
                                    {fmtMinor(remainingUnassigned)} quedará disponible para aplicar después.
                                    {localGroups.some((g) => !g.isSelected && g.totalDebt > 0) && ' Puedes seleccionar más locales si deseas.'}
                                </span>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Submit button */}
                <Button
                    onClick={handleSubmit}
                    disabled={submitting || totalToApply <= 0}
                    className="w-full bg-green-600 py-6 text-lg hover:bg-green-700"
                >
                    {submitting ? (
                        <>
                            <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                            Aplicando...
                        </>
                    ) : (
                        <>
                            <CreditCard className="mr-2 h-5 w-5" />
                            Aplicar {fmtMinor(totalToApply)}
                            <ArrowRight className="ml-2 h-5 w-5" />
                        </>
                    )}
                </Button>

                {totalToApply <= 0 && selectedLocalIds.size === 0 && (
                    <p className="mt-2 text-center text-sm text-slate-500">Selecciona al menos un local para continuar</p>
                )}
            </div>
        </AppLayout>
    );
}
