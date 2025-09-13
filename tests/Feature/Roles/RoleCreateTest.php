<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $unauthorizedUser;

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

        // Create user with permissions
        $this->user = User::factory()->create();
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['roles.view', 'roles.create', 'roles.update', 'roles.delete']);
        $this->user->assignRole($adminRole);

        // Create unauthorized user
        $this->unauthorizedUser = User::factory()->create();

        // Reset permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_create_form_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('roles.create'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/form', false) // Don't check if component file exists
            ->has('model', fn (AssertableInertia $page) => $page
                ->missing('id')
                ->where('name', null)
                ->where('guard_name', null)
                ->where('is_active', null)
                ->etc()
            )
            ->has('permissions')
            ->has('guards')
        );
    }

    public function test_create_form_returns_403_without_permission(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('roles.create'));

        $response->assertForbidden();
    }

    public function test_can_store_role_with_valid_data(): void
    {
        $permissions = Permission::whereIn('name', ['test.permission.one', 'test.permission.two'])->get();

        $response = $this->actingAs($this->user)
            ->post(route('roles.store'), [
                'name' => 'Test Role',
                'guard_name' => 'web',
                'permissions_ids' => $permissions->pluck('id')->toArray(),
                'is_active' => true,
            ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('roles', [
            'name' => 'Test Role',
            'guard_name' => 'web',
            'is_active' => true,
        ]);

        $role = Role::where('name', 'Test Role')->first();
        $this->assertTrue($role->hasPermissionTo('test.permission.one'));
        $this->assertTrue($role->hasPermissionTo('test.permission.two'));
    }

    public function test_store_returns_403_without_permission(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->post(route('roles.store'), [
                'name' => 'Test Role',
                'guard_name' => 'web',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Test Role']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('roles.store'), []);

        $response->assertSessionHasErrors(['name']); // guard_name gets default value
    }

    public function test_store_validates_unique_role_name(): void
    {
        Role::create(['name' => 'Existing Role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->user)
            ->post(route('roles.store'), [
                'name' => 'Existing Role',
                'guard_name' => 'web',
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_store_validates_permissions_exist(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('roles.store'), [
                'name' => 'Test Role',
                'guard_name' => 'web',
                'permissions_ids' => [999999], // Non-existent permission ID
            ]);

        $response->assertSessionHasErrors(['permissions_ids.0']);
    }

    public function test_store_normalizes_string_permissions_ids_to_array(): void
    {
        $permission = Permission::where('name', 'test.permission.one')->first();

        $response = $this->actingAs($this->user)
            ->post(route('roles.store'), [
                'name' => 'Test Role',
                'guard_name' => 'web',
                'permissions_ids' => (string) $permission->id, // String instead of array
                'is_active' => '1', // String instead of boolean
            ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');

        $role = Role::where('name', 'Test Role')->first();
        $this->assertTrue($role->hasPermissionTo('test.permission.one'));
        $this->assertTrue((bool) $role->is_active);
    }

    public function test_store_sets_default_is_active_to_true(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('roles.store'), [
                'name' => 'Test Role',
                'guard_name' => 'web',
                // is_active not provided
            ]);

        $response->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Test Role')->first();
        $this->assertTrue((bool) $role->is_active);
    }

    public function test_store_sets_default_guard_name_to_web(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('roles.store'), [
                'name' => 'Test Role',
                // guard_name not provided (but should be set in prepareForValidation)
            ]);

        $response->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Test Role')->first();
        $this->assertEquals('web', $role->guard_name);
    }

    public function test_store_trims_whitespace_from_name(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('roles.store'), [
                'name' => '  Test Role  ',
                'guard_name' => 'web',
            ]);

        $response->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('roles', [
            'name' => 'Test Role', // Trimmed
            'guard_name' => 'web',
        ]);
    }
}
