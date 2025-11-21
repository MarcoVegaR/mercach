import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, ChevronLeft, ChevronRight, Circle, Info, Sparkles } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

function fmtMinor(minor?: number | null, curr: 'USD' | 'EUR' | 'VES' = 'VES') {
    if (typeof minor !== 'number') return '—';
    return (minor / 100).toLocaleString(undefined, { style: 'currency', currency: curr, minimumFractionDigits: 2 });
}

function fmtDate(dateStr?: string) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-VE', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return dateStr;
    }
}

function getExchangeRate(charge: Charge): number {
    // Calculate exchange rate from outstanding amounts
    if (charge.outstanding_minor > 0 && charge.outstanding_bs_minor > 0) {
        return charge.outstanding_bs_minor / charge.outstanding_minor;
    }
    return 0;
}

function formatChargeTitle(charge: Charge): string {
    const kindMap: Record<string, string> = {
        RENT_EUR_M2: 'Alquiler',
        RENT_USD_M2: 'Alquiler',
        CONDO_USD_M2: 'Condominio',
        CONDO_EUR_M2: 'Condominio',
        FINE: 'Multa',
        ADJ: 'Ajuste',
    };
    return kindMap[charge.kind] || charge.kind;
}

type Charge = {
    charge_id: number;
    period: string;
    due_on: string;
    currency: string;
    amount_minor: number;
    amount_bs_minor: number;
    outstanding_minor: number;
    outstanding_bs_minor: number;
    kind: string;
};

type PaymentVM = {
    id: number;
    status: string;
    paid_on: string;
    amount_bs_minor: number;
    applied_bs_minor: number;
    available_bs_minor: number;
};

type Props = {
    payment: PaymentVM;
    customer_credit_bs_minor?: number;
    success?: string;
    error?: string;
};

type Step = 'smart-suggestion' | 'review' | 'confirm';

export default function PortalPaymentsApplyModern() {
    const { payment, customer_credit_bs_minor = 0, success, error } = usePage<Props>().props;
    const [step, setStep] = React.useState<Step>('smart-suggestion');
    const [charges, setCharges] = React.useState<Charge[]>([]);
    const [selectedCharges, setSelectedCharges] = React.useState<Set<number>>(new Set());
    const [customAmounts, setCustomAmounts] = React.useState<Record<number, number>>({});
    const [useCredit, setUseCredit] = React.useState(false);
    const [loading, setLoading] = React.useState(false);
    const [errors, setErrors] = React.useState<string[]>([]);
    const [_showAdvanced, _setShowAdvanced] = React.useState(false);

    const getCookie = (name: string) => {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return decodeURIComponent(parts.pop()!.split(';').shift() || '');
        return '';
    };

    // Fetch charges and auto-suggest
    React.useEffect(() => {
        const fetchAndSuggest = async () => {
            setLoading(true);
            try {
                // Get open charges (prioritize overdue)
                const resCharges = await fetch(`/portal/pagos/${payment.id}/open-charges?overdue_only=0`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const jsCharges = await resCharges.json();
                const chargesData: Charge[] = Array.isArray(jsCharges.items) ? jsCharges.items : [];
                setCharges(chargesData);

                // Auto-suggest using FIFO
                const resSuggest = await fetch(`/portal/pagos/${payment.id}/allocations/suggest`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                        'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ strategy: 'fifo' }),
                });
                const jsSuggest = await resSuggest.json();

                if (Array.isArray(jsSuggest.items)) {
                    const suggested = new Set<number>();
                    const amounts: Record<number, number> = {};
                    for (const it of jsSuggest.items) {
                        if (it && typeof it.charge_id === 'number' && typeof it.amount_bs_minor === 'number' && it.amount_bs_minor > 0) {
                            suggested.add(it.charge_id);
                            amounts[it.charge_id] = it.amount_bs_minor;
                        }
                    }
                    setSelectedCharges(suggested);
                    setCustomAmounts(amounts);
                }
            } catch {
                setErrors(['No se pudieron cargar las deudas. Intenta de nuevo.']);
            } finally {
                setLoading(false);
            }
        };
        fetchAndSuggest();
    }, [payment.id]);

    const totalRequested = React.useMemo(() => {
        return Array.from(selectedCharges).reduce((sum, cid) => {
            const charge = charges.find((c) => c.charge_id === cid);
            if (!charge) return sum;
            const custom = customAmounts[cid];
            if (typeof custom === 'number' && custom > 0) return sum + custom;
            return sum + charge.outstanding_bs_minor;
        }, 0);
    }, [selectedCharges, customAmounts, charges]);

    const totalAvailable = payment.available_bs_minor + (useCredit ? customer_credit_bs_minor : 0);
    const remaining = Math.max(0, totalAvailable - totalRequested);
    const progressPercent = totalAvailable > 0 ? Math.min(100, (totalRequested / totalAvailable) * 100) : 0;

    const handleToggleCharge = (cid: number) => {
        const next = new Set(selectedCharges);
        if (next.has(cid)) {
            // Deselect: remove selection and any custom amount
            next.delete(cid);
            const nextAmounts = { ...customAmounts };
            delete nextAmounts[cid];
            setCustomAmounts(nextAmounts);
            setSelectedCharges(next);
            return;
        }

        // Select: clamp the assigned amount to remaining availability
        const charge = charges.find((c) => c.charge_id === cid);
        if (!charge) return;
        // Compute requested using current state (without this new selection) to avoid async race
        const baseRequested = Array.from(selectedCharges).reduce((sum, id) => {
            const ch = charges.find((x) => x.charge_id === id);
            if (!ch) return sum;
            const custom = customAmounts[id];
            return sum + (typeof custom === 'number' && custom > 0 ? custom : ch.outstanding_bs_minor);
        }, 0);
        const totalAvail = payment.available_bs_minor + (useCredit ? customer_credit_bs_minor : 0);
        const remaining = Math.max(0, totalAvail - baseRequested);
        if (remaining <= 0) {
            toast.warning('No tienes saldo suficiente para seleccionar más deudas');
            return;
        }
        const assign = Math.min(remaining, charge.outstanding_bs_minor);
        const nextAmounts = { ...customAmounts, [cid]: assign };
        next.add(cid);
        setCustomAmounts(nextAmounts);
        setSelectedCharges(next);

        if (assign < charge.outstanding_bs_minor) {
            toast.info('Se aplicará parcialmente a esta deuda con el saldo restante');
        }
    };

    const handleApply = async () => {
        setErrors([]);
        // Build and clamp items to available balance to avoid exceeding total
        const prelim = Array.from(selectedCharges)
            .map((cid) => {
                const charge = charges.find((c) => c.charge_id === cid);
                const custom = customAmounts[cid];
                const amt = typeof custom === 'number' && custom > 0 ? custom : charge?.outstanding_bs_minor || 0;
                return { charge_id: cid, amount_bs_minor: amt };
            })
            .filter((x) => x.amount_bs_minor > 0);

        let remainingAvail = totalAvailable;
        const items: { charge_id: number; amount_bs_minor: number }[] = [];
        for (const it of prelim) {
            if (remainingAvail <= 0) break;
            const taken = Math.min(it.amount_bs_minor, remainingAvail);
            if (taken > 0) {
                items.push({ charge_id: it.charge_id, amount_bs_minor: taken });
                remainingAvail -= taken;
            }
        }

        if (items.length === 0) {
            setErrors(['Debes seleccionar al menos una deuda para aplicar el pago.']);
            return;
        }

        try {
            const key = `portal-pay-${payment.id}-${Date.now()}`;
            router.post(
                `/portal/pagos/${payment.id}/allocations`,
                { items, idempotency_key: key, use_credit: useCredit ? 1 : 0 },
                {
                    preserveScroll: true,
                    onSuccess: () => router.visit('/portal/recibos', { preserveScroll: false }),
                    onError: (errs) => setErrors(Object.values(errs).flat() as string[]),
                },
            );
        } catch {
            setErrors(['Error al aplicar el pago. Intenta de nuevo.']);
        }
    };

    const overdue = charges.filter((c) => new Date(c.due_on) < new Date(payment.paid_on));
    const current = charges.filter((c) => new Date(c.due_on) >= new Date(payment.paid_on));

    const renderStepIndicator = () => (
        <div className="mb-6 flex items-center justify-center gap-2">
            <div className={`flex items-center gap-2 ${step === 'smart-suggestion' ? 'text-blue-600' : 'text-muted-foreground'}`}>
                <div
                    className={`flex h-8 w-8 items-center justify-center rounded-full ${step === 'smart-suggestion' ? 'bg-blue-600 text-white' : 'bg-gray-200'}`}
                >
                    1
                </div>
                <span className="hidden text-sm font-medium sm:inline">Sugerencia</span>
            </div>
            <ChevronRight className="text-muted-foreground h-4 w-4" />
            <div className={`flex items-center gap-2 ${step === 'review' ? 'text-blue-600' : 'text-muted-foreground'}`}>
                <div
                    className={`flex h-8 w-8 items-center justify-center rounded-full ${step === 'review' ? 'bg-blue-600 text-white' : 'bg-gray-200'}`}
                >
                    2
                </div>
                <span className="hidden text-sm font-medium sm:inline">Revisar</span>
            </div>
            <ChevronRight className="text-muted-foreground h-4 w-4" />
            <div className={`flex items-center gap-2 ${step === 'confirm' ? 'text-blue-600' : 'text-muted-foreground'}`}>
                <div
                    className={`flex h-8 w-8 items-center justify-center rounded-full ${step === 'confirm' ? 'bg-blue-600 text-white' : 'bg-gray-200'}`}
                >
                    3
                </div>
                <span className="hidden text-sm font-medium sm:inline">Confirmar</span>
            </div>
        </div>
    );

    const renderSmartSuggestion = () => (
        <div className="space-y-6">
            <Alert>
                <Sparkles className="h-4 w-4" />
                <AlertDescription>
                    Hemos seleccionado automáticamente las deudas más antiguas para tu pago de {fmtMinor(payment.available_bs_minor)}. Puedes ajustar
                    la selección en el siguiente paso.
                </AlertDescription>
            </Alert>

            {selectedCharges.size > 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Deudas que serán cubiertas ({selectedCharges.size})</CardTitle>
                        <CardDescription>Estas deudas serán pagadas con este registro</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {charges
                                .filter((c) => selectedCharges.has(c.charge_id))
                                .slice(0, 5)
                                .map((charge) => {
                                    const exchangeRate = getExchangeRate(charge);
                                    const amountInOriginalCurrency = charge.outstanding_minor;
                                    return (
                                        <div
                                            key={charge.charge_id}
                                            className="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 p-3"
                                        >
                                            <div className="flex flex-1 items-center gap-3">
                                                <CheckCircle2 className="h-5 w-5 text-green-600" />
                                                <div>
                                                    <div className="text-sm font-medium">
                                                        {formatChargeTitle(charge)} • {fmtDate(charge.period)}
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">Vence: {fmtDate(charge.due_on)}</div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-muted-foreground text-xs">
                                                        {fmtMinor(amountInOriginalCurrency, charge.currency as any)}
                                                    </span>
                                                    <span className="font-semibold">
                                                        {fmtMinor(customAmounts[charge.charge_id] || charge.outstanding_bs_minor)}
                                                    </span>
                                                </div>
                                                <div className="text-muted-foreground text-xs">Tasa: {exchangeRate.toFixed(2)} VES</div>
                                            </div>
                                        </div>
                                    );
                                })}
                            {selectedCharges.size > 5 && (
                                <div className="text-muted-foreground text-center text-sm">+ {selectedCharges.size - 5} deudas más</div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            ) : loading ? (
                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertDescription>Cargando deudas disponibles…</AlertDescription>
                </Alert>
            ) : charges.length > 0 ? (
                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertDescription>No pudimos autoseleccionar deudas. Revisa y selecciona manualmente en el siguiente paso.</AlertDescription>
                </Alert>
            ) : (
                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertDescription>No hay deudas pendientes para aplicar este pago.</AlertDescription>
                </Alert>
            )}

            <div className="flex items-center justify-between">
                <Link href="/portal/pagos">
                    <Button variant="outline" size="lg">
                        Cancelar
                    </Button>
                </Link>
                <Button size="lg" onClick={() => setStep('review')} disabled={loading || charges.length === 0}>
                    Continuar <ChevronRight className="ml-2 h-4 w-4" />
                </Button>
            </div>
        </div>
    );

    const renderReview = () => (
        <div className="space-y-6">
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    Revisa y ajusta las deudas seleccionadas. Puedes marcar o desmarcar deudas, o personalizar montos.
                </AlertDescription>
            </Alert>

            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Saldo disponible</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-blue-600">{fmtMinor(totalAvailable)}</div>
                        <Progress value={progressPercent} className="mt-2" />
                        <div className="text-muted-foreground mt-1 text-xs">
                            {fmtMinor(totalRequested)} aplicado • {fmtMinor(remaining)} restante
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Deudas seleccionadas</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold">{selectedCharges.size}</div>
                        <div className="text-muted-foreground mt-1 text-sm">de {charges.length} deudas pendientes</div>
                    </CardContent>
                </Card>
            </div>

            {overdue.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <AlertCircle className="h-4 w-4 text-red-600" />
                            Deudas vencidas ({overdue.length})
                        </CardTitle>
                        <CardDescription>Prioriza estas deudas para evitar recargos</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {overdue.map((charge) => {
                                const isSelected = selectedCharges.has(charge.charge_id);
                                const customAmt = customAmounts[charge.charge_id];
                                const _isPartial = typeof customAmt === 'number' && customAmt > 0 && customAmt < charge.outstanding_bs_minor;
                                const appliedAmt = isSelected
                                    ? typeof customAmt === 'number' && customAmt > 0
                                        ? customAmt
                                        : charge.outstanding_bs_minor
                                    : charge.outstanding_bs_minor;
                                return (
                                    <Card
                                        key={charge.charge_id}
                                        className={`cursor-pointer transition-all ${
                                            isSelected ? 'border-blue-500 bg-blue-50/50' : 'hover:border-gray-300'
                                        }`}
                                        onClick={() => handleToggleCharge(charge.charge_id)}
                                    >
                                        <CardContent className="pt-4 pb-4">
                                            <div className="flex items-center justify-between">
                                                <div className="flex flex-1 items-center gap-3">
                                                    {isSelected ? (
                                                        <CheckCircle2 className="h-5 w-5 text-blue-600" />
                                                    ) : (
                                                        <Circle className="h-5 w-5 text-gray-400" />
                                                    )}
                                                    <div>
                                                        <div className="text-sm font-medium">
                                                            {formatChargeTitle(charge)} • {fmtDate(charge.period)}
                                                        </div>
                                                        <div className="text-xs text-red-600">Vencida desde {fmtDate(charge.due_on)}</div>
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-muted-foreground text-xs">
                                                            {fmtMinor(charge.outstanding_minor, charge.currency as any)}
                                                        </span>
                                                        <span className="font-semibold">{fmtMinor(appliedAmt)}</span>
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">
                                                        Tasa: {getExchangeRate(charge).toFixed(2)} VES
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            )}

            {current.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Deudas al día ({current.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {current.map((charge) => {
                                const isSelected = selectedCharges.has(charge.charge_id);
                                const customAmt = customAmounts[charge.charge_id];
                                const _appliedAmt = isSelected
                                    ? typeof customAmt === 'number' && customAmt > 0
                                        ? customAmt
                                        : charge.outstanding_bs_minor
                                    : charge.outstanding_bs_minor;
                                return (
                                    <Card
                                        key={charge.charge_id}
                                        className={`cursor-pointer transition-all ${
                                            isSelected ? 'border-blue-500 bg-blue-50/50' : 'hover:border-gray-300'
                                        }`}
                                        onClick={() => handleToggleCharge(charge.charge_id)}
                                    >
                                        <CardContent className="pt-4 pb-4">
                                            <div className="flex items-center justify-between">
                                                <div className="flex flex-1 items-center gap-3">
                                                    {isSelected ? (
                                                        <CheckCircle2 className="h-5 w-5 text-blue-600" />
                                                    ) : (
                                                        <Circle className="h-5 w-5 text-gray-400" />
                                                    )}
                                                    <div>
                                                        <div className="text-sm font-medium">
                                                            {formatChargeTitle(charge)} • {fmtDate(charge.period)}
                                                        </div>
                                                        <div className="text-muted-foreground text-xs">Vence: {fmtDate(charge.due_on)}</div>
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-muted-foreground text-xs">
                                                            {fmtMinor(charge.outstanding_minor, charge.currency as any)}
                                                        </span>
                                                        <span className="font-semibold">{fmtMinor(charge.outstanding_bs_minor)}</span>
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">
                                                        Tasa: {getExchangeRate(charge).toFixed(2)} VES
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            )}

            {customer_credit_bs_minor > 0 && (
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center gap-3">
                            <input
                                id="useCredit"
                                type="checkbox"
                                checked={useCredit}
                                onChange={(e) => setUseCredit(e.target.checked)}
                                className="h-4 w-4"
                            />
                            <label htmlFor="useCredit" className="flex-1 cursor-pointer text-sm">
                                Usar mi saldo a favor de {fmtMinor(customer_credit_bs_minor)} para cubrir más deudas
                            </label>
                        </div>
                    </CardContent>
                </Card>
            )}

            <div className="flex items-center justify-between">
                <Button variant="outline" size="lg" onClick={() => setStep('smart-suggestion')}>
                    <ChevronLeft className="mr-2 h-4 w-4" /> Atrás
                </Button>
                <Button size="lg" onClick={() => setStep('confirm')} disabled={selectedCharges.size === 0}>
                    Continuar <ChevronRight className="ml-2 h-4 w-4" />
                </Button>
            </div>
        </div>
    );

    const renderConfirm = () => (
        <div className="space-y-6">
            <Alert className="border-blue-200 bg-blue-50">
                <CheckCircle2 className="h-4 w-4 text-blue-600" />
                <AlertDescription>
                    Estás a punto de aplicar {fmtMinor(totalRequested)} a {selectedCharges.size} deuda(s). Esta acción generará recibos de pago.
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Resumen de aplicación</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <span className="text-muted-foreground text-sm">Saldo del pago</span>
                            <span className="font-semibold">{fmtMinor(payment.available_bs_minor)}</span>
                        </div>
                        {useCredit && customer_credit_bs_minor > 0 && (
                            <div className="flex items-center justify-between border-b pb-3">
                                <span className="text-muted-foreground text-sm">Saldo a favor aplicado</span>
                                <span className="font-semibold text-green-600">+ {fmtMinor(customer_credit_bs_minor)}</span>
                            </div>
                        )}
                        <div className="flex items-center justify-between border-b pb-3">
                            <span className="text-muted-foreground text-sm">Total a aplicar</span>
                            <span className="font-semibold text-blue-600">{fmtMinor(totalRequested)}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-sm">Saldo restante</span>
                            <span className="font-semibold">{fmtMinor(remaining)}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Deudas que serán cubiertas</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="max-h-64 space-y-2 overflow-y-auto">
                        {charges
                            .filter((c) => selectedCharges.has(c.charge_id))
                            .map((charge) => {
                                const exchangeRate = getExchangeRate(charge);
                                const amountInOriginalCurrency = charge.outstanding_minor;
                                return (
                                    <div key={charge.charge_id} className="flex items-center justify-between rounded bg-gray-50 p-2">
                                        <div className="flex-1">
                                            <div className="text-sm font-medium">
                                                {formatChargeTitle(charge)} • {fmtDate(charge.period)}
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                {fmtMinor(amountInOriginalCurrency, charge.currency as any)} × Tasa {exchangeRate.toFixed(2)}
                                            </div>
                                        </div>
                                        <div className="text-sm font-semibold">
                                            {fmtMinor(customAmounts[charge.charge_id] || charge.outstanding_bs_minor)}
                                        </div>
                                    </div>
                                );
                            })}
                    </div>
                </CardContent>
            </Card>

            {errors.length > 0 && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        {errors.map((e, i) => (
                            <div key={i}>{e}</div>
                        ))}
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex items-center justify-between">
                <Button variant="outline" size="lg" onClick={() => setStep('review')}>
                    <ChevronLeft className="mr-2 h-4 w-4" /> Atrás
                </Button>
                <Button size="lg" onClick={handleApply} className="bg-green-600 hover:bg-green-700">
                    <CheckCircle2 className="mr-2 h-4 w-4" /> Aplicar pago
                </Button>
            </div>
        </div>
    );

    return (
        <AppLayout>
            <Head title={`Aplicar pago #${payment.id}`} />
            <div className="container mx-auto max-w-4xl px-4 py-8">
                {/* Flash messages */}
                {success && (
                    <Alert className="mb-6 border-green-500 bg-green-50 dark:bg-green-950/20">
                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                        <AlertDescription className="text-green-900 dark:text-green-400">{success}</AlertDescription>
                    </Alert>
                )}
                {error && (
                    <Alert variant="destructive" className="mb-6">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold tracking-tight">Aplicar pago a mis deudas</h1>
                    <p className="text-muted-foreground mt-2">
                        Pago #{payment.id} • {fmtDate(payment.paid_on)}
                    </p>
                </div>

                {renderStepIndicator()}

                {loading ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-12 text-center">Cargando...</CardContent>
                    </Card>
                ) : (
                    <>
                        {step === 'smart-suggestion' && renderSmartSuggestion()}
                        {step === 'review' && renderReview()}
                        {step === 'confirm' && renderConfirm()}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
