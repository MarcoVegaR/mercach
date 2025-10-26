import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { FormActions } from '@/components/forms/form-actions';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { DatePicker, type DatePickerValue } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CreditCard, Landmark, Phone } from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    local_id?: number | null;
    debtor_type?: string | null;
    debtor_id?: number | null;
    company_bank_account_id?: number | null;
    method?: string | null;
    origin_bank_id?: number | null;
    payer_document_type?: string | null;
    payer_document_number?: string | null;
    payer_account_number?: string | null;
    payer_phone_e164?: string | null;
    reference?: string | null;
    amount_bs_minor?: number | null;
    paid_on?: string | null;
    fx_rate_id?: number | null;
    status?: string | null;
    gateway_request?: string | null;
    gateway_response?: string | null;
    gateway_resp_code?: string | null;
    gateway_message?: string | null;
    payer_details?: string | null;
    idempotency_key?: string | null;
    updated_at?: string | null;
}

interface PageProps {
    mode: FormMode;
    model?: ModelShape;
    options?: {
        companyBankAccounts: Array<{ id: number; label: string }>;
        banks: Array<{ id: number; name: string }>;
        statuses: Array<{ value: string; label: string }>;
        concessionaires: Array<{ id: number; name: string; document_number?: string; document_type_code?: string }>;
        locals: Array<{ id: number; label: string }>;
        paymentTypes: Array<{ value: string; label: string }>;
    };
}

export default function FormPage(props: PageProps) {
    const mode: FormMode = props.mode ?? 'create';
    const initial = props.model ?? {};
    type FormOptions = Required<NonNullable<PageProps['options']>>;
    const opts: FormOptions = (props.options ?? {
        companyBankAccounts: [],
        banks: [],
        statuses: [],
        concessionaires: [],
        locals: [],
        paymentTypes: [],
    }) as FormOptions;

    const { flash } = usePage<{ flash?: { success?: string; error?: string; warning?: string; info?: string } }>().props;
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash?.success, flash?.error, flash?.warning, flash?.info]);

    const form = useForm({
        local_id: initial.local_id ?? null,
        debtor_type: initial.debtor_type ?? 'CONCESSIONAIRE',
        debtor_id: initial.debtor_id ?? null,
        company_bank_account_id: initial.company_bank_account_id ?? null,
        method: initial.method ?? '',
        origin_bank_id: initial.origin_bank_id ?? null,
        payer_document_type: initial.payer_document_type ?? '',
        payer_document_number: initial.payer_document_number ?? '',
        payer_account_number: initial.payer_account_number ?? '',
        payer_phone_e164: initial.payer_phone_e164 ?? '',
        reference: initial.reference ?? '',
        amount_bs_minor: initial.amount_bs_minor ?? null,
        paid_on: initial.paid_on ?? '',
        fx_rate_id: initial.fx_rate_id ?? null,
        status: initial.status ?? '',
        gateway_request: initial.gateway_request ?? '',
        gateway_response: initial.gateway_response ?? '',
        gateway_resp_code: initial.gateway_resp_code ?? '',
        gateway_message: initial.gateway_message ?? '',
        payer_details: initial.payer_details ?? '',
        idempotency_key: initial.idempotency_key ?? '',
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    // Amount major (bank-style auto-decimals). Keep UI string and sync to cents in form.
    const [amountMajor, setAmountMajor] = useState<string>(() => {
        const cents = Number(props.model?.amount_bs_minor ?? 0);
        return (cents / 100).toFixed(2);
    });
    useEffect(() => {
        const cents = Number(form.data.amount_bs_minor ?? 0);
        setAmountMajor((cents / 100).toFixed(2));
    }, [form.data.amount_bs_minor]);
    const handleAmountChange = (raw: string) => {
        const digits = String(raw).replace(/\D+/g, '');
        const intVal = digits === '' ? 0 : Number(digits);
        const major = (intVal / 100).toFixed(2);
        setAmountMajor(major);
        form.setData('amount_bs_minor', intVal);
    };

    const breadcrumbs = [
        { title: 'Pagos', href: '/payments' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    const firstErrorRef = useRef<HTMLInputElement>(null);
    const verifyToastRef = useRef<string | number | null>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    // Guardar la última fecha sin tasa para no spamear toasts
    const lastNoFxFor = useRef<string | null>(null);
    const [fxRateValue, setFxRateValue] = useState<string | null>(null);

    // Resolver FX para una fecha específica evitando re-renders innecesarios
    const tryResolveFx = React.useCallback(
        async (paidOn: string) => {
            try {
                const qs = new URLSearchParams({ currency: 'USD', paid_on: paidOn });
                const res = await fetch(`/payments/resolve-fx?${qs.toString()}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('resolve_fx_failed');
                const json = await res.json();
                if (json && typeof json.fx_rate_id !== 'undefined') {
                    const newId = json.fx_rate_id ?? null;
                    // Solo actualizar si cambia para evitar re-render y posibles bucles
                    if ((form.data.fx_rate_id ?? null) !== newId) {
                        form.setData('fx_rate_id', newId);
                    }
                    // Mostrar el valor de la tasa si viene
                    if (typeof json.rate_to_ves !== 'undefined' && json.rate_to_ves !== null && json.rate_to_ves !== '') {
                        const num = Number(json.rate_to_ves);
                        const v = isNaN(num) ? null : num.toFixed(2);
                        setFxRateValue(v);
                    } else {
                        setFxRateValue(null);
                    }
                    if (!newId) {
                        const curr = (form.data.paid_on ?? '').trim();
                        if (lastNoFxFor.current !== curr) {
                            toast.message('No hay tasa disponible para la fecha.');
                            lastNoFxFor.current = curr;
                        }
                    } else {
                        lastNoFxFor.current = null;
                    }
                }
            } catch {
                toast.error('No se pudo resolver la tasa para la fecha.');
            }
        },
        [form],
    );

    useEffect(() => {
        const paid = form.data.paid_on?.trim();
        if (paid) {
            void tryResolveFx(paid);
        }
        // Importante: depender solo de paid_on para evitar bucles por identidad de funciones/objetos
    }, [form.data.paid_on, tryResolveFx]);

    // Date helpers (reusar patrón de contratos y fx-rate)
    const parseYMD = (s?: string | null): Date | undefined => {
        if (!s) return undefined;
        const parts = String(s).slice(0, 10).split('-');
        if (parts.length !== 3) return undefined;
        const [y, m, d] = parts.map((n) => parseInt(n, 10));
        if (!y || !m || !d) return undefined;
        return new Date(y, m - 1, d);
    };
    const toYMD = (d?: Date | null): string => {
        if (!d) return '';
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };

    function handleCancel() {
        router.visit('/payments', { preserveScroll: true });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (mode === 'create') {
            const method = String(form.data.method ?? '').toUpperCase();
            const isVerifyingWithBank = method !== 'DEB';
            form.post(route('payments.store'), {
                onStart: () => {
                    if (isVerifyingWithBank) {
                        verifyToastRef.current = toast.loading('Verificando en banco…');
                    }
                },
                onError: () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    toast.error('Corrige los errores del formulario.');
                },
                onFinish: () => {
                    if (verifyToastRef.current != null) {
                        toast.dismiss(verifyToastRef.current as any);
                        verifyToastRef.current = null;
                    }
                },
            });
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            form.put(route('payments.update', id), {
                onError: () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    toast.error('Corrige los errores del formulario.');
                },
            });
        }
    }

    // Derivados para mostrar equivalente en USD
    const rateNum = fxRateValue ? Number(fxRateValue) : null;
    const amountBs = Number(form.data.amount_bs_minor ?? 0) / 100;
    const equivUsd = rateNum && rateNum > 0 ? amountBs / rateNum : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar Pago' : 'Crear Pago'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100">{mode === 'edit' ? 'Editar' : 'Crear'} Pago</h1>

                        <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                            {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field id="debtor_type" label="Tipo de deudor" error={form.errors.debtor_type}>
                                    <Select
                                        value={String(form.data.debtor_type ?? 'CONCESSIONAIRE')}
                                        onValueChange={(val) => {
                                            form.setData('debtor_type', val);
                                            if (val !== 'LOCAL') {
                                                form.setData('local_id', null);
                                            }
                                        }}
                                    >
                                        <SelectTrigger id="debtor_type" className="w-full">
                                            <SelectValue placeholder="Seleccionar tipo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="CONCESSIONAIRE">Concesionario</SelectItem>
                                            <SelectItem value="LOCAL">Local</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                {form.data.debtor_type === 'CONCESSIONAIRE' && (
                                    <Field id="debtor_id" label="Concesionario" error={form.errors.debtor_id as any}>
                                        <Combobox
                                            options={(opts.concessionaires ?? []).map((c) => ({
                                                value: String(c.id),
                                                label:
                                                    `${c.document_type_code ? c.document_type_code + ' ' : ''}` +
                                                    `${c.document_number ? c.document_number + ' - ' : ''}` +
                                                    `${c.name}`,
                                            }))}
                                            value={form.data.debtor_id ? String(form.data.debtor_id) : ''}
                                            onChange={(v) =>
                                                form.setData('debtor_id', Array.isArray(v) ? (v[0] ? Number(v[0]) : null) : v ? Number(v) : null)
                                            }
                                            placeholder="Seleccionar concesionario"
                                            searchPlaceholder="Buscar concesionario..."
                                            emptyText="Sin resultados"
                                        />
                                    </Field>
                                )}

                                {form.data.debtor_type === 'LOCAL' && (
                                    <Field id="local_id" label="Local" error={form.errors.local_id}>
                                        <Combobox
                                            options={(opts.locals ?? []).map((l: { id: number; label: string }) => ({
                                                value: String(l.id),
                                                label: l.label,
                                            }))}
                                            value={form.data.local_id ? String(form.data.local_id) : ''}
                                            onChange={(v) => {
                                                const s = Array.isArray(v) ? (v[0] ? Number(v[0]) : null) : v ? Number(v) : null;
                                                form.setData('local_id', s);
                                                form.setData('debtor_id', s);
                                            }}
                                            placeholder="Seleccionar local"
                                            searchPlaceholder="Buscar local..."
                                            emptyText="Sin resultados"
                                            leadingIcon={Landmark}
                                            leadingIconClassName="text-indigo-600"
                                        />
                                    </Field>
                                )}

                                {null}
                                {null}

                                <Field id="company_bank_account_id" label="Cuenta receptora" error={form.errors.company_bank_account_id}>
                                    <Select
                                        value={String(form.data.company_bank_account_id ?? '')}
                                        onValueChange={(val) => form.setData('company_bank_account_id', Number(val))}
                                    >
                                        <SelectTrigger
                                            id="company_bank_account_id"
                                            className="w-full"
                                            leadingIcon={CreditCard}
                                            leadingIconClassName="text-indigo-600"
                                        >
                                            <SelectValue placeholder="Seleccionar cuenta" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {opts.companyBankAccounts.map((acc) => (
                                                <SelectItem key={acc.id} value={String(acc.id)}>
                                                    {acc.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field id="method" label="Método" error={form.errors.method}>
                                    <Select
                                        value={String(form.data.method ?? '')}
                                        onValueChange={(val) => {
                                            form.setData('method', val);
                                            // Ajustes por manual del banco
                                            if (val === 'PMOV') {
                                                // Para PMOV el teléfono es requerido y la referencia puede ser "0"
                                            }
                                        }}
                                    >
                                        <SelectTrigger
                                            id="method"
                                            className="w-full"
                                            leadingIcon={CreditCard}
                                            leadingIconClassName="text-emerald-600"
                                        >
                                            <SelectValue placeholder="Seleccionar método" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {opts.paymentTypes?.map((pt: { value: string; label: string }) => (
                                                <SelectItem key={pt.value} value={pt.value}>
                                                    {pt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field id="origin_bank_id" label="Banco origen" error={form.errors.origin_bank_id}>
                                    <Select
                                        value={String(form.data.origin_bank_id ?? '')}
                                        onValueChange={(val) => form.setData('origin_bank_id', Number(val))}
                                    >
                                        <SelectTrigger
                                            id="origin_bank_id"
                                            className="w-full"
                                            leadingIcon={Landmark}
                                            leadingIconClassName="text-sky-600"
                                        >
                                            <SelectValue placeholder="Seleccionar banco" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {opts.banks.map((b) => (
                                                <SelectItem key={b.id} value={String(b.id)}>
                                                    {b.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field id="payer_document_type" label="Tipo doc. pagador" error={form.errors.payer_document_type}>
                                    <Select
                                        value={String(form.data.payer_document_type ?? '')}
                                        onValueChange={(val) => form.setData('payer_document_type', val)}
                                    >
                                        <SelectTrigger id="payer_document_type" className="w-full">
                                            <SelectValue placeholder="Seleccionar tipo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="V">V</SelectItem>
                                            <SelectItem value="E">E</SelectItem>
                                            <SelectItem value="J">J</SelectItem>
                                            <SelectItem value="G">G</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field id="payer_document_number" label="Documento pagador" error={form.errors.payer_document_number}>
                                    <Input
                                        name="payer_document_number"
                                        value={form.data.payer_document_number}
                                        onChange={(e) => form.setData('payer_document_number', e.target.value.replace(/\D+/g, '').slice(0, 12))}
                                        required
                                        maxLength={12}
                                    />
                                </Field>

                                <Field id="payer_account_number" label="Cuenta pagador" error={form.errors.payer_account_number}>
                                    <Input
                                        name="payer_account_number"
                                        value={form.data.payer_account_number}
                                        onChange={(e) => form.setData('payer_account_number', e.target.value.replace(/\D+/g, '').slice(0, 20))}
                                        maxLength={20}
                                        minLength={20}
                                        required={form.data.method !== 'PMOV' && form.data.method !== 'DEB'}
                                        placeholder="20 dígitos"
                                    />
                                </Field>

                                <Field id="payer_phone_e164" label="Teléfono pagador (58XXXXXXXXXX)" error={form.errors.payer_phone_e164}>
                                    <Input
                                        name="payer_phone_e164"
                                        value={form.data.payer_phone_e164}
                                        onChange={(e) => form.setData('payer_phone_e164', e.target.value.replace(/\D+/g, '').slice(0, 12))}
                                        maxLength={12}
                                        minLength={12}
                                        pattern={'^58\\d{10}$'}
                                        required={form.data.method === 'PMOV'}
                                        placeholder="58XXXXXXXXXX"
                                        leadingIcon={Phone}
                                        leadingIconClassName="text-sky-600"
                                    />
                                </Field>
                                <Field id="reference" label="Referencia" error={form.errors.reference}>
                                    <Input
                                        name="reference"
                                        value={form.data.reference}
                                        onChange={(e) => form.setData('reference', e.target.value.replace(/\D+/g, '').slice(0, 12))}
                                        required
                                        pattern={'^\\d{6,12}$'}
                                        placeholder={'6–12 dígitos'}
                                        maxLength={12}
                                    />
                                </Field>

                                <Field id="amount_bs_major" label="Monto (Bs)" error={form.errors.amount_bs_minor}>
                                    <Input
                                        value={amountMajor}
                                        onChange={(e) => handleAmountChange(e.target.value)}
                                        inputMode="numeric"
                                        required
                                        placeholder="0.00"
                                    />
                                </Field>

                                <Field id="paid_on" label="Pagado el" error={form.errors.paid_on}>
                                    <DatePicker
                                        id="paid_on"
                                        mode="single"
                                        value={parseYMD(form.data.paid_on)}
                                        onChange={(v: DatePickerValue) => form.setData('paid_on', toYMD(v as Date))}
                                        placeholder="Seleccionar fecha"
                                        buttonClassName="w-full justify-between"
                                    />
                                </Field>

                                <Field id="fx_rate" label="Tasa FX" error={form.errors.fx_rate_id}>
                                    <div className="flex flex-col gap-2">
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                onClick={() => {
                                                    const paid = form.data.paid_on?.trim();
                                                    if (paid) void tryResolveFx(paid);
                                                }}
                                            >
                                                Resolver FX
                                            </Button>
                                            {fxRateValue && <span className="text-muted-foreground text-sm">Tasa: {fxRateValue} Bs/USD</span>}
                                        </div>
                                        {equivUsd !== null && (
                                            <span className="text-muted-foreground text-sm">Equivalente: ${equivUsd.toFixed(2)} USD</span>
                                        )}
                                    </div>
                                </Field>

                                {null}

                                {null}
                            </div>

                            <p className="text-muted-foreground text-xs">
                                <span className="text-destructive">*</span> Campos obligatorios
                            </p>

                            <FormActions
                                onCancel={handleCancel}
                                isSubmitting={form.processing}
                                isDirty={true}
                                submitText={mode === 'create' ? 'Crear' : 'Actualizar'}
                            />
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
