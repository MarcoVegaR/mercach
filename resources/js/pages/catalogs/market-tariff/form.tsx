import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { DollarSign } from 'lucide-react';
import React, { useEffect, useMemo, useRef } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    market_id?: string | null;
    valid_from?: string | null;
    price_per_m2_eur_minor?: string | null;
    is_current?: boolean | null;
    is_active?: boolean | null;
    updated_at?: string | null;
}

interface PageProps {
    mode: FormMode;
    model?: ModelShape;
    options?: { markets: Array<{ id: number; name: string }> };
}

export default function FormPage(props: PageProps) {
    const mode: FormMode = props.mode ?? 'create';
    const initial = props.model ?? {};
    const opts = props.options ?? { markets: [] };

    const form = useForm({
        market_id: initial.market_id ?? '',
        valid_from: initial.valid_from ?? '',
        // Display price in major units (e.g., 0.10) even though backend stores minor units
        price_per_m2_eur_minor:
            initial.price_per_m2_eur_minor != null && initial.price_per_m2_eur_minor !== ''
                ? (Number(initial.price_per_m2_eur_minor) / 100).toFixed(2)
                : '',
        is_current: Boolean(initial.is_current ?? false),
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    // Date helpers
    const parseISO = (s?: string | null): Date | undefined => {
        if (!s) return undefined;
        const d = new Date(s);
        return isNaN(d.getTime()) ? undefined : d;
    };
    const toISO = (d?: Date): string => {
        if (!d) return '';
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };
    const validFromDate = useMemo(() => parseISO(form.data.valid_from), [form.data.valid_from]);

    // Bank-style money input: parses digits and formats as X.XX
    const onPriceChange = (raw: string) => {
        const digits = String(raw).replace(/[^0-9]/g, '');
        if (!digits) {
            form.setData('price_per_m2_eur_minor', '');
            return;
        }
        const whole = digits.slice(0, -2) || '0';
        const cents = digits.slice(-2).padStart(2, '0');
        form.setData('price_per_m2_eur_minor', `${String(parseInt(whole, 10))}.${cents}`);
    };

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Tarifas de mercado', href: '/catalogs/market-tariff' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    const firstErrorRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    function handleCancel() {
        router.visit('/catalogs/market-tariff', { preserveScroll: true });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (mode === 'create') {
            form.post(route('catalogs.market-tariff.store'));
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            form.put(route('catalogs.market-tariff.update', id));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar tarifa de mercado' : 'Crear tarifa de mercado'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {mode === 'edit' ? 'Editar' : 'Crear'} tarifa de mercado
                        </h1>
                        <TooltipProvider delayDuration={300}>
                            <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                                {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field id="market_id" label="Mercado" error={form.errors.market_id}>
                                        <select
                                            name="market_id"
                                            className="w-full rounded-md border px-3 py-2 text-sm"
                                            value={form.data.market_id}
                                            onChange={(e) => form.setData('market_id', e.target.value)}
                                        >
                                            <option value="">Selecciona…</option>
                                            {opts.markets.map((m) => (
                                                <option key={m.id} value={m.id}>
                                                    {m.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field id="valid_from" label="Válido desde" error={form.errors.valid_from}>
                                        <DatePicker
                                            id="valid_from"
                                            mode="single"
                                            value={validFromDate}
                                            onChange={(v) => form.setData('valid_from', toISO(v as Date))}
                                            placeholder="Selecciona una fecha"
                                            buttonClassName="w-full"
                                        />
                                    </Field>

                                    <Field id="price_per_m2_eur_minor" label="Precio EUR/m² (día)" error={form.errors.price_per_m2_eur_minor}>
                                        <Input
                                            name="price_per_m2_eur_minor"
                                            type="text"
                                            inputMode="decimal"
                                            placeholder="0.10"
                                            leadingIcon={DollarSign}
                                            leadingIconClassName="text-emerald-600"
                                            value={form.data.price_per_m2_eur_minor}
                                            onChange={(e) => onPriceChange(e.target.value)}
                                        />
                                    </Field>
                                </div>

                                {mode === 'edit' && (
                                    <Field id="is_active" label="Estado activo" error={form.errors.is_active}>
                                        <ActiveField
                                            checked={!!form.data.is_active}
                                            onChange={(v) => form.setData('is_active', v)}
                                            canToggle={true}
                                            activeLabel="Registro activo"
                                            inactiveLabel="Registro inactivo"
                                        />
                                        <FieldError message={form.errors.is_active} />
                                    </Field>
                                )}

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
                        </TooltipProvider>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
