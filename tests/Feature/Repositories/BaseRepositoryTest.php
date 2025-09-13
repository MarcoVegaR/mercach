<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\DTO\ListQuery;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests exhaustivos para BaseRepository.
 *
 * Valida todas las funcionalidades del repositorio base:
 * - Paginación con búsqueda, filtros y ordenamiento
 * - Operaciones CRUD básicas
 * - Operaciones masivas
 * - Concurrencia con pessimistic locks
 */
class BaseRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TestModelRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestTable();
        $this->repository = new TestModelRepository;
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_models');
        parent::tearDown();
    }

    private function createTestTable(): void
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('score')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Índices estándar para testing
            $table->index(['name']);
            $table->index(['email']);
            $table->index(['active']);
            $table->index(['score']);
        });
    }

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

    // === TESTS PAGINATE ===

    public function test_paginate_returns_paginated_results(): void
    {
        // Arrange
        $this->createTestModel(['name' => 'John Doe']);
        $this->createTestModel(['name' => 'Jane Smith']);
        $this->createTestModel(['name' => 'Bob Johnson']);

        $query = new ListQuery(page: 1, perPage: 2);

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(2, $result->perPage());
        $this->assertEquals(1, $result->currentPage());
        $this->assertEquals(3, $result->total());
        $this->assertEquals(2, $result->lastPage());
        $this->assertCount(2, $result->items());
    }

    public function test_paginate_applies_search(): void
    {
        // Arrange
        $this->createTestModel(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->createTestModel(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $query = new ListQuery(q: 'john');

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertEquals(1, $result->total());
        $this->assertEquals('John Doe', $result->items()[0]->name);
    }

    public function test_paginate_applies_filters(): void
    {
        // Arrange
        $this->createTestModel(['active' => true, 'score' => 80]);
        $this->createTestModel(['active' => false, 'score' => 60]);
        $this->createTestModel(['active' => true, 'score' => 90]);

        $query = new ListQuery(filters: ['active' => true]);

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertEquals(2, $result->total());
        foreach ($result->items() as $item) {
            $this->assertTrue($item->active);
        }
    }

    public function test_paginate_applies_sort(): void
    {
        // Arrange
        $model1 = $this->createTestModel(['name' => 'Alice']);
        $model2 = $this->createTestModel(['name' => 'Bob']);
        $model3 = $this->createTestModel(['name' => 'Charlie']);

        $query = new ListQuery(sort: 'name', dir: 'asc');

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $items = $result->items();
        $this->assertEquals('Alice', $items[0]->name);
        $this->assertEquals('Bob', $items[1]->name);
        $this->assertEquals('Charlie', $items[2]->name);
    }

    public function test_paginate_uses_default_sort_for_invalid_sort(): void
    {
        // Arrange
        $model1 = $this->createTestModel();
        $model2 = $this->createTestModel();

        $query = new ListQuery(sort: 'invalid_column');

        // Act
        $result = $this->repository->paginate($query);

        // Assert - default sort is id DESC
        $items = $result->items();
        $this->assertTrue($items[0]->id > $items[1]->id);
    }

    public function test_paginate_applies_like_filter(): void
    {
        // Arrange
        $this->createTestModel(['name' => 'John Doe']);
        $this->createTestModel(['name' => 'Jane Smith']);

        $query = new ListQuery(filters: ['name_like' => 'john']);

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertEquals(1, $result->total());
        $this->assertEquals('John Doe', $result->items()[0]->name);
    }

    public function test_paginate_applies_between_filter(): void
    {
        // Arrange
        $this->createTestModel(['score' => 30]);
        $this->createTestModel(['score' => 70]);
        $this->createTestModel(['score' => 90]);

        $query = new ListQuery(filters: [
            'score_between' => ['from' => 50, 'to' => 80],
        ]);

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertEquals(1, $result->total());
        $this->assertEquals(70, $result->items()[0]->score);
    }

    public function test_paginate_applies_in_filter(): void
    {
        // Arrange
        $model1 = $this->createTestModel(['score' => 10]);
        $model2 = $this->createTestModel(['score' => 20]);
        $model3 = $this->createTestModel(['score' => 30]);

        $query = new ListQuery(filters: ['score_in' => [10, 30]]);

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertEquals(2, $result->total());
        $scores = collect($result->items())->pluck('score')->toArray();
        $this->assertContains(10, $scores);
        $this->assertContains(30, $scores);
        $this->assertNotContains(20, $scores);
    }

    // === TESTS PAGINATE BY IDS DESC ===

    public function test_paginate_by_ids_desc_respects_ids_and_order(): void
    {
        // Arrange
        $model1 = $this->createTestModel();
        $model2 = $this->createTestModel();
        $model3 = $this->createTestModel();

        $ids = [$model1->id, $model3->id];

        // Act
        $result = $this->repository->paginateByIdsDesc($ids, 10);

        // Assert
        $this->assertEquals(2, $result->total());
        $items = $result->items();
        $this->assertEquals($model3->id, $items[0]->id); // DESC order
        $this->assertEquals($model1->id, $items[1]->id);
    }

    public function test_paginate_by_ids_desc_handles_empty_ids(): void
    {
        // Arrange
        $this->createTestModel();

        // Act
        $result = $this->repository->paginateByIdsDesc([], 10);

        // Assert
        $this->assertEquals(0, $result->total());
        $this->assertEmpty($result->items());
    }

    // === TESTS ALL / COUNT / EXISTS ===

    public function test_all_returns_all_records(): void
    {
        // Arrange
        $this->createTestModel();
        $this->createTestModel();
        $this->createTestModel();

        // Act
        $result = $this->repository->all();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
    }

    public function test_count_returns_correct_count(): void
    {
        // Arrange
        $this->createTestModel(['active' => true]);
        $this->createTestModel(['active' => false]);
        $this->createTestModel(['active' => true]);

        // Act
        $totalCount = $this->repository->count();
        $activeCount = $this->repository->count(['active' => true]);

        // Assert
        $this->assertEquals(3, $totalCount);
        $this->assertEquals(2, $activeCount);
    }

    public function test_exists_by_id_returns_correct_boolean(): void
    {
        // Arrange
        $model = $this->createTestModel();

        // Act & Assert
        $this->assertTrue($this->repository->existsById($model->id));
        $this->assertFalse($this->repository->existsById(999));
    }

    public function test_exists_by_uuid_returns_correct_boolean(): void
    {
        // Arrange
        $model = $this->createTestModel();

        // Act & Assert
        $this->assertTrue($this->repository->existsByUuid($model->uuid));
        $this->assertFalse($this->repository->existsByUuid('550e8400-e29b-41d4-a716-446655440000'));
    }

    // === TESTS FIND ===

    public function test_find_by_id_returns_model_or_null(): void
    {
        // Arrange
        $model = $this->createTestModel();

        // Act & Assert
        $found = $this->repository->findById($model->id);
        $this->assertInstanceOf(TestModel::class, $found);
        $this->assertEquals($model->id, $found->id);

        $notFound = $this->repository->findById(999);
        $this->assertNull($notFound);
    }

    public function test_find_or_fail_by_id_throws_when_not_found(): void
    {
        // Arrange
        $model = $this->createTestModel();

        // Act & Assert
        $found = $this->repository->findOrFailById($model->id);
        $this->assertInstanceOf(TestModel::class, $found);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->repository->findOrFailById(999);
    }

    public function test_find_by_uuid_returns_model_or_null(): void
    {
        // Arrange
        $model = $this->createTestModel();

        // Act & Assert
        $found = $this->repository->findByUuid($model->uuid);
        $this->assertInstanceOf(TestModel::class, $found);
        $this->assertEquals($model->uuid, $found->uuid);

        $notFound = $this->repository->findByUuid('550e8400-e29b-41d4-a716-446655440000');
        $this->assertNull($notFound);
    }

    public function test_find_or_fail_by_uuid_throws_when_not_found(): void
    {
        // Arrange
        $model = $this->createTestModel();

        // Act & Assert
        $found = $this->repository->findOrFailByUuid($model->uuid);
        $this->assertInstanceOf(TestModel::class, $found);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->repository->findOrFailByUuid('550e8400-e29b-41d4-a716-446655440000');
    }

    // === TESTS CREATE / UPDATE ===

    public function test_create_respects_fillable_and_casts(): void
    {
        // Act
        $model = $this->repository->create([
            'uuid' => fake()->uuid(),
            'name' => 'Test User',
            'email' => 'test@example.com',
            'active' => true,
        ]);

        // Assert
        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertEquals('Test User', $model->name);
        $this->assertEquals('test@example.com', $model->email);
        $this->assertTrue($model->active);
        $this->assertDatabaseHas('test_models', ['name' => 'Test User']);
    }

    public function test_create_many_creates_multiple_records(): void
    {
        // Arrange
        $rows = [
            ['uuid' => fake()->uuid(), 'name' => 'User 1', 'email' => 'user1@test.com'],
            ['uuid' => fake()->uuid(), 'name' => 'User 2', 'email' => 'user2@test.com'],
        ];

        // Act
        $result = $this->repository->createMany($rows);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertDatabaseHas('test_models', ['name' => 'User 1']);
        $this->assertDatabaseHas('test_models', ['name' => 'User 2']);
    }

    public function test_update_modifies_existing_model(): void
    {
        // Arrange
        $model = $this->createTestModel(['name' => 'Original Name']);

        // Act
        $updated = $this->repository->update($model->id, ['name' => 'Updated Name']);

        // Assert
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertDatabaseHas('test_models', ['id' => $model->id, 'name' => 'Updated Name']);
    }

    public function test_update_accepts_model_instance(): void
    {
        // Arrange
        $model = $this->createTestModel(['name' => 'Original Name']);

        // Act
        $updated = $this->repository->update($model, ['name' => 'Updated Name']);

        // Assert
        $this->assertEquals('Updated Name', $updated->name);
    }

    public function test_upsert_inserts_and_updates_correctly(): void
    {
        // Arrange
        $existing = $this->createTestModel(['email' => 'existing@test.com', 'name' => 'Existing']);

        $rows = [
            ['email' => 'existing@test.com', 'name' => 'Updated Existing', 'uuid' => fake()->uuid()],
            ['email' => 'new@test.com', 'name' => 'New User', 'uuid' => fake()->uuid()],
        ];

        // Act
        $affected = $this->repository->upsert($rows, ['email'], ['name']);

        // Assert
        $this->assertEquals(2, $affected);
        $this->assertDatabaseHas('test_models', ['email' => 'existing@test.com', 'name' => 'Updated Existing']);
        $this->assertDatabaseHas('test_models', ['email' => 'new@test.com', 'name' => 'New User']);
    }

    // === TESTS DELETE / RESTORE ===

    public function test_delete_soft_deletes_model(): void
    {
        // Arrange
        $model = $this->createTestModel();

        // Act
        $result = $this->repository->delete($model->id);

        // Assert
        $this->assertTrue($result);
        $this->assertSoftDeleted('test_models', ['id' => $model->id]);
    }

    public function test_force_delete_permanently_removes_model(): void
    {
        // Arrange
        $model = $this->createTestModel();

        // Act
        $result = $this->repository->forceDelete($model->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('test_models', ['id' => $model->id]);
    }

    public function test_restore_undeletes_soft_deleted_model(): void
    {
        // Arrange
        $model = $this->createTestModel();
        $model->delete();

        // Act
        $result = $this->repository->restore($model->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('test_models', ['id' => $model->id, 'deleted_at' => null]);
    }

    public function test_set_active_changes_boolean_state(): void
    {
        // Arrange
        $model = $this->createTestModel(['active' => true]);

        // Act
        $updated = $this->repository->setActive($model->id, false);

        // Assert
        $this->assertFalse($updated->active);
        $this->assertDatabaseHas('test_models', ['id' => $model->id, 'active' => false]);
    }

    // === TESTS BULK OPERATIONS ===

    public function test_bulk_delete_by_ids_affects_correct_count(): void
    {
        // Arrange
        $model1 = $this->createTestModel();
        $model2 = $this->createTestModel();
        $model3 = $this->createTestModel();

        // Act
        $affected = $this->repository->bulkDeleteByIds([$model1->id, $model2->id]);

        // Assert
        $this->assertEquals(2, $affected);
        $this->assertSoftDeleted('test_models', ['id' => $model1->id]);
        $this->assertSoftDeleted('test_models', ['id' => $model2->id]);
        $this->assertDatabaseHas('test_models', ['id' => $model3->id, 'deleted_at' => null]);
    }

    public function test_bulk_force_delete_by_ids_permanently_removes(): void
    {
        // Arrange
        $model1 = $this->createTestModel();
        $model2 = $this->createTestModel();

        // Act
        $affected = $this->repository->bulkForceDeleteByIds([$model1->id, $model2->id]);

        // Assert
        $this->assertEquals(2, $affected);
        $this->assertDatabaseMissing('test_models', ['id' => $model1->id]);
        $this->assertDatabaseMissing('test_models', ['id' => $model2->id]);
    }

    public function test_bulk_restore_by_ids_restores_soft_deleted(): void
    {
        // Arrange
        $model1 = $this->createTestModel();
        $model2 = $this->createTestModel();
        $model1->delete();
        $model2->delete();

        // Act
        $affected = $this->repository->bulkRestoreByIds([$model1->id, $model2->id]);

        // Assert
        $this->assertEquals(2, $affected);
        $this->assertDatabaseHas('test_models', ['id' => $model1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('test_models', ['id' => $model2->id, 'deleted_at' => null]);
    }

    public function test_bulk_set_active_by_ids_updates_boolean(): void
    {
        // Arrange
        $model1 = $this->createTestModel(['active' => true]);
        $model2 = $this->createTestModel(['active' => true]);

        // Act
        $affected = $this->repository->bulkSetActiveByIds([$model1->id, $model2->id], false);

        // Assert
        $this->assertEquals(2, $affected);
        $this->assertDatabaseHas('test_models', ['id' => $model1->id, 'active' => false]);
        $this->assertDatabaseHas('test_models', ['id' => $model2->id, 'active' => false]);
    }

    public function test_bulk_operations_by_uuids_work_correctly(): void
    {
        // Arrange
        $model1 = $this->createTestModel();
        $model2 = $this->createTestModel();

        // Act
        $affected = $this->repository->bulkDeleteByUuids([$model1->uuid, $model2->uuid]);

        // Assert
        $this->assertEquals(2, $affected);
        $this->assertSoftDeleted('test_models', ['uuid' => $model1->uuid]);
        $this->assertSoftDeleted('test_models', ['uuid' => $model2->uuid]);
    }

    public function test_bulk_operations_handle_empty_arrays(): void
    {
        // Act & Assert
        $this->assertEquals(0, $this->repository->bulkDeleteByIds([]));
        $this->assertEquals(0, $this->repository->bulkForceDeleteByIds([]));
        $this->assertEquals(0, $this->repository->bulkRestoreByIds([]));
        $this->assertEquals(0, $this->repository->bulkSetActiveByIds([], true));
        $this->assertEquals(0, $this->repository->bulkDeleteByUuids([]));
    }

    // === TESTS PESSIMISTIC LOCKS ===

    public function test_with_pessimistic_lock_by_id_executes_callback(): void
    {
        // Arrange
        $model = $this->createTestModel(['name' => 'Original']);

        // Act
        $result = $this->repository->withPessimisticLockById($model->id, function (TestModel $lockedModel) {
            $lockedModel->update(['name' => 'Updated in Lock']);

            return 'callback_result';
        });

        // Assert
        $this->assertEquals('callback_result', $result);
        $this->assertDatabaseHas('test_models', ['id' => $model->id, 'name' => 'Updated in Lock']);
    }

    public function test_with_pessimistic_lock_by_uuid_executes_callback(): void
    {
        // Arrange
        $model = $this->createTestModel(['name' => 'Original']);

        // Act
        $result = $this->repository->withPessimisticLockByUuid($model->uuid, function (TestModel $lockedModel) {
            $lockedModel->update(['name' => 'Updated in Lock']);

            return 'callback_result';
        });

        // Assert
        $this->assertEquals('callback_result', $result);
        $this->assertDatabaseHas('test_models', ['uuid' => $model->uuid, 'name' => 'Updated in Lock']);
    }

    public function test_pessimistic_lock_throws_when_model_not_found(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->repository->withPessimisticLockById(999, fn ($model) => $model);
    }
}

// === TEST MODEL CLASSES ===

/**
 * Modelo de prueba para testing del BaseRepository.
 */
class TestModel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'description',
        'active',
        'score',
    ];

    protected $casts = [
        'active' => 'boolean',
        'score' => 'integer',
    ];
}

/**
 * Repositorio de prueba que extiende BaseRepository.
 */
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
