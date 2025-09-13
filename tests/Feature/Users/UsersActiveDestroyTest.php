<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class UsersActiveDestroyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions for users module
        $perms = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.restore',
            'users.forceDelete',
            'users.export',
            'users.setActive',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Create acting admin with all permissions
        $this->admin = User::factory()->create(['is_active' => true]);
        $role = SpatieRole::create(['name' => 'test_admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());
        $this->admin->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_set_active_toggles_user_state_with_permission(): void
    {
        $target = User::factory()->create(['is_active' => false]);

        // Activate
        $respActivate = $this->actingAs($this->admin)->patch('/users/'.$target->id.'/active', [
            'active' => true,
        ]);
        $respActivate->assertRedirect('/users');
        $respActivate->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);

        // Deactivate
        $respDeactivate = $this->actingAs($this->admin)->patch('/users/'.$target->id.'/active', [
            'active' => false,
        ]);
        $respDeactivate->assertRedirect('/users');
        $respDeactivate->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_set_active_forbidden_without_permission(): void
    {
        $userWithoutPermission = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($userWithoutPermission)->patch('/users/'.$target->id.'/active', [
            'active' => false,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
    }

    public function test_set_active_blocks_self_deactivate_when_configured(): void
    {
        config()->set('permissions.users.activation.block_self_deactivate', true);

        $response = $this->actingAs($this->admin)->from('/users')->patch('/users/'.$this->admin->id.'/active', [
            'active' => false,
        ]);

        $response->assertRedirect('/users');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'is_active' => true]);
    }

    public function test_set_active_blocks_last_admin_when_configured(): void
    {
        config()->set('permissions.users.activation.block_deactivate_if_last_admin', true);
        $adminRoleName = config('permissions.users.activation.admin_role_name', 'admin');

        // Create a target user who is the only one with the admin role
        $target = User::factory()->create(['is_active' => true]);
        $adminRole = SpatieRole::firstOrCreate(['name' => $adminRoleName, 'guard_name' => 'web']);
        $target->assignRole($adminRole);

        $response = $this->actingAs($this->admin)->from('/users')->patch('/users/'.$target->id.'/active', [
            'active' => false,
        ]);

        $response->assertRedirect('/users');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
    }

    public function test_destroy_blocks_self_delete(): void
    {
        $response = $this->actingAs($this->admin)->from('/users')->delete('/users/'.$this->admin->id);

        $response->assertRedirect('/users');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'deleted_at' => null]);
    }

    public function test_destroy_requires_inactive_when_flag_true(): void
    {
        config()->set('permissions.users.deletion.require_inactive', true);
        $target = User::factory()->create(['is_active' => true]);

        // Attempt delete while active
        $respActive = $this->actingAs($this->admin)->from('/users')->delete('/users/'.$target->id);
        $respActive->assertRedirect('/users');
        $respActive->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);

        // Deactivate and try again
        $target->update(['is_active' => false]);
        $respInactive = $this->actingAs($this->admin)->delete('/users/'.$target->id);
        $respInactive->assertRedirect('/users');
        $respInactive->assertSessionHas('success');
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_destroy_blocks_last_admin_when_flag_true(): void
    {
        config()->set('permissions.users.deletion.block_if_last_admin', true);
        $adminRoleName = config('permissions.users.activation.admin_role_name', 'admin');

        $target = User::factory()->create(['is_active' => false]);
        $adminRole = SpatieRole::firstOrCreate(['name' => $adminRoleName, 'guard_name' => 'web']);
        $target->assignRole($adminRole);

        $response = $this->actingAs($this->admin)->from('/users')->delete('/users/'.$target->id);

        $response->assertRedirect('/users');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);
    }
}
