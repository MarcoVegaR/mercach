import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    code?: string | null;
    name?: string | null;
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
        code: initial.code ?? '',
        name: initial.name ?? '',
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Ubicaciones de local', href: '/catalogs/local-location' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    const firstErrorRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    function handleCancel() {
        router.visit('/catalogs/local-location', { preserveScroll: true });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (mode === 'create') {
            form.post(route('catalogs.local-location.store'));
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            form.put(route('catalogs.local-location.update', id));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar Ubicación de local' : 'Crear Ubicación de local'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {mode === 'edit' ? 'Editar' : 'Crear'} Ubicación de local
                        </h1>

                        <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                            {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field id="code" label="Código" error={form.errors.code}>
                                    <Input
                                        name="code"
                                        ref={firstErrorRef}
                                        autoFocus
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        maxLength={10}
                                        className="font-mono"
                                    />
                                </Field>

                                <Field id="name" label="Nombre" error={form.errors.name}>
                                    <Input
                                        name="name"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        maxLength={100}
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
