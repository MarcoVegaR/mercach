---
title: 'Services — Guía de Buenas Prácticas'
summary: 'Rol de la capa de Services: orquestación, transacciones, transformación de datos, concurrencia y exportación; patrones y ejemplos.'
icon: material/cogs
tags:
    - explicación
    - backend
    - services
---

# Services — Guía de Buenas Prácticas

## Introducción

Los **Services** actúan como la capa de orquestación entre controladores y repositorios, implementando la lógica de aplicación y coordinando operaciones complejas. Siguen el principio de **Inversión de Dependencias (DIP)** para facilitar testing y mantenibilidad.

## Responsabilidades de los Services

### ✅ Responsabilidades Principales

- **Orquestación**: Coordinar múltiples repositorios y operaciones
- **Transacciones**: Manejar consistencia de datos en operaciones complejas
- **Transformación**: Adaptar datos entre capas (API ↔ Dominio ↔ Persistencia)
- **Validación de negocio**: Reglas que trascienden un solo modelo
- **Exportación**: Generar reportes y archivos de datos
- **Concurrencia**: Manejar locks pesimistas para operaciones críticas

### ❌ No Responsabilidades

- **Acceso directo a DB**: Usar repositorios, nunca queries crudas
- **Validación de entrada**: Delegarla a Form Requests
- **Formateo de respuesta**: Controladores manejan formato HTTP
- **Lógica de presentación**: Mantener separación UI/lógica

## Arquitectura Base

```php
<?php

// Contrato común para todos los services
interface ServiceInterface
{
    // Listado con shape ['rows', 'meta']
    public function list(ListQuery $query, array $with = [], array $withCount = []): array;

    // Export con streaming para memoria eficiente
    public function export(ListQuery $query, string $format, ?array $columns = null, ?string $filename = null): StreamedResponse;

    // CRUD con transacciones automáticas
    public function create(array $attributes): Model;
    public function update(Model|int|string $modelOrId, array $attributes): Model;

    // Operaciones masivas
    public function bulkDeleteByIds(array $ids): int;

    // Concurrencia
    public function transaction(callable $callback);
    public function withPessimisticLockById(int|string $id, callable $callback);
}

// Implementación base abstracta
abstract class BaseService implements ServiceInterface
{
    public function __construct(
        protected RepositoryInterface $repo,
        protected ContainerInterface $container
    ) {}

    // Implementaciones comunes que delegan al repositorio
    // + hooks extensibles para servicios concretos
}
```

## Implementación de Service Concreto

### Ejemplo: RoleService

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\RoleRepositoryInterface;
use App\Contracts\Services\RoleServiceInterface;
use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Psr\Container\ContainerInterface;

class RoleService extends BaseService implements RoleServiceInterface
{
    public function __construct(
        protected RoleRepositoryInterface $repo,
        ContainerInterface $container
    ) {
        parent::__construct($repo, $container);
    }

    // --- Hooks personalizados ---

    /**
     * Mapea Role a formato optimizado para UI
     */
    protected function toRow(Model $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'active' => $role->active,
            'permissions_count' => $role->permissions_count ?? 0,
            'users_count' => $role->users_count ?? 0,
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
        ];
    }

    /**
     * Columnas por defecto para exportación
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id',
            'name',
            'display_name',
            'description',
            'active',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Nombre personalizado para exports
     */
    protected function defaultExportFilename(string $format, ListQuery $query): string
    {
        $timestamp = date('Ymd_His');
        return "roles_export_{$timestamp}.{$format}";
    }

    /**
     * Clase del modelo para generar nombres de archivo
     */
    protected function repoModelClass(): string
    {
        return Role::class;
    }

    // --- Métodos específicos del dominio ---

    /**
     * Asignar permisos a un rol con validación de negocio
     */
    public function assignPermissions(int $roleId, array $permissionIds): Role
    {
        return $this->transaction(function () use ($roleId, $permissionIds) {
            $role = $this->getOrFailById($roleId, ['permissions']);

            // Validar que todos los permisos existen
            $this->validatePermissionsExist($permissionIds);

            // Validar reglas de negocio específicas
            $this->validateRolePermissionRules($role, $permissionIds);

            // Sincronizar permisos
            $role->permissions()->sync($permissionIds);

            return $role->fresh(['permissions']);
        });
    }

    private function validatePermissionsExist(array $permissionIds): void
    {
        // Implementar validación...
    }

    private function validateRolePermissionRules(Role $role, array $permissionIds): void
    {
        // Implementar reglas de negocio...
    }
}
```

## Shape de Respuesta para Index

Los services devuelven un formato consistente para listados que es compatible con **Inertia partial reloads**:

```php
[
    'rows' => [
        ['id' => 1, 'name' => 'Admin', 'active' => true],
        ['id' => 2, 'name' => 'User', 'active' => true],
    ],
    'meta' => [
        'currentPage' => 1,
        'perPage' => 10,
        'total' => 25,
        'lastPage' => 3
    ]
]
```

### Uso en Controladores

```php
public function index(ListRolesRequest $request): Response
{
    $query = ListQuery::fromRequest($request);

    $result = $this->roleService->list(
        $query,
        with: ['permissions:id,name'],
        withCount: [] // Conteos derivados vía subselect sobre pivots en el repositorio
    );

    return Inertia::render('Roles/Index', [
        'roles' => $result,
        'filters' => $query->toArray(),
    ]);
}
```

## Exportación de Datos

### Streaming para Eficiencia de Memoria

Los services implementan exportación con **streaming** para manejar grandes volúmenes sin agotar memoria:

```php
public function export(Request $request): StreamedResponse
{
    $query = ListQuery::fromRequest($request);

    // Columnas visibles desde el frontend (SSOT)
    $columns = $request->input('columns', null);

    return $this->roleService->export(
        $query,
        format: 'csv',
        columns: $columns,
        filename: 'roles_filtered.csv'
    );
}
```

### Configuración de Exporters

Los exporters se registran en el container:

```php
// En un ServiceProvider
$this->app->bind('exporter.csv', CsvExporter::class);
$this->app->bind('exporter.xlsx', XlsxExporter::class);
$this->app->bind('exporter.json', JsonExporter::class);
```

## Transacciones y Concurrencia

### Transacciones Automáticas

Las operaciones de escritura se envuelven automáticamente en transacciones:

```php
// Automáticamente transaccional
$role = $this->roleService->create([
    'name' => 'manager',
    'display_name' => 'Manager'
]);

// Para operaciones complejas
$result = $this->roleService->transaction(function () {
    $role = $this->roleService->create(['name' => 'temp']);
    $this->permissionService->assignToRole($role->id, [1, 2, 3]);
    return $role;
});
```

### Locks Pesimistas para Casos Críticos

```php
// Para operaciones que requieren consistencia estricta
$updatedRole = $this->roleService->withPessimisticLockById(
    $roleId,
    function () use ($roleId, $changes) {
        $role = $this->roleService->getOrFailById($roleId);

        // Validar estado actual con lock
        if ($role->active && $this->hasActiveUsers($role)) {
            throw new BusinessException('Cannot modify active role with users');
        }

        return $this->roleService->update($role, $changes);
    }
);
```

### Bloqueo Optimista con updated_at

En operaciones de actualización, los services implementan bloqueo optimista comparando el `updated_at` esperado con el valor actual del modelo. Esto evita sobrescribir cambios hechos por otro usuario:

```php
// App\Http\Controllers\Concerns\HandlesForm@update
$expectedUpdatedAt = $request->input('_version'); // ISO 8601 desde el frontend
$model = $this->service->update($model, $validated, $expectedUpdatedAt);
```

En `App\Services\BaseService::update()` se normalizan ambos valores a timestamps Unix para evitar discrepancias de formato:

```php
// Normalización y comparación segura por timestamp
$currentTimestamp  = $model->updated_at?->timestamp;
$expectedTimestamp = \Carbon\Carbon::parse($expectedUpdatedAt)->timestamp;

if ($currentTimestamp !== $expectedTimestamp) {
    throw new \App\Exceptions\DomainActionException(
        'El registro ha sido modificado por otro usuario. Por favor, recarga la página e intenta nuevamente.'
    );
}
```

Recomendaciones:

- Enviar desde el frontend un campo oculto `_version` con el `updated_at` recibido al cargar el formulario.
- Tras un conflicto, refrescar el recurso y mostrar un aviso claro al usuario.

## Manejo de Errores

### Propagar Excepciones de Dominio

```php
public function deactivateRole(int $roleId): Role
{
    return $this->transaction(function () use ($roleId) {
        $role = $this->getOrFailById($roleId, ['users']);

        // Lanzar excepción de dominio, no atrapar para "silenciar"
        if ($role->users->count() > 0) {
            throw new RoleHasActiveUsersException(
                "Cannot deactivate role '{$role->name}' with {$role->users->count()} active users"
            );
        }

        return $this->update($role, ['active' => false]);
    });
}
```

### Tipos de Excepciones

- **ModelNotFoundException**: Para recursos no encontrados
- **BusinessRuleException**: Para violaciones de reglas de negocio
- **ValidationException**: Para datos inválidos (generalmente desde Form Requests)
- **ConcurrencyException**: Para conflictos de versioning optimista

## Escalabilidad y Performance

### Operaciones Masivas

```php
// Eficiente para grandes volúmenes
$affectedCount = $this->roleService->bulkSetActiveByIds(
    $roleIds,
    active: false
);

// Para operaciones muy pesadas, delegar a queues
dispatch(new BulkUpdateRolesJob($roleIds, $changes));
```

### Caching en Services

```php
public function getActiveRolesForUser(int $userId): Collection
{
    return Cache::tags(['users', 'roles'])
        ->remember(
            "user.{$userId}.active_roles",
            now()->addHour(),
            fn() => $this->repo->getActiveRolesForUser($userId)
        );
}
```

## Registro en Container

### DomainServiceProvider

```php
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerServices();
    }

    private function registerServices(): void
    {
        $this->app->bind(
            RoleServiceInterface::class,
            RoleService::class
        );

        $this->app->bind(
            UserServiceInterface::class,
            UserService::class
        );

        // Exporters
        $this->app->bind('exporter.csv', CsvExporter::class);
        $this->app->bind('exporter.xlsx', XlsxExporter::class);
        $this->app->bind('exporter.json', JsonExporter::class);
    }
}
```

## Testing

Ver [Testing Services](testing-services.md) para patrones de prueba detallados.

## Checklist de Implementación

- [ ] Definir interfaz específica que extienda `ServiceInterface`
- [ ] Implementar service concreto extendiendo `BaseService`
- [ ] Sobrescribir hooks: `toRow()`, `defaultExportColumns()`, `repoModelClass()`
- [ ] Implementar métodos específicos del dominio
- [ ] Registrar bindings en `DomainServiceProvider`
- [ ] Crear tests unitarios con mocks
- [ ] Validar que operaciones de escritura usen transacciones
- [ ] Verificar manejo adecuado de excepciones
- [ ] Probar exportación con volúmenes grandes
- [ ] Documentar métodos públicos específicos del dominio
