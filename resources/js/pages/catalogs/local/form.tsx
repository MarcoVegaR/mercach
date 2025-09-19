import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    code?: string | null;
    name?: string | null;
    market_id?: string | null;
    local_type_id?: string | null;
    // local_status_id is managed server-side; not editable in form
    local_location_id?: string | null;
    area_m2?: number | null;
    is_active?: boolean | null;
    updated_at?: string | null;
}

interface PageProps {
    mode: FormMode;
    model?: ModelShape;
    options?: {
        markets: Array<{ id: number; name: string }>;
        local_types: Array<{ id: number; name: string }>;
        local_statuses: Array<{ id: number; name: string }>;
        local_locations: Array<{ id: number; name: string }>;
    };
}

export default function FormPage(props: PageProps) {
    const mode: FormMode = props.mode ?? 'create';
    const initial = props.model ?? {};
    const opts: NonNullable<PageProps['options']> = props.options ?? {
        markets: [],
        local_types: [],
        local_statuses: [],
        local_locations: [],
    };

    const form = useForm({
        code: initial.code ?? '',
        name: initial.name ?? '',
        market_id: initial.market_id ?? '',
        local_type_id: initial.local_type_id ?? '',
        // local_status_id not part of form payload; LocalService sets default on create
        local_location_id: initial.local_location_id ?? '',
        area_m2: initial.area_m2 ?? null,
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Locales', href: '/catalogs/local' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    const firstErrorRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    function handleCancel() {
        router.visit('/catalogs/local', { preserveScroll: true });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (mode === 'create') {
            form.post(route('catalogs.local.store'));
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            form.put(route('catalogs.local.update', id));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar Local' : 'Crear Local'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100">{mode === 'edit' ? 'Editar' : 'Crear'} Local</h1>

                        <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                            {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field id="code" label="Código" error={form.errors.code} hint="Ej.: A-01">
                                    <Input
                                        name="code"
                                        ref={firstErrorRef}
                                        autoFocus
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        maxLength={4}
                                        className="font-mono"
                                    />
                                </Field>

                                <Field id="name" label="Nombre" error={form.errors.name}>
                                    <Input
                                        name="name"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        maxLength={160}
                                    />
                                </Field>

                                <Field id="market_id" label="Mercado" error={form.errors.market_id}>
                                    <Select value={form.data.market_id ?? ''} onValueChange={(val) => form.setData('market_id', val)}>
                                        <SelectTrigger id="market_id" className="w-full">
                                            <SelectValue placeholder="Seleccionar mercado" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {opts.markets.map((m) => (
                                                <SelectItem key={m.id} value={String(m.id)}>
                                                    {m.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field id="local_type_id" label="Tipo de local" error={form.errors.local_type_id}>
                                    <Select value={form.data.local_type_id ?? ''} onValueChange={(val) => form.setData('local_type_id', val)}>
                                        <SelectTrigger id="local_type_id" className="w-full">
                                            <SelectValue placeholder="Seleccionar tipo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {opts.local_types.map((m) => (
                                                <SelectItem key={m.id} value={String(m.id)}>
                                                    {m.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>

                                {null /* Local status is set automatically (DISP) and not editable */}

                                <Field id="local_location_id" label="Ubicación" error={form.errors.local_location_id}>
                                    <Select value={form.data.local_location_id ?? ''} onValueChange={(val) => form.setData('local_location_id', val)}>
                                        <SelectTrigger id="local_location_id" className="w-full">
                                            <SelectValue placeholder="Seleccionar ubicación" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {opts.local_locations.map((m) => (
                                                <SelectItem key={m.id} value={String(m.id)}>
                                                    {m.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field id="area_m2" label="Área (m²)" error={form.errors.area_m2}>
                                    <Input
                                        name="area_m2"
                                        type="number"
                                        step="0.01"
                                        value={form.data.area_m2 ?? ''}
                                        onChange={(e) => {
                                            const v = e.target.value;
                                            form.setData('area_m2', v === '' ? null : Number(v));
                                        }}
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
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
