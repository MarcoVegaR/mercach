<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $unauthorizedUser;

    private Role $testRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Register the policy manually for tests
        \Illuminate\Support\Facades\Gate::policy(Role::class, \App\Policies\RolePolicy::class);

        // Create permissions
        Permission::create(['name' => 'roles.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'test.permission.one', 'guard_name' => 'web']);
        Permission::create(['name' => 'test.permission.two', 'guard_name' => 'web']);
        Permission::create(['name' => 'test.permission.three', 'guard_name' => 'web']);

        // Create user with permissions
        $this->user = User::factory()->create();
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['roles.view', 'roles.create', 'roles.update', 'roles.delete']);
        $this->user->assignRole($adminRole);

        // Create unauthorized user
        $this->unauthorizedUser = User::factory()->create();

        // Create test role
        $this->testRole = Role::create(['name' => 'test_role', 'guard_name' => 'web', 'is_active' => true]);
        $this->testRole->syncPermissions(['test.permission.one']);

        // Reset permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_edit_form_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('roles.edit', $this->testRole));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/form', false) // Don't check if component file exists
            ->has('model', fn (AssertableInertia $page) => $page
                ->where('id', $this->testRole->id)
                ->where('name', $this->testRole->name)
                ->where('guard_name', $this->testRole->guard_name)
                ->where('is_active', true)
                ->has('permissions')
                ->has('updated_at')
                ->etc()
            )
            ->has('permissions')
            ->has('guards')
        );
    }

    public function test_edit_form_returns_403_without_permission(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('roles.edit', $this->testRole));

        $response->assertForbidden();
    }

    public function test_can_update_role_with_valid_data(): void
    {
        $permission = Permission::where('name', 'test.permission.two')->first();
        $version = $this->testRole->updated_at->toIso8601String();

        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'Updated Role Name',
                'guard_name' => 'web',
                'permissions_ids' => [$permission->id],
                'is_active' => false,
                '_version' => $version,
            ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');

        $this->testRole->refresh();
        $this->assertEquals('Updated Role Name', $this->testRole->name);
        $this->assertFalse((bool) $this->testRole->is_active);
        $this->assertFalse($this->testRole->hasPermissionTo('test.permission.one'));
        $this->assertTrue($this->testRole->hasPermissionTo('test.permission.two'));
        $this->assertFalse($this->testRole->hasPermissionTo('test.permission.three'));
    }

    public function test_update_returns_403_without_permission(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'Updated Name',
                'guard_name' => 'web',
            ]);

        $response->assertForbidden();
        $this->testRole->refresh();
        $this->assertEquals('test_role', $this->testRole->name);
    }

    public function test_update_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), []);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_update_validates_unique_role_name_excluding_self(): void
    {
        $otherRole = Role::create(['name' => 'other_role', 'guard_name' => 'web']);

        // Should fail when using another role's name
        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'other_role',
                'guard_name' => 'web',
            ]);

        $response->assertSessionHasErrors(['name']);

        // Should succeed when keeping the same name
        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'test_role',
                'guard_name' => 'web',
                '_version' => $this->testRole->updated_at->toIso8601String(),
            ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');
    }

    public function test_update_with_optimistic_locking_prevents_concurrent_updates(): void
    {
        // Get initial version
        $initialVersion = $this->testRole->updated_at->toIso8601String();

        // Simulate another update happening (changing the updated_at)
        // Use raw SQL to ensure the timestamp changes
        \DB::table('roles')
            ->where('id', $this->testRole->id)
            ->update(['updated_at' => now()->addSecond()]);

        // Try to update with old version
        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'Updated Name',
                'guard_name' => 'web',
                '_version' => $initialVersion, // Old version
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionHas('error', 'El registro ha sido modificado por otro usuario. Por favor, recarga la página e intenta nuevamente.');

        $this->testRole->refresh();
        $this->assertEquals('test_role', $this->testRole->name); // Name should not have changed
    }

    public function test_update_without_version_still_works(): void
    {
        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'Updated Without Version',
                'guard_name' => 'web',
                // No _version provided
            ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');

        $this->testRole->refresh();
        $this->assertEquals('Updated Without Version', $this->testRole->name);
    }

    public function test_update_removes_all_permissions_when_empty_array_provided(): void
    {
        // Ensure role has permissions initially
        $this->assertTrue($this->testRole->hasPermissionTo('test.permission.one'));

        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'test_role',
                'guard_name' => 'web',
                'permissions_ids' => [], // Empty array
                '_version' => $this->testRole->updated_at->toIso8601String(),
            ]);

        $response->assertRedirect(route('roles.index'));

        $this->testRole->refresh();
        $this->assertEquals(0, $this->testRole->permissions->count());
    }

    public function test_update_preserves_permissions_when_not_provided(): void
    {
        // Ensure role has permissions initially
        $this->assertTrue($this->testRole->hasPermissionTo('test.permission.one'));
        $initialPermissionCount = $this->testRole->permissions->count();

        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'Updated Name',
                'guard_name' => 'web',
                // permissions_ids not provided
                '_version' => $this->testRole->updated_at->toIso8601String(),
            ]);

        $response->assertRedirect(route('roles.index'));

        $this->testRole->refresh();
        $this->assertEquals($initialPermissionCount, $this->testRole->permissions->count());
        $this->assertTrue($this->testRole->hasPermissionTo('test.permission.one'));
    }

    public function test_update_normalizes_input_data(): void
    {
        $permission = Permission::where('name', 'test.permission.two')->first();

        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => '  Updated Role  ', // Should be trimmed
                'guard_name' => 'web',
                'permissions_ids' => (string) $permission->id, // String should be converted to array
                'is_active' => '0', // String should be converted to boolean
                '_version' => $this->testRole->updated_at->toIso8601String(),
            ]);

        $response->assertRedirect(route('roles.index'));

        $this->testRole->refresh();
        $this->assertEquals('Updated Role', $this->testRole->name);
        $this->assertFalse((bool) $this->testRole->is_active);
        $this->assertTrue($this->testRole->hasPermissionTo('test.permission.two'));
    }

    public function test_update_validates_permissions_exist(): void
    {
        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'test_role',
                'guard_name' => 'web',
                'permissions_ids' => [999999], // Non-existent permission ID
            ]);

        $response->assertSessionHasErrors(['permissions_ids.0']);
    }

    public function test_update_preserves_guard_name_when_not_provided(): void
    {
        $response = $this->actingAs($this->user)
            ->put(route('roles.update', $this->testRole), [
                'name' => 'Updated Name',
                // guard_name not provided (should be preserved from existing model)
                '_version' => $this->testRole->updated_at->toIso8601String(),
            ]);

        $response->assertRedirect(route('roles.index'));

        $this->testRole->refresh();
        $this->assertEquals('Updated Name', $this->testRole->name);
        $this->assertEquals('web', $this->testRole->guard_name); // Should preserve original
    }
}
