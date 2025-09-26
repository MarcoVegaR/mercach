import { CatalogCodeField, CatalogIsActiveField, CatalogNameField } from '@/components/catalogs/fields';
import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { FormActions } from '@/components/forms/form-actions';
import { Input } from '@/components/ui/input';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import React, { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    code?: string | null;
    name?: string | null;
    description?: string | null;
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
        description: initial.description ?? '',
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    // Track unsaved changes
    const initialData = {
        code: initial.code ?? '',
        name: initial.name ?? '',
        description: initial.description ?? '',
        is_active: Boolean(initial.is_active ?? true),
    };
    const { hasUnsavedChanges, clearUnsavedChanges } = useUnsavedChanges(form.data, initialData, true, {
        excludeKeys: ['_token', '_method', '_version'],
        ignoreUnderscored: true,
    });

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Tipos de local', href: '/catalogs/local-type' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    const firstErrorRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    function handleCancel() {
        router.visit('/catalogs/local-type', { preserveScroll: true });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (mode === 'edit' && !hasUnsavedChanges) {
            toast.info('No hay cambios para actualizar');
            return;
        }

        if (mode === 'create') {
            clearUnsavedChanges();
            form.post(route('catalogs.local-type.store'));
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            clearUnsavedChanges();
            form.put(route('catalogs.local-type.update', id));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar Tipo de local' : 'Crear Tipo de local'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {mode === 'edit' ? 'Editar' : 'Crear'} Tipo de local
                        </h1>

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
                                        maxLength={50}
                                    />

                                    <CatalogNameField
                                        value={form.data.name}
                                        onChange={(v) => form.setData('name', v)}
                                        error={form.errors.name}
                                        maxLength={120}
                                    />

                                    <Field
                                        id="description"
                                        label="Descripción"
                                        error={form.errors.description}
                                        tooltip="Descripción breve del tipo de local"
                                    >
                                        <Input
                                            name="description"
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            maxLength={200}
                                            leadingIcon={FileText}
                                            leadingIconClassName="text-blue-600"
                                            placeholder="Ej: Local comercial"
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
