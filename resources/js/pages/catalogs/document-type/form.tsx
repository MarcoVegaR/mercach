import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { Input } from '@/components/ui/input';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Code, FileText, Hash } from 'lucide-react';
import React, { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    code?: string | null;
    name?: string | null;
    mask?: string | null;
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
        mask: initial.mask ?? '',
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    // Track unsaved changes similar to users/roles forms
    const initialData = {
        code: initial.code ?? '',
        name: initial.name ?? '',
        mask: initial.mask ?? '',
        is_active: Boolean(initial.is_active ?? true),
    };
    const { hasUnsavedChanges, clearUnsavedChanges } = useUnsavedChanges(form.data, initialData, true, {
        excludeKeys: ['_token', '_method', '_version'],
        ignoreUnderscored: true,
    });

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Tipos de documento', href: '/catalogs/document-type' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    const firstErrorRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    function handleCancel() {
        router.visit('/catalogs/document-type', { preserveScroll: true });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (mode === 'edit' && !hasUnsavedChanges) {
            toast.info('No hay cambios para actualizar');
            return;
        }

        if (mode === 'create') {
            clearUnsavedChanges();
            form.post(route('catalogs.document-type.store'));
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            clearUnsavedChanges();
            form.put(route('catalogs.document-type.update', id));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar Tipo de documento' : 'Crear Tipo de documento'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {mode === 'edit' ? 'Editar' : 'Crear'} Tipo de documento
                        </h1>

                        <TooltipProvider delayDuration={300}>
                            <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                                {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        id="code"
                                        label="Código"
                                        error={form.errors.code}
                                        tooltip="Código único alfanumérico. Ejemplo: V, E, J, P"
                                        required
                                    >
                                        <Input
                                            name="code"
                                            ref={firstErrorRef}
                                            autoFocus
                                            value={form.data.code}
                                            onChange={(e) => form.setData('code', e.target.value)}
                                            maxLength={10}
                                            className="font-mono"
                                            leadingIcon={Hash}
                                            leadingIconClassName="text-amber-600"
                                            placeholder="V"
                                        />
                                    </Field>

                                    <Field
                                        id="name"
                                        label="Nombre"
                                        error={form.errors.name}
                                        tooltip="Nombre descriptivo del tipo de documento"
                                        required
                                    >
                                        <Input
                                            name="name"
                                            value={form.data.name}
                                            onChange={(e) => form.setData('name', e.target.value)}
                                            maxLength={100}
                                            leadingIcon={FileText}
                                            leadingIconClassName="text-teal-600"
                                            placeholder="Venezolano"
                                        />
                                    </Field>

                                    <Field
                                        id="mask"
                                        label="Máscara"
                                        error={form.errors.mask}
                                        tooltip="Patrón de formato para el documento. Ejemplo: 99999999"
                                    >
                                        <Input
                                            name="mask"
                                            value={form.data.mask}
                                            onChange={(e) => form.setData('mask', e.target.value)}
                                            maxLength={30}
                                            leadingIcon={Code}
                                            leadingIconClassName="text-indigo-600"
                                            placeholder="99999999"
                                            className="font-mono"
                                        />
                                    </Field>
                                </div>

                                {mode === 'edit' && (
                                    <Field
                                        id="is_active"
                                        label="Estado activo"
                                        error={form.errors.is_active}
                                        tooltip="Controla si el tipo está disponible para uso"
                                    >
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

                                {/* Required fields notice removed - tooltips provide context */}

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
