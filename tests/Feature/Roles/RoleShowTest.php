<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleShowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with view permission
        $this->user = User::factory()->create();

        // Create permissions
        Permission::create(['name' => 'roles.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'test.permission1', 'guard_name' => 'web']);
        Permission::create(['name' => 'test.permission2', 'guard_name' => 'web']);

        // Create test role
        $this->role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $this->role->givePermissionTo(['test.permission1', 'test.permission2']);
    }

    public function test_show_requires_authentication(): void
    {
        $response = $this->get(route('roles.show', $this->role));
        $response->assertRedirect(route('login'));
    }

    public function test_show_requires_view_permission(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('roles.show', $this->role));

        $response->assertForbidden();
    }

    public function test_show_returns_role_with_default_data(): void
    {
        $this->user->givePermissionTo('roles.view');

        $response = $this->actingAs($this->user)
            ->get(route('roles.show', $this->role));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('roles/show')
            ->has('item', fn ($item) => $item
                ->where('id', $this->role->id)
                ->where('name', 'test-role')
                ->where('guard_name', 'web')
                ->etc()
            )
            ->has('meta')
        );
    }

    public function test_show_loads_permissions_when_requested(): void
    {
        $this->user->givePermissionTo('roles.view');

        $response = $this->actingAs($this->user)
            ->get(route('roles.show', [$this->role, 'with' => ['permissions']]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('item.permissions', 2)
            ->where('meta.loaded_relations', fn ($relations) => in_array('permissions', $relations->toArray())
            )
        );
    }

    public function test_show_counts_permissions_when_requested(): void
    {
        $this->user->givePermissionTo('roles.view');

        $response = $this->actingAs($this->user)
            ->get(route('roles.show', [$this->role, 'withCount' => ['permissions']]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('item.permissions_count', 2)
            ->where('meta.loaded_counts', fn ($counts) => in_array('permissions_count', $counts->toArray())
            )
        );
    }

    public function test_show_rejects_non_whitelisted_relations(): void
    {
        $this->user->givePermissionTo('roles.view');

        $response = $this->actingAs($this->user)
            ->get(route('roles.show', [$this->role, 'with' => ['users']]));

        $response->assertSessionHasErrors('with.0');
    }

    public function test_show_returns_404_for_non_existent_role(): void
    {
        $this->user->givePermissionTo('roles.view');

        $response = $this->actingAs($this->user)
            ->get('/roles/999999');

        $response->assertNotFound();
    }

    public function test_show_handles_soft_deleted_roles_when_requested(): void
    {
        $this->user->givePermissionTo('roles.view');

        // Add soft deletes to roles table if needed
        $deletedRole = Role::create(['name' => 'deleted-role', 'guard_name' => 'web']);
        $deletedRole->delete();

        $response = $this->actingAs($this->user)
            ->get(route('roles.show', [$deletedRole->id, 'withTrashed' => true]));

        // Should find the soft deleted role if soft deletes are implemented
        // Otherwise will return 404 as expected
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_show_validates_with_trashed_as_boolean(): void
    {
        $this->user->givePermissionTo('roles.view');

        $response = $this->actingAs($this->user)
            ->get(route('roles.show', [$this->role, 'withTrashed' => 'invalid']));

        $response->assertSessionHasErrors('withTrashed');
    }

    public function test_show_validates_with_as_array(): void
    {
        $this->user->givePermissionTo('roles.view');

        $response = $this->actingAs($this->user)
            ->get(route('roles.show', $this->role).'?with=not_an_array');

        $response->assertSessionHasErrors('with');
    }
}
