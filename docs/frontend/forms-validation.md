---
title: 'Validación de formularios (frontend)'
summary: 'Sistema de validación con Zod + Inertia useForm: hooks, componentes accesibles y patrones para errores, focus y recargas parciales.'
icon: material/form-textbox
tags:
    - how-to
    - frontend
    - validación
---

# Sistema de Validación de Formularios

## Introducción

Este documento describe el sistema de validación de formularios implementado en el frontend, que combina validación del lado del cliente con Zod y validación del servidor mediante Laravel FormRequests.

## Arquitectura

### Principios de Diseño

1. **El servidor es la fuente de verdad**: La validación del cliente es para mejorar la UX, pero la validación del servidor es autoritativa
2. **Estado único**: `useForm` de Inertia.js es la única fuente de estado del formulario
3. **Accesibilidad**: Todos los componentes siguen las mejores prácticas ARIA
4. **Retroalimentación inmediata**: Validación en `blur` con debounce y en `submit`
5. **Sin duplicación**: Reutilización de componentes y helpers existentes

## Componentes del Sistema

### 1. Librería de Validación Base

#### `/resources/js/lib/validation/validation.config.ts`

Contiene constantes de configuración y mensajes de validación en español.

```typescript
import { MAX_LENGTHS, VALIDATION_MESSAGES } from '@/lib/validation/validation.config';

// Usar las constantes definidas
const maxNameLength = MAX_LENGTHS.DEFAULT_STRING;
const requiredMessage = VALIDATION_MESSAGES.REQUIRED;
```

#### `/resources/js/lib/validation/zod-kit.ts`

Helpers de Zod preconfigurados con mensajes en español para tipos comunes.

```typescript
import { requiredString, requiredBoolean, numericIdArray } from '@/lib/validation/zod-kit';

// Crear un esquema con helpers
const schema = z.object({
    name: requiredString().max(100),
    is_active: requiredBoolean(),
    permissions_ids: numericIdArray(),
});
```

### 2. Hooks de Validación

#### `useClientValidation`

Hook principal para validación del lado del cliente con Zod.

```typescript
import { useClientValidation } from '@/hooks/useClientValidation';

// En tu componente
const schema = z.object({
    name: z.string().min(1, 'El nombre es obligatorio'),
    email: z.string().email('Email inválido')
});

const { validateOnBlur, validateOnSubmit, errorsClient, mergeErrors } = useClientValidation(
    schema,
    () => form.data
);

// Merge errores del servidor y cliente
const errors = mergeErrors(form.errors, errorsClient);

// Validar campo en blur
<input onBlur={() => validateOnBlur('name')} />

// Validar formulario en submit
const handleSubmit = (e) => {
    e.preventDefault();
    if (!validateOnSubmit()) {
        return; // Hay errores de validación
    }
    form.post(route('resource.store'));
};
```

#### `useFirstErrorFocus`

Hook para enfocar automáticamente el primer campo con error.

```typescript
import { useFirstErrorFocus } from '@/hooks/useFirstErrorFocus';

const { focusFirstError } = useFirstErrorFocus();

// Después de validación fallida
useEffect(() => {
    if (Object.keys(errors).length > 0) {
        focusFirstError(errors);
    }
}, [errors]);
```

### 3. Componentes Accesibles

#### `Field`

Wrapper accesible para campos de formulario con label, hint y error.

```tsx
import { Field } from '@/components/form/Field';

<Field id="name" label="Nombre" required error={errors.name} hint="Ingresa el nombre completo">
    <Input name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} onBlur={() => validateOnBlur('name')} />
</Field>;
```

Props del componente Field:

- `id`: ID único del campo (requerido)
- `label`: Etiqueta del campo (requerido)
- `required`: Si el campo es obligatorio
- `error`: Mensaje de error a mostrar
- `hint`: Texto de ayuda
- `className`: Clases CSS adicionales
- `children`: Input o control del formulario

#### `ErrorSummary`

Lista todos los errores del formulario con enlaces para enfocar campos.

```tsx
import { ErrorSummary } from '@/components/form/ErrorSummary';

// Al inicio del formulario
{
    Object.keys(errors).length > 0 && <ErrorSummary errors={errors} className="mb-4" />;
}
```

## Integración Completa

### Ejemplo: Formulario de Roles

```tsx
// resources/js/pages/roles/form.tsx
import { useForm } from '@inertiajs/react';
import { makeRoleSchema } from '@/lib/validation/schema-role';
import { useClientValidation } from '@/hooks/useClientValidation';
import { useFirstErrorFocus } from '@/hooks/useFirstErrorFocus';
import { Field } from '@/components/form/Field';
import { ErrorSummary } from '@/components/form/ErrorSummary';

export default function RoleForm({ initial, options }) {
    const form = useForm({
        name: initial?.name ?? '',
        guard_name: initial?.guard_name ?? 'web',
        is_active: initial?.is_active ?? true,
        permissions_ids: initial?.permissions_ids ?? [],
    });

    // Configurar validación
    const guards = options.guards?.map((g) => g.value) || ['web'];
    const schema = makeRoleSchema(guards);

    const { validateOnBlur, validateOnSubmit, errorsClient, mergeErrors } = useClientValidation(schema, () => form.data);

    const { focusFirstError } = useFirstErrorFocus();

    // Combinar errores del servidor y cliente
    const errors = mergeErrors(form.errors, errorsClient);

    // Efecto para enfocar primer error
    useEffect(() => {
        if (Object.keys(errors).length > 0) {
            focusFirstError(errors);
        }
    }, [errors]);

    const handleSubmit = (e) => {
        e.preventDefault();

        // Validación del cliente
        if (!validateOnSubmit()) {
            focusFirstError(errorsClient);
            return;
        }

        // Enviar al servidor
        form.post(route('roles.store'), {
            onSuccess: () => {
                toast.success('Rol creado exitosamente');
            },
            onError: () => {
                // Los errores del servidor se mostrarán automáticamente
                focusFirstError(form.errors);
            },
        });
    };

    return (
        <form onSubmit={handleSubmit}>
            {/* Resumen de errores */}
            {Object.keys(errors).length > 0 && <ErrorSummary errors={errors} className="mb-4" />}

            {/* Campos del formulario */}
            <Field id="name" label="Nombre del rol" required error={errors.name}>
                <Input
                    name="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    onBlur={() => validateOnBlur('name')}
                    autoFocus
                />
            </Field>

            <Field id="guard_name" label="Guard" required error={errors.guard_name} hint="Define el contexto de autenticación">
                <Select
                    value={form.data.guard_name}
                    onValueChange={(value) => {
                        form.setData('guard_name', value);
                        validateOnBlur('guard_name');
                    }}
                >
                    {/* opciones del select */}
                </Select>
            </Field>

            {/* Botón de envío */}
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Guardando...' : 'Guardar'}
            </Button>
        </form>
    );
}
```

## Esquemas de Validación Personalizados

### Creando un Esquema Reutilizable

```typescript
// resources/js/lib/validation/schema-user.ts
import { z } from 'zod';
import { requiredString, email } from '@/lib/validation/zod-kit';

export const makeUserSchema = (requirePassword = true) => {
    const base = z.object({
        name: requiredString().max(100),
        email: email(),
        role_id: z.number().positive('Selecciona un rol'),
        is_active: z.boolean(),
    });

    if (requirePassword) {
        return base
            .extend({
                password: requiredString().min(8, 'Mínimo 8 caracteres'),
                password_confirmation: z.string(),
            })
            .refine((data) => data.password === data.password_confirmation, {
                message: 'Las contraseñas no coinciden',
                path: ['password_confirmation'],
            });
    }

    return base;
};

// Validar un campo individual
export const validateUserField = (field: string, value: any, requirePassword = true): string | undefined => {
    const schema = makeUserSchema(requirePassword);

    try {
        const partial = { [field]: value };
        schema.pick({ [field]: true }).parse(partial);
        return undefined;
    } catch (error) {
        if (error instanceof z.ZodError) {
            return error.errors[0]?.message;
        }
        return 'Error de validación';
    }
};
```

## Patrones Avanzados

### Validación Asíncrona

Para validaciones que requieren verificación del servidor (ej: email único):

```typescript
const handleEmailBlur = async () => {
    // Validación local primero
    validateOnBlur('email');

    // Si pasa la validación local, verificar en el servidor
    if (!errorsClient.email && form.data.email) {
        try {
            const response = await fetch(`/api/check-email?email=${form.data.email}`);
            const { available } = await response.json();

            if (!available) {
                form.setError('email', 'Este email ya está registrado');
            }
        } catch (error) {
            console.error('Error verificando email:', error);
        }
    }
};
```

### Validación Condicional

Para campos que dependen de otros:

```typescript
const schema = z
    .object({
        type: z.enum(['personal', 'company']),
        name: requiredString(),
        company_name: z.string().optional(),
        tax_id: z.string().optional(),
    })
    .refine(
        (data) => {
            if (data.type === 'company') {
                return data.company_name && data.tax_id;
            }
            return true;
        },
        {
            message: 'Datos de empresa son obligatorios',
            path: ['company_name'],
        },
    );
```

### Integración con Partial Reloads

Para recargar opciones sin perder el estado del formulario:

```typescript
const handleGuardChange = (newGuard: string) => {
    form.setData('guard_name', newGuard);

    // Limpiar permisos que no aplican
    const validPerms = permissions.filter((p) => p.guard === newGuard).map((p) => p.id);

    const filtered = form.data.permissions_ids.filter((id) => validPerms.includes(id));

    if (filtered.length !== form.data.permissions_ids.length) {
        form.setData('permissions_ids', filtered);
        toast.info('Permisos actualizados para el nuevo guard');
    }

    // Partial reload
    router.reload({
        only: ['permissions'],
        data: { guard_name: newGuard },
        preserveScroll: true,
    });
};
```

## Testing

### Testing de Validación

```typescript
// tests/validation/role-schema.test.ts
import { describe, it, expect } from 'vitest';
import { makeRoleSchema } from '@/lib/validation/schema-role';

describe('Role Schema Validation', () => {
    it('validates required fields', () => {
        const schema = makeRoleSchema(['web']);
        const result = schema.safeParse({});

        expect(result.success).toBe(false);
        expect(result.error?.issues).toHaveLength(3); // name, guard_name, is_active
    });

    it('accepts valid data', () => {
        const schema = makeRoleSchema(['web', 'api']);
        const result = schema.safeParse({
            name: 'Admin',
            guard_name: 'web',
            is_active: true,
            permissions_ids: [1, 2, 3],
        });

        expect(result.success).toBe(true);
    });
});
```

### Testing de Componentes

```typescript
// tests/components/Field.test.tsx
import { render, screen } from '@testing-library/react';
import { Field } from '@/components/form/Field';

describe('Field Component', () => {
    it('renders label and error', () => {
        render(
            <Field id="test" label="Test Field" error="Error message" required>
                <input id="test" />
            </Field>
        );

        expect(screen.getByLabelText(/Test Field/)).toBeInTheDocument();
        expect(screen.getByText('Error message')).toBeInTheDocument();
        expect(screen.getByText('*')).toBeInTheDocument(); // required indicator
    });

    it('applies ARIA attributes', () => {
        const { container } = render(
            <Field id="test" label="Test" error="Error">
                <input id="test" />
            </Field>
        );

        const input = container.querySelector('input');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAttribute('aria-describedby', 'test-error');
    });
});
```

## Mejores Prácticas

1. **Siempre validar en el servidor**: La validación del cliente es solo para UX
2. **Usar mensajes descriptivos**: Los mensajes de error deben guiar al usuario
3. **Debounce en validación de campo**: Evita validaciones excesivas en `onChange`
4. **Enfocar errores**: Mejora la accesibilidad y UX
5. **Mostrar resumen de errores**: En formularios largos, ayuda a la navegación
6. **Preservar estado en recargas parciales**: No perder datos del usuario
7. **Limpiar errores al corregir**: Retroalimentación inmediata positiva

## Migración desde Formularios Existentes

Para migrar un formulario existente al nuevo sistema:

1. **Identificar validaciones actuales** del FormRequest de Laravel
2. **Crear esquema Zod equivalente** usando los helpers de `zod-kit`
3. **Integrar hooks de validación** (`useClientValidation`, `useFirstErrorFocus`)
4. **Reemplazar campos** con el componente `Field`
5. **Agregar `ErrorSummary`** al inicio del formulario
6. **Conectar eventos** (`onBlur`, `onSubmit`)
7. **Probar integración** con servidor (errores 422)

## Troubleshooting

### Problema: Los errores del servidor no se muestran

**Solución**: Asegúrate de usar `mergeErrors(form.errors, errorsClient)` y mostrar `errors` en lugar de `form.errors`.

### Problema: El focus no funciona en campos custom

**Solución**: Asegúrate de que el campo tenga un `id` único y que el componente `Field` esté pasando las props ARIA correctamente.

### Problema: Validación se ejecuta demasiadas veces

**Solución**: Usa `validateOnBlur` en lugar de `onChange`, o implementa tu propio debounce.

### Problema: Mensajes en inglés en lugar de español

**Solución**: Importa y usa los helpers de `zod-kit` que ya tienen mensajes en español configurados.

## Recursos Adicionales

- [Documentación de Zod](https://zod.dev)
- [Inertia.js Forms](https://inertiajs.com/forms)
- [ARIA Authoring Practices](https://www.w3.org/WAI/ARIA/apg/)
- [Laravel Validation](https://laravel.com/docs/validation)
