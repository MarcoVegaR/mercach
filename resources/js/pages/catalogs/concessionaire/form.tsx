import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { FileDropzone } from '@/components/ui/file-dropzone';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { Building2, IdCard, Mail, MapPin, Phone as PhoneIcon, User } from 'lucide-react';
// Removed Avatar import - now handled by FileDropzone
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    concessionaire_type_id?: string | null;
    full_name?: string | null;
    document_type_id?: string | null;
    document_number?: string | null;
    fiscal_address?: string | null;
    email?: string | null;
    phone_area_code_id?: string | null;
    phone_number?: string | null;
    photo_path?: string | null;
    photo_url?: string | null;
    id_document_path?: string | null;
    id_document_url?: string | null;
    is_active?: boolean | null;
    updated_at?: string | null;
}

interface PageProps {
    mode: FormMode;
    model?: ModelShape;
    options?: {
        concessionaire_types: Array<{ id: number; name: string }>;
        document_types: Array<{ id: number; name: string }>;
        phone_area_codes: Array<{ id: number; name: string }>;
    };
}

export default function FormPage(props: PageProps) {
    const mode: FormMode = props.mode ?? 'create';
    const initial = props.model ?? {};

    const opts: NonNullable<PageProps['options']> = props.options ?? {
        concessionaire_types: [],
        document_types: [],
        phone_area_codes: [],
    };

    const form = useForm({
        concessionaire_type_id: initial.concessionaire_type_id ? String(initial.concessionaire_type_id) : '',
        full_name: initial.full_name ?? '',
        document_type_id: initial.document_type_id ? String(initial.document_type_id) : '',
        document_number: initial.document_number ?? '',
        fiscal_address: initial.fiscal_address ?? '',
        email: initial.email ?? '',
        phone_area_code_id: initial.phone_area_code_id ? String(initial.phone_area_code_id) : '',
        phone_number: initial.phone_number ?? '',
        // Files for upload
        photo: null as File | null,
        id_document: null as File | null,
        // Existing stored paths
        photo_path: initial.photo_path ?? '',
        id_document_path: initial.id_document_path ?? '',
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    // Track unsaved changes to avoid submitting when nothing changed (same UX as users/roles)
    const initialData = {
        concessionaire_type_id: initial.concessionaire_type_id ? String(initial.concessionaire_type_id) : '',
        full_name: initial.full_name ?? '',
        document_type_id: initial.document_type_id ? String(initial.document_type_id) : '',
        document_number: initial.document_number ?? '',
        fiscal_address: initial.fiscal_address ?? '',
        email: initial.email ?? '',
        phone_area_code_id: initial.phone_area_code_id ? String(initial.phone_area_code_id) : '',
        phone_number: initial.phone_number ?? '',
        photo_path: initial.photo_path ?? '',
        id_document_path: initial.id_document_path ?? '',
        is_active: Boolean(initial.is_active ?? true),
    };

    const { hasUnsavedChanges, clearUnsavedChanges } = useUnsavedChanges(form.data, initialData, true, {
        excludeKeys: ['_token', '_method', '_version', 'photo', 'id_document'],
        ignoreUnderscored: true,
        confirmMessage: '¿Estás seguro de salir? Los cambios no guardados se perderán.',
    });

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Concesionarios', href: '/catalogs/concessionaire' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    const firstErrorRef = useRef<HTMLInputElement>(null);
    const photoInputRef = useRef<HTMLInputElement>(null);
    const idDocumentInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    function handleCancel() {
        router.visit('/catalogs/concessionaire', { preserveScroll: true });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        // Avoid updating when there are no changes (consistent with users/roles forms)
        if (mode === 'edit' && !hasUnsavedChanges) {
            toast.info('No hay cambios para actualizar');
            return;
        }

        // Coerce types and ensure we send all required fields with FormData
        const transformOnce = (data: typeof form.data) => {
            const payload: Record<string, any> = { ...data };
            // Coerce IDs to numbers where applicable
            payload.concessionaire_type_id = data.concessionaire_type_id ? Number(data.concessionaire_type_id) : '';
            payload.document_type_id = data.document_type_id ? Number(data.document_type_id) : '';
            payload.phone_area_code_id = data.phone_area_code_id ? Number(data.phone_area_code_id) : '';
            // Booleans as 1/0 for consistent backend boolean rule
            payload.is_active = data.is_active ? 1 : 0;
            // Remove null file fields so backend keeps existing files
            if (!data.photo) delete payload.photo;
            if (!data.id_document) delete payload.id_document;
            return payload;
        };

        if (mode === 'create') {
            form.transform(transformOnce);
            // We are submitting, disable unsaved-changes guard to avoid blocking navigations
            clearUnsavedChanges();
            form.post(route('catalogs.concessionaire.store'), { forceFormData: true, preserveScroll: true });
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            // Use POST + method spoofing to ensure PHP parses multipart form-data
            form.transform((data) => ({ ...transformOnce(data), _method: 'put' }));
            clearUnsavedChanges();
            form.post(route('catalogs.concessionaire.update', { concessionaire: id }), {
                forceFormData: true,
                preserveScroll: true,
                onError: () => {
                    toast.error('No se pudo actualizar. Revisa los campos marcados.');
                },
            });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar Concesionario' : 'Crear Concesionario'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {mode === 'edit' ? 'Editar' : 'Crear'} Concesionario
                        </h1>

                        <TooltipProvider delayDuration={300}>
                            <form
                                onSubmit={handleSubmit}
                                encType="multipart/form-data"
                                className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7"
                            >
                                {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        id="concessionaire_type_id"
                                        label="Tipo de concesionario"
                                        error={form.errors.concessionaire_type_id}
                                        tooltip="Selecciona la naturaleza jurídica del concesionario"
                                        required
                                    >
                                        <Select
                                            value={form.data.concessionaire_type_id ?? ''}
                                            onValueChange={(val) => form.setData('concessionaire_type_id', val)}
                                        >
                                            <SelectTrigger
                                                id="concessionaire_type_id"
                                                className="w-full"
                                                leadingIcon={Building2}
                                                leadingIconClassName="text-teal-600"
                                            >
                                                <SelectValue placeholder="Seleccionar tipo" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {opts.concessionaire_types.map((m) => (
                                                    <SelectItem key={m.id} value={String(m.id)}>
                                                        {m.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field
                                        id="full_name"
                                        label="Nombre completo"
                                        error={form.errors.full_name}
                                        tooltip="Nombre legal o comercial del concesionario"
                                        required
                                    >
                                        <Input
                                            name="full_name"
                                            value={form.data.full_name}
                                            onChange={(e) => form.setData('full_name', e.target.value)}
                                            maxLength={160}
                                            leadingIcon={User}
                                            leadingIconClassName="text-blue-600"
                                            placeholder="Ingrese el nombre completo"
                                        />
                                    </Field>

                                    <Field
                                        id="document_type_id"
                                        label="Tipo de documento"
                                        error={form.errors.document_type_id}
                                        tooltip="Tipo de identificación: V (Venezolano), E (Extranjero), J (Jurídico), G (Gobierno), P (Pasaporte)"
                                        required
                                    >
                                        <Select
                                            value={form.data.document_type_id ?? ''}
                                            onValueChange={(val) => form.setData('document_type_id', val)}
                                        >
                                            <SelectTrigger
                                                id="document_type_id"
                                                className="w-full"
                                                leadingIcon={IdCard}
                                                leadingIconClassName="text-amber-600"
                                            >
                                                <SelectValue placeholder="Seleccionar tipo de documento" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {opts.document_types.map((m) => (
                                                    <SelectItem key={m.id} value={String(m.id)}>
                                                        {m.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field
                                        id="document_number"
                                        label="Número de documento"
                                        error={form.errors.document_number}
                                        tooltip="Ingrese el número sin puntos ni espacios. Ejemplo: 12345678"
                                        required
                                    >
                                        <Input
                                            name="document_number"
                                            value={form.data.document_number}
                                            onChange={(e) => form.setData('document_number', e.target.value)}
                                            onBlur={() => form.setData('document_number', (form.data.document_number ?? '').toUpperCase().trim())}
                                            maxLength={30}
                                            leadingIcon={IdCard}
                                            leadingIconClassName="text-amber-600"
                                            placeholder="Ej: 12345678"
                                        />
                                    </Field>

                                    <Field
                                        id="fiscal_address"
                                        label="Dirección fiscal"
                                        error={form.errors.fiscal_address}
                                        tooltip="Dirección completa incluyendo calle, número, edificio, ciudad y estado"
                                    >
                                        <div className="relative">
                                            <div className="text-muted-foreground pointer-events-none absolute top-3 left-3">
                                                <MapPin className="h-4 w-4 text-rose-600 dark:text-rose-400" />
                                            </div>
                                            <Textarea
                                                id="fiscal_address"
                                                name="fiscal_address"
                                                value={form.data.fiscal_address ?? ''}
                                                onChange={(e) => form.setData('fiscal_address', e.target.value)}
                                                maxLength={255}
                                                rows={3}
                                                className="pl-9"
                                                placeholder="Ingrese la dirección fiscal completa"
                                            />
                                        </div>
                                    </Field>

                                    <Field
                                        id="email"
                                        label="Correo electrónico"
                                        error={form.errors.email}
                                        tooltip="Correo electrónico para contacto y notificaciones del sistema"
                                        required
                                    >
                                        <Input
                                            name="email"
                                            type="email"
                                            value={form.data.email}
                                            onChange={(e) => form.setData('email', e.target.value)}
                                            onBlur={() => form.setData('email', (form.data.email ?? '').toLowerCase().trim())}
                                            maxLength={160}
                                            leadingIcon={Mail}
                                            leadingIconClassName="text-purple-600"
                                            placeholder="ejemplo@correo.com"
                                        />
                                    </Field>

                                    {/* Teléfono (código + número) agrupado */}
                                    <Field
                                        id="phone"
                                        label="Teléfono"
                                        error={form.errors.phone_area_code_id || form.errors.phone_number}
                                        className="md:col-span-2"
                                        tooltip="Selecciona el código de área e ingresa el número de 7 dígitos"
                                    >
                                        <div className="flex flex-wrap items-start gap-3">
                                            <div className="w-32 md:w-40">
                                                <Select
                                                    value={form.data.phone_area_code_id ?? ''}
                                                    onValueChange={(val) => form.setData('phone_area_code_id', val)}
                                                >
                                                    <SelectTrigger
                                                        id="phone_area_code_id"
                                                        className="w-full"
                                                        leadingIcon={PhoneIcon}
                                                        leadingIconClassName="text-emerald-600"
                                                    >
                                                        <SelectValue placeholder="Código" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {opts.phone_area_codes.map((m) => (
                                                            <SelectItem key={m.id} value={String(m.id)}>
                                                                {m.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <div className="mt-1">
                                                    <FieldError message={form.errors.phone_area_code_id} />
                                                </div>
                                            </div>
                                            <div className="min-w-[12rem] flex-1">
                                                <Input
                                                    name="phone_number"
                                                    value={form.data.phone_number}
                                                    onChange={(e) => form.setData('phone_number', e.target.value)}
                                                    maxLength={7}
                                                    placeholder="1234567"
                                                />
                                                <div className="mt-1">
                                                    <FieldError message={form.errors.phone_number} />
                                                </div>
                                            </div>
                                        </div>
                                    </Field>

                                    <Field
                                        id="photo"
                                        label="Foto"
                                        error={form.errors.photo as any}
                                        tooltip="Imagen del concesionario en formato PNG o JPG, tamaño máximo 5 MB"
                                    >
                                        <FileDropzone
                                            ref={photoInputRef}
                                            onFileSelect={(file) => {
                                                if (file && file.size > 5 * 1024 * 1024) {
                                                    toast.error('La foto supera el tamaño máximo permitido (5 MB).');
                                                    return;
                                                }
                                                form.setData('photo', file);
                                            }}
                                            file={form.data.photo}
                                            existingFileUrl={
                                                form.data.photo_path ? `/storage/${form.data.photo_path}` : initial.photo_url || undefined
                                            }
                                            existingFileName={form.data.photo_path ? String(form.data.photo_path).split('/').pop() : undefined}
                                            accept="image/png,image/jpeg"
                                            maxSize="5 MB"
                                            preview={true}
                                            placeholder="Seleccionar foto"
                                        />
                                    </Field>

                                    <Field
                                        id="id_document"
                                        label="Documento de identidad"
                                        error={form.errors.id_document as any}
                                        tooltip="Documento de identidad en formato PDF, PNG o JPG, tamaño máximo 5 MB"
                                    >
                                        <FileDropzone
                                            ref={idDocumentInputRef}
                                            onFileSelect={(file) => {
                                                if (file && file.size > 5 * 1024 * 1024) {
                                                    toast.error('El documento supera el tamaño máximo permitido (5 MB).');
                                                    return;
                                                }
                                                form.setData('id_document', file);
                                            }}
                                            file={form.data.id_document}
                                            existingFileUrl={
                                                form.data.id_document_path
                                                    ? `/storage/${form.data.id_document_path}`
                                                    : initial.id_document_url || undefined
                                            }
                                            existingFileName={
                                                form.data.id_document_path ? String(form.data.id_document_path).split('/').pop() : undefined
                                            }
                                            accept="application/pdf,image/png,image/jpeg"
                                            maxSize="5 MB"
                                            preview={false}
                                            placeholder="Seleccionar documento"
                                        />
                                    </Field>
                                </div>

                                {/* Estado activo se maneja desde el listado, no desde el formulario */}

                                {/* Removed required fields notice - asterisks are self-explanatory */}

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
