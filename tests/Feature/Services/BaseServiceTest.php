<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Contracts\Exports\ExporterInterface;
use App\Contracts\Repositories\RepositoryInterface;
use App\DTO\ListQuery;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class BaseServiceTest extends TestCase
{
    private MockInterface $mockRepo;

    private MockInterface $mockContainer;

    private MockInterface $mockExporter;

    private TestableBaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepo = Mockery::mock(RepositoryInterface::class);
        $this->mockContainer = Mockery::mock(ContainerInterface::class);
        $this->mockExporter = Mockery::mock(ExporterInterface::class);

        $this->service = new TestableBaseService($this->mockRepo, $this->mockContainer);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Listado (Index) ---

    public function test_list_returns_correct_shape_with_rows_and_meta(): void
    {
        $query = new ListQuery(page: 1, perPage: 10);
        $with = ['relation'];
        $withCount = ['count'];

        $model1 = $this->createMockModel(['id' => 1, 'name' => 'Test 1']);
        $model2 = $this->createMockModel(['id' => 2, 'name' => 'Test 2']);

        $paginator = new LengthAwarePaginator(
            [$model1, $model2],
            100, // total
            10,  // perPage
            1,   // currentPage
            ['path' => 'test']
        );

        $this->mockRepo->shouldReceive('paginate')
            ->once()
            ->with($query, $with, $withCount)
            ->andReturn($paginator);

        $result = $this->service->list($query, $with, $withCount);

        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertCount(2, $result['rows']);

        $this->assertEquals([
            ['id' => 1, 'name' => 'Test 1'],
            ['id' => 2, 'name' => 'Test 2'],
        ], $result['rows']);

        $this->assertEquals([
            'currentPage' => 1,
            'perPage' => 10,
            'total' => 100,
            'lastPage' => 10,
        ], $result['meta']);
    }

    public function test_list_by_ids_desc_delegates_to_repo(): void
    {
        $ids = [1, 2, 3];
        $perPage = 15;
        $with = ['relation'];
        $withCount = ['count'];

        $model = $this->createMockModel(['id' => 3, 'name' => 'Test']);
        $paginator = new LengthAwarePaginator([$model], 1, 15, 1, ['path' => 'test']);

        $this->mockRepo->shouldReceive('paginateByIdsDesc')
            ->once()
            ->with($ids, $perPage, $with, $withCount)
            ->andReturn($paginator);

        $result = $this->service->listByIdsDesc($ids, $perPage, $with, $withCount);

        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertCount(1, $result['rows']);
    }

    // --- Lecturas puntuales ---

    public function test_get_by_id_delegates_to_repo(): void
    {
        $id = 1;
        $with = ['relation'];
        $model = $this->createMockModel(['id' => $id]);

        $this->mockRepo->shouldReceive('findById')
            ->once()
            ->with($id, $with)
            ->andReturn($model);

        $result = $this->service->getById($id, $with);

        $this->assertSame($model, $result);
    }

    public function test_get_by_id_returns_null_when_not_found(): void
    {
        $this->mockRepo->shouldReceive('findById')
            ->once()
            ->andReturn(null);

        $result = $this->service->getById(999);

        $this->assertNull($result);
    }

    public function test_get_or_fail_by_id_returns_model(): void
    {
        $id = 1;
        $model = $this->createMockModel(['id' => $id]);

        $this->mockRepo->shouldReceive('findOrFailById')
            ->once()
            ->with($id, [])
            ->andReturn($model);

        $result = $this->service->getOrFailById($id);

        $this->assertSame($model, $result);
    }

    public function test_get_or_fail_by_id_throws_exception_when_not_found(): void
    {
        $this->mockRepo->shouldReceive('findOrFailById')
            ->once()
            ->andThrow(ModelNotFoundException::class);

        $this->expectException(ModelNotFoundException::class);

        $this->service->getOrFailById(999);
    }

    public function test_get_by_uuid_delegates_to_repo(): void
    {
        $uuid = 'test-uuid';
        $with = ['relation'];
        $model = $this->createMockModel(['uuid' => $uuid]);

        $this->mockRepo->shouldReceive('findByUuid')
            ->once()
            ->with($uuid, $with)
            ->andReturn($model);

        $result = $this->service->getByUuid($uuid, $with);

        $this->assertSame($model, $result);
    }

    public function test_get_or_fail_by_uuid_throws_exception_when_not_found(): void
    {
        $this->mockRepo->shouldReceive('findOrFailByUuid')
            ->once()
            ->andThrow(ModelNotFoundException::class);

        $this->expectException(ModelNotFoundException::class);

        $this->service->getOrFailByUuid('invalid-uuid');
    }

    // --- Escritura ---

    public function test_create_wraps_in_transaction(): void
    {
        $attributes = ['name' => 'Test'];
        $model = $this->createMockModel($attributes);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockRepo->shouldReceive('create')
            ->once()
            ->with($attributes)
            ->andReturn($model);

        $result = $this->service->create($attributes);

        $this->assertSame($model, $result);
    }

    public function test_create_many_wraps_in_transaction(): void
    {
        $rows = [['name' => 'Test 1'], ['name' => 'Test 2']];
        $collection = new Collection([
            $this->createMockModel(['name' => 'Test 1']),
            $this->createMockModel(['name' => 'Test 2']),
        ]);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockRepo->shouldReceive('createMany')
            ->once()
            ->with($rows)
            ->andReturn($collection);

        $result = $this->service->createMany($rows);

        $this->assertSame($collection, $result);
    }

    public function test_update_calls_repository_update_method(): void
    {
        $id = 1;
        $attributes = ['name' => 'Updated'];
        $model = $this->createMockModel($attributes);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockRepo->shouldReceive('findOrFailById')
            ->once()
            ->with($id)
            ->andReturn($model);

        $this->mockRepo->shouldReceive('update')
            ->once()
            ->with($model, $attributes)
            ->andReturn($model);

        $result = $this->service->update($id, $attributes);

        $this->assertSame($model, $result);
    }

    public function test_upsert_wraps_in_transaction(): void
    {
        $rows = [['id' => 1, 'name' => 'Test']];
        $uniqueBy = ['id'];
        $updateColumns = ['name'];
        $affectedRows = 1;

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockRepo->shouldReceive('upsert')
            ->once()
            ->with($rows, $uniqueBy, $updateColumns)
            ->andReturn($affectedRows);

        $result = $this->service->upsert($rows, $uniqueBy, $updateColumns);

        $this->assertSame($affectedRows, $result);
    }

    // --- Borrado / restauración ---

    public function test_delete_delegates_to_repo(): void
    {
        $id = 1;

        $this->mockRepo->shouldReceive('delete')
            ->once()
            ->with($id)
            ->andReturn(true);

        $result = $this->service->delete($id);

        $this->assertTrue($result);
    }

    public function test_force_delete_delegates_to_repo(): void
    {
        $id = 1;

        $this->mockRepo->shouldReceive('forceDelete')
            ->once()
            ->with($id)
            ->andReturn(true);

        $result = $this->service->forceDelete($id);

        $this->assertTrue($result);
    }

    public function test_restore_delegates_to_repo(): void
    {
        $id = 1;

        $this->mockRepo->shouldReceive('restore')
            ->once()
            ->with($id)
            ->andReturn(true);

        $result = $this->service->restore($id);

        $this->assertTrue($result);
    }

    // --- Estado común ---

    public function test_set_active_wraps_in_transaction(): void
    {
        $id = 1;
        $active = true;
        $model = $this->createMockModel(['id' => $id, 'active' => $active]);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockRepo->shouldReceive('setActive')
            ->once()
            ->with($id, $active)
            ->andReturn($model);

        $result = $this->service->setActive($id, $active);

        $this->assertSame($model, $result);
    }

    // --- Operaciones MASIVAS ---

    public function test_bulk_delete_by_ids_delegates_to_repo(): void
    {
        $ids = [1, 2, 3];
        $affectedRows = 3;

        $this->mockRepo->shouldReceive('bulkDeleteByIds')
            ->once()
            ->with($ids)
            ->andReturn($affectedRows);

        $result = $this->service->bulkDeleteByIds($ids);

        $this->assertSame($affectedRows, $result);
    }

    public function test_bulk_force_delete_by_ids_delegates_to_repo(): void
    {
        $ids = [1, 2];
        $affectedRows = 2;

        $this->mockRepo->shouldReceive('bulkForceDeleteByIds')
            ->once()
            ->with($ids)
            ->andReturn($affectedRows);

        $result = $this->service->bulkForceDeleteByIds($ids);

        $this->assertSame($affectedRows, $result);
    }

    public function test_bulk_restore_by_ids_delegates_to_repo(): void
    {
        $ids = [1, 2, 3];
        $affectedRows = 3;

        $this->mockRepo->shouldReceive('bulkRestoreByIds')
            ->once()
            ->with($ids)
            ->andReturn($affectedRows);

        $result = $this->service->bulkRestoreByIds($ids);

        $this->assertSame($affectedRows, $result);
    }

    public function test_bulk_set_active_by_ids_delegates_to_repo(): void
    {
        $ids = [1, 2, 3];
        $active = false;
        $affectedRows = 3;

        $this->mockRepo->shouldReceive('bulkSetActiveByIds')
            ->once()
            ->with($ids, $active)
            ->andReturn($affectedRows);

        $result = $this->service->bulkSetActiveByIds($ids, $active);

        $this->assertSame($affectedRows, $result);
    }

    public function test_bulk_operations_by_uuids_delegate_to_repo(): void
    {
        $uuids = ['uuid1', 'uuid2', 'uuid3'];
        $affectedRows = 3;

        // Test all UUID bulk operations
        $this->mockRepo->shouldReceive('bulkDeleteByUuids')->once()->with($uuids)->andReturn($affectedRows);
        $this->mockRepo->shouldReceive('bulkForceDeleteByUuids')->once()->with($uuids)->andReturn($affectedRows);
        $this->mockRepo->shouldReceive('bulkRestoreByUuids')->once()->with($uuids)->andReturn($affectedRows);
        $this->mockRepo->shouldReceive('bulkSetActiveByUuids')->once()->with($uuids, true)->andReturn($affectedRows);

        $this->assertSame($affectedRows, $this->service->bulkDeleteByUuids($uuids));
        $this->assertSame($affectedRows, $this->service->bulkForceDeleteByUuids($uuids));
        $this->assertSame($affectedRows, $this->service->bulkRestoreByUuids($uuids));
        $this->assertSame($affectedRows, $this->service->bulkSetActiveByUuids($uuids, true));
    }

    // --- Concurrencia / Transacciones ---

    public function test_transaction_calls_db_transaction(): void
    {
        $result = 'test-result';
        $callback = fn () => $result;

        DB::shouldReceive('transaction')
            ->once()
            ->with($callback)
            ->andReturn($result);

        $actualResult = $this->service->transaction($callback);

        $this->assertSame($result, $actualResult);
    }

    public function test_transaction_rolls_back_on_exception(): void
    {
        $exception = new \Exception('Test exception');
        $callback = function () use ($exception) {
            throw $exception;
        };

        DB::shouldReceive('transaction')
            ->once()
            ->with($callback)
            ->andThrow($exception);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        $this->service->transaction($callback);
    }

    public function test_with_pessimistic_lock_by_id_delegates_to_repo(): void
    {
        $id = 1;
        $result = 'locked-result';
        $callback = fn () => $result;

        $this->mockRepo->shouldReceive('withPessimisticLockById')
            ->once()
            ->with($id, $callback)
            ->andReturn($result);

        $actualResult = $this->service->withPessimisticLockById($id, $callback);

        $this->assertSame($result, $actualResult);
    }

    public function test_with_pessimistic_lock_by_uuid_delegates_to_repo(): void
    {
        $uuid = 'test-uuid';
        $result = 'locked-result';
        $callback = fn () => $result;

        $this->mockRepo->shouldReceive('withPessimisticLockByUuid')
            ->once()
            ->with($uuid, $callback)
            ->andReturn($result);

        $actualResult = $this->service->withPessimisticLockByUuid($uuid, $callback);

        $this->assertSame($result, $actualResult);
    }

    // --- Export ---

    public function test_export_resolves_exporter_and_sets_headers(): void
    {
        $query = new ListQuery(page: 1, perPage: 10);
        $format = 'csv';

        // Create real response to avoid header initialization issues
        $response = new StreamedResponse(function () {}, 200, []);

        $this->mockContainer->shouldReceive('get')
            ->once()
            ->with('exporter.csv')
            ->andReturn($this->mockExporter);

        $this->mockExporter->shouldReceive('stream')
            ->once()
            ->with(Mockery::type('Generator'), ['id', 'created_at', 'updated_at'])
            ->andReturnUsing(function ($generator) use ($response) {
                // Consume the generator to trigger repo calls
                iterator_to_array($generator);

                return $response;
            });

        // Mock paginate for exportRows
        $model = $this->createMockModel(['id' => 1, 'created_at' => '2024-01-01', 'updated_at' => '2024-01-01']);
        $paginator = new LengthAwarePaginator([$model], 1, 1000, 1, ['path' => 'test']);
        $this->mockRepo->shouldReceive('paginate')->once()->andReturn($paginator);

        $result = $this->service->export($query, $format);

        $this->assertInstanceOf(StreamedResponse::class, $result);
        $this->assertStringContainsString('attachment', $result->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.csv', $result->headers->get('Content-Disposition'));
    }

    public function test_export_uses_custom_columns_and_filename(): void
    {
        $query = new ListQuery(page: 1);
        $format = 'xlsx';
        $columns = ['id', 'name'];
        $filename = 'custom_export.xlsx';

        // Create real response
        $response = new StreamedResponse(function () {}, 200, []);

        $this->mockContainer->shouldReceive('get')
            ->once()
            ->with('exporter.xlsx')
            ->andReturn($this->mockExporter);

        $this->mockExporter->shouldReceive('stream')
            ->once()
            ->with(Mockery::type('Generator'), $columns)
            ->andReturnUsing(function ($generator) use ($response) {
                // Consume the generator to trigger repo calls
                iterator_to_array($generator);

                return $response;
            });

        // Mock paginate for exportRows
        $model = $this->createMockModel(['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']);
        $paginator = new LengthAwarePaginator([$model], 1, 1000, 1, ['path' => 'test']);
        $this->mockRepo->shouldReceive('paginate')->once()->andReturn($paginator);

        $result = $this->service->export($query, $format, $columns, $filename);

        $this->assertInstanceOf(StreamedResponse::class, $result);
        $this->assertStringContainsString('attachment; filename="'.$filename.'"', $result->headers->get('Content-Disposition'));
    }

    public function test_export_rows_generator_handles_pagination(): void
    {
        $query = new ListQuery(page: 1);

        // Mock two pages of results
        $model1 = $this->createMockModel(['id' => 1, 'name' => 'Test 1']);
        $model2 = $this->createMockModel(['id' => 2, 'name' => 'Test 2']);

        $paginator1 = new LengthAwarePaginator([$model1], 2, 1, 1, ['path' => 'test']);
        $paginator1->withPath('test')->appends([]);

        $paginator2 = new LengthAwarePaginator([$model2], 2, 1, 2, ['path' => 'test']);
        $paginator2->withPath('test')->appends([]);

        $this->mockRepo->shouldReceive('paginate')
            ->twice()
            ->andReturn($paginator1, $paginator2);

        // Test the exportRows generator directly
        $columns = ['id', 'name'];
        $generator = $this->service->testExportRows($query, $columns);

        $rows = iterator_to_array($generator);

        $this->assertCount(2, $rows);
        $this->assertEquals(['id' => 1, 'name' => 'Test 1'], $rows[0]);
        $this->assertEquals(['id' => 2, 'name' => 'Test 2'], $rows[1]);
    }

    // --- Hook Tests ---

    public function test_to_row_hook_can_be_overridden(): void
    {
        $service = new TestableBaseServiceWithCustomToRow($this->mockRepo, $this->mockContainer);
        $model = $this->createMockModel(['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']);

        $paginator = new LengthAwarePaginator([$model], 1, 10, 1, ['path' => 'test']);

        $this->mockRepo->shouldReceive('paginate')
            ->once()
            ->andReturn($paginator);

        $result = $service->list(new ListQuery(page: 1));

        // Should only include id and name due to custom toRow implementation
        $this->assertEquals([['id' => 1, 'name' => 'Test']], $result['rows']);
    }

    private function createMockModel(array $attributes): MockInterface
    {
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('attributesToArray')
            ->andReturn($attributes);

        return $model;
    }
}

// Test implementations
class TestableBaseService extends BaseService
{
    protected function repoModelClass(): string
    {
        return 'TestModel';
    }

    protected function exportPageSize(): int
    {
        return 1; // Small page size for testing
    }

    public function testExportRows(\App\DTO\ListQuery $query, array $columns): \Generator
    {
        return $this->exportRows($query, $columns);
    }
}

class TestableBaseServiceWithCustomToRow extends TestableBaseService
{
    protected function toRow(Model $model): array
    {
        $attributes = $model->attributesToArray();

        return [
            'id' => $attributes['id'],
            'name' => $attributes['name'],
        ];
    }
}
