<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Contracts\Services\ServiceInterface;
use App\DTO\ListQuery;
use App\Http\Controllers\Concerns\HandlesIndexAndExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\BaseIndexRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Test controller class that uses the HandlesIndexAndExport trait.
 */
class TestController extends Controller
{
    use AuthorizesRequests;
    use HandlesIndexAndExport;

    public function __construct(protected ServiceInterface $service) {}

    protected function policyModel(): string
    {
        return Role::class;
    }

    protected function view(): string
    {
        return 'Test/Index';
    }

    protected function with(): array
    {
        return ['relation1'];
    }

    protected function withCount(): array
    {
        return ['relation2_count'];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'pdf'];
    }

    protected function indexRouteName(): string
    {
        return 'test.index';
    }

    protected function indexRequestClass(): string
    {
        return TestIndexRequest::class;
    }

    protected function exportPermission(): string
    {
        // Override to use roles.export permission for consistency with test setup
        return 'roles.export';
    }

    // Override helper methods to use direct URLs for tests
    protected function ok(string $routeName, array $params = [], ?string $message = null): \Illuminate\Http\RedirectResponse
    {
        $redirect = redirect('/test-index');

        if ($message !== null) {
            $redirect->with('success', $message);
        }

        return $redirect;
    }

    protected function fail(string $routeName, array $params = [], ?string $message = null): \Illuminate\Http\RedirectResponse
    {
        $redirect = redirect('/test-index');

        if ($message !== null) {
            $redirect->with('error', $message);
        }

        return $redirect;
    }
}

/**
 * Test IndexRequest class.
 */
class TestIndexRequest extends BaseIndexRequest
{
    protected function allowedSorts(): array
    {
        return ['id', 'name', 'created_at'];
    }
}

/**
 * Tests de integración para HandlesIndexAndExport trait.
 *
 * Cubre funcionalidad de index, export, bulk y selected con:
 * - Autorización via Policies
 * - Integración con Services
 * - Respuestas optimizadas para Inertia partial reloads
 * - Validación de formatos y payloads
 * - Casos de error (403 Forbidden)
 */
class HandlesIndexAndExportTest extends TestCase
{
    use RefreshDatabase;

    private ServiceInterface $mockService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create all necessary permissions for tests
        $this->createTestPermissions();

        $this->user = User::factory()->create();
        $this->mockService = Mockery::mock(ServiceInterface::class);

        // Set up default successful mock responses (but not for export)
        $this->mockService->shouldReceive('list')
            ->andReturn(['rows' => [], 'meta' => ['total' => 0]])
            ->byDefault();

        $this->mockService->shouldReceive('listByIdsDesc')
            ->andReturn(['rows' => [], 'meta' => ['total' => 0]])
            ->byDefault();

        $this->mockService->shouldReceive('bulkDeleteByIds', 'bulkDeleteByUuids',
            'bulkRestoreByIds', 'bulkRestoreByUuids',
            'bulkForceDeleteByIds', 'bulkForceDeleteByUuids',
            'bulkSetActiveByIds', 'bulkSetActiveByUuids')
            ->andReturn(0)
            ->byDefault();

        $this->setupTestRoutes();
        $this->setupTestPolicy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_with_authorized_user_calls_service_and_returns_inertia_with_only_rows_meta(): void
    {
        // Allow policy for this test
        $this->allowPolicy('viewAny');

        $expectedResult = [
            'rows' => [
                ['id' => 1, 'name' => 'Test Role 1'],
                ['id' => 2, 'name' => 'Test Role 2'],
            ],
            'meta' => [
                'current_page' => 2,
                'per_page' => 15,
                'total' => 50,
                'last_page' => 4,
            ],
        ];

        $this->mockService->shouldReceive('list')
            ->once()
            ->with(
                Mockery::type(ListQuery::class),
                ['relation1'],  // with()
                ['relation2_count']  // withCount()
            )
            ->andReturn($expectedResult);

        $response = $this->actingAs($this->user)->get('/test-index?'.http_build_query([
            'q' => 'test search',
            'page' => 2,
            'sort' => 'name',
            'dir' => 'asc',
            'filters' => ['active' => true],
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Test/Index')
            ->has('rows', 2)
            ->where('rows.0.id', 1)
            ->where('rows.1.name', 'Test Role 2')
            ->has('meta')
            ->where('meta.current_page', 2)
            ->where('meta.total', 50)
            // Verificar que las props principales están presentes
            ->hasAll(['rows', 'meta'])
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_forbidden_when_policy_denies_view_any(): void
    {
        $this->denyPolicy('viewAny');

        $response = $this->actingAs($this->user)->get('/test-index');

        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function export_with_authorized_user_calls_service_export_method(): void
    {
        // Allow policies for this test
        $this->allowPolicy('viewAny');
        $this->allowPolicy('export');

        $streamedResponse = new StreamedResponse(
            function () {
                echo 'csv,content';
            },
            200,
            ['Content-Disposition' => 'attachment; filename="export.csv"']
        );

        // Mock que acepta cualquier formato válido
        $this->mockService->shouldReceive('export')
            ->once()
            ->with(Mockery::type(ListQuery::class), Mockery::anyOf('csv', 'xlsx', 'pdf'))
            ->andReturn($streamedResponse);

        $response = $this->actingAs($this->user)->get('/test-export?'.http_build_query([
            'q' => 'export search',
            'filters' => ['status' => 'active'],
            'format' => 'xlsx',
        ]));

        $response->assertStatus(200);
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function export_defaults_to_csv_when_no_format_specified(): void
    {
        // Allow policies for this test
        $this->allowPolicy('viewAny');
        $this->allowPolicy('export');

        $this->mockService->shouldReceive('export')
            ->once()
            ->with(Mockery::type(ListQuery::class), 'csv')  // Debe usar CSV como fallback
            ->andReturn(new StreamedResponse(function () {
                echo 'test';
            }, 200));

        $response = $this->actingAs($this->user)->get('/test-export');

        $response->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function export_forbidden_when_policy_denies_export(): void
    {
        $this->denyPolicy('export');

        $response = $this->actingAs($this->user)->get('/test-export');

        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bulk_delete_validates_payload_and_calls_service_methods(): void
    {
        $this->allowPolicy('update');

        $this->mockService->shouldReceive('bulkDeleteByIds')
            ->once()
            ->with([1, 2, 3])
            ->andReturn(3);

        $this->mockService->shouldReceive('bulkDeleteByUuids')
            ->once()
            ->with([])
            ->andReturn(0);

        $response = $this->actingAs($this->user)->post('/test-bulk', [
            'action' => 'delete',
            'ids' => [1, 2, 3],
        ]);

        $response->assertRedirect('/test-index');
        $response->assertSessionHas('success', '3 registro(s) eliminados exitosamente');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bulk_set_active_validates_payload_and_calls_service_methods(): void
    {
        $this->allowPolicy('update');

        $this->mockService->shouldReceive('bulkSetActiveByIds')
            ->once()
            ->with([4, 5], true)
            ->andReturn(2);

        $this->mockService->shouldReceive('bulkSetActiveByUuids')
            ->once()
            ->with([], true)
            ->andReturn(0);

        $response = $this->actingAs($this->user)->post('/test-bulk', [
            'action' => 'setActive',
            'ids' => [4, 5],
            'active' => true,
        ]);

        $response->assertRedirect('/test-index');
        $response->assertSessionHas('success', '2 registro(s) activados exitosamente');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bulk_validates_action_and_rejects_invalid_actions(): void
    {
        $this->allowPolicy('update');

        $response = $this->actingAs($this->user)->post('/test-bulk', [
            'action' => 'invalidAction',
            'ids' => [1, 2],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['action']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bulk_forbidden_when_policy_denies_update(): void
    {
        $this->denyPolicy('update');

        $response = $this->actingAs($this->user)->post('/test-bulk', [
            'action' => 'delete',
            'ids' => [1, 2],
        ]);

        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bulk_delete_redirects_with_success_message(): void
    {
        // Allow policies for this test
        $this->allowPolicy('viewAny');
        $this->allowPolicy('update');

        $this->mockService->shouldReceive('bulkDeleteByIds')
            ->once()
            ->with([1, 2, 3])
            ->andReturn(3);

        $this->mockService->shouldReceive('bulkDeleteByUuids')
            ->once()
            ->with([])
            ->andReturn(0);

        $response = $this->actingAs($this->user)->post('/test-bulk', [
            'action' => 'delete',
            'ids' => [1, 2, 3],
        ]);

        $response->assertRedirect('/test-index');
        $response->assertSessionHas('success', '3 registro(s) eliminados exitosamente');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bulk_set_active_with_custom_action(): void
    {
        // Allow policies for this test
        $this->allowPolicy('viewAny');
        $this->allowPolicy('update');

        $this->mockService->shouldReceive('bulkSetActiveByIds')
            ->once()
            ->with([4, 5], true)
            ->andReturn(2);

        $this->mockService->shouldReceive('bulkSetActiveByUuids')
            ->once()
            ->with([], true)
            ->andReturn(0);

        $response = $this->actingAs($this->user)->post('/test-bulk', [
            'action' => 'setActive',
            'ids' => [4, 5],
            'active' => true,
        ]);

        $response->assertRedirect('/test-index');
        $response->assertSessionHas('success', '2 registro(s) activados exitosamente');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function selected_returns_roles_by_ids(): void
    {
        // Allow policies for this test
        $this->allowPolicy('viewSelected');

        $expectedResult = [
            'rows' => [
                ['id' => 3, 'name' => 'Selected Role 3'],
                ['id' => 1, 'name' => 'Selected Role 1'],
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => 25,
                'total' => 2,
            ],
        ];

        $this->mockService->shouldReceive('listByIdsDesc')
            ->once()
            ->with(
                [1, 3],  // IDs
                25,      // perPage
                ['relation1'],  // with()
                ['relation2_count']  // withCount()
            )
            ->andReturn($expectedResult);

        $response = $this->actingAs($this->user)->get('/test-selected?'.http_build_query([
            'ids' => [1, 3],
            'perPage' => 25,
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'rows' => [
                ['id' => 3, 'name' => 'Selected Role 3'],
                ['id' => 1, 'name' => 'Selected Role 1'],
            ],
            'total' => 2,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function selected_returns_empty_when_missing_ids(): void
    {
        // Allow policies for this test
        $this->allowPolicy('viewAny');

        // Test should redirect with validation error instead
        $response = $this->actingAs($this->user)->get('/test-selected');

        // Laravel redirects validation errors to previous page in web routes
        // In a real app, this would be handled by frontend validation
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['ids']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function selected_requires_ids_array(): void
    {
        $this->allowPolicy('viewAny');

        $response = $this->actingAs($this->user)->get('/test-selected');

        // Laravel redirects validation errors to previous page in web routes
        // In a real app, this would be handled by frontend validation
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['ids']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function selected_forbidden_when_policy_denies_view_any(): void
    {
        $this->denyPolicy('viewAny');

        $response = $this->actingAs($this->user)->get('/test-selected?ids[]=1&ids[]=2');

        $response->assertForbidden();
    }

    /**
     * Configura las rutas de prueba usando el trait.
     */
    private function setupTestRoutes(): void
    {
        // Bind the service in the container for the test controller
        $this->app->instance(ServiceInterface::class, $this->mockService);

        // Bind TestIndexRequest to resolve BaseIndexRequest dependency
        $this->app->bind(BaseIndexRequest::class, TestIndexRequest::class);

        // Register test controller class
        $this->app->bind('TestController', function ($app) {
            return new TestController($app->make(ServiceInterface::class));
        });

        // Disable Inertia SSR and testing page existence check
        config(['inertia.testing.ensure_pages_exist' => false]);

        // Registrar rutas de prueba
        Route::get('/test-index', [TestController::class, 'index'])->name('test.index');
        Route::get('/test-export', [TestController::class, 'export'])->name('test.export');
        Route::post('/test-bulk', [TestController::class, 'bulk'])->name('test.bulk');
        Route::get('/test-selected', [TestController::class, 'selected'])->name('test.selected');
    }

    /**
     * Configura una policy de prueba para autorización.
     */
    private function setupTestPolicy(): void
    {
        Gate::define('viewAny', fn () => $this->shouldAllow('viewAny'));
        Gate::define('export', fn () => $this->shouldAllow('export'));
        Gate::define('update', fn () => $this->shouldAllow('update'));
    }

    private array $allowedPolicies = [];

    private function allowPolicy(string $ability): void
    {
        $this->allowedPolicies[$ability] = true;

        // Map abilities to permissions and grant them to the user
        $permissionMap = [
            'viewAny' => 'roles.view',
            'export' => 'roles.export',
            'update' => ['roles.update', 'roles.delete', 'roles.restore', 'roles.force-delete', 'roles.setActive'],
            'viewSelected' => 'roles.view',  // Add mapping for viewSelected
        ];

        if (isset($permissionMap[$ability])) {
            $permissions = is_array($permissionMap[$ability]) ? $permissionMap[$ability] : [$permissionMap[$ability]];
            $this->user->givePermissionTo($permissions);
        }
    }

    private function denyPolicy(string $ability): void
    {
        $this->allowedPolicies[$ability] = false;

        // Map abilities to permissions and revoke them from the user
        $permissionMap = [
            'viewAny' => 'roles.view',
            'export' => 'roles.export',
            'update' => ['roles.update', 'roles.delete', 'roles.restore', 'roles.force-delete', 'roles.setActive'],
            'viewSelected' => 'roles.view',  // Add mapping for viewSelected
        ];

        if (isset($permissionMap[$ability])) {
            $permissions = is_array($permissionMap[$ability]) ? $permissionMap[$ability] : [$permissionMap[$ability]];
            $this->user->revokePermissionTo($permissions);
        }
    }

    private function shouldAllow(string $ability): bool
    {
        return $this->allowedPolicies[$ability] ?? false;
    }

    /**
     * Create test permissions for Spatie Laravel Permission
     */
    private function createTestPermissions(): void
    {
        $permissions = [
            'roles.view',
            'roles.export',
            'roles.update',
            'roles.delete',
            'roles.restore',
            'roles.force-delete',
            'roles.setActive',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
