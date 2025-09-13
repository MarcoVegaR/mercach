---
title: 'Gestión de Roles'
summary: 'Referencia funcional de la gestión de roles: filtros, conteos por pivot, controlador, columnas de tabla, filtros y parámetros de URL.'
icon: material/shield-account
tags:
    - referencia
    - roles
    - backend
    - frontend
---

# Gestión de Roles

## Descripción General

El sistema de gestión de roles proporciona una interfaz completa para administrar roles y permisos en la aplicación. Utiliza Laravel con Spatie Permission en el backend y React con TanStack Table en el frontend.

## Características Principales

### Búsqueda y Filtrado

- **Búsqueda por ID y Nombre**: El sistema permite buscar roles tanto por ID como por nombre
- **Filtros Avanzados**:
    - Por guard de autenticación (por defecto: web)
    - Por rango de fechas de creación
    - Por permisos específicos (multi-select)
    - Por cantidad de usuarios asignados
    - Por estado activo/inactivo

### Visualización de Datos

- **Tabla Optimizada**: Diseño UX mejorado con reordenamiento inteligente de columnas
    - Flujo de lectura optimizado: ID → Nombre → Usuarios → Permisos → Guard → Creado → Estado → Acciones
    - Alturas de fila consistentes y alineación mejorada
- **Columna de Permisos**: Muestra nombres como badges con overflow inteligente
    - Muestra los primeros 2 permisos como badges
    - Si hay más, muestra un badge "+N"
    - Popover opcional para ver la lista completa
- **Columna de Usuarios**: Conteo con detalles en popover
    - Badge con número de usuarios asignados
    - Popover con lista de nombres de usuarios
- **Columna de Estado**: Indicador visual claro
    - Punto de color + badge de texto
    - Posicionado antes de acciones para mejor contexto
- **Paginación Mejorada**:
    - Selector de filas por página funcional
    - Muestra "Mostrando X a Y de Z registros"
    - Controles siempre visibles incluso sin resultados

### Interfaz de Usuario

- **Estado Vacío Inteligente**: Los controles de búsqueda y filtros permanecen visibles cuando no hay resultados
- **Exportación de Datos**: Soporte para CSV, XLSX y JSON
- **Operaciones en Lote**:
    - Eliminación múltiple de roles seleccionados
    - Activación/desactivación masiva de roles
    - Validaciones inteligentes para evitar operaciones redundantes

## Implementación Backend

### RoleRepository

```php
class RoleRepository extends BaseRepository
{
    protected function searchable(): array
    {
        return ['id', 'name', 'display_name'];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'guard_name', 'created_at', 'permissions_count', 'users_count', 'is_active'];
    }
}
```

#### Conteo de usuarios por pivot (robusto a guards)

Para evitar errores por `guard_name` inconsistentes al usar `Role::users`, se calcula `users_count` con un subselect sobre la tabla pivot `model_has_roles` filtrando por `model_type = App\Models\User::class`.

```php
use Illuminate\Support\Facades\DB;
use App\Models\User;

protected function withRelations(Builder $builder): Builder
{
    return $builder
        ->with(['permissions'])
        ->withCount(['permissions'])
        ->selectSub(
            DB::table('model_has_roles as mhr')
                ->selectRaw('count(*)')
                ->whereColumn('mhr.role_id', 'roles.id')
                ->where('mhr.model_type', User::class),
            'users_count'
        );
}
```

#### Filtros por `users_count` vía pivot

```php
protected function filterMap(): array
{
    return [
        'users_count_min' => function (Builder $q, int $min) {
            $q->where(
                DB::raw('(
                    SELECT COUNT(*) FROM model_has_roles mhr
                    WHERE mhr.role_id = roles.id
                      AND mhr.model_type = ' . DB::getPdo()->quote(App\Models\User::class) . '
                )'),
                '>=',
                $min
            );
        },
        'users_count_max' => function (Builder $q, int $max) {
            $q->where(
                DB::raw('(
                    SELECT COUNT(*) FROM model_has_roles mhr
                    WHERE mhr.role_id = roles.id
                      AND mhr.model_type = ' . DB::getPdo()->quote(App\Models\User::class) . '
                )'),
                '<=',
                $max
            );
        },
    ];
}
```

Nota: El endpoint `selected` también aplica `withRelations()` para que las filas incluyan `users_count` y `permissions_count` de forma consistente.

### RolesController

```php
use Illuminate\Http\Request;
use App\Contracts\Services\RoleServiceInterface;

class RolesController extends BaseIndexController
{
    public function __construct(private RoleServiceInterface $roleService)
    {
        parent::__construct($this->roleService);
    }

    public function index(Request $request): \Inertia\Response
    {
        $response = parent::index($request);

        // Extras provistos por el Service (SOLID)
        $extras = $this->roleService->getIndexExtras();
        $response->with('stats', $extras['stats'] ?? []);
        $response->with('availablePermissions', $extras['availablePermissions'] ?? []);

        return $response;
    }

    protected function with(): array
    {
        return ['permissions']; // Eager loading
    }

    protected function withCount(): array
    {
        return []; // Conteos por pivot/subselect en el repositorio
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    public function bulk(Request $request): \Illuminate\Http\RedirectResponse
    {
        $action = $request->input('action');

        if ($action === 'delete') {
            $this->authorize('bulk', [Role::class, 'delete']);
            // Manejo de eliminación masiva con DeleteBulkRolesRequest
        }

        if ($action === 'setActive') {
            $this->authorize('bulk', [Role::class, 'setActive']);
            // Manejo de activación/desactivación masiva con ActivateBulkRolesRequest
            $validatedRequest = ActivateBulkRolesRequest::createFrom($request);
            // Validación y procesamiento de roles actualizables
        }

        return parent::bulk($request);
    }
}
```

### BaseRepository - Búsqueda Numérica

El `BaseRepository` fue modificado para soportar búsquedas LIKE en columnas numéricas:

```php
private function applyStandardFilter(Builder $builder, string $key, mixed $value): void
{
    if (str_ends_with($key, '_like')) {
        $column = str_replace('_like', '', $key);
        if ($column === 'id' || str_ends_with($column, '_id')) {
            // Cast columnas numéricas a TEXT para búsqueda
            $builder->whereRaw("CAST({$column} AS TEXT) LIKE ?", ['%' . $value . '%']);
        } else {
            $builder->whereRaw("LOWER({$column}::text) LIKE ?", ['%' . strtolower($value) . '%']);
        }
    }
}
```

## Implementación Frontend

### Columnas de la Tabla

```tsx
// columns.tsx
{
    accessorKey: 'permissions',
    header: 'Permisos',
    cell: ({ row }) => {
        const permissions = row.original.permissions || [];
        if (permissions.length === 0) {
            return <span className="text-gray-400">Sin permisos</span>;
        }

        const visiblePermissions = permissions.slice(0, 2);
        const remainingCount = permissions.length - 2;

        return (
            <div className="flex flex-wrap gap-1">
                {visiblePermissions.map((permission: any) => (
                    <Badge key={permission.id} variant="secondary">
                        {permission.name}
                    </Badge>
                ))}
                {remainingCount > 0 && (
                    <Badge variant="outline">+{remainingCount}</Badge>
                )}
            </div>
        );
    }
}
```

### Filtros de Roles

```tsx
interface RoleFiltersProps {
    value: {
        guard_name?: string;
        created_between?: { from?: string; to?: string };
        permissions?: string[]; // Array de nombres de permisos
        users_count_min?: number;
        users_count_max?: number;
        is_active?: boolean;
    };
    onChange: (filters: any) => void;
    availablePermissions?: Array<{ id: number; name: string }>;
}
```

### DataTable - Estado Vacío

```tsx
// DataTable.tsx - Siempre muestra controles
{table.getRowModel().rows?.length ? (
    // Renderizar filas
) : (
    <TableRow>
        <TableCell colSpan={columns.length} className="h-24 text-center">
            No hay resultados.
        </TableCell>
    </TableRow>
)}

// Paginación con total de registros
<div className="flex items-center text-sm text-muted-foreground">
    {rowCount > 0 ? (
        <>
            Mostrando {Math.min((pageIndex * pageSize) + 1, rowCount)} a{' '}
            {Math.min((pageIndex + 1) * pageSize, rowCount)} de {rowCount} registros
        </>
    ) : (
        'Sin registros'
    )}
</div>
```

## Parámetros de URL

El sistema utiliza los siguientes parámetros de consulta:

- `q`: Búsqueda global (ID o nombre)
- `page`: Número de página actual
- `per_page`: Cantidad de registros por página
- `sort`: Campo de ordenamiento
- `dir`: Dirección del ordenamiento (asc/desc)
- `filters[guard_name]`: Filtro por guard
- `filters[permissions]`: Array de permisos seleccionados
- `filters[created_between]`: Rango de fechas
- `filters[users_count_min]`: Mínimo de usuarios
- `filters[users_count_max]`: Máximo de usuarios
- `filters[is_active]`: Estado activo/inactivo

## Consideraciones Técnicas

### TypeScript

Algunos metadatos de columnas como `exportLabel` pueden generar advertencias de lint ya que no están definidos en el tipo `ColumnMeta` de TanStack Table.

### Rendimiento

- La búsqueda por ID utiliza casting a TEXT que puede impactar el rendimiento en tablas grandes
- Se recomienda indexar las columnas utilizadas frecuentemente en búsquedas
- El eager loading de permisos reduce las consultas N+1

### Concurrencia y Bloqueo Optimista

Para evitar sobrescrituras en ediciones concurrentes, se implementa bloqueo optimista usando el campo `_version` con el valor de `updated_at` del modelo.

- En el frontend (formulario de edición), se envía `_version` como el `updated_at` original (ISO 8601) recibido al cargar la página.
- En el backend, `HandlesForm::update()` reenvía `_version` a `BaseService::update()`, que convierte ambos valores a timestamps Unix antes de compararlos.
- Si el timestamp actual del modelo difiere del esperado, se lanza una `DomainActionException` con un mensaje claro para recargar la página.

Ejemplo (backend):

```php
// App\Http\Controllers\Concerns\HandlesForm@update
$expectedUpdatedAt = $request->input('_version');
$model = $this->service->update($model, $validated, $expectedUpdatedAt);

// App\Services\BaseService::update
$currentTimestamp  = $model->updated_at?->timestamp;
$expectedTimestamp = \Carbon\Carbon::parse($expectedUpdatedAt)->timestamp;

if ($currentTimestamp !== $expectedTimestamp) {
    throw new \App\Exceptions\DomainActionException(
        'El registro ha sido modificado por otro usuario. Por favor, recarga la página e intenta nuevamente.'
    );
}
```

### Seguridad

- Todas las operaciones requieren los permisos correspondientes
- Los filtros son sanitizados en el backend
- La eliminación en lote requiere confirmación del usuario

#### Validaciones de eliminación de roles

Las eliminaciones (individuales y en lote) se validan en `FormRequest` dedicados para evitar estados inconsistentes:

- `DeleteRolesRequest` (individual) y `DeleteBulkRolesRequest` (lote).
- Reglas aplicadas:
    - No permitir eliminar roles marcados como protegidos (`permissions.roles.protected`).
    - Bloquear si el rol tiene usuarios asignados (consulta directa a pivot `model_has_roles`).
    - Opcional: requerir que el rol esté inactivo antes de eliminar (`permissions.roles.deletion.require_inactive`).
    - Opcional: bloquear si el rol tiene permisos, salvo que `force=true` (`permissions.roles.deletion.block_if_has_permissions`).
    - Salvaguarda crítica: impedir eliminar el último rol que otorgue todos los permisos críticos definidos (`permissions.roles.deletion.critical_permissions`).

#### Validaciones de activación/desactivación masiva

Las operaciones de activación/desactivación masiva utilizan `ActivateBulkRolesRequest`:

- **Validaciones aplicadas**:
    - No permitir cambiar estado de roles protegidos
    - Evitar operaciones redundantes (roles ya en el estado solicitado)
    - No desactivar roles con usuarios activos asignados
    - Autorización requerida con permiso `roles.setActive`
- **Retroalimentación inteligente**:
    - Mensajes contextuales sobre roles actualizados vs omitidos
    - Conteo preciso de operaciones exitosas
    - Alertas cuando no se realizan cambios

Configuración por defecto en `config/permissions/roles.php`:

```php
return [
    'roles' => [
        'protected' => [
            // 'admin', 'owner'
        ],
        'deletion' => [
            'block_if_has_permissions' => false,
            'require_inactive' => false,
            'critical_permissions' => [
                'roles.view', 'roles.update', 'roles.delete', 'roles.export',
            ],
        ],
    ],
];
```

### Spatie Permission y guards

- El guard por defecto es `web`. Si existen roles con `guard_name` no configurado (por ejemplo `api`), evita usar `Role::users` y realiza conteos vía pivot como se describe arriba.
- Se recomienda normalizar roles existentes a `guard_name = 'web'` y limpiar la cache de Spatie:

```sql
-- Normalización (ejecutar acorde a tu entorno)
UPDATE roles SET guard_name = 'web' WHERE guard_name <> 'web';
```

```bash
php artisan permission:cache-reset
```

## Resolución de Problemas

### Error de Tipo Request

Si aparece el error "Could not check compatibility between... Request", asegúrese de importar:

```php
use Illuminate\Http\Request;
```

No use `Request` sin el namespace completo en las firmas de métodos.

### Filtros No Funcionan

Verifique que:

1. Los nombres de filtros coincidan con los esperados en el backend
2. Los permisos estén correctamente cargados con eager loading
3. Los parámetros se envíen en el formato correcto (`filters[key]`)
