import { CatalogCodeField, CatalogIsActiveField, CatalogNameField } from '@/components/catalogs/fields';
import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { FormActions } from '@/components/forms/form-actions';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Building2, MapPinned, Ruler, Store } from 'lucide-react';
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
        market_id: initial.market_id ? String(initial.market_id) : '',
        local_type_id: initial.local_type_id ? String(initial.local_type_id) : '',
        // local_status_id not part of form payload; LocalService sets default on create
        local_location_id: initial.local_location_id ? String(initial.local_location_id) : '',
        area_m2: initial.area_m2 ?? null,
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    // Track unsaved changes
    const initialData = {
        code: initial.code ?? '',
        name: initial.name ?? '',
        market_id: initial.market_id ? String(initial.market_id) : '',
        local_type_id: initial.local_type_id ? String(initial.local_type_id) : '',
        local_location_id: initial.local_location_id ? String(initial.local_location_id) : '',
        area_m2: initial.area_m2 ?? null,
        is_active: Boolean(initial.is_active ?? true),
    };
    const { hasUnsavedChanges, clearUnsavedChanges } = useUnsavedChanges(form.data, initialData, true, {
        excludeKeys: ['_token', '_method', '_version'],
        ignoreUnderscored: true,
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

        if (mode === 'edit' && !hasUnsavedChanges) {
            toast.info('No hay cambios para actualizar');
            return;
        }

        if (mode === 'create') {
            clearUnsavedChanges();
            form.post(route('catalogs.local.store'));
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            clearUnsavedChanges();
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

                        <TooltipProvider delayDuration={300}>
                            <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                                {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <CatalogCodeField
                                        value={form.data.code}
                                        onChange={(v) => form.setData('code', v)}
                                        error={form.errors.code}
                                        inputRef={firstErrorRef}
                                        autoFocus
                                        maxLength={4}
                                    />

                                    <CatalogNameField
                                        value={form.data.name}
                                        onChange={(v) => form.setData('name', v)}
                                        error={form.errors.name}
                                        tooltip="Nombre identificador del local"
                                        maxLength={160}
                                    />

                                    <Field
                                        id="market_id"
                                        label="Mercado"
                                        error={form.errors.market_id}
                                        tooltip="Selecciona el mercado al que pertenece el local"
                                    >
                                        <Select value={form.data.market_id ?? ''} onValueChange={(val) => form.setData('market_id', val)}>
                                            <SelectTrigger
                                                id="market_id"
                                                className="w-full"
                                                leadingIcon={Store}
                                                leadingIconClassName="text-amber-600"
                                            >
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

                                    <Field
                                        id="local_type_id"
                                        label="Tipo de local"
                                        error={form.errors.local_type_id}
                                        tooltip="Clasificación del local según su uso"
                                    >
                                        <Select value={form.data.local_type_id ?? ''} onValueChange={(val) => form.setData('local_type_id', val)}>
                                            <SelectTrigger
                                                id="local_type_id"
                                                className="w-full"
                                                leadingIcon={Building2}
                                                leadingIconClassName="text-sky-600"
                                            >
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

                                    <Field
                                        id="local_location_id"
                                        label="Ubicación"
                                        error={form.errors.local_location_id}
                                        tooltip="Zona o bloque donde se encuentra el local"
                                    >
                                        <Select
                                            value={form.data.local_location_id ?? ''}
                                            onValueChange={(val) => form.setData('local_location_id', val)}
                                        >
                                            <SelectTrigger
                                                id="local_location_id"
                                                className="w-full"
                                                leadingIcon={MapPinned}
                                                leadingIconClassName="text-green-600"
                                            >
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

                                    <Field
                                        id="area_m2"
                                        label="Área (m²)"
                                        error={form.errors.area_m2}
                                        tooltip="Área aproximada del local en metros cuadrados"
                                    >
                                        <Input
                                            name="area_m2"
                                            type="number"
                                            step="0.01"
                                            value={form.data.area_m2 ?? ''}
                                            onChange={(e) => {
                                                const v = e.target.value;
                                                form.setData('area_m2', v === '' ? null : Number(v));
                                            }}
                                            leadingIcon={Ruler}
                                            leadingIconClassName="text-purple-600"
                                            placeholder="Ej: 24.5"
                                        />
                                    </Field>
                                </div>

                                {mode === 'edit' && (
                                    <CatalogIsActiveField
                                        checked={!!form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                    />
                                )}

                                <FormActions
                                    onCancel={handleCancel}
                                    isSubmitting={form.processing}
                                    isDirty={hasUnsavedChanges}
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
