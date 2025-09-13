---
title: 'Módulo: Usuarios'
summary: 'Referencia del módulo de Usuarios: columnas (SSOT), filtros, permisos, endpoints, integración frontend y reglas de negocio.'
icon: material/account-group
tags:
    - referencia
    - módulo
    - usuarios
---

# Módulo: Usuarios

Este módulo implementa listado, edición y acciones de activación/eliminación de usuarios siguiendo el mismo patrón que `roles/*`.

## Columnas (SSOT)

- `id` (ordenable)
- `name` (ordenable)
- `email` (ordenable)
- `roles_count` (ordenable)
- `is_active` (ordenable)
- `created_at` (ordenable)

Las columnas están definidas en `resources/js/pages/users/columns.tsx` y son la fuente de verdad también para exportaciones (vía `UserService::defaultExportColumns`).

## Filtros

Implementados con `FilterSheet` y `FilterBadges` (mismo UX de Roles):

- `name` (texto, LIKE case-insensitive)
- `email` (texto, LIKE case-insensitive)
- `role_id` (select de roles disponibles)
- `is_active` (select all/active/inactive)
- `created_between` (rango de fechas)

Archivo: `resources/js/pages/users/filters.tsx`.

## Permisos

Definidos en `config/permissions/users.php` y agregados por `config/permissions.php`:

- `users.view`
- `users.create`
- `users.update`
- `users.delete`
- `users.restore`
- `users.forceDelete`
- `users.export`
- `users.setActive`

Policy: `App\Policies\UserPolicy` (extiende `BaseResourcePolicy` con prefijo `users`).

## Exportación

- Endpoint: `GET /users/export?format=csv|xlsx|json`
- Permiso: `users.export`
- Columns SSOT desde `UserService::defaultExportColumns()`

## Backend (resumen)

- Request: `App\Http\Requests\UserIndexRequest` (extiende `BaseIndexRequest`)
- Repository: `App\Repositories\UserRepository` (extiende `BaseRepository`)
    - `searchable()`: `name`, `email`
    - `allowedSorts()`: `id`, `name`, `email`, `is_active`, `created_at`
    - `withRelations()`: `withCount('roles')`
    - `filterMap()`: `name`, `email`, `role_id`, `is_active`, `created_between`
- Service: `App\Services\UserService` (extiende `BaseService`)
    - `toRow()`: `{ id, name, email, is_active, roles_count, created_at }`
    - `defaultExportColumns()` y `defaultExportFilename()`
- Controller: `App\Http\Controllers\UsersController` (extiende `BaseIndexController`)
    - `view()`: `users/index`
    - `indexRequestClass()`: `UserIndexRequest`
    - `allowedExportFormats()`: `csv`, `xlsx`, `json`
    - Extra: `availableRoles` para el filtro
- Rutas: `routes/users.php`

    - `GET /users` → index
    - `GET /users/export` → export
    - `POST /users/bulk` → operaciones masivas (`delete`, `restore`, `forceDelete`, `setActive`)
    - `GET /users/selected` → subset de IDs
    - `GET /users/{user}` → show
    - `GET /users/{user}/edit` → edit
    - `PUT /users/{user}` → update
    - `PATCH /users/{user}/active` → setActive (independiente de update)
    - `DELETE /users/{user}` → destroy

    En Laravel 12, las rutas están reforzadas con middleware de Spatie (`permission:*`) además de las Policies, p. ej. `permission:users.view`, `permission:users.export`, `permission:users.setActive`. Asegúrate de registrar los aliases en `bootstrap/app.php` (ver guía "Permisos (permission-first)").

## Frontend (resumen)

- Vista: `resources/js/pages/users/index.tsx`
    - TanStack v8 en modo manual: `manualPagination`, `manualSorting`, `rowCount`
    - Inertia partial reloads: `only: ['rows', 'meta']`, `preserveState`, `preserveScroll`
    - Bulk actions: delete (si `auth.can['users.delete']`)
    - Export: `users.export`
    - Stats cards: tarjetas dinámicas con `stats.total`, `stats.inactive` (backend las provee)
- Columnas: `resources/js/pages/users/columns.tsx`
- Filtros: `resources/js/pages/users/filters.tsx`
- Vista Show: `resources/js/pages/users/show.tsx`
    - Botón Eliminar con `ConfirmAlert` → `DELETE /users/{id}` (toasts y redirección)
    - Consistencia con Roles Show

## UX/Comportamiento clave

- Misma UI/estilos que Roles
- Controles visibles aunque no haya resultados
- Densidad de tabla persistida en `localStorage` bajo `users_table_density`
- Navegación lateral: ítem "Usuarios" visible sólo si `auth.can['users.view']`

## Reglas de negocio (configurables)

- Archivo: `config/permissions/users.php`
    - `users.activation.block_self_deactivate` (bool, default true): impide desactivar tu propio usuario
    - `users.activation.block_deactivate_if_last_admin` (bool): impide desactivar al último administrador
    - `users.activation.admin_role_name` (string): nombre del rol considerado admin
    - `users.deletion.require_inactive` (bool): requiere inactivo antes de permitir eliminar
    - `users.deletion.block_if_last_admin` (bool): impide eliminar al último administrador

Estas reglas se aplican en los `FormRequest`:

- `SetUserActiveRequest` (PATCH `/users/{id}/active`)
- `DeleteUsersRequest` (DELETE `/users/{id}`)
