---
title: 'Formularios de Roles'
summary: 'Patrón de formularios de Roles con React/Inertia: validación, recargas parciales, cambios sin guardar, bloqueo optimista y accesibilidad.'
icon: material/form-select
tags:
    - how-to
    - frontend
    - roles
---

# Roles Forms Pattern

## Overview

The Roles forms implementation provides a complete CRUD interface for managing roles with React/Inertia, following best practices for accessibility, validation, and user experience.

## Components Structure

### Pages

- **`pages/roles/form.tsx`** - RoleForm page used for both create and edit (rendered as Inertia view `roles/form`). It declares `RoleForm.layout = (page) => <AppLayout>{page}</AppLayout>` so the sidebar is shown consistently.
- **Note**: `pages/roles/create.tsx` and `pages/roles/edit.tsx` are not used in this boilerplate; the controller renders `roles/form` directly for both actions.

### Components

- **`components/pickers/role-picker.tsx`** - Reusable role selector with search and quick-create
- **`components/forms/field-error.tsx`** - Field error display component
- **`components/forms/form-section.tsx`** - Form section wrapper with title/description
- **`components/forms/form-actions.tsx`** - Form actions bar with submit/cancel buttons
- **`components/ui/switch.tsx`** - Toggle switch component

### Hooks

- **`hooks/use-unsaved-changes.ts`** - Detect and warn about unsaved form changes

## RoleForm Component

The main form component that handles both create and edit modes:

```typescript
interface RoleFormProps {
    mode: 'create' | 'edit';
    initial?: {
        id?: number;
        name?: string;
        guard_name?: string;
        is_active?: boolean;
        permissions_ids?: number[];
        updated_at?: string;
    };
    options: {
        guards: Array<{ value: string; label: string }>;
        permissions: Permission[];
    };
    can: Record<string, boolean>;
    onSaved?: () => void;
}
```

### Features

- **Validation** - Client-side and server-side validation with error display
- **Partial Reloads** - Permissions reload when guard changes
- **Unsaved Changes** - Warns users before navigating away with unsaved changes
- **Accessibility** - Proper ARIA attributes, labels, and focus management
- **Optimistic Locking** - Includes version field for update conflicts
- **Error Focus** - Automatically focuses first field with error

#### Optimistic Locking Details

The form includes a hidden version field (`_version`) derived from `initial.updated_at` on edit. On submit, `_version` is sent back to the server and compared against the current model `updated_at` using a timestamp-safe comparison. If they differ, the backend throws a domain exception to prevent overwriting concurrent changes.

Example (TypeScript + Inertia):

```tsx
const { data, setData, processing } = useForm({
    name: initial?.name ?? '',
    guard_name: initial?.guard_name ?? 'web',
    is_active: initial?.is_active ?? true,
    permissions_ids: initial?.permissions_ids ?? [],
    _version: initial?.updated_at ?? undefined, // keep original updated_at (ISO 8601)
});

function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    router.put(route('roles.update', initial!.id), data, {
        onSuccess: () => {
            clearUnsavedChanges?.();
            onSaved?.();
        },
    });
}
```

On the backend, `HandlesForm::update()` passes `_version` to `BaseService::update()`, which normalizes both values to Unix timestamps before comparing them.

### Form Fields

1. **Name** - Required text input for role name
2. **Guard** - Select dropdown for authentication guard
3. **Active Status** - Toggle switch for role activation
4. **Permissions** - Checkbox list filtered by selected guard

## RolePicker Component

A reusable role selector component with advanced features:

```typescript
interface RolePickerProps {
    value?: number | number[];
    onChange: (value: number | number[]) => void;
    options?: Role[];
    multiple?: boolean;
    placeholder?: string;
    className?: string;
    disabled?: boolean;
    allowCreate?: boolean;
    canCreate?: boolean;
    createOptions?: {
        guards: Array<{ value: string; label: string }>;
        permissions: Array<{ value: number; label: string; guard: string }>;
    };
}
```

### Features

- **Search** - Filter roles by name
- **Single/Multiple** - Support for single or multiple selection
- **Quick Create** - Optional modal to create new role inline
- **Visual Feedback** - Shows guard, permissions count, active status
- **Selection Display** - Shows selected roles as removable badges

## Usage Examples

### Create Page

```tsx
export default function CreateRole({ can, options }: CreateRoleProps) {
    return (
        <AppLayout>
            <Head title="Crear Rol" />
            <RoleForm mode="create" options={options} can={can} />
        </AppLayout>
    );
}
```

### Edit Page

```tsx
export default function EditRole({ can, role, options }: EditRoleProps) {
    return (
        <AppLayout>
            <Head title={`Editar Rol - ${role.name}`} />
            <RoleForm mode="edit" initial={role} options={options} can={can} />
        </AppLayout>
    );
}
```

### Using RolePicker

```tsx
// Single selection
<RolePicker
    value={selectedRoleId}
    onChange={(id) => setSelectedRoleId(id)}
    options={availableRoles}
    placeholder="Select a role"
/>

// Multiple selection with create
<RolePicker
    multiple
    value={selectedRoleIds}
    onChange={(ids) => setSelectedRoleIds(ids)}
    options={availableRoles}
    allowCreate
    canCreate={can['roles.create']}
    createOptions={roleFormOptions}
/>
```

## Backend Integration

The forms integrate with Laravel backend through Inertia:

- **Create**: POST to `route('roles.store')`
- **Edit**: PUT to `route('roles.update', id)`
- **Partial Reload**: Reloads options when guard changes
- **Flash Messages**: Success/error messages via Laravel sessions
- **Validation**: Server-side validation with 422 responses

## Best Practices

1. **Always include required field indicators** with asterisk (\*)
2. **Provide clear error messages** below each field
3. **Use proper semantic HTML** for accessibility
4. **Handle loading states** with disabled inputs during submission
5. **Preserve scroll position** when navigating between pages
6. **Focus management** after validation errors
7. **Warn about unsaved changes** before navigation
8. **Use optimistic locking** for concurrent edit protection

## UI/UX Enhancements

- Modern card layout with sensible max width for readability
- Header toolbar for permissions with pill counter and separators
- Tri-state group selection via group-level checkbox (checked/indeterminate/unchecked)
- Updated microcopy: “Define el nombre y el guard del rol”, “Buscar permisos”
- Required fields legend and consistent asterisk usage
- Consistent typography and spacing in `FormSection`
- Layout assignment ensures the sidebar: `RoleForm.layout = (page) => <AppLayout>{page}</AppLayout>`
- Clear unsaved changes on successful submit using `useUnsavedChanges`’ `clearUnsavedChanges()`
- Edit form preselects permissions via `permissions_ids` provided by the backend

## Accessibility Features

- Semantic HTML structure
- ARIA labels and descriptions
- Keyboard navigation support
- Focus indicators
- Screen reader announcements
- Error association with fields
- Required field indicators

## Testing Considerations

When testing role forms:

1. Verify validation messages appear correctly
2. Test unsaved changes warnings
3. Check partial reload functionality
4. Ensure proper error focus
5. Test permission filtering by guard
6. Verify optimistic locking
7. Test quick-create in RolePicker
8. Check accessibility with screen readers
