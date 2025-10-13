import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Hash, Landmark, Phone, User } from 'lucide-react';
import React, { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    bank_id?: string | null;
    account_number?: string | null;
    phone_number?: string | null;
    account_holder_name?: string | null;
    document_type?: string | null;
    document_number?: string | null;
    is_active?: boolean | null;
    updated_at?: string | null;
}

interface PageProps {
    mode: FormMode;
    model?: ModelShape;
    options?: { banks: Array<{ id: number; name: string }> };
}

export default function FormPage(props: PageProps) {
    const mode: FormMode = props.mode ?? 'create';
    const initial = props.model ?? {};
    const opts = props.options ?? { banks: [] };

    const form = useForm({
        bank_id: initial.bank_id ?? '',
        account_number: initial.account_number ?? '',
        phone_number: initial.phone_number ?? '',
        account_holder_name: initial.account_holder_name ?? '',
        document_type: initial.document_type ?? '',
        document_number: initial.document_number ?? '',
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Cuentas receptoras', href: '/catalogs/company-bank-account' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    const firstErrorRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    function handleCancel() {
        router.visit('/catalogs/company-bank-account', { preserveScroll: true });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (mode === 'create') {
            form.post(route('catalogs.company-bank-account.store'));
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            form.put(route('catalogs.company-bank-account.update', id));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar Cuenta receptora' : 'Crear Cuenta receptora'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {mode === 'edit' ? 'Editar' : 'Crear'} Cuenta receptora
                        </h1>

                        <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                            {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field id="bank_id" label="Banco" error={form.errors.bank_id}>
                                    <Select value={String(form.data.bank_id ?? '')} onValueChange={(val) => form.setData('bank_id', val)}>
                                        <SelectTrigger id="bank_id" className="w-full" leadingIcon={Landmark} leadingIconClassName="text-indigo-600">
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

                                <Field id="account_number" label="Número de cuenta" error={form.errors.account_number}>
                                    <Input
                                        name="account_number"
                                        value={form.data.account_number}
                                        onChange={(e) => form.setData('account_number', e.target.value.replace(/\D+/g, '').slice(0, 20))}
                                        maxLength={20}
                                        leadingIcon={Hash}
                                        leadingIconClassName="text-emerald-600"
                                        placeholder="Solo números (20)"
                                    />
                                </Field>

                                <Field id="phone_number" label="Teléfono" error={form.errors.phone_number}>
                                    <Input
                                        name="phone_number"
                                        value={form.data.phone_number}
                                        onChange={(e) => form.setData('phone_number', e.target.value.replace(/\D+/g, '').slice(0, 11))}
                                        maxLength={11}
                                        leadingIcon={Phone}
                                        leadingIconClassName="text-sky-600"
                                        placeholder="Ej: 04121234567"
                                    />
                                </Field>

                                <Field id="account_holder_name" label="Titular" error={form.errors.account_holder_name}>
                                    <Input
                                        name="account_holder_name"
                                        value={form.data.account_holder_name}
                                        onChange={(e) => form.setData('account_holder_name', e.target.value)}
                                        maxLength={160}
                                        leadingIcon={User}
                                        leadingIconClassName="text-indigo-600"
                                    />
                                </Field>

                                <Field id="document_type" label="Tipo de documento" error={form.errors.document_type}>
                                    <Select value={String(form.data.document_type ?? '')} onValueChange={(val) => form.setData('document_type', val)}>
                                        <SelectTrigger id="document_type" className="w-full">
                                            <SelectValue placeholder="Seleccionar tipo" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="J">J</SelectItem>
                                            <SelectItem value="G">G</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field id="document_number" label="Número de documento" error={form.errors.document_number}>
                                    <Input
                                        name="document_number"
                                        value={form.data.document_number}
                                        onChange={(e) => form.setData('document_number', e.target.value.replace(/\D+/g, '').slice(0, 12))}
                                        maxLength={12}
                                        placeholder="Solo números (6-12)"
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
