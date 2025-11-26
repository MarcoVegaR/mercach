import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Link, useForm } from '@inertiajs/react';
import React from 'react';
import { toast } from 'sonner';

interface Option {
    id: number;
    label: string;
}
interface Bank {
    id: number;
    name: string;
}

type Props = {
    options: {
        companyBankAccounts: Option[];
        banks: Bank[];
        methods: { value: string; label: string }[];
    };
    defaults: { payer_document_type?: string; payer_document_number?: string };
};

export default function PortalPaymentCreate({ options, defaults }: Props) {
    const verifyToastRef = React.useRef<string | number | null>(null);
    const [fxRateUsd, setFxRateUsd] = React.useState<string | null>(null);
    const [fxRateEur, setFxRateEur] = React.useState<string | null>(null);

    const todayCaracas = React.useMemo(
        () =>
            new Intl.DateTimeFormat('en-CA', {
                timeZone: 'America/Caracas',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date()),
        [],
    );

    const {
        data,
        setData,
        processing,
        post,
        errors,
        reset: _reset,
    } = useForm({
        company_bank_account_id: '',
        method: 'TRANSFER',
        origin_bank_id: '',
        payer_document_type: defaults?.payer_document_type || '',
        payer_document_number: defaults?.payer_document_number || '',
        payer_account_number: '',
        payer_phone_e164: '',
        reference: '',
        amount_bs_minor: 0,
        paid_on: todayCaracas,
        fx_rate_id: '' as any,
    });

    // Amount major (bank-style auto-decimals). Keep UI string and sync to cents in form.
    const [amountMajor, setAmountMajor] = React.useState<string>(() => {
        const cents = Number(0);
        return (cents / 100).toFixed(2);
    });
    React.useEffect(() => {
        const cents = Number(data.amount_bs_minor ?? 0);
        setAmountMajor((cents / 100).toFixed(2));
    }, [data.amount_bs_minor]);
    const handleAmountChange = (raw: string) => {
        const digits = String(raw).replace(/\D+/g, '');
        const intVal = digits === '' ? 0 : Number(digits);
        const major = (intVal / 100).toFixed(2);
        setAmountMajor(major);
        setData('amount_bs_minor', intVal);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const isVerifying = String(data.method).toUpperCase() !== 'DEB';
        post('/portal/pagos', {
            onStart: () => {
                if (isVerifying) verifyToastRef.current = toast.loading('Verificando en banco…');
            },
            onFinish: () => {
                if (verifyToastRef.current != null) {
                    toast.dismiss(verifyToastRef.current as any);
                    verifyToastRef.current = null;
                }
            },
            onError: () => {
                toast.error('Corrige los errores del formulario.');
            },
        });
    };

    // Resolve FX when date changes (both USD and EUR)
    React.useEffect(() => {
        const paid = (data.paid_on || '').trim();
        if (!paid) return;
        const fetchFx = async (cur: 'USD' | 'EUR') => {
            const qs = new URLSearchParams({ currency: cur, paid_on: paid });
            const res = await fetch(`/portal/pagos/resolve-fx?${qs.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('fx');
            const json = await res.json();
            const rate =
                typeof json.rate_to_ves !== 'undefined' && json.rate_to_ves !== null && json.rate_to_ves !== '' ? Number(json.rate_to_ves) : null;
            if (cur === 'USD') {
                if (typeof json.fx_rate_id !== 'undefined') setData('fx_rate_id', json.fx_rate_id ?? '');
                setFxRateUsd(rate !== null && !isNaN(rate) ? rate.toFixed(2) : null);
            } else {
                setFxRateEur(rate !== null && !isNaN(rate) ? rate.toFixed(2) : null);
            }
        };
        Promise.allSettled([fetchFx('USD'), fetchFx('EUR')]).catch(() => {
            /* ignore */
        });
    }, [data.paid_on, setData]);

    return (
        <div className="container mx-auto max-w-3xl px-4 py-8">
            <div className="mb-8 flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Registrar Pago</h1>
                    <p className="text-muted-foreground mt-2">Completa los datos del pago efectuado</p>
                </div>
                <Link href="/portal">
                    <Button variant="outline" size="sm">
                        Volver al Portal
                    </Button>
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Datos del pago</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Cuenta receptora</Label>
                                <Select value={String(data.company_bank_account_id)} onValueChange={(v) => setData('company_bank_account_id', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona cuenta" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.companyBankAccounts.map((o) => (
                                            <SelectItem key={o.id} value={String(o.id)}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.company_bank_account_id && <p className="mt-1 text-xs text-red-500">{errors.company_bank_account_id}</p>}
                            </div>
                            <div>
                                <Label>Método</Label>
                                <Select value={data.method} onValueChange={(v) => setData('method', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona método" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.methods.map((m) => (
                                            <SelectItem key={m.value} value={m.value}>
                                                {m.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.method && <p className="mt-1 text-xs text-red-500">{errors.method}</p>}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Banco origen</Label>
                                <Select value={String(data.origin_bank_id)} onValueChange={(v) => setData('origin_bank_id', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona banco" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.banks.map((b) => (
                                            <SelectItem key={b.id} value={String(b.id)}>
                                                {b.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.origin_bank_id && <p className="mt-1 text-xs text-red-500">{errors.origin_bank_id}</p>}
                            </div>
                            <div>
                                <Label>Referencia</Label>
                                <Input
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value.replace(/\D+/g, '').slice(0, 8))}
                                    placeholder="6–8 dígitos"
                                    pattern={'^\\d{6,8}$'}
                                    required
                                />
                                {errors.reference && <p className="mt-1 text-xs text-red-500">{errors.reference}</p>}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Tipo de documento</Label>
                                <Input
                                    value={data.payer_document_type}
                                    onChange={(e) => setData('payer_document_type', e.target.value.toUpperCase().slice(0, 1))}
                                    maxLength={1}
                                    required
                                />
                                {errors.payer_document_type && <p className="mt-1 text-xs text-red-500">{errors.payer_document_type}</p>}
                            </div>
                            <div>
                                <Label>Número de documento</Label>
                                <Input
                                    value={data.payer_document_number}
                                    onChange={(e) => setData('payer_document_number', e.target.value.replace(/\D+/g, '').slice(0, 12))}
                                    maxLength={12}
                                    required
                                />
                                {errors.payer_document_number && <p className="mt-1 text-xs text-red-500">{errors.payer_document_number}</p>}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Cuenta del pagador (Transferencia)</Label>
                                <Input
                                    value={data.payer_account_number}
                                    onChange={(e) => setData('payer_account_number', e.target.value.replace(/\D+/g, '').slice(0, 20))}
                                    placeholder="20 dígitos"
                                    minLength={20}
                                    maxLength={20}
                                    required={data.method !== 'PMOV' && data.method !== 'DEB'}
                                />
                                {errors.payer_account_number && <p className="mt-1 text-xs text-red-500">{errors.payer_account_number}</p>}
                            </div>
                            <div>
                                <Label>Teléfono del pagador (Pago Móvil)</Label>
                                <Input
                                    value={data.payer_phone_e164}
                                    onChange={(e) => setData('payer_phone_e164', e.target.value.replace(/\D+/g, '').slice(0, 12))}
                                    placeholder="58XXXXXXXXXX"
                                    minLength={12}
                                    maxLength={12}
                                    pattern={'^58\\d{10}$'}
                                    required={data.method === 'PMOV'}
                                />
                                {errors.payer_phone_e164 && <p className="mt-1 text-xs text-red-500">{errors.payer_phone_e164}</p>}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Monto (VES)</Label>
                                <Input
                                    value={amountMajor}
                                    onChange={(e) => handleAmountChange(e.target.value)}
                                    placeholder="0.00"
                                    inputMode="numeric"
                                    required
                                />
                                {errors.amount_bs_minor && <p className="mt-1 text-xs text-red-500">{errors.amount_bs_minor}</p>}
                            </div>
                            <div>
                                <Label>Fecha de pago</Label>
                                <Input type="date" value={data.paid_on} onChange={(e) => setData('paid_on', e.target.value)} required />
                                {errors.paid_on && <p className="mt-1 text-xs text-red-500">{errors.paid_on}</p>}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="submit" disabled={processing}>
                                Registrar
                            </Button>
                            <Link href="/portal">
                                <Button variant="outline" type="button">
                                    Cancelar
                                </Button>
                            </Link>
                            {(fxRateUsd || fxRateEur) && (
                                <span className="text-muted-foreground text-sm">
                                    {fxRateUsd ? `Tasa: ${fxRateUsd} Bs/USD` : ''}
                                    {fxRateUsd && fxRateEur ? ' • ' : ''}
                                    {fxRateEur ? `Tasa: ${fxRateEur} Bs/EUR` : ''}
                                </span>
                            )}
                            {(fxRateUsd || fxRateEur) &&
                                (() => {
                                    const cents = Number(data.amount_bs_minor ?? 0);
                                    const amountBs = cents / 100;
                                    const usd = fxRateUsd ? amountBs / Number(fxRateUsd) : null;
                                    const eur = fxRateEur ? amountBs / Number(fxRateEur) : null;
                                    if (usd === null && eur === null) return null;
                                    return (
                                        <span className="text-muted-foreground text-sm">
                                            Equivalente: {usd !== null ? `$${usd.toFixed(2)} USD` : ''}
                                            {usd !== null && eur !== null ? ' • ' : ''}
                                            {eur !== null ? `€${eur.toFixed(2)} EUR` : ''}
                                        </span>
                                    );
                                })()}
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

PortalPaymentCreate.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
