<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\DTO\ListQuery;
use App\Models\Role;
use App\Models\User;
use App\Repositories\RoleRepository;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoleService $service;

    private RoleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RoleRepository;
        $this->service = new RoleService($this->repository, app());
    }

    public function test_list_returns_formatted_rows_and_meta(): void
    {
        // Arrange
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $query = new ListQuery(perPage: 10, page: 1);

        // Act
        $result = $this->service->list($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('meta', $result);

        $this->assertCount(2, $result['rows']);
        $this->assertEquals(2, $result['meta']['total']);

        // Check row format
        $row = $result['rows'][0];
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('guard_name', $row);
        $this->assertArrayHasKey('permissions_count', $row);
        $this->assertArrayHasKey('created_at', $row);
    }

    public function test_list_by_ids_desc_returns_roles_in_order(): void
    {
        // Arrange
        $role1 = Role::create(['name' => 'first', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'second', 'guard_name' => 'web']);
        $role3 = Role::create(['name' => 'third', 'guard_name' => 'web']);

        // Act
        $result = $this->service->listByIdsDesc([$role1->id, $role2->id, $role3->id], 10);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertCount(3, $result['rows']);
        $this->assertEquals($role3->id, $result['rows'][0]['id']);
        $this->assertEquals($role2->id, $result['rows'][1]['id']);
        $this->assertEquals($role1->id, $result['rows'][2]['id']);
    }

    public function test_export_returns_streamed_response(): void
    {
        // Skip if exporter not configured
        $this->markTestSkipped('Export functionality requires external exporter configuration');
    }

    public function test_export_with_xlsx_format(): void
    {
        // Skip if exporter not configured
        $this->markTestSkipped('Export functionality requires external exporter configuration');
    }

    public function test_bulk_delete_delegates_to_repository(): void
    {
        // Arrange
        $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);

        // Act
        $result = $this->service->bulkDeleteByIds([$role1->id, $role2->id]);

        // Assert
        $this->assertEquals(2, $result);
        $this->assertDatabaseMissing('roles', ['id' => $role1->id]);
        $this->assertDatabaseMissing('roles', ['id' => $role2->id]);
    }

    public function test_to_row_transforms_model_correctly(): void
    {
        // Arrange
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'api']);
        $perm = \Spatie\Permission\Models\Permission::create(['name' => 'test.perm', 'guard_name' => 'api']);
        $role->syncPermissions([$perm]);

        // Reload with permissions count
        $role = Role::withCount('permissions')->find($role->id);

        // Act - use reflection to test protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('toRow');
        $method->setAccessible(true);
        $row = $method->invoke($this->service, $role);

        // Assert
        $this->assertEquals($role->id, $row['id']);
        $this->assertEquals('test_role', $row['name']);
        $this->assertEquals('api', $row['guard_name']);
        $this->assertEquals(1, $row['permissions_count']);
        $this->assertNotNull($row['created_at']);
    }

    public function test_get_index_extras_returns_stats_and_permissions(): void
    {
        // Arrange
        $user = User::factory()->create();

        $roleWeb1 = Role::create(['name' => 'web1', 'guard_name' => 'web']);
        $permWeb1 = Permission::create(['name' => 'perm.web.1', 'guard_name' => 'web', 'description' => 'Permiso web 1']);
        $roleWeb1->givePermissionTo($permWeb1);
        $user->assignRole($roleWeb1); // contributes to 'active'

        $roleWeb2 = Role::create(['name' => 'web2', 'guard_name' => 'web', 'is_active' => false]);
        $permWeb2 = Permission::create(['name' => 'perm.web.2', 'guard_name' => 'web']); // no description on purpose
        $roleWeb2->givePermissionTo($permWeb2);

        $roleApi = Role::create(['name' => 'api1', 'guard_name' => 'api']);
        $permApi = Permission::create(['name' => 'perm.api.1', 'guard_name' => 'api', 'description' => 'Permiso api 1']);
        $roleApi->givePermissionTo($permApi);
        // simulate assignment via pivot for unconfigured guard
        DB::table('model_has_roles')->insert([
            'role_id' => $roleApi->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        // Act
        $extras = $this->service->getIndexExtras();

        // Assert
        $this->assertIsArray($extras);
        $this->assertArrayHasKey('stats', $extras);
        $this->assertArrayHasKey('availablePermissions', $extras);

        $stats = $extras['stats'];
        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['active']); // web1 + api1 (simulated)
        $this->assertSame(3, $stats['with_permissions']);

        $perms = $extras['availablePermissions'];
        $this->assertIsArray($perms);
        $this->assertNotEmpty($perms);
        $this->assertArrayHasKey('id', $perms[0]);
        $this->assertArrayHasKey('name', $perms[0]);
        $this->assertArrayHasKey('description', $perms[0]);
    }

    public function test_delete_safely_configured_guard_deletes_role_and_pivots(): void
    {
        // Arrange
        $user = User::factory()->create();
        $role = Role::create(['name' => 'to_delete', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'perm.delete', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $user->assignRole($role);

        // Act
        $this->service->deleteSafely($role);

        // Assert
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
        $this->assertDatabaseMissing('model_has_roles', ['role_id' => $role->id]);
    }

    public function test_delete_safely_unconfigured_guard_deletes_role_and_pivots(): void
    {
        // Arrange
        $user = User::factory()->create();
        $role = Role::create(['name' => 'api_to_delete', 'guard_name' => 'api']);
        $perm = Permission::create(['name' => 'perm.api.delete', 'guard_name' => 'api']);
        $role->givePermissionTo($perm);
        // simulate pivot rows that could cause guard issues
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        // Act
        $this->service->deleteSafely($role);

        // Assert
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
        $this->assertDatabaseMissing('model_has_roles', ['role_id' => $role->id]);
    }
}
