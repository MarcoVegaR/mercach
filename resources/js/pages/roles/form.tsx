import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { FormSection } from '@/components/forms/form-section';
import { FormVersion } from '@/components/forms/form-version';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useClientValidation } from '@/hooks/useClientValidation';
import { useFirstErrorFocus } from '@/hooks/useFirstErrorFocus';
import AppLayout from '@/layouts/app-layout';
import { resourceCrumbs } from '@/lib/breadcrumbs';
import { sanitizeIds } from '@/lib/utils';
import { makeRoleSchema } from '@/lib/validation/schema-role';
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Database, FileText, Info, Lock, Search, Settings, Shield, Users } from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

export interface Permission {
    value: number;
    label: string;
    name: string;
    guard: string;
}

// (moved layout assignment to bottom to match project pattern)

export interface RoleFormData {
    name: string;
    guard_name: string;
    is_active: boolean;
    permissions_ids: number[];
    _version?: string | null;
    [key: string]: FormDataConvertible;
}

export interface RoleFormProps {
    mode: 'create' | 'edit';
    initial?: {
        id?: number;
        name?: string;
        guard_name?: string;
        is_active?: boolean;
        permissions_ids?: number[];
        updated_at?: string;
    };
    // When used as an embedded component (e.g., RolePicker quick-create)
    options?: {
        guards: Array<{ value: string; label: string }>;
        permissions: Permission[];
    };
    // When rendered directly as a page by Inertia (HandlesForm), data may come flat
    guards?: Array<{ value: string; label: string }>;
    permissions?: Permission[];
    model?: {
        id?: number;
        name?: string;
        guard_name?: string;
        is_active?: boolean;
        permissions_ids?: number[];
        updated_at?: string;
    };
    can?: Record<string, boolean>;
    onSaved?: () => void;
}

// Icon mapping for permission categories
const categoryIcons: Record<string, React.ElementType> = {
    roles: Shield,
    users: Users,
    settings: Settings,
    permissions: Lock,
    audit: FileText,
    default: Database,
};

const getCategoryIcon = (category: string) => {
    return categoryIcons[category.toLowerCase()] || categoryIcons.default;
};

// Color mapping for permission category icons
const categoryIconColors: Record<string, string> = {
    roles: 'text-blue-500 dark:text-blue-400',
    users: 'text-purple-500 dark:text-purple-400',
    settings: 'text-green-600 dark:text-green-400',
    permissions: 'text-amber-500 dark:text-amber-400',
    audit: 'text-rose-500 dark:text-rose-400',
    default: 'text-slate-500 dark:text-slate-400',
};

const getCategoryColor = (category: string) => {
    return categoryIconColors[category.toLowerCase()] || categoryIconColors.default;
};

// Category label translations (Spanish)
const categoryLabels: Record<string, string> = {
    settings: 'Configuración',
    users: 'Usuarios',
};

const getCategoryLabel = (category: string) => {
    const key = category.toLowerCase();
    return categoryLabels[key] ?? category;
};

export default function RoleForm(props: RoleFormProps) {
    const { mode, onSaved } = props;
    // Accept both 'initial' and 'model' (from Inertia HandlesForm)
    const initial = props.initial ?? props.model;
    // Resolve options from either nested 'options' or flat props
    const resolvedOptions = {
        guards: props.options?.guards ?? props.guards ?? [],
        permissions: props.options?.permissions ?? props.permissions ?? [],
    };
    // Safely default permissions map when not provided (e.g., embedded usage)
    const can = props.can ?? {};
    const firstErrorRef = useRef<HTMLInputElement>(null);
    const [leaveConfirmOpen, setLeaveConfirmOpen] = useState(false);
    const [, _setIsNavigating] = useState(false);
    const [, _setResumeNavigation] = useState<(() => void) | null>(null);
    const resumeNavRef = useRef<null | (() => void)>(null);

    const form = useForm<RoleFormData>({
        name: initial?.name ?? '',
        guard_name: initial?.guard_name ?? 'web',
        is_active: initial?.is_active ?? true,
        permissions_ids: initial?.permissions_ids ?? [],
        _version: mode === 'edit' ? (initial?.updated_at ?? null) : null,
    });

    // Track unsaved changes
    const initialData = {
        name: initial?.name ?? '',
        guard_name: initial?.guard_name ?? 'web',
        is_active: initial?.is_active ?? true,
        permissions_ids: initial?.permissions_ids ?? [],
    };

    const { hasUnsavedChanges, clearUnsavedChanges } = useUnsavedChanges(form.data, initialData, true, {
        excludeKeys: ['_token', '_method', '_version'],
        ignoreUnderscored: true,
        confirmMessage: '¿Estás seguro de salir? Los cambios no guardados se perderán.',
        onConfirm: (resume) => {
            // Store and open our design-system ConfirmAlert
            resumeNavRef.current = resume;
            setLeaveConfirmOpen(true);
        },
    });

    // Setup client validation
    const guards = resolvedOptions.guards?.map((g) => g.value) || ['web'];
    const schema = makeRoleSchema(guards);
    const { validateOnBlur, validateOnSubmit, errorsClient, mergeErrors } = useClientValidation(schema, () => form.data);
    const { focusFirstError } = useFirstErrorFocus();

    // Merge server and client errors
    const errors = mergeErrors(form.errors, errorsClient);

    // Filter permissions by selected guard
    const [permSearch, setPermSearch] = useState('');
    const [expandedGroups, setExpandedGroups] = useState<string[]>([]);

    // Group permissions by category
    const groupedPermissions = useMemo(() => {
        const guardPermissions = (resolvedOptions.permissions || [])
            .filter((permission) => permission.guard === form.data.guard_name)
            .filter((p) => (permSearch.trim() === '' ? true : p.label.toLowerCase().includes(permSearch.toLowerCase())));

        const groups = new Map<string, Permission[]>();

        guardPermissions.forEach((permission) => {
            // Extract category from permission name (e.g., "roles.create" -> "roles")
            const category = permission.name.split('.')[0] || 'general';

            if (!groups.has(category)) {
                groups.set(category, []);
            }
            groups.get(category)!.push(permission);
        });

        // Convert to array and sort by category name
        return Array.from(groups.entries())
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([category, perms]) => ({
                category,
                permissions: perms.sort((a, b) => a.label.localeCompare(b.label)),
                icon: getCategoryIcon(category),
                color: getCategoryColor(category),
                selectedCount: perms.filter((p) => form.data.permissions_ids.includes(p.value)).length,
                totalCount: perms.length,
            }));
    }, [resolvedOptions.permissions, form.data.guard_name, form.data.permissions_ids, permSearch]);

    // Handle expand/collapse all
    const handleExpandAll = () => {
        setExpandedGroups(groupedPermissions.map((g) => g.category));
    };

    const handleCollapseAll = () => {
        setExpandedGroups([]);
    };

    // Handle select all in a group
    const handleSelectGroup = (category: string) => {
        const group = groupedPermissions.find((g) => g.category === category);
        if (!group) return;

        const groupIds = group.permissions.map((p) => p.value);
        const merged = Array.from(new Set([...form.data.permissions_ids, ...groupIds]));
        form.setData('permissions_ids', merged);
    };

    // Handle deselect all in a group
    const handleDeselectGroup = (category: string) => {
        const group = groupedPermissions.find((g) => g.category === category);
        if (!group) return;

        const groupIds = new Set(group.permissions.map((p) => p.value));
        const remaining = form.data.permissions_ids.filter((id) => !groupIds.has(id));
        form.setData('permissions_ids', remaining);
    };

    // Handle guard change
    const handleGuardChange = (newGuard: string) => {
        form.setData('guard_name', newGuard);

        // Clear permissions that don't belong to the new guard
        const validPermissionIds = (resolvedOptions.permissions || []).filter((p) => p.guard === newGuard).map((p) => p.value);

        const filteredIds = form.data.permissions_ids.filter((id) => validPermissionIds.includes(id));

        if (filteredIds.length !== form.data.permissions_ids.length) {
            form.setData('permissions_ids', filteredIds);
            toast.info('Los permisos seleccionados se han actualizado para el nuevo guard');
        }

        // Reload permissions for the new guard (partial reload)
        router.reload({
            only: ['options'],
            data: { guard_name: newGuard },
        });
    };

    // Handle permission toggle
    const togglePermission = (permissionId: number) => {
        const current = form.data.permissions_ids;
        const updated = current.includes(permissionId) ? current.filter((id) => id !== permissionId) : [...current, permissionId];

        form.setData('permissions_ids', updated);
    };

    // Handle cancel
    const handleCancel = () => {
        router.visit(route('roles.index'), {
            preserveScroll: true,
        });
    };

    // Handle submit
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (mode === 'edit' && !hasUnsavedChanges) {
            toast.info('No hay cambios para actualizar');
            return;
        }

        // Validate client-side first
        if (!validateOnSubmit()) {
            focusFirstError(errorsClient);
            toast.error('Por favor, corrige los errores antes de continuar');
            return;
        }

        // We are submitting (non-GET). Ensure the unsaved changes guard does not interfere
        clearUnsavedChanges();

        // Frontend sanitization: ensure permissions_ids are valid integers and deduplicated
        const sanitizedPermissions = sanitizeIds((form.data.permissions_ids || []) as Array<number | string>).filter((v) => v >= 0);
        // Use transform so we don't rely on async state updates before submit
        form.transform((data) => ({
            ...data,
            permissions_ids: sanitizedPermissions,
            is_active: !!data.is_active,
        }));

        if (mode === 'create') {
            form.post(route('roles.store'), {
                onSuccess: () => {
                    if (onSaved) {
                        onSaved();
                    }
                },
                onError: (serverErrors) => {
                    toast.error('Error al crear el rol');
                    focusFirstError(serverErrors);
                },
            });
        } else {
            const roleId = Number(initial?.id);
            if (!Number.isInteger(roleId)) {
                toast.error('ID de rol inválido.');
                return;
            }
            form.put(route('roles.update', roleId), {
                onSuccess: () => {
                    if (onSaved) {
                        onSaved();
                    }
                },
                onError: (serverErrors) => {
                    toast.error('Error al actualizar el rol');
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

    return (
        <TooltipProvider delayDuration={200}>
            <Head title={mode === 'create' ? 'Crear Rol' : 'Editar Rol'} />

            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                            {/* Show error summary for long forms */}
                            {Object.keys(errors).length > 0 && <ErrorSummary errors={errors} className="mb-4" />}

                            <FormSection title="Información básica" description="Define el nombre y el guard del rol">
                                <div className="grid gap-4 md:grid-cols-2">
                                    {/* Name field */}
                                    <Field id="name" label="Nombre" required error={errors.name} hint="Ejemplo: Administrador">
                                        <Input
                                            name="name"
                                            type="text"
                                            value={form.data.name}
                                            onChange={(e) => form.setData('name', e.target.value)}
                                            onBlur={() => validateOnBlur('name')}
                                            autoFocus
                                            maxLength={100}
                                        />
                                    </Field>

                                    {/* Guard field */}
                                    <Field
                                        id="guard_name"
                                        label="Guard"
                                        required
                                        error={errors.guard_name}
                                        hint="Define el contexto de autenticación del rol"
                                    >
                                        <Select
                                            value={form.data.guard_name}
                                            onValueChange={(value) => {
                                                handleGuardChange(value);
                                                validateOnBlur('guard_name');
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Selecciona un guard" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {(resolvedOptions.guards || []).map((guard) => (
                                                    <SelectItem key={guard.value} value={guard.value}>
                                                        {guard.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </div>

                                {/* Active status (only in edit) */}
                                {mode === 'edit' && (
                                    <Field id="is_active" label="Estado activo" required error={errors.is_active} className="md:col-span-2">
                                        <ActiveField
                                            checked={form.data.is_active}
                                            onChange={(v) => {
                                                form.setData('is_active', v);
                                                validateOnBlur('is_active');
                                            }}
                                            canToggle={can['roles.setActive'] !== false}
                                            activeLabel="Rol activo"
                                            inactiveLabel="Rol inactivo"
                                            permissionHint="No tienes permisos para cambiar el estado del rol"
                                        />
                                    </Field>
                                )}

                                {/* Permission and version info */}
                                {mode === 'edit' && can['roles.setActive'] === false && (
                                    <p className="text-muted-foreground mt-2 text-sm">
                                        <Info className="mr-1 inline h-3 w-3" />
                                        No tienes permisos para cambiar el estado del rol
                                    </p>
                                )}
                                {mode === 'edit' && <FormVersion updatedAt={initial?.updated_at} version={initial?.updated_at ?? null} />}
                            </FormSection>

                            <FormSection title="Permisos" description="Asigna los permisos específicos que tendrá este rol en el sistema">
                                {groupedPermissions.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-gray-300 p-8 text-center dark:border-gray-600">
                                        <Lock className="mx-auto h-12 w-12 text-gray-400" />
                                        <p className="text-muted-foreground mt-2 text-sm">No hay permisos disponibles para el guard seleccionado</p>
                                    </div>
                                ) : (
                                    <div className="space-y-6">
                                        {/* Search and controls */}
                                        <div className="sticky top-0 z-10 -mx-4 -mt-4 bg-white p-4 dark:bg-gray-800">
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                                    <Badge variant="secondary" className="rounded-full px-2.5 py-0.5 text-xs font-medium">
                                                        {form.data.permissions_ids.length} /{' '}
                                                        {groupedPermissions.reduce((acc, g) => acc + g.totalCount, 0)}
                                                    </Badge>
                                                    <span>seleccionados</span>
                                                    <span aria-hidden="true">·</span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={handleExpandAll}
                                                        className="gap-1"
                                                        title="Expandir todos los grupos"
                                                    >
                                                        <ChevronDown className="h-3 w-3" />
                                                        Expandir todos
                                                    </Button>
                                                    <span aria-hidden="true">·</span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={handleCollapseAll}
                                                        className="gap-1"
                                                        title="Colapsar todos los grupos"
                                                    >
                                                        <ChevronUp className="h-3 w-3" />
                                                        Colapsar todos
                                                    </Button>
                                                </div>
                                                <div className="relative w-full sm:w-72">
                                                    <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                                    <Input
                                                        placeholder="Buscar permisos"
                                                        value={permSearch}
                                                        onChange={(e) => setPermSearch(e.target.value)}
                                                        className="pl-9"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        {/* Grouped permissions accordion */}
                                        <Accordion
                                            type="multiple"
                                            value={expandedGroups}
                                            onValueChange={setExpandedGroups}
                                            className="w-full space-y-2"
                                        >
                                            {groupedPermissions.map((group) => {
                                                const Icon = group.icon;
                                                const isFullySelected = group.selectedCount === group.totalCount;
                                                const groupState: boolean | 'indeterminate' = isFullySelected
                                                    ? true
                                                    : group.selectedCount > 0
                                                      ? 'indeterminate'
                                                      : false;

                                                return (
                                                    <AccordionItem
                                                        key={group.category}
                                                        value={group.category}
                                                        className="bg-card rounded-xl border px-4 shadow-sm"
                                                    >
                                                        <AccordionTrigger className="hover:no-underline">
                                                            <div className="flex w-full items-center justify-between pr-4">
                                                                <div className="flex items-center gap-3">
                                                                    <Tooltip>
                                                                        <TooltipTrigger asChild>
                                                                            <span className="inline-flex">
                                                                                <Icon className={`h-4 w-4 ${group.color}`} />
                                                                            </span>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>
                                                                            <span className="text-xs">
                                                                                {getCategoryLabel(group.category)} · {group.selectedCount} /{' '}
                                                                                {group.totalCount} seleccionados
                                                                            </span>
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                    <span className="text-sm font-medium capitalize">
                                                                        {getCategoryLabel(group.category)}
                                                                    </span>
                                                                    <Badge
                                                                        variant="secondary"
                                                                        className="ml-2 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                                    >
                                                                        {group.selectedCount} / {group.totalCount}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                        </AccordionTrigger>
                                                        <AccordionContent>
                                                            <div className="flex items-center justify-end gap-2 pt-4 pb-2">
                                                                <Checkbox
                                                                    id={`select-group-${group.category}`}
                                                                    checked={groupState}
                                                                    onCheckedChange={(value) => {
                                                                        if (value) {
                                                                            handleSelectGroup(group.category);
                                                                        } else {
                                                                            handleDeselectGroup(group.category);
                                                                        }
                                                                    }}
                                                                    disabled={form.processing}
                                                                    className="data-[state=checked]:border-primary data-[state=checked]:bg-primary focus-visible:ring-primary/50 mt-0.5 border-2 border-slate-400 shadow-sm hover:border-slate-500 dark:border-slate-500 dark:hover:border-slate-400"
                                                                />
                                                                <Label
                                                                    htmlFor={`select-group-${group.category}`}
                                                                    className="cursor-pointer text-xs font-medium sm:text-sm"
                                                                >
                                                                    Seleccionar todos
                                                                </Label>
                                                            </div>
                                                            <div className="grid gap-3 pb-2 sm:grid-cols-2">
                                                                {group.permissions.map((permission) => (
                                                                    <div key={permission.value} className="flex items-start space-x-2">
                                                                        <Checkbox
                                                                            id={`permission-${permission.value}`}
                                                                            checked={form.data.permissions_ids.includes(permission.value)}
                                                                            onCheckedChange={() => togglePermission(permission.value)}
                                                                            disabled={form.processing}
                                                                            className="data-[state=checked]:border-primary data-[state=checked]:bg-primary focus-visible:ring-primary/50 mt-0.5 border-2 border-slate-400 shadow-sm hover:border-slate-500 dark:border-slate-500 dark:hover:border-slate-400"
                                                                        />
                                                                        <Label
                                                                            htmlFor={`permission-${permission.value}`}
                                                                            className="flex-1 cursor-pointer text-sm leading-relaxed font-normal"
                                                                        >
                                                                            <span className="block">{permission.label}</span>
                                                                        </Label>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </AccordionContent>
                                                    </AccordionItem>
                                                );
                                            })}
                                        </Accordion>
                                    </div>
                                )}
                                {errors.permissions_ids && <FieldError message={errors.permissions_ids} />}
                            </FormSection>
                            <p className="text-muted-foreground text-xs">
                                <span className="text-destructive">*</span> Campo obligatorio
                            </p>
                            <FormActions
                                onCancel={handleCancel}
                                isSubmitting={form.processing}
                                isDirty={hasUnsavedChanges}
                                submitText={mode === 'create' ? 'Crear rol' : 'Actualizar rol'}
                            />
                        </form>
                    </div>
                </div>
            </div>
            {/* Confirm navigation away with unsaved changes */}
            <ConfirmAlert
                open={leaveConfirmOpen}
                onOpenChange={(open) => {
                    setLeaveConfirmOpen(open);
                    if (!open) {
                        // Clear any pending navigation callback when dialog closes
                        resumeNavRef.current = null;
                    }
                }}
                title="Descartar cambios"
                description="Tienes cambios sin guardar. ¿Deseas salir de todas formas?"
                confirmLabel="Salir sin guardar"
                cancelLabel="Seguir editando"
                confirmDestructive
                onConfirm={() => {
                    setLeaveConfirmOpen(false);
                    const resume = resumeNavRef.current;
                    resumeNavRef.current = null;
                    resume?.();
                }}
            />
        </TooltipProvider>
    );
}

// Apply App layout with header breadcrumbs
// Compute breadcrumbs from page props (mode + initial/model)
type InertiaPageWithProps<P> = React.ReactElement & { props: P };

RoleForm.layout = (
    page: InertiaPageWithProps<{ mode?: 'create' | 'edit'; initial?: { id?: number; name?: string }; model?: { id?: number; name?: string } }>,
) => {
    const props = page.props ?? {};
    const mode = (props?.mode as 'create' | 'edit') ?? 'create';
    const initial = props?.initial ?? props?.model ?? {};
    const crumbs = mode === 'edit' ? resourceCrumbs('roles', 'edit', { id: initial?.id, name: initial?.name }) : resourceCrumbs('roles', 'create');
    return <AppLayout breadcrumbs={crumbs}>{page}</AppLayout>;
};
