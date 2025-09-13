---
title: 'Testing Services — Guía y Patrones'
summary: 'Patrones y ejemplos para testear Services con mocks de repositorios/exporters, transacciones, concurrencia, exportación y cobertura.'
icon: material/test-tube
tags:
    - how-to
    - testing
    - services
    - backend
---

# Testing Services — Guía y Patrones

## Introducción

Los **Services** requieren un enfoque de testing específico que utiliza **mocks** para repositorios y dependencias externas, permitiendo pruebas rápidas y aisladas de la lógica de aplicación.

## Configuración Base para Tests

### Estructura de Test Típica

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Contracts\Repositories\RoleRepositoryInterface;
use App\Contracts\Exports\ExporterInterface;
use App\Services\RoleService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Psr\Container\ContainerInterface;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    private MockInterface $mockRepo;
    private MockInterface $mockContainer;
    private MockInterface $mockExporter;
    private RoleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->mockContainer = Mockery::mock(ContainerInterface::class);
        $this->mockExporter = Mockery::mock(ExporterInterface::class);

        $this->service = new RoleService($this->mockRepo, $this->mockContainer);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

## Ejecutar Tests

### Comandos Artisan

```bash
# Ejecutar todos los tests
php artisan test

# Tests específicos de services
php artisan test tests/Feature/Services/

# Test específico con detalle
php artisan test tests/Feature/Services/RoleServiceTest.php --verbose

# Con coverage (requiere Xdebug)
php artisan test --coverage --min=80
```

### Configuración PHPUnit

El archivo `phpunit.xml` debe incluir:

```xml
<phpunit>
    <testsuites>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="testing"/>
    </php>
</phpunit>
```

## Patrones de Testing por Método

### 1. Listado (list/listByIdsDesc)

```php
public function test_list_returns_correct_shape_with_rows_and_meta(): void
{
    // Arrange
    $query = new ListQuery(['page' => 1, 'perPage' => 10]);
    $with = ['permissions'];
    $withCount = ['users'];

    $role1 = $this->createMockRole(['id' => 1, 'name' => 'Admin']);
    $role2 = $this->createMockRole(['id' => 2, 'name' => 'User']);

    $paginator = new LengthAwarePaginator(
        [$role1, $role2],
        25,  // total
        10,  // perPage
        1,   // currentPage
        ['path' => 'test']
    );

    // Act & Assert
    $this->mockRepo->shouldReceive('paginate')
        ->once()
        ->with($query, $with, $withCount)
        ->andReturn($paginator);

    $result = $this->service->list($query, $with, $withCount);

    $this->assertArrayHasKey('rows', $result);
    $this->assertArrayHasKey('meta', $result);
    $this->assertCount(2, $result['rows']);

    $this->assertEquals([
        'currentPage' => 1,
        'perPage' => 10,
        'total' => 25,
        'lastPage' => 3
    ], $result['meta']);
}

public function test_list_applies_custom_to_row_transformation(): void
{
    $role = $this->createMockRole([
        'id' => 1,
        'name' => 'admin',
        'display_name' => 'Administrator',
        'created_at' => '2024-01-01 10:00:00'
    ]);

    $paginator = new LengthAwarePaginator([$role], 1, 10, 1, ['path' => 'test']);

    $this->mockRepo->shouldReceive('paginate')->once()->andReturn($paginator);

    $result = $this->service->list(new ListQuery(['page' => 1]));

    // Verificar que toRow() personalizado se aplicó
    $this->assertEquals([
        'id' => 1,
        'name' => 'admin',
        'display_name' => 'Administrator',
        // ... otros campos según implementación de toRow()
    ], $result['rows'][0]);
}
```

### 2. Lecturas Puntuales (getById/getOrFailById)

```php
public function test_get_by_id_delegates_to_repository(): void
{
    $id = 1;
    $with = ['permissions'];
    $role = $this->createMockRole(['id' => $id]);

    $this->mockRepo->shouldReceive('getById')
        ->once()
        ->with($id, $with)
        ->andReturn($role);

    $result = $this->service->getById($id, $with);

    $this->assertSame($role, $result);
}

public function test_get_by_id_returns_null_when_not_found(): void
{
    $this->mockRepo->shouldReceive('getById')
        ->once()
        ->with(999, [])
        ->andReturn(null);

    $result = $this->service->getById(999);

    $this->assertNull($result);
}

public function test_get_or_fail_by_id_throws_exception_when_not_found(): void
{
    $this->mockRepo->shouldReceive('getOrFailById')
        ->once()
        ->with(999, [])
        ->andThrow(new ModelNotFoundException());

    $this->expectException(ModelNotFoundException::class);

    $this->service->getOrFailById(999);
}
```

### 3. Operaciones de Escritura (create/update)

```php
public function test_create_wraps_in_transaction(): void
{
    $attributes = ['name' => 'manager', 'display_name' => 'Manager'];
    $role = $this->createMockRole($attributes);

    // Verificar que se usa transacción
    DB::shouldReceive('transaction')
        ->once()
        ->andReturnUsing(function ($callback) {
            return $callback();
        });

    $this->mockRepo->shouldReceive('create')
        ->once()
        ->with($attributes)
        ->andReturn($role);

    $result = $this->service->create($attributes);

    $this->assertSame($role, $result);
}

public function test_update_handles_model_and_id_parameter(): void
{
    $attributes = ['display_name' => 'Updated Manager'];
    $role = $this->createMockRole(['id' => 1] + $attributes);

    DB::shouldReceive('transaction')
        ->once()
        ->andReturnUsing(function ($callback) {
            return $callback();
        });

    // Test con ID
    $this->mockRepo->shouldReceive('update')
        ->once()
        ->with(1, $attributes)
        ->andReturn($role);

    $result = $this->service->update(1, $attributes);
    $this->assertSame($role, $result);

    // Test con Model
    $existingRole = $this->createMockRole(['id' => 1]);

    $this->mockRepo->shouldReceive('update')
        ->once()
        ->with($existingRole, $attributes)
        ->andReturn($role);

    $result = $this->service->update($existingRole, $attributes);
    $this->assertSame($role, $result);
}
```

### 4. Operaciones Masivas (bulk\*)

```php
public function test_bulk_operations_delegate_to_repository(): void
{
    $ids = [1, 2, 3];
    $uuids = ['uuid1', 'uuid2', 'uuid3'];
    $affectedRows = 3;

    // Test todas las operaciones masivas por IDs
    $this->mockRepo->shouldReceive('bulkDeleteByIds')->once()->with($ids)->andReturn($affectedRows);
    $this->mockRepo->shouldReceive('bulkForceDeleteByIds')->once()->with($ids)->andReturn($affectedRows);
    $this->mockRepo->shouldReceive('bulkRestoreByIds')->once()->with($ids)->andReturn($affectedRows);
    $this->mockRepo->shouldReceive('bulkSetActiveByIds')->once()->with($ids, true)->andReturn($affectedRows);

    $this->assertEquals($affectedRows, $this->service->bulkDeleteByIds($ids));
    $this->assertEquals($affectedRows, $this->service->bulkForceDeleteByIds($ids));
    $this->assertEquals($affectedRows, $this->service->bulkRestoreByIds($ids));
    $this->assertEquals($affectedRows, $this->service->bulkSetActiveByIds($ids, true));

    // Test operaciones masivas por UUIDs
    $this->mockRepo->shouldReceive('bulkDeleteByUuids')->once()->with($uuids)->andReturn($affectedRows);
    $this->mockRepo->shouldReceive('bulkForceDeleteByUuids')->once()->with($uuids)->andReturn($affectedRows);
    $this->mockRepo->shouldReceive('bulkRestoreByUuids')->once()->with($uuids)->andReturn($affectedRows);
    $this->mockRepo->shouldReceive('bulkSetActiveByUuids')->once()->with($uuids, false)->andReturn($affectedRows);

    $this->assertEquals($affectedRows, $this->service->bulkDeleteByUuids($uuids));
    $this->assertEquals($affectedRows, $this->service->bulkForceDeleteByUuids($uuids));
    $this->assertEquals($affectedRows, $this->service->bulkRestoreByUuids($uuids));
    $this->assertEquals($affectedRows, $this->service->bulkSetActiveByUuids($uuids, false));
}
```

### 5. Transacciones y Concurrencia

```php
public function test_transaction_executes_callback_and_returns_result(): void
{
    $expectedResult = 'transaction-success';
    $callback = function () use ($expectedResult) {
        return $expectedResult;
    };

    DB::shouldReceive('transaction')
        ->once()
        ->with($callback)
        ->andReturn($expectedResult);

    $result = $this->service->transaction($callback);

    $this->assertEquals($expectedResult, $result);
}

public function test_transaction_rolls_back_on_exception(): void
{
    $exception = new \Exception('Business rule violation');
    $callback = function () use ($exception) {
        throw $exception;
    };

    DB::shouldReceive('transaction')
        ->once()
        ->with($callback)
        ->andThrow($exception);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Business rule violation');

    $this->service->transaction($callback);
}

public function test_pessimistic_lock_delegates_to_repository(): void
{
    $id = 1;
    $result = 'locked-operation-result';
    $callback = fn() => $result;

    $this->mockRepo->shouldReceive('withPessimisticLockById')
        ->once()
        ->with($id, $callback)
        ->andReturn($result);

    $actualResult = $this->service->withPessimisticLockById($id, $callback);

    $this->assertEquals($result, $actualResult);
}
```

### 6. Exportación

```php
public function test_export_uses_default_columns_and_filename(): void
{
    $query = new ListQuery(['page' => 1]);
    $format = 'csv';

    // Mock del response
    $response = Mockery::mock(StreamedResponse::class);
    $response->headers = Mockery::mock();
    $response->headers->shouldReceive('set')
        ->once()
        ->with('Content-Disposition', Mockery::pattern('/attachment; filename=".*\.csv"/'));

    // Mock del container y exporter
    $this->mockContainer->shouldReceive('get')
        ->once()
        ->with('exporter.csv')
        ->andReturn($this->mockExporter);

    $this->mockExporter->shouldReceive('stream')
        ->once()
        ->with(Mockery::type('Generator'), ['id', 'name', 'display_name']) // columnas por defecto
        ->andReturn($response);

    // Mock del paginator para exportRows
    $role = $this->createMockRole(['id' => 1, 'name' => 'admin']);
    $paginator = new LengthAwarePaginator([$role], 1, 1000, 1, ['path' => 'test']);
    $this->mockRepo->shouldReceive('paginate')->once()->andReturn($paginator);

    $result = $this->service->export($query, $format);

    $this->assertSame($response, $result);
}

public function test_export_filters_columns_correctly(): void
{
    $query = new ListQuery(['page' => 1]);
    $format = 'xlsx';
    $columns = ['id', 'name']; // Solo estas columnas
    $filename = 'custom_roles.xlsx';

    $response = Mockery::mock(StreamedResponse::class);
    $response->headers = Mockery::mock();
    $response->headers->shouldReceive('set')
        ->once()
        ->with('Content-Disposition', 'attachment; filename="' . $filename . '"');

    $this->mockContainer->shouldReceive('get')
        ->once()
        ->with('exporter.xlsx')
        ->andReturn($this->mockExporter);

    $this->mockExporter->shouldReceive('stream')
        ->once()
        ->with(Mockery::type('Generator'), $columns)
        ->andReturn($response);

    // Verificar que el generador filtra columnas
    $role = $this->createMockRole([
        'id' => 1,
        'name' => 'admin',
        'display_name' => 'Administrator', // Esta no debe aparecer
        'description' => 'Admin role' // Esta tampoco
    ]);

    $paginator = new LengthAwarePaginator([$role], 1, 1000, 1, ['path' => 'test']);
    $this->mockRepo->shouldReceive('paginate')->once()->andReturn($paginator);

    $result = $this->service->export($query, $format, $columns, $filename);

    $this->assertSame($response, $result);
}

public function test_export_handles_multiple_pages(): void
{
    $query = new ListQuery(['page' => 1]);

    // Simular 2 páginas de resultados
    $role1 = $this->createMockRole(['id' => 1, 'name' => 'admin']);
    $role2 = $this->createMockRole(['id' => 2, 'name' => 'user']);

    $paginator1 = new LengthAwarePaginator([$role1], 2, 1, 1, ['path' => 'test']);
    $paginator2 = new LengthAwarePaginator([$role2], 2, 1, 2, ['path' => 'test']);

    $this->mockRepo->shouldReceive('paginate')
        ->twice()
        ->andReturn($paginator1, $paginator2);

    $response = Mockery::mock(StreamedResponse::class);
    $response->headers = Mockery::mock();
    $response->headers->shouldReceive('set')->once();

    $this->mockContainer->shouldReceive('get')
        ->once()
        ->with('exporter.csv')
        ->andReturn($this->mockExporter);

    $this->mockExporter->shouldReceive('stream')
        ->once()
        ->with(Mockery::type('Generator'), Mockery::any())
        ->andReturn($response);

    $this->service->export($query, 'csv');
}
```

## Testing de Métodos Específicos del Dominio

```php
public function test_assign_permissions_validates_and_syncs(): void
{
    $roleId = 1;
    $permissionIds = [1, 2, 3];

    $role = $this->createMockRole(['id' => $roleId]);
    $role->shouldReceive('fresh')->once()->with(['permissions'])->andReturnSelf();

    // Mock de relación permissions
    $permissionsRelation = Mockery::mock();
    $permissionsRelation->shouldReceive('sync')->once()->with($permissionIds);
    $role->shouldReceive('permissions')->once()->andReturn($permissionsRelation);

    DB::shouldReceive('transaction')
        ->once()
        ->andReturnUsing(function ($callback) {
            return $callback();
        });

    $this->mockRepo->shouldReceive('getOrFailById')
        ->once()
        ->with($roleId, ['permissions'])
        ->andReturn($role);

    // Simular validaciones internas (pueden requerir mocks adicionales)
    $this->mockPermissionRepository = Mockery::mock();
    // ... setup para validaciones

    $result = $this->service->assignPermissions($roleId, $permissionIds);

    $this->assertSame($role, $result);
}

public function test_assign_permissions_throws_exception_for_invalid_permissions(): void
{
    $roleId = 1;
    $invalidPermissionIds = [999, 1000]; // IDs que no existen

    $role = $this->createMockRole(['id' => $roleId]);

    DB::shouldReceive('transaction')
        ->once()
        ->andReturnUsing(function ($callback) {
            return $callback();
        });

    $this->mockRepo->shouldReceive('getOrFailById')
        ->once()
        ->with($roleId, ['permissions'])
        ->andReturn($role);

    // El método debe lanzar excepción al validar permisos inexistentes
    $this->expectException(BusinessRuleException::class);
    $this->expectExceptionMessage('Invalid permissions provided');

    $this->service->assignPermissions($roleId, $invalidPermissionIds);
}
```

## Helpers para Tests

### Factory de Mocks

```php
protected function createMockRole(array $attributes = []): MockInterface
{
    $role = Mockery::mock(Role::class);

    // Defaults
    $defaults = [
        'id' => 1,
        'name' => 'test-role',
        'display_name' => 'Test Role',
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $attributes = array_merge($defaults, $attributes);

    $role->shouldReceive('attributesToArray')
        ->andReturn($attributes);

    // Setup properties if needed
    foreach ($attributes as $key => $value) {
        $role->{$key} = $value;
    }

    return $role;
}

protected function createMockCollection(array $items = []): MockInterface
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('count')->andReturn(count($items));
    $collection->shouldReceive('isEmpty')->andReturn(empty($items));
    $collection->shouldReceive('isNotEmpty')->andReturn(!empty($items));

    return $collection;
}
```

## Assertions Personalizadas

```php
protected function assertValidListResult(array $result): void
{
    $this->assertArrayHasKey('rows', $result);
    $this->assertArrayHasKey('meta', $result);
    $this->assertIsArray($result['rows']);
    $this->assertIsArray($result['meta']);

    // Verificar estructura de meta
    $requiredMetaKeys = ['currentPage', 'perPage', 'total', 'lastPage'];
    foreach ($requiredMetaKeys as $key) {
        $this->assertArrayHasKey($key, $result['meta']);
        $this->assertIsInt($result['meta'][$key]);
    }
}

protected function assertValidExportResponse(StreamedResponse $response): void
{
    $this->assertInstanceOf(StreamedResponse::class, $response);

    $contentDisposition = $response->headers->get('Content-Disposition');
    $this->assertStringContains('attachment', $contentDisposition);
    $this->assertStringContains('filename=', $contentDisposition);
}
```

## Coverage y Métricas

### Comandos útiles

```bash
# Coverage mínimo requerido
php artisan test --coverage --min=85

# Coverage detallado por clase
php artisan test --coverage-html=storage/coverage

# Solo tests que fallen
php artisan test --stop-on-failure

# Ejecutar con profiling
php artisan test --profile
```

### Métricas Objetivo

- **Coverage**: Mínimo 85% en services
- **Assertions por test**: 3-5 en promedio
- **Tiempo por test**: < 100ms para tests unitarios
- **Casos por método público**: Mínimo 2 (happy path + edge case)

## Checklist de Testing

- [ ] Mock todas las dependencias externas (repos, exporters)
- [ ] Test happy path y edge cases para cada método público
- [ ] Verificar que transacciones se usan correctamente
- [ ] Probar manejo de excepciones
- [ ] Validar formato de respuesta en list() y export()
- [ ] Test métodos específicos del dominio
- [ ] Verificar que hooks personalizados funcionan
- [ ] Assertion de que mocks se llaman con parámetros correctos
- [ ] Test de concurrencia y locks pesimistas
- [ ] Coverage mínimo del 85%
