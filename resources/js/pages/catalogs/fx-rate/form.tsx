import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { DatePicker, type DatePickerValue } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Banknote, Coins, Landmark } from 'lucide-react';
import React, { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    currency_code?: string | null;
    rate_date?: string | null;
    value_date?: string | null;
    published_at?: string | null;
    rate_to_ves?: number | null;
    operational_from?: string | null;
    operational_to?: string | null;
    source?: string | null;
    is_official?: boolean | null;
    is_active?: boolean | null;
    updated_at?: string | null;
}

interface PageProps {
    mode: FormMode;
    model?: ModelShape;
}

export default function FormPage(props: PageProps) {
    const mode: FormMode = props.mode ?? 'create';
    const initial = props.model ?? {};

    const form = useForm({
        currency_code: initial.currency_code ?? '',
        rate_date: initial.rate_date ?? '',
        value_date: initial.value_date ?? '',
        published_at: initial.published_at ?? '',
        rate_to_ves: initial.rate_to_ves != null ? String(initial.rate_to_ves) : '',
        operational_from: initial.operational_from ?? '',
        operational_to: initial.operational_to ?? '',
        source: initial.source ?? '',
        is_official: Boolean(initial.is_official ?? false),
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Tasas de cambio', href: '/catalogs/fx-rate' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    const firstErrorRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    function handleCancel() {
        router.visit('/catalogs/fx-rate', { preserveScroll: true });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (mode === 'create') {
            form.post(route('catalogs.fx-rate.store'));
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            form.put(route('catalogs.fx-rate.update', id));
        }
    }

    // Helpers
    // Date helpers (same approach as contratos)
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar Tasa de cambio' : 'Crear Tasa de cambio'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 flex items-center gap-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            <Coins className="h-6 w-6 text-indigo-500" />
                            {mode === 'edit' ? 'Editar' : 'Crear'} Tasa de cambio
                        </h1>

                        <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                            {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field id="currency_code" label="Moneda" error={form.errors.currency_code}>
                                    <Select value={form.data.currency_code ?? ''} onValueChange={(val) => form.setData('currency_code', val)}>
                                        <SelectTrigger
                                            id="currency_code"
                                            className="w-full"
                                            leadingIcon={Coins}
                                            leadingIconClassName="text-indigo-600"
                                        >
                                            <SelectValue placeholder="Seleccionar moneda" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="USD">Dólar (USD)</SelectItem>
                                            <SelectItem value="EUR">Euro (EUR)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field id="rate_to_ves" label="Tasa (VES)" error={form.errors.rate_to_ves}>
                                    <Input
                                        type="number"
                                        inputMode="decimal"
                                        step="0.01"
                                        min="0"
                                        name="rate_to_ves"
                                        value={form.data.rate_to_ves ?? ''}
                                        onChange={(e) => form.setData('rate_to_ves', e.target.value)}
                                        leadingIcon={Banknote}
                                        leadingIconClassName="text-emerald-600"
                                        onBlur={(e) => {
                                            const val = e.currentTarget.value;
                                            if (val !== '') {
                                                const num = Number(val.replace(',', '.'));
                                                if (!Number.isNaN(num)) {
                                                    form.setData('rate_to_ves', num.toFixed(2));
                                                }
                                            }
                                        }}
                                    />
                                </Field>

                                <Field id="rate_date" label="Fecha de tasa" error={form.errors.rate_date}>
                                    <DatePicker
                                        id="rate_date"
                                        mode="single"
                                        value={parseYMD(form.data.rate_date)}
                                        onChange={(v: DatePickerValue) => form.setData('rate_date', toYMD(v as Date))}
                                        placeholder="Seleccionar fecha"
                                        buttonClassName="w-full justify-between"
                                    />
                                </Field>

                                <Field id="value_date" label="Fecha valor" error={form.errors.value_date}>
                                    <DatePicker
                                        id="value_date"
                                        mode="single"
                                        value={parseYMD(form.data.value_date)}
                                        onChange={(v: DatePickerValue) => form.setData('value_date', toYMD(v as Date))}
                                        placeholder="Seleccionar fecha"
                                        buttonClassName="w-full justify-between"
                                    />
                                </Field>

                                <Field id="operational_from" label="Vigente desde" error={form.errors.operational_from}>
                                    <DatePicker
                                        id="operational_from"
                                        mode="single"
                                        value={parseYMD(form.data.operational_from)}
                                        onChange={(v: DatePickerValue) => form.setData('operational_from', toYMD(v as Date))}
                                        placeholder="Seleccionar fecha"
                                        buttonClassName="w-full justify-between"
                                    />
                                </Field>

                                <Field id="operational_to" label="Vigente hasta" error={form.errors.operational_to}>
                                    <DatePicker
                                        id="operational_to"
                                        mode="single"
                                        value={parseYMD(form.data.operational_to)}
                                        onChange={(v: DatePickerValue) => form.setData('operational_to', toYMD(v as Date))}
                                        placeholder="Seleccionar fecha"
                                        buttonClassName="w-full justify-between"
                                    />
                                </Field>

                                <Field id="published_at" label="Publicado el" error={form.errors.published_at}>
                                    <DatePicker
                                        id="published_at"
                                        mode="single"
                                        value={parseYMD(form.data.published_at)}
                                        onChange={(v: DatePickerValue) => form.setData('published_at', toYMD(v as Date))}
                                        placeholder="Seleccionar fecha"
                                        buttonClassName="w-full justify-between"
                                    />
                                </Field>

                                <Field id="source" label="Fuente" error={form.errors.source}>
                                    <Select value={form.data.source ?? ''} onValueChange={(val) => form.setData('source', val)}>
                                        <SelectTrigger id="source" className="w-full" leadingIcon={Landmark} leadingIconClassName="text-sky-600">
                                            <SelectValue placeholder="Seleccionar fuente" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="BCV">BCV</SelectItem>
                                            <SelectItem value="MANUAL">Manual</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field id="is_official" label="Oficial (BCV)" error={form.errors.is_official}>
                                    <ActiveField
                                        checked={!!form.data.is_official}
                                        onChange={(v) => form.setData('is_official', v)}
                                        canToggle={true}
                                        activeLabel="Sí"
                                        inactiveLabel="No"
                                    />
                                    <FieldError message={form.errors.is_official} />
                                </Field>
                            </div>

                            {null}

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
