---
title: 'Permisos (permission-first)'
summary: 'Cómo definir permisos por módulo, sincronizarlos con la base de datos, proteger rutas y exponerlos al frontend (Inertia) vía auth.can.'
icon: material/key-variant
tags:
    - how-to
    - permisos
    - backend
---

# Permisos (permission-first)

Objetivo: agregar un permiso nuevo, sembrarlo, proteger rutas y exponerlo al frontend (Inertia) vía `auth.can`.

## 1) Declarar permisos por módulo

- Archivo por módulo en `config/permissions/<modulo>.php`.

```php
<?php
return [
    'permissions' => [
        '<modulo>.index', '<modulo>.view', '<modulo>.create', '<modulo>.update', '<modulo>.delete',
    ],
    'descriptions' => [
        '<modulo>.index' => 'Listar <modulo>',
        '<modulo>.view' => 'Ver <modulo>',
        // ...
    ],
];
```

## 2) Agregador de permisos (ya incluido)

- `config/permissions.php` fusiona todos los módulos:

```php
<?php
$permissions = $descriptions = [];
foreach (glob(__DIR__.'/permissions/*.php') as $file) {
    $data = require $file;
    $permissions = array_merge($permissions, $data['permissions'] ?? []);
    $descriptions = array_merge($descriptions, $data['descriptions'] ?? []);
}
return [
    'guard' => 'web',
    'permissions' => array_values(array_unique($permissions)),
    'descriptions' => $descriptions,
];
```

## 3) Sembrar y sincronizar

```bash
php artisan config:clear
php artisan db:seed --class=Database\\Seeders\\PermissionsSeeder
```

## 4) Proteger rutas

Usa middleware `can:<permiso>` o, si prefieres Spatie directamente, `permission:<permiso>`.

```php
Route::get('settings/profile', [ProfileController::class, 'edit'])
    ->middleware('can:settings.profile.view')
    ->name('profile.edit');
```

### Laravel 12 + Spatie: registrar aliases de middleware de ruta

En Laravel 12 ya no existe `Http/Kernel.php`. Si vas a usar `permission:`/`role:` en rutas, registra los aliases en `bootstrap/app.php`:

```php
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

// ...
->withMiddleware(function (Middleware $middleware) {
    // ...
    $middleware->alias([
        'role' => RoleMiddleware::class,
        'permission' => PermissionMiddleware::class,
        'role_or_permission' => RoleOrPermissionMiddleware::class,
    ]);
});
```

Luego puedes proteger rutas así:

```php
Route::get('/roles', [RolesController::class, 'index'])
    ->middleware('permission:roles.view')
    ->name('roles.index');
```

Nota: si no registras los aliases verás el error: "Target class [permission] does not exist.".

## 5) Frontend (Inertia)

- `app/Http/Middleware/HandleInertiaRequests.php` comparte `auth.can` (mapa permiso => booleano).
- En React:

```tsx
if (!page.props.auth.can['users.create']) return null;
```

## 6) Tests recomendados

- `tests/Feature/Permissions/PermissionsBehaviorTest.php`
- `tests/Feature/Infrastructure/InertiaSharedPropsTest.php`

Con estos tests, si agregas un permiso en `config/permissions/`, se valida que:

- El frontend reciba la llave en `auth.can`.
- Las rutas protegidas respondan según corresponda.
