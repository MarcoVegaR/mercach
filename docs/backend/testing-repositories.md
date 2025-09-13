---
title: 'Testing Repositories — Guía de Pruebas'
summary: 'Cómo ejecutar y crear tests para el sistema de repositorios: casos por método, setup de DB de pruebas, assertions y cobertura.'
icon: material/clipboard-check-outline
tags:
    - how-to
    - testing
    - repositorios
    - backend
---

# Testing Repositories — Guía de Pruebas

Esta guía describe cómo ejecutar y crear tests para el sistema de repositorios base, incluyendo casos de prueba específicos para cada método y mejores prácticas de testing.

## Ejecutar Tests

### Comando Principal

```bash
php artisan test
```

### Tests Específicos de Repositorios

```bash
# Ejecutar solo tests de repositorios
php artisan test tests/Feature/Repositories/

# Ejecutar test específico de BaseRepository
php artisan test tests/Feature/Repositories/BaseRepositoryTest.php

# Ejecutar con cobertura de código
php artisan test --coverage

# Ejecutar en paralelo (más rápido)
php artisan test --parallel
```

### Configuración de Test Database

El proyecto usa `.env.testing` para configuración de tests. Asegúrate de tener:

```bash
# .env.testing
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5434
DB_DATABASE=boilerplate_laravel12_test
DB_USERNAME=postgres
DB_PASSWORD=password
```

## Casos de Prueba por Método

### Método `paginate()`

**Casos Validados:**

- ✅ Retorna `LengthAwarePaginator` con metadatos correctos
- ✅ Aplica búsqueda de texto (parámetro `q`)
- ✅ Aplica filtros tipados (boolean, string, arrays)
- ✅ Aplica ordenamiento válido y rechaza inválido
- ✅ Usa ordenamiento por defecto cuando no es válido
- ✅ Procesa filtros LIKE case-insensitive
- ✅ Procesa filtros BETWEEN con rangos
- ✅ Procesa filtros IN con arrays

```php
public function test_paginate_returns_paginated_results(): void
{
    // Arrange: 3 registros, 2 por página
    $this->createTestModel(['name' => 'John Doe']);
    $this->createTestModel(['name' => 'Jane Smith']);
    $this->createTestModel(['name' => 'Bob Johnson']);

    $query = new ListQuery(page: 1, perPage: 2);

    // Act
    $result = $this->repository->paginate($query);

    // Assert: Metadatos de paginación
    $this->assertEquals(2, $result->perPage());
    $this->assertEquals(1, $result->currentPage());
    $this->assertEquals(3, $result->total());
    $this->assertEquals(2, $result->lastPage());
    $this->assertCount(2, $result->items());
}
```

### Método `paginateByIdsDesc()`

**Casos Validados:**

- ✅ Respeta subconjunto de IDs especificados
- ✅ Ordena en descendente por ID
- ✅ Maneja arrays vacíos sin errores

```php
public function test_paginate_by_ids_desc_respects_ids_and_order(): void
{
    // Arrange: 3 modelos, seleccionar 2 específicos
    $model1 = $this->createTestModel();
    $model2 = $this->createTestModel();
    $model3 = $this->createTestModel();

    $ids = [$model1->id, $model3->id];

    // Act
    $result = $this->repository->paginateByIdsDesc($ids, 10);

    // Assert: Solo IDs seleccionados, orden DESC
    $this->assertEquals(2, $result->total());
    $items = $result->items();
    $this->assertEquals($model3->id, $items[0]->id); // DESC order
    $this->assertEquals($model1->id, $items[1]->id);
}
```

### Métodos `all()`, `count()`, `exists*()`

**Casos Validados:**

- ✅ `all()` retorna `Collection` con todos los registros
- ✅ `count()` retorna entero correcto con/sin filtros
- ✅ `existsById()` y `existsByUuid()` retornan boolean correcto

```php
public function test_count_returns_correct_count(): void
{
    // Arrange: 2 activos, 1 inactivo
    $this->createTestModel(['active' => true]);
    $this->createTestModel(['active' => false]);
    $this->createTestModel(['active' => true]);

    // Act & Assert
    $this->assertEquals(3, $this->repository->count());
    $this->assertEquals(2, $this->repository->count(['active' => true]));
}
```

### Métodos `find*()`

**Casos Validados:**

- ✅ `findById()` retorna modelo o null
- ✅ `findOrFailById()` lanza `ModelNotFoundException` cuando no encuentra
- ✅ `findByUuid()` y `findOrFailByUuid()` funcionan igual con UUID

```php
public function test_find_or_fail_by_id_throws_when_not_found(): void
{
    // Arrange
    $model = $this->createTestModel();

    // Act & Assert: Encuentra existente
    $found = $this->repository->findOrFailById($model->id);
    $this->assertInstanceOf(TestModel::class, $found);

    // Assert: Lanza excepción para ID inexistente
    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    $this->repository->findOrFailById(999);
}
```

### Métodos `create*()`, `update()`, `upsert()`

**Casos Validados:**

- ✅ `create()` respeta `$fillable` y `$casts`
- ✅ `createMany()` crea múltiples registros
- ✅ `update()` acepta modelo o ID, retorna modelo actualizado
- ✅ `upsert()` inserta nuevos y actualiza existentes

```php
public function test_upsert_inserts_and_updates_correctly(): void
{
    // Arrange: Registro existente
    $existing = $this->createTestModel(['email' => 'existing@test.com', 'name' => 'Existing']);

    $rows = [
        ['email' => 'existing@test.com', 'name' => 'Updated Existing', 'uuid' => fake()->uuid()],
        ['email' => 'new@test.com', 'name' => 'New User', 'uuid' => fake()->uuid()],
    ];

    // Act
    $affected = $this->repository->upsert($rows, ['email'], ['name']);

    // Assert: 2 afectados, 1 actualizado + 1 insertado
    $this->assertEquals(2, $affected);
    $this->assertDatabaseHas('test_models', ['email' => 'existing@test.com', 'name' => 'Updated Existing']);
    $this->assertDatabaseHas('test_models', ['email' => 'new@test.com', 'name' => 'New User']);
}
```

### Métodos `delete()`, `forceDelete()`, `restore()`

**Casos Validados:**

- ✅ `delete()` aplica soft delete cuando disponible
- ✅ `forceDelete()` elimina permanentemente
- ✅ `restore()` restaura registros soft deleted
- ✅ `setActive()` cambia estado boolean

```php
public function test_restore_undeletes_soft_deleted_model(): void
{
    // Arrange: Modelo soft deleted
    $model = $this->createTestModel();
    $model->delete();

    // Act
    $result = $this->repository->restore($model->id);

    // Assert: Restaurado (deleted_at = null)
    $this->assertTrue($result);
    $this->assertDatabaseHas('test_models', ['id' => $model->id, 'deleted_at' => null]);
}
```

### Operaciones Masivas `bulk*()`

**Casos Validados:**

- ✅ `bulkDeleteByIds()` retorna conteo correcto y aplica soft delete
- ✅ `bulkForceDeleteByIds()` elimina permanentemente
- ✅ `bulkRestoreByIds()` restaura múltiples registros
- ✅ `bulkSetActiveByIds()` actualiza estado boolean masivamente
- ✅ Versiones con UUID funcionan igual
- ✅ Arrays vacíos retornan 0 sin errores

```php
public function test_bulk_delete_by_ids_affects_correct_count(): void
{
    // Arrange: 3 modelos
    $model1 = $this->createTestModel();
    $model2 = $this->createTestModel();
    $model3 = $this->createTestModel();

    // Act: Eliminar 2 de 3
    $affected = $this->repository->bulkDeleteByIds([$model1->id, $model2->id]);

    // Assert: 2 afectados, soft deleted
    $this->assertEquals(2, $affected);
    $this->assertSoftDeleted('test_models', ['id' => $model1->id]);
    $this->assertSoftDeleted('test_models', ['id' => $model2->id]);
    $this->assertDatabaseHas('test_models', ['id' => $model3->id, 'deleted_at' => null]);
}
```

### Concurrencia `withPessimisticLock*()`

**Casos Validados:**

- ✅ `withPessimisticLockById()` ejecuta callback con modelo bloqueado
- ✅ `withPessimisticLockByUuid()` funciona igual con UUID
- ✅ Lanza `ModelNotFoundException` para IDs inexistentes
- ✅ Cambios dentro del callback se persisten

```php
public function test_with_pessimistic_lock_by_id_executes_callback(): void
{
    // Arrange
    $model = $this->createTestModel(['name' => 'Original']);

    // Act: Callback que modifica el modelo
    $result = $this->repository->withPessimisticLockById($model->id, function (TestModel $lockedModel) {
        $lockedModel->update(['name' => 'Updated in Lock']);
        return 'callback_result';
    });

    // Assert: Callback ejecutado y cambios persistidos
    $this->assertEquals('callback_result', $result);
    $this->assertDatabaseHas('test_models', ['id' => $model->id, 'name' => 'Updated in Lock']);
}
```

## Estructura de Test Model

### Modelo de Prueba

```php
class TestModel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'email', 'description', 'active', 'score',
    ];

    protected $casts = [
        'active' => 'boolean',
        'score' => 'integer',
    ];
}
```

### Repositorio de Prueba

```php
class TestModelRepository extends BaseRepository
{
    protected string $modelClass = TestModel::class;

    protected function searchable(): array
    {
        return ['name', 'email'];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'email', 'score', 'created_at', 'updated_at'];
    }

    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    protected function filterMap(): array
    {
        return [
            'high_score' => function (Builder $builder, mixed $value) {
                $builder->where('score', '>=', (int) $value);
            },
        ];
    }
}
```

## Mejores Prácticas de Testing

### Setup y Teardown

```php
protected function setUp(): void
{
    parent::setUp();

    $this->createTestTable();
    $this->repository = new TestModelRepository();
}

protected function tearDown(): void
{
    Schema::dropIfExists('test_models');
    parent::tearDown();
}
```

### Factory Methods

```php
private function createTestModel(array $attributes = []): TestModel
{
    return TestModel::create(array_merge([
        'uuid' => fake()->uuid(),
        'name' => fake()->name(),
        'email' => fake()->unique()->email(),
        'description' => fake()->text(),
        'active' => true,
        'score' => fake()->numberBetween(1, 100),
    ], $attributes));
}
```

### Aserciones Específicas

```php
// Para paginación
$this->assertInstanceOf(LengthAwarePaginator::class, $result);

// Para base de datos
$this->assertDatabaseHas('test_models', ['name' => 'Expected']);
$this->assertSoftDeleted('test_models', ['id' => $model->id]);
$this->assertDatabaseMissing('test_models', ['id' => $model->id]);

// Para excepciones
$this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
```

## Testing de Repositorios Concretos

### Template Base

```php
<?php

namespace Tests\Feature\Repositories;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository();
    }

    public function test_find_by_email_returns_correct_user(): void
    {
        // Arrange
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Act
        $found = $this->repository->findByEmail('test@example.com');

        // Assert
        $this->assertInstanceOf(User::class, $found);
        $this->assertEquals($user->id, $found->id);
    }

    public function test_find_by_email_returns_null_when_not_found(): void
    {
        // Act
        $found = $this->repository->findByEmail('nonexistent@example.com');

        // Assert
        $this->assertNull($found);
    }

    public function test_searchable_includes_expected_columns(): void
    {
        // Arrange
        User::factory()->create(['name' => 'John Doe', 'email' => 'other@test.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'john@test.com']);

        $query = new ListQuery(q: 'john');

        // Act
        $result = $this->repository->paginate($query);

        // Assert: Encuentra en ambas columnas
        $this->assertEquals(2, $result->total());
    }
}
```

### Coverage Goals

**Cobertura Objetivo:**

- Líneas: > 90%
- Métodos: 100%
- Branches: > 85%

**Comandos de Coverage:**

```bash
# Generar reporte HTML
php artisan test --coverage-html coverage/

# Ver coverage en terminal
php artisan test --coverage-text

# Coverage específico
php artisan test tests/Feature/Repositories/ --coverage
```

## Debugging Tests

### Logging en Tests

```php
public function test_complex_filter_behavior(): void
{
    // Enable query logging
    DB::enableQueryLog();

    $result = $this->repository->paginate($query);

    // Debug queries
    $queries = DB::getQueryLog();
    dump($queries); // Ver queries ejecutadas

    $this->assertEquals(2, $result->total());
}
```

### Test Database Inspection

```php
// Ver estado de la DB en un test
public function test_bulk_operation(): void
{
    $this->repository->bulkDeleteByIds([1, 2, 3]);

    // Debug: ver qué quedó en la DB
    $remaining = DB::table('test_models')->get();
    dump($remaining->toArray());

    $this->assertEquals(0, $remaining->count());
}
```

El sistema de tests garantiza que cualquier cambio en el BaseRepository será detectado inmediatamente, manteniendo la integridad del sistema de repositorios.
