<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SetRoleActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->seed(\Database\Seeders\RolesTestSeeder::class);

        // Ensure default behavior in tests: blocking deactivation of protected roles
        // and roles with users, and the protected list includes 'admin'.
        config([
            'permissions.roles.activation.block_deactivate_protected' => true,
            'permissions.roles.activation.block_deactivate_if_has_users' => true,
            'permissions.roles.protected' => ['admin'],
        ]);
    }

    public function test_can_activate_inactive_role(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'test-role',
            'guard_name' => 'web',
            'is_active' => false,
        ]);

        // Act
        $response = $this->patchWithCsrf("/roles/{$role->id}/active", [
            'active' => true,
        ]);

        // Assert
        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $role->refresh();
        $this->assertTrue($role->is_active);
    }

    public function test_cannot_deactivate_protected_role(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');
        $this->actingAs($user);

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ], [
            'is_active' => true,
        ]);

        // Act
        $response = $this->patchWithCsrf("/roles/{$adminRole->id}/active", [
            'active' => false,
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasErrors(['role']);
        $response->assertSessionHas('error', 'No se puede desactivar un rol protegido del sistema.');

        $adminRole->refresh();
        $this->assertTrue($adminRole->is_active); // Should remain active
    }

    public function test_cannot_deactivate_role_with_assigned_users(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'role-with-users',
            'guard_name' => 'web',
            'is_active' => true,
        ]);

        // Assign a user to this role
        $targetUser = User::factory()->create();
        $targetUser->assignRole($role);

        // Act
        $response = $this->patchWithCsrf("/roles/{$role->id}/active", [
            'active' => false,
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasErrors(['role']);
        $response->assertSessionHas('error', 'No se puede desactivar un rol que tiene usuarios asignados.');

        $role->refresh();
        $this->assertTrue($role->is_active); // Should remain active
    }

    public function test_can_deactivate_unprotected_role_without_users(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'regular-role',
            'guard_name' => 'web',
            'is_active' => true,
        ]);

        // Act
        $response = $this->patchWithCsrf("/roles/{$role->id}/active", [
            'active' => false,
        ]);

        // Assert
        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $role->refresh();
        $this->assertFalse($role->is_active);
    }

    public function test_requires_permission_to_set_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        // User doesn't have 'roles.setActive' permission
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'test-role',
            'guard_name' => 'web',
            'is_active' => true,
        ]);

        // Act
        $response = $this->patchWithCsrf("/roles/{$role->id}/active", [
            'active' => false,
        ]);

        // Assert
        $response->assertStatus(403);
    }

    public function test_validates_active_parameter(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'test-role',
            'guard_name' => 'web',
            'is_active' => true,
        ]);

        // Act - Missing 'active' parameter
        $response = $this->patchWithCsrf("/roles/{$role->id}/active", []);

        // Assert
        $response->assertSessionHasErrors(['active']);
    }

    public function test_validates_active_parameter_is_boolean(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'test-role',
            'guard_name' => 'web',
            'is_active' => true,
        ]);

        // Act - Invalid 'active' parameter
        $response = $this->patchWithCsrf("/roles/{$role->id}/active", [
            'active' => 'invalid',
        ]);

        // Assert
        $response->assertSessionHasErrors(['active']);
    }

    public function test_role_not_found_returns_404(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');
        $this->actingAs($user);

        // Act
        $response = $this->patchWithCsrf('/roles/999/active', [
            'active' => false,
        ]);

        // Assert
        $response->assertStatus(404);
    }

    public function test_config_controls_protection_validation(): void
    {
        // Arrange - Temporarily disable protection via config
        config(['permissions.roles.activation.block_deactivate_protected' => false]);

        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');
        $this->actingAs($user);

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ], [
            'is_active' => true,
        ]);

        // Act
        $response = $this->patchWithCsrf("/roles/{$adminRole->id}/active", [
            'active' => false,
        ]);

        // Assert - Should succeed when protection is disabled
        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $adminRole->refresh();
        $this->assertFalse($adminRole->is_active);
    }

    public function test_config_controls_users_validation(): void
    {
        // Arrange - Temporarily disable users validation via config
        config(['permissions.roles.activation.block_deactivate_if_has_users' => false]);

        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'role-with-users',
            'guard_name' => 'web',
            'is_active' => true,
        ]);

        // Assign a user to this role
        $targetUser = User::factory()->create();
        $targetUser->assignRole($role);

        // Act
        $response = $this->patchWithCsrf("/roles/{$role->id}/active", [
            'active' => false,
        ]);

        // Assert - Should succeed when users validation is disabled
        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $role->refresh();
        $this->assertFalse($role->is_active);
    }
}
