import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { FormSection } from '@/components/forms/form-section';
import { FormVersion } from '@/components/forms/form-version';
import { RolePicker } from '@/components/pickers/role-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useClientValidation } from '@/hooks/useClientValidation';
import { useFirstErrorFocus } from '@/hooks/useFirstErrorFocus';
import AppLayout from '@/layouts/app-layout';
import { resourceCrumbs } from '@/lib/breadcrumbs';
import { sanitizeIds } from '@/lib/utils';
import { makeUserSchema } from '@/lib/validation/schema-user';
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router, useForm } from '@inertiajs/react';
import { zxcvbn, zxcvbnOptions } from '@zxcvbn-ts/core';
import { adjacencyGraphs, dictionary as commonDictionary } from '@zxcvbn-ts/language-common';
import { dictionary as esDictionary, translations } from '@zxcvbn-ts/language-es-es';
import { Copy as CopyIcon, Eye, EyeOff, Info, Lock, Mail, Shield, User, Wand2 } from 'lucide-react';
import React, { useEffect, useMemo, useRef } from 'react';
import { toast } from 'sonner';

export interface UserFormProps {
    mode: 'create' | 'edit';
    // The HandlesForm trait sends `model`; keep `initial` for backward-compatibility.
    model?: {
        id?: number;
        name?: string;
        email?: string;
        is_active?: boolean;
        roles_ids?: number[];
        roles?: Array<{ id: number; name: string }>;
        updated_at?: string | null;
    };
    initial?: {
        id?: number;
        name?: string;
        email?: string;
        is_active?: boolean;
        roles_ids?: number[];
        roles?: Array<{ id: number; name: string }>;
        updated_at?: string | null;
    };
    options: { roleOptions: Array<{ id: number; name: string }> };
    can?: Record<string, boolean>;
    onSaved?: () => void;
}

export interface UserFormData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    is_active: boolean;
    roles_ids: number[];
    _version: string | null;
    [key: string]: FormDataConvertible;
}

export default function UserForm({ mode, model, initial, options, can, onSaved }: UserFormProps) {
    const firstErrorRef = useRef<HTMLInputElement>(null);

    // Prefer `model` from backend; fallback to `initial`
    type WithRoles = { roles?: Array<{ id: number; name: string }>; roles_ids?: number[] };
    const initialModel = (model ?? initial) as (UserFormProps['model'] & WithRoles) | undefined;

    // Derive roles_ids from initialModel.roles if roles_ids not provided
    const initialRolesIds = React.useMemo(() => {
        if (initialModel?.roles_ids && Array.isArray(initialModel.roles_ids)) return initialModel.roles_ids;
        const roles = initialModel?.roles as Array<{ id: number }> | undefined;
        if (Array.isArray(roles)) return roles.map((r) => r.id);
        return [] as number[];
    }, [initialModel]);

    const form = useForm<UserFormData>({
        name: initialModel?.name ?? '',
        email: initialModel?.email ?? '',
        password: '',
        password_confirmation: '',
        is_active: initialModel?.is_active ?? true,
        roles_ids: initialRolesIds,
        _version: mode === 'edit' ? (initialModel?.updated_at ?? null) : null,
    });

    // Unsaved changes tracking (compare initial vs current)
    const initialData = useMemo(
        () => ({
            name: initialModel?.name ?? '',
            email: initialModel?.email ?? '',
            is_active: initialModel?.is_active ?? true,
            roles_ids: initialRolesIds,
        }),
        [initialModel, initialRolesIds],
    );

    const [navConfirm, setNavConfirm] = React.useState<{ open: boolean; resume?: () => void } | null>(null);
    const [showPassword, setShowPassword] = React.useState(false);
    const [copyAnnouncement, setCopyAnnouncement] = React.useState('');

    const { hasUnsavedChanges, clearUnsavedChanges } = useUnsavedChanges(form.data, initialData, true, {
        excludeKeys: ['_token', '_method', '_version', 'password', 'password_confirmation'],
        ignoreUnderscored: true,
        confirmMessage: '¿Estás seguro de salir? Los cambios no guardados se perderán.',
        onConfirm: (resume) => {
            setNavConfirm({ open: true, resume });
        },
    });

    // Client-side validation (Zod) with the same pattern used by roles
    const schema = useMemo(() => makeUserSchema(mode), [mode]);
    const { validateOnBlur, validateOnSubmit, errorsClient, mergeErrors } = useClientValidation(schema, () => form.data);
    const { focusFirstError } = useFirstErrorFocus();

    // Initialize zxcvbn (Spanish dictionaries and graphs) once
    React.useEffect(() => {
        zxcvbnOptions.setOptions({
            translations,
            dictionary: {
                ...commonDictionary,
                ...esDictionary,
            },
            graphs: adjacencyGraphs,
        });
    }, []);

    // Password strength evaluation
    const zx = useMemo(() => {
        const pwd = (form.data.password as string) || '';
        if (!pwd) return null;
        try {
            return zxcvbn(pwd, [form.data.name ?? '', form.data.email ?? '']);
        } catch {
            return null;
        }
    }, [form.data.password, form.data.name, form.data.email]);

    const score = zx?.score ?? 0;
    const scoreLabel = ['Muy débil', 'Débil', 'Aceptable', 'Fuerte', 'Excelente'][score] ?? '';
    const scoreColor = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-lime-500', 'bg-green-600'][score] ?? 'bg-gray-300';

    // Secure password generator (ensures at least one char of each class)
    const generateSecurePassword = useMemo(
        () =>
            function (length = 16) {
                const U = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
                const L = 'abcdefghijkmnopqrstuvwxyz';
                const D = '23456789';
                const S = '!@#$%^&*()-_=+[]{};:,.?/';
                const all = U + L + D + S;

                const pick = (set: string) => set[Math.floor(Math.random() * set.length)];
                const chars = [pick(U), pick(L), pick(D), pick(S)];
                for (let i = chars.length; i < length; i++) {
                    chars.push(pick(all));
                }
                // Shuffle (Fisher-Yates)
                for (let i = chars.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [chars[i], chars[j]] = [chars[j], chars[i]];
                }
                return chars.join('');
            },
        [],
    );

    const handleGeneratePassword = () => {
        const pwd = generateSecurePassword(16);
        form.setData('password', pwd);
        form.setData('password_confirmation', pwd);
        toast.success('Contraseña generada');
    };

    const handleCopyPassword = async () => {
        const pwd = (form.data.password as string) || '';
        if (!pwd) return;
        try {
            await navigator.clipboard.writeText(pwd);
            setCopyAnnouncement('Copiada');
            setTimeout(() => setCopyAnnouncement(''), 1200);
            toast.success('Contraseña copiada');
        } catch {
            toast.error('No se pudo copiar la contraseña');
        }
    };

    // Merge server and client errors
    const errors = mergeErrors(form.errors, errorsClient);

    // Cancel -> go back to users index
    const handleCancel = () => {
        router.visit(route('users.index'));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (mode === 'edit' && !hasUnsavedChanges) {
            toast.info('No hay cambios para actualizar');
            return;
        }

        if (!validateOnSubmit()) {
            focusFirstError(errorsClient);
            toast.error('Por favor, corrige los errores antes de continuar');
            return;
        }

        // Clear unsaved-changes guard on actual submit
        clearUnsavedChanges();

        form.transform((data) => ({
            ...data,
            roles_ids: sanitizeIds((data.roles_ids as Array<number | string>) ?? []),
            is_active: !!data.is_active,
        }));

        if (mode === 'create') {
            form.post(route('users.store'), {
                onSuccess: () => onSaved?.(),
                onError: (serverErrors) => {
                    toast.error('Error al crear el usuario');
                    focusFirstError(serverErrors);
                },
            });
        } else {
            const id = Number(initialModel?.id);
            if (!Number.isInteger(id)) {
                toast.error('ID de usuario inválido');
                return;
            }
            form.put(route('users.update', { user: id }), {
                onSuccess: () => onSaved?.(),
                onError: (serverErrors) => {
                    toast.error('Error al actualizar el usuario');
                    focusFirstError(serverErrors);
                },
            });
        }
    };

    // Focus first error on validation errors
    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    // Map roles for RolePicker
    const rolePickerOptions = (options?.roleOptions || []).map((r) => ({ id: r.id, name: r.name, guard_name: 'web', is_active: true }));

    return (
        <>
            <Head title={mode === 'create' ? 'Crear Usuario' : 'Editar Usuario'} />

            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <TooltipProvider delayDuration={300}>
                            <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                                {/* Error summary */}
                                {Object.keys(errors).length > 0 && <ErrorSummary errors={errors} className="mb-4" />}

                                <FormSection title="Información básica" description="Datos principales del usuario">
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {/* Name */}
                                        <Field
                                            id="name"
                                            label="Nombre"
                                            required
                                            error={errors.name}
                                            tooltip="Nombre completo del usuario. Ejemplo: Juan Pérez"
                                        >
                                            <Input
                                                ref={firstErrorRef}
                                                name="name"
                                                type="text"
                                                value={form.data.name}
                                                onChange={(e) => form.setData('name', e.target.value)}
                                                onBlur={() => validateOnBlur('name')}
                                                maxLength={100}
                                                leadingIcon={User}
                                                leadingIconClassName="text-blue-600"
                                                placeholder="Ingrese el nombre completo"
                                            />
                                        </Field>

                                        {/* Email */}
                                        <Field
                                            id="email"
                                            label="Email"
                                            required
                                            error={errors.email}
                                            tooltip="Correo electrónico único para acceso al sistema"
                                        >
                                            <Input
                                                name="email"
                                                type="email"
                                                value={form.data.email}
                                                onChange={(e) => form.setData('email', e.target.value)}
                                                onBlur={() => validateOnBlur('email')}
                                                maxLength={150}
                                                leadingIcon={Mail}
                                                leadingIconClassName="text-purple-600"
                                                placeholder="usuario@empresa.com"
                                            />
                                        </Field>

                                        {/* Password */}
                                        <Field
                                            id="password"
                                            label="Contraseña"
                                            required={mode === 'create'}
                                            error={errors.password}
                                            tooltip={
                                                mode === 'create'
                                                    ? 'Contraseña segura para el acceso del usuario'
                                                    : 'Dejar vacío para mantener la contraseña actual'
                                            }
                                        >
                                            <div className="flex flex-col gap-2">
                                                <Input
                                                    name="password"
                                                    type={showPassword ? 'text' : 'password'}
                                                    autoComplete="new-password"
                                                    value={form.data.password}
                                                    onChange={(e) => form.setData('password', e.target.value)}
                                                    onBlur={() => validateOnBlur('password')}
                                                    maxLength={24}
                                                    leadingIcon={Lock}
                                                    leadingIconClassName="text-amber-600"
                                                    placeholder={mode === 'create' ? 'Ingrese contraseña segura' : 'Dejar vacío para mantener actual'}
                                                />
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={handleGeneratePassword}
                                                        title="Generar contraseña segura"
                                                    >
                                                        <Wand2 className="h-4 w-4" />
                                                        <span className="sr-only sm:not-sr-only sm:ml-1">Generar</span>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={handleCopyPassword}
                                                        title="Copiar contraseña"
                                                        disabled={!form.data.password}
                                                    >
                                                        <CopyIcon className="h-4 w-4" />
                                                        <span className="sr-only sm:not-sr-only sm:ml-1">Copiar</span>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setShowPassword((v) => !v)}
                                                        title={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                                    >
                                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                        <span className="sr-only sm:not-sr-only sm:ml-1">{showPassword ? 'Ocultar' : 'Mostrar'}</span>
                                                    </Button>
                                                </div>
                                                <span aria-live="polite" role="status" className="sr-only">
                                                    {copyAnnouncement}
                                                </span>
                                                {form.data.password && (
                                                    <div className="mt-1">
                                                        <div className="overflow-hidden rounded bg-gray-200 dark:bg-gray-700">
                                                            <div
                                                                className={`${scoreColor} h-2 transition-all`}
                                                                style={{ width: `${Math.max(1, score + 1) * 20}%` }}
                                                            />
                                                        </div>
                                                        <div className="text-muted-foreground mt-1 text-xs">
                                                            {scoreLabel}
                                                            {zx?.feedback?.warning ? ` — ${zx.feedback.warning}` : ''}
                                                        </div>
                                                    </div>
                                                )}
                                                <p className="text-muted-foreground text-xs">
                                                    Mínimo 8 caracteres. Debe incluir al menos una mayúscula, una minúscula, un dígito y un símbolo.
                                                    Se permiten espacios y cualquier carácter. Recomendado 12+.
                                                </p>
                                            </div>
                                        </Field>

                                        {/* Password confirmation */}
                                        <Field
                                            id="password_confirmation"
                                            label="Confirmar contraseña"
                                            required={mode === 'create'}
                                            error={errors.password_confirmation}
                                            tooltip="Debe coincidir exactamente con la contraseña anterior"
                                        >
                                            <Input
                                                name="password_confirmation"
                                                type="password"
                                                value={form.data.password_confirmation}
                                                onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                                onBlur={() => validateOnBlur('password_confirmation')}
                                                maxLength={24}
                                                leadingIcon={Lock}
                                                leadingIconClassName="text-amber-600"
                                                placeholder="Repita la contraseña"
                                            />
                                        </Field>
                                    </div>

                                    {/* Active status (only in edit, same pattern as roles) */}
                                    {mode === 'edit' && (
                                        <Field
                                            id="is_active"
                                            label="Estado activo"
                                            error={errors.is_active}
                                            className="md:col-span-2"
                                            tooltip="Controla si el usuario puede acceder al sistema"
                                        >
                                            <ActiveField
                                                checked={form.data.is_active}
                                                onChange={(v) => {
                                                    form.setData('is_active', v);
                                                    validateOnBlur('is_active');
                                                }}
                                                canToggle={(can ?? {})['users.setActive'] !== false}
                                                activeLabel="Usuario activo"
                                                inactiveLabel="Usuario inactivo"
                                                permissionHint="No tienes permisos para cambiar el estado del usuario"
                                            />
                                        </Field>
                                    )}

                                    {mode === 'edit' && (can ?? {})['users.setActive'] === false && (
                                        <p className="text-muted-foreground mt-2 text-sm">
                                            <Info className="mr-1 inline h-3 w-3" />
                                            No tienes permisos para cambiar el estado del usuario
                                        </p>
                                    )}

                                    {mode === 'edit' && (
                                        <FormVersion updatedAt={initialModel?.updated_at ?? undefined} version={initialModel?.updated_at ?? null} />
                                    )}
                                </FormSection>

                                {/* Roles */}
                                <FormSection title="Roles" description="Asigna los roles que tendrá este usuario">
                                    <div className="space-y-2">
                                        <Label className="text-sm font-medium">
                                            <Shield className="mr-1 inline h-4 w-4" /> Roles asignados
                                        </Label>
                                        <RolePicker
                                            multiple
                                            value={form.data.roles_ids}
                                            onChange={(val) => form.setData('roles_ids', (val as number[]) ?? [])}
                                            options={rolePickerOptions}
                                            allowCreate={false}
                                            canCreate={false}
                                        />
                                        {errors.roles_ids && <FieldError message={errors.roles_ids} />}
                                    </div>
                                </FormSection>

                                {/* Required fields notice removed - tooltips provide context */}

                                <FormActions
                                    onCancel={handleCancel}
                                    isSubmitting={form.processing}
                                    isDirty={hasUnsavedChanges}
                                    submitText={mode === 'create' ? 'Crear usuario' : 'Actualizar usuario'}
                                />
                            </form>
                        </TooltipProvider>
                    </div>
                </div>
            </div>

            {/* Unsaved changes confirmation dialog (replaces native confirm) */}
            <ConfirmAlert
                open={Boolean(navConfirm?.open)}
                onOpenChange={(open) => {
                    if (!open) setNavConfirm(null);
                }}
                title="Descartar cambios"
                description="Tienes cambios sin guardar. ¿Deseas salir de todas formas?"
                confirmLabel="Salir sin guardar"
                cancelLabel="Seguir editando"
                confirmDestructive
                onConfirm={() => {
                    const resume = navConfirm?.resume;
                    setNavConfirm(null);
                    resume?.();
                }}
            />
        </>
    );
}

// Apply App layout with header breadcrumbs using centralized helper
type InertiaPageWithProps<P> = React.ReactElement & { props: P };

UserForm.layout = (
    page: InertiaPageWithProps<{ mode?: 'create' | 'edit'; model?: { id?: number; name?: string }; initial?: { id?: number; name?: string } }>,
) => {
    const props = page.props ?? {};
    const mode = (props?.mode as 'create' | 'edit') ?? 'create';
    const initial = props?.model ?? props?.initial ?? {};
    const crumbs = mode === 'edit' ? resourceCrumbs('users', 'edit', { id: initial?.id, name: initial?.name }) : resourceCrumbs('users', 'create');
    return <AppLayout breadcrumbs={crumbs}>{page}</AppLayout>;
};

// Controlled ConfirmAlert UI for unsaved changes (Inertia navigations)
// Placed after component definition to keep JSX clean in main return
// Note: This pattern leverages the onConfirm override from useUnsavedChanges above
// to show our design system AlertDialog instead of window.confirm
// (Optional helper component removed; inline ConfirmAlert is rendered above)
