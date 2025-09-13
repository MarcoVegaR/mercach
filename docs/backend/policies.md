---
title: 'Policies'
summary: 'Guía de policies de recursos con BaseResourcePolicy: abilities estándar, multi-guard, registro y mejores prácticas.'
icon: material/shield-lock
tags:
    - explicación
    - backend
    - policies
---

# Policies

Las **Policies** son clases que organizan la lógica de autorización para modelos específicos. Proporcionan una forma limpia de determinar si un usuario puede realizar una acción determinada sobre un recurso.

## ¿Cuándo usar Policies vs Gates?

- **Policies**: Para autorización basada en recursos/modelos (ej. `User`, `Role`, `Post`)
- **Gates**: Para acciones simples no relacionadas con modelos (ej. `view-admin-panel`)

## Base Resource Policy

El sistema incluye una `BaseResourcePolicy` abstracta que estandariza el mapeo de abilities de Laravel a permisos de Spatie, con soporte para prefijos por recurso y multi-guard.

### Abilities Estándar

La `BaseResourcePolicy` proporciona estas abilities estándar:

| Ability       | Propósito                   | Permiso Construido     |
| ------------- | --------------------------- | ---------------------- |
| `viewAny`     | Listar recursos             | `{prefix}.view`        |
| `view`        | Ver recurso específico      | `{prefix}.view`        |
| `create`      | Crear nuevo recurso         | `{prefix}.create`      |
| `update`      | Actualizar recurso          | `{prefix}.update`      |
| `delete`      | Eliminar recurso            | `{prefix}.delete`      |
| `restore`     | Restaurar recurso eliminado | `{prefix}.restore`     |
| `forceDelete` | Eliminar permanentemente    | `{prefix}.forceDelete` |
| `export`      | Exportar recursos           | `{prefix}.export`      |

### Implementación Básica

```php
<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'roles'; // roles.view, roles.create, etc.
    }
}
```

## Consideraciones Multi-Guard

Spatie Permission segmenta permisos por guard. La `BaseResourcePolicy` soporta esto mediante el método `guardName()`:

```php
class ApiRolePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'roles';
    }

    protected function guardName(): ?string
    {
        return 'api'; // Usa permisos del guard 'api'
    }
}
```

**Importante**: Los permisos deben crearse con el `guard_name` correcto:

```php
// Permiso para guard 'web'
Permission::create(['name' => 'roles.view', 'guard_name' => 'web']);

// Permiso para guard 'api'
Permission::create(['name' => 'roles.view', 'guard_name' => 'api']);
```

## Registro en AuthServiceProvider

### Método 1: Array $policies

```php
<?php

namespace App\Providers;

use App\Policies\RolePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Role::class => RolePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
```

### Método 2: Gate::policy()

```php
public function boot(): void
{
    Gate::policy(Role::class, RolePolicy::class);
    Gate::policy(Permission::class, PermissionPolicy::class);
}
```

## Gate::before para Super-Admin

Para permitir que un rol `super-admin` bypasee todas las políticas:

```php
public function boot(): void
{
    $this->registerPolicies();

    Gate::before(function (User $user, string $ability) {
        return $user->hasRole('super-admin') ? true : null;
    });
}
```

**⚠️ Advertencias:**

- `Gate::before` se ejecuta ANTES que cualquier policy
- Retornar `true` = permitir, `false` = denegar, `null` = continuar con policy
- Úsalo con cuidado - el super-admin tendrá acceso total

## Personalización de Policies

Puedes override métodos de `BaseResourcePolicy` para lógica contextual:

```php
class RolePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'roles';
    }

    public function delete(User $user, Role $role): bool
    {
        // Verificar permiso base
        if (!parent::delete($user, $role)) {
            return false;
        }

        // Regla de negocio: no eliminar roles con usuarios asignados
        return $role->users()->count() === 0;
    }
}
```

## Integración en Controladores

### Index (Listado)

```php
public function index()
{
    $this->authorize('viewAny', Role::class);

    // Lógica del listado...
}
```

### Show (Ver)

```php
public function show(Role $role)
{
    $this->authorize('view', $role);

    return response()->json($role);
}
```

### Store (Crear)

```php
public function store(StoreRoleRequest $request)
{
    $this->authorize('create', Role::class);

    // Crear rol...
}
```

### Update (Actualizar)

```php
public function update(UpdateRoleRequest $request, Role $role)
{
    $this->authorize('update', $role);

    // Actualizar rol...
}
```

### Destroy (Eliminar)

```php
public function destroy(Role $role)
{
    $this->authorize('delete', $role);

    $role->delete();
}
```

### Export (Exportar)

```php
public function export()
{
    $this->authorize('export', Role::class);

    // Lógica de exportación...
}
```

## Verificación Manual

También puedes verificar permisos manualmente:

```php
// En controladores
if (Gate::allows('viewAny', Role::class)) {
    // Usuario puede ver roles
}

// En vistas Blade
@can('create', App\Models\Role::class)
    <a href="{{ route('roles.create') }}">Crear Rol</a>
@endcan

// En código general
if ($user->can('update', $role)) {
    // Usuario puede actualizar este rol
}
```

## Integración Frontend (Inertia.js)

Pasa información de permisos al frontend:

```php
// En HandleInertiaRequests middleware
public function share(Request $request): array
{
    return [
        'auth' => [
            'user' => $request->user(),
            'permissions' => $request->user()?->getAllPermissions()->pluck('name'),
        ],
    ];
}
```

```jsx
// En React/Vue
import { usePage } from '@inertiajs/react';

function RolesList() {
    const { auth } = usePage().props;
    const canCreate = auth.permissions.includes('roles.create');

    return <div>{canCreate && <button onClick={createRole}>Crear Rol</button>}</div>;
}
```

## Testing

### Setup Base en Tests

```php
beforeEach(function () {
    // Crear permisos necesarios
    Permission::create(['name' => 'roles.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'roles.create', 'guard_name' => 'web']);
    // ...
});
```

### Test de Autorización

```php
it('authorizes user with correct permissions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('roles.view');

    $this->actingAs($user);

    expect(Gate::allows('viewAny', Role::class))->toBeTrue();
});

it('denies user without permissions', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    expect(Gate::denies('viewAny', Role::class))->toBeTrue();
});
```

### Test de HTTP

```php
it('returns 403 for unauthorized access', function () {
    $user = User::factory()->create(); // Sin permisos

    $this->actingAs($user)
         ->get('/roles')
         ->assertForbidden();
});
```

## Mejores Prácticas

### ✅ Recomendado

- Usar `BaseResourcePolicy` para consistencia
- Definir permisos con prefijos claros (`roles.view`, `users.create`)
- Registrar policies en `AuthServiceProvider`
- Verificar autorización en todos los endpoints
- Testear todos los casos de autorización

### ❌ Evitar

- Lógica de autorización directa en controladores
- Mezclar Gates y Policies para el mismo recurso
- Usar `Gate::before` sin considerar las implicaciones
- Permisos sin prefijo o con nombres inconsistentes
- Autorización solo en frontend

## Patrones Comunes

### Policy con Scope

```php
public function viewAny(User $user): bool
{
    // Solo puede ver roles de su organización
    return $this->can($user, 'view') && $user->organization_id !== null;
}
```

### Policy Condicional

```php
public function update(User $user, Role $role): bool
{
    if (!parent::update($user, $role)) {
        return false;
    }

    // Solo puede actualizar si es de su organización
    return $user->organization_id === $role->organization_id;
}
```

### Policy con Relaciones

```php
public function assign(User $user, Role $role, User $targetUser): bool
{
    return $this->can($user, 'assign') &&
           $user->can('manage', $targetUser);
}
```
