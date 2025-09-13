<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\DTO\ListQuery;
use App\Repositories\RoleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private RoleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RoleRepository;
    }

    public function test_paginate_returns_roles_with_default_sort(): void
    {
        // Arrange
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'api']);

        $query = new ListQuery;

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertCount(3, $result->items());
        $this->assertEquals(3, $result->total());
        // Should be sorted by id DESC by default
        $this->assertEquals('viewer', $result->items()[0]->name);
        $this->assertEquals('editor', $result->items()[1]->name);
        $this->assertEquals('admin', $result->items()[2]->name);
    }

    public function test_paginate_with_search_filters_by_name(): void
    {
        // Arrange
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $query = new ListQuery(q: 'edit');

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertCount(1, $result->items());
        $this->assertEquals('editor', $result->items()[0]->name);
    }

    public function test_paginate_with_guard_name_filter(): void
    {
        // Arrange
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'api_admin', 'guard_name' => 'api']);
        Role::create(['name' => 'api_user', 'guard_name' => 'api']);

        $query = new ListQuery(filters: ['guard_name' => 'api']);

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertCount(2, $result->items());
        foreach ($result->items() as $role) {
            $this->assertEquals('api', $role->guard_name);
        }
    }

    public function test_paginate_with_created_between_filter(): void
    {
        // Arrange
        Role::create(['name' => 'old_role', 'guard_name' => 'web', 'created_at' => now()->subDays(10)]);
        Role::create(['name' => 'recent_role', 'guard_name' => 'web', 'created_at' => now()->subDays(2)]);
        Role::create(['name' => 'new_role', 'guard_name' => 'web', 'created_at' => now()]);

        $query = new ListQuery(filters: [
            'created_between' => [
                'from' => now()->subDays(5)->format('Y-m-d'),
                'to' => now()->subDay()->format('Y-m-d'),
            ],
        ]);

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertCount(1, $result->items());
        $this->assertEquals('recent_role', $result->items()[0]->name);
    }

    public function test_paginate_with_permissions_count_filter(): void
    {
        // Arrange
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        // Create permissions and assign to roles
        $perm1 = \Spatie\Permission\Models\Permission::create(['name' => 'edit', 'guard_name' => 'web']);
        $perm2 = \Spatie\Permission\Models\Permission::create(['name' => 'delete', 'guard_name' => 'web']);

        $role1->syncPermissions([$perm1, $perm2]);
        $role2->syncPermissions([$perm1]);

        $query = new ListQuery(filters: ['permissions_count_min' => 2]);

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertCount(1, $result->items());
        $this->assertEquals('admin', $result->items()[0]->name);
        $this->assertEquals(2, $result->items()[0]->permissions_count);
    }

    public function test_paginate_with_sorting(): void
    {
        // Arrange
        Role::create(['name' => 'beta', 'guard_name' => 'web']);
        Role::create(['name' => 'alpha', 'guard_name' => 'web']);
        Role::create(['name' => 'gamma', 'guard_name' => 'web']);

        $query = new ListQuery(sort: 'name', dir: 'asc');

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertEquals('alpha', $result->items()[0]->name);
        $this->assertEquals('beta', $result->items()[1]->name);
        $this->assertEquals('gamma', $result->items()[2]->name);
    }

    public function test_paginate_includes_permissions_count(): void
    {
        // Arrange
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $perm = \Spatie\Permission\Models\Permission::create(['name' => 'test.perm', 'guard_name' => 'web']);
        $role->syncPermissions([$perm]);

        $query = new ListQuery;

        // Act
        $result = $this->repository->paginate($query);

        // Assert
        $this->assertNotNull($result->items()[0]->permissions_count);
        $this->assertEquals(1, $result->items()[0]->permissions_count);
    }

    public function test_find_returns_role_by_id(): void
    {
        // Arrange
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        // Act
        $found = $this->repository->findById($role->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals('admin', $found->name);
    }

    public function test_create_stores_new_role(): void
    {
        // Act
        $role = $this->repository->create([
            'name' => 'moderator',
            'guard_name' => 'web',
        ]);

        // Assert
        $this->assertDatabaseHas('roles', [
            'name' => 'moderator',
            'guard_name' => 'web',
        ]);
        $this->assertEquals('moderator', $role->name);
    }

    public function test_update_modifies_existing_role(): void
    {
        // Arrange
        $role = Role::create(['name' => 'old_name', 'guard_name' => 'web']);

        // Act
        $updated = $this->repository->update($role->id, ['name' => 'new_name']);

        // Assert
        $this->assertInstanceOf(Role::class, $updated);
        $this->assertEquals('new_name', $updated->name);
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'new_name',
        ]);
    }

    public function test_delete_removes_role(): void
    {
        // Arrange
        $role = Role::create(['name' => 'to_delete', 'guard_name' => 'web']);

        // Act
        $deleted = $this->repository->delete($role->id);

        // Assert
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_bulk_delete_removes_multiple_roles(): void
    {
        // Arrange
        $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);
        $role3 = Role::create(['name' => 'role3', 'guard_name' => 'web']);

        // Act
        $count = $this->repository->bulkDeleteByIds([$role1->id, $role2->id]);

        // Assert
        $this->assertEquals(2, $count);
        $this->assertDatabaseMissing('roles', ['id' => $role1->id]);
        $this->assertDatabaseMissing('roles', ['id' => $role2->id]);
        $this->assertDatabaseHas('roles', ['id' => $role3->id]);
    }
}
