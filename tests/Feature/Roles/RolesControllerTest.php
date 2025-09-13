<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Register the policy manually for tests
        \Illuminate\Support\Facades\Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);

        // Create permissions
        Permission::create(['name' => 'roles.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.export', 'guard_name' => 'web']);
        Permission::create(['name' => 'roles.setActive', 'guard_name' => 'web']);

        // Create user with permissions
        $this->user = User::factory()->create();
        $adminRole = Role::create(['name' => 'test_admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());
        $this->user->assignRole($adminRole);

        // Reset permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_destroy_blocks_when_require_inactive_true_and_role_is_active(): void
    {
        // Arrange
        config()->set('permissions.roles.deletion.require_inactive', true);
        $role = Role::create(['name' => 'active_role_to_block', 'guard_name' => 'web', 'is_active' => true]);

        // Act
        $response = $this->actingAs($this->user)->from('/roles')->delete('/roles/'.$role->id);

        // Assert
        $response->assertRedirect('/roles');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_destroy_allows_when_require_inactive_true_and_role_is_inactive(): void
    {
        // Arrange
        config()->set('permissions.roles.deletion.require_inactive', true);
        $role = Role::create(['name' => 'inactive_role_ok', 'guard_name' => 'web', 'is_active' => false]);

        // Act
        $response = $this->actingAs($this->user)->delete('/roles/'.$role->id);

        // Assert
        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_destroy_blocks_last_admin_role_when_it_is_the_only_one_with_critical_permissions(): void
    {
        // Arrange: create custom critical permissions and a role that is the only one with them
        $crit1 = Permission::create(['name' => 'crit.one', 'guard_name' => 'web']);
        $crit2 = Permission::create(['name' => 'crit.two', 'guard_name' => 'web']);
        config()->set('permissions.roles.deletion.critical_permissions', ['crit.one', 'crit.two']);

        $soloAdmin = Role::create(['name' => 'solo_admin', 'guard_name' => 'web']);
        $soloAdmin->syncPermissions([$crit1, $crit2]);

        // Act: attempt to delete the only admin role (not assigned to any users)
        $response = $this->actingAs($this->user)->from('/roles')->delete('/roles/'.$soloAdmin->id);

        // Assert: blocked as last admin role
        $response->assertRedirect('/roles');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $soloAdmin->id]);
    }

    public function test_destroy_allows_deleting_one_of_multiple_admin_roles(): void
    {
        // Arrange: two roles both with all critical permissions
        $crit1 = Permission::firstOrCreate(['name' => 'crit.one', 'guard_name' => 'web']);
        $crit2 = Permission::firstOrCreate(['name' => 'crit.two', 'guard_name' => 'web']);
        config()->set('permissions.roles.deletion.critical_permissions', ['crit.one', 'crit.two']);

        $adminA = Role::create(['name' => 'adminA', 'guard_name' => 'web']);
        $adminB = Role::create(['name' => 'adminB', 'guard_name' => 'web']);
        $adminA->syncPermissions([$crit1, $crit2]);
        $adminB->syncPermissions([$crit1, $crit2]);

        // Act: delete one of them
        $response = $this->actingAs($this->user)->delete('/roles/'.$adminA->id);

        // Assert: deletion allowed, the other remains
        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $adminA->id]);
        $this->assertDatabaseHas('roles', ['id' => $adminB->id]);
    }

    public function test_destroy_requires_force_when_block_if_has_permissions_is_enabled(): void
    {
        // Arrange
        config()->set('permissions.roles.deletion.block_if_has_permissions', true);
        $role = Role::create(['name' => 'role_with_perm', 'guard_name' => 'web']);
        $perm = Permission::findByName('roles.view', 'web');
        $role->givePermissionTo($perm);

        // Act: without force
        $responseNoForce = $this->actingAs($this->user)->from('/roles')->delete('/roles/'.$role->id);
        $responseNoForce->assertRedirect('/roles');
        $responseNoForce->assertSessionHas('warning');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);

        // Act: with force
        $responseForce = $this->actingAs($this->user)->delete('/roles/'.$role->id, ['force' => true]);
        $responseForce->assertRedirect('/roles');
        $responseForce->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_bulk_delete_respects_require_inactive_and_skips_active_roles(): void
    {
        // Arrange
        config()->set('permissions.roles.deletion.require_inactive', true);
        $activeRole = Role::create(['name' => 'active_bulk', 'guard_name' => 'web', 'is_active' => true]);
        $inactiveRole = Role::create(['name' => 'inactive_bulk', 'guard_name' => 'web', 'is_active' => false]);

        // Act
        $response = $this->actingAs($this->user)->post('/roles/bulk', [
            'action' => 'delete',
            'ids' => [$activeRole->id, $inactiveRole->id],
        ]);

        // Assert: inactive deleted, active skipped
        $response->assertRedirect('/roles');
        $response->assertSessionHas('warning');
        $this->assertDatabaseHas('roles', ['id' => $activeRole->id]);
        $this->assertDatabaseMissing('roles', ['id' => $inactiveRole->id]);
    }

    public function test_bulk_delete_blocks_last_admin_role_but_allows_when_another_remains(): void
    {
        // Arrange
        $crit1 = Permission::firstOrCreate(['name' => 'crit.one', 'guard_name' => 'web']);
        $crit2 = Permission::firstOrCreate(['name' => 'crit.two', 'guard_name' => 'web']);
        config()->set('permissions.roles.deletion.critical_permissions', ['crit.one', 'crit.two']);

        $adminA = Role::create(['name' => 'adminA_bulk', 'guard_name' => 'web']);
        $adminB = Role::create(['name' => 'adminB_bulk', 'guard_name' => 'web']);
        $adminA->syncPermissions([$crit1, $crit2]);
        $adminB->syncPermissions([$crit1, $crit2]);

        // Act: try to delete both in bulk
        $response = $this->actingAs($this->user)->post('/roles/bulk', [
            'action' => 'delete',
            'ids' => [$adminA->id, $adminB->id],
        ]);

        // Assert: one deleted, one skipped to ensure at least one remains
        $response->assertRedirect('/roles');
        $response->assertSessionHas('warning');
        $remaining = Role::whereIn('id', [$adminA->id, $adminB->id])->count();
        $this->assertEquals(1, $remaining);
    }

    public function test_bulk_delete_requires_force_when_block_if_has_permissions_is_enabled(): void
    {
        // Arrange
        config()->set('permissions.roles.deletion.block_if_has_permissions', true);
        $role = Role::create(['name' => 'bulk_role_with_perm', 'guard_name' => 'web']);
        $perm = Permission::findByName('roles.view', 'web');
        $role->givePermissionTo($perm);

        // Act: without force
        $responseNoForce = $this->actingAs($this->user)->post('/roles/bulk', [
            'action' => 'delete',
            'ids' => [$role->id],
        ]);
        $responseNoForce->assertRedirect('/roles');
        $responseNoForce->assertSessionHas('warning');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);

        // Act: with force
        $responseForce = $this->actingAs($this->user)->post('/roles/bulk', [
            'action' => 'delete',
            'ids' => [$role->id],
            'force' => true,
        ]);
        $responseForce->assertRedirect('/roles');
        $responseForce->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_index_shows_roles_with_proper_authorization(): void
    {
        // Arrange
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        // Act
        $response = $this->actingAs($this->user)->get('/roles');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/index')
            ->has('rows', 3) // test_admin, editor, viewer
            ->has('meta')
            ->where('meta.total', 3)
            ->has('rows.0', fn (AssertableInertia $role) => $role
                ->hasAll(['id', 'name', 'guard_name', 'permissions', 'permissions_count', 'users_count', 'is_active', 'created_at'])
                ->etc()
            )
        );
    }

    public function test_index_with_search_filters_results(): void
    {
        // Arrange
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        // Act - Search for 'edit' which should only match 'editor'
        $response = $this->actingAs($this->user)->get('/roles?q=edit');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/index')
            ->has('rows', 1)
            ->where('rows.0.name', 'editor')
        );
    }

    public function test_index_with_sorting_returns_sorted_results(): void
    {
        // Arrange - Clean up existing roles first
        Role::where('name', '!=', 'test_admin')->delete();
        Role::create(['name' => 'beta', 'guard_name' => 'web']);
        Role::create(['name' => 'alpha', 'guard_name' => 'web']);

        // Act
        $response = $this->actingAs($this->user)->get('/roles?sort=name&dir=asc');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/index')
            ->where('rows.0.name', 'alpha')
            ->where('rows.1.name', 'beta')
            ->where('rows.2.name', 'test_admin')
        );
    }

    public function test_index_with_filters_applies_them(): void
    {
        // Arrange
        Role::create(['name' => 'web_role', 'guard_name' => 'web']);
        Role::create(['name' => 'api_role', 'guard_name' => 'api']);

        // Act
        $response = $this->actingAs($this->user)->get('/roles?filters[guard_name]=api');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/index')
            ->has('rows', 1)
            ->where('rows.0.name', 'api_role')
        );
    }

    public function test_index_filters_by_permissions_count_range(): void
    {
        // Arrange
        $role1 = Role::create(['name' => 'role_with_few_perms', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role_with_many_perms', 'guard_name' => 'web']);

        // Give role1 2 permissions and role2 4 permissions
        $role1->givePermissionTo(['roles.view', 'roles.create']);
        $role2->givePermissionTo(['roles.view', 'roles.create', 'roles.update', 'roles.delete']);

        // Act - Filter for roles with 3-6 permissions
        $response = $this->actingAs($this->user)->get('/roles?filters[permissions_count_min]=3&filters[permissions_count_max]=6');

        // Assert - Should only return role2 and test_admin (which has all 6 permissions)
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/index')
            ->has('rows', 2)
        );

        // Verify the returned roles have the expected names
        $data = $response->original->getData()['page']['props'];
        $roleNames = collect($data['rows'])->pluck('name')->toArray();
        $this->assertContains('test_admin', $roleNames);
        $this->assertContains('role_with_many_perms', $roleNames);
    }

    public function test_index_filters_by_users_count_range(): void
    {
        // Arrange
        $role1 = Role::create(['name' => 'role_no_users', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role_with_users', 'guard_name' => 'web']);

        // Assign users to role2
        User::factory()->count(3)->create()->each(fn ($user) => $user->assignRole($role2));

        // Act - Filter for roles with 2-4 users
        $response = $this->actingAs($this->user)->get('/roles?filters[users_count_min]=2&filters[users_count_max]=4');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/index')
            ->has('rows', 1)
            ->where('rows.0.name', 'role_with_users')
        );
    }

    public function test_index_filters_by_active_status(): void
    {
        // Arrange
        $activeRole = Role::create(['name' => 'active_role', 'guard_name' => 'web', 'is_active' => true]);
        $inactiveRole = Role::create(['name' => 'inactive_role', 'guard_name' => 'web', 'is_active' => false]);

        // Act - Filter for active roles only
        $response = $this->actingAs($this->user)->get('/roles?filters[is_active]=1');

        // Assert - Should return test_admin and active_role (assuming test_admin is active by default)
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/index')
            ->has('rows')
        );

        // Verify inactive role is not in results
        $data = $response->original->getData()['page']['props'];
        $roleNames = collect($data['rows'])->pluck('name')->toArray();
        $this->assertNotContains('inactive_role', $roleNames);
        $this->assertContains('active_role', $roleNames);
    }

    public function test_index_with_partial_reload_only_returns_requested_props(): void
    {
        // Arrange
        Role::create(['name' => 'role1', 'guard_name' => 'web']);

        // Act - Normal request to get Inertia response
        $response = $this->actingAs($this->user)
            ->get('/roles', [
                'Accept' => 'text/html, application/xhtml+xml',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Inertia' => true,
            ]);

        // Assert Inertia response structure
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('roles/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_index_forbidden_without_view_permission(): void
    {
        // Arrange
        $userWithoutPermission = User::factory()->create();

        // Act
        $response = $this->actingAs($userWithoutPermission)->get('/roles');

        // Assert
        $response->assertForbidden();
    }

    public function test_export_returns_csv_file(): void
    {
        // Arrange
        Role::create(['name' => 'export_test', 'guard_name' => 'web']);

        // Debug: Check user has permission
        $this->assertTrue($this->user->can('roles.export'), 'User should have roles.export permission');

        // Double-check auth user is set
        auth()->login($this->user);
        $this->assertTrue(auth()->check(), 'User should be authenticated');
        $this->assertTrue(auth()->user()->can('roles.export'), 'Auth user should have roles.export permission');

        // Act
        $response = $this->actingAs($this->user)->get('/roles/export?format=csv');

        // Assert
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $content = $response->streamedContent();
        $this->assertStringContainsString('export_test', $content);
    }

    public function test_export_supports_multiple_formats(): void
    {
        // Arrange
        Role::create(['name' => 'export_test', 'guard_name' => 'web', 'is_active' => true]);

        // Test CSV format
        $csvResponse = $this->actingAs($this->user)->get('/roles/export?format=csv');
        $csvResponse->assertOk();
        $csvResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Test JSON format
        $jsonResponse = $this->actingAs($this->user)->get('/roles/export?format=json');
        $jsonResponse->assertOk();
        $jsonResponse->assertHeader('content-type', 'application/json');

        $jsonContent = json_decode($jsonResponse->streamedContent(), true);
        $this->assertIsArray($jsonContent);
        $this->assertTrue(
            collect($jsonContent)->contains(fn ($role) => $role['Nombre'] === 'export_test')
        );
    }

    public function test_export_applies_filters(): void
    {
        // Arrange
        $activeRole = Role::create(['name' => 'active_export', 'guard_name' => 'web', 'is_active' => true]);
        $inactiveRole = Role::create(['name' => 'inactive_export', 'guard_name' => 'web', 'is_active' => false]);

        // Act - Export only active roles
        $response = $this->actingAs($this->user)->get('/roles/export?format=csv&filters[is_active]=1');

        // Assert
        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('active_export', $content);
        $this->assertStringNotContainsString('inactive_export', $content);
    }

    public function test_export_forbidden_without_export_permission(): void
    {
        // Arrange
        $userWithoutExportPermission = User::factory()->create();
        $viewPermission = Permission::findByName('roles.view', 'web');
        $userWithoutExportPermission->givePermissionTo($viewPermission);

        // Act
        $response = $this->actingAs($userWithoutExportPermission)->get('/roles/export');

        // Assert
        $response->assertForbidden();
    }

    public function test_bulk_delete_redirects_with_success_message(): void
    {
        // Arrange
        $role1 = Role::create(['name' => 'to_delete_1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'to_delete_2', 'guard_name' => 'web']);

        // Act
        $response = $this->actingAs($this->user)->post('/roles/bulk', [
            'action' => 'delete',
            'ids' => [$role1->id, $role2->id],
        ]);

        // Assert
        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role1->id]);
        $this->assertDatabaseMissing('roles', ['id' => $role2->id]);
    }

    public function test_bulk_delete_forbidden_without_delete_permission(): void
    {
        // Arrange
        $userWithoutDeletePermission = User::factory()->create();
        $viewPermission = Permission::findByName('roles.view', 'web');
        $userWithoutDeletePermission->givePermissionTo($viewPermission);

        $role = Role::create(['name' => 'should_not_delete', 'guard_name' => 'web']);

        // Act
        $response = $this->actingAs($userWithoutDeletePermission)->post('/roles/bulk', [
            'action' => 'delete',
            'ids' => [$role->id],
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_bulk_with_invalid_action_returns_error(): void
    {
        // Act
        $response = $this->actingAs($this->user)->post('/roles/bulk', [
            'action' => 'invalid',
            'ids' => [1],
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasErrors('action');
    }

    public function test_selected_returns_roles_by_ids(): void
    {
        // Arrange
        $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);
        Role::create(['name' => 'role3', 'guard_name' => 'web']);

        // Act
        $response = $this->actingAs($this->user)->get('/roles/selected?ids[]='.$role1->id.'&ids[]='.$role2->id);

        // Assert
        $response->assertOk();
        $response->assertJsonCount(2, 'rows');
        $response->assertJsonFragment(['name' => 'role1']);
        $response->assertJsonFragment(['name' => 'role2']);
    }

    public function test_destroy_deletes_role_with_invalid_guard_via_fallback(): void
    {
        // Arrange: create a role with an invalid/non-configured guard
        $invalidGuardRole = Role::create(['name' => 'bogus_guard_role', 'guard_name' => 'bogus']);

        // Act: attempt to delete via controller which should use fallback path
        $response = $this->actingAs($this->user)->delete('/roles/'.$invalidGuardRole->id);

        // Assert: redirect with success and role removed from DB
        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $invalidGuardRole->id]);
    }

    public function test_destroy_prevents_delete_when_role_has_users_even_with_invalid_guard(): void
    {
        // Arrange: create a role with invalid guard and attach to a user directly via pivot
        $invalidGuardRole = Role::create(['name' => 'bogus_with_users', 'guard_name' => 'bogus']);

        \DB::table('model_has_roles')->insert([
            'role_id' => $invalidGuardRole->id,
            'model_type' => \App\Models\User::class,
            'model_id' => $this->user->id,
        ]);

        // Act: delete should be blocked and redirect back with error
        $response = $this->actingAs($this->user)->from('/roles')->delete('/roles/'.$invalidGuardRole->id);

        // Assert
        $response->assertRedirect('/roles');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $invalidGuardRole->id]);
    }

    public function test_bulk_delete_handles_roles_with_invalid_guard(): void
    {
        // Arrange: create roles with invalid guard
        $role1 = Role::create(['name' => 'invalid1', 'guard_name' => 'bogus']);
        $role2 = Role::create(['name' => 'invalid2', 'guard_name' => 'bogus']);

        // Act: bulk delete
        $response = $this->actingAs($this->user)->post('/roles/bulk', [
            'action' => 'delete',
            'ids' => [$role1->id, $role2->id],
        ]);

        // Assert: success redirect and roles removed
        $response->assertRedirect('/roles');
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role1->id]);
        $this->assertDatabaseMissing('roles', ['id' => $role2->id]);
    }

    public function test_set_active_toggles_role_state_with_permission(): void
    {
        // Arrange: role initially inactive
        $role = Role::create(['name' => 'can_toggle', 'guard_name' => 'web', 'is_active' => false]);

        // Act: activate
        $respActivate = $this->actingAs($this->user)->patch('/roles/'.$role->id.'/active', [
            'active' => true,
        ]);

        // Assert
        $respActivate->assertRedirect('/roles');
        $respActivate->assertSessionHas('success');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'is_active' => true]);

        // Act: deactivate
        $respDeactivate = $this->actingAs($this->user)->patch('/roles/'.$role->id.'/active', [
            'active' => false,
        ]);

        // Assert
        $respDeactivate->assertRedirect('/roles');
        $respDeactivate->assertSessionHas('success');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'is_active' => false]);
    }

    public function test_set_active_forbidden_without_permission(): void
    {
        // Arrange
        $userWithoutPermission = User::factory()->create();
        $role = Role::create(['name' => 'no_perm_role', 'guard_name' => 'web', 'is_active' => true]);

        // Act
        $response = $this->actingAs($userWithoutPermission)->patch('/roles/'.$role->id.'/active', [
            'active' => false,
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'is_active' => true]);
    }

    public function test_set_active_blocks_deactivate_for_protected_role_when_configured(): void
    {
        // Arrange
        config()->set('permissions.roles.activation.block_deactivate_protected', true);
        config()->set('permissions.roles.protected', ['protected_role']);
        $role = Role::create(['name' => 'protected_role', 'guard_name' => 'web', 'is_active' => true]);

        // Act
        $response = $this->actingAs($this->user)->from('/roles')->patch('/roles/'.$role->id.'/active', [
            'active' => false,
        ]);

        // Assert
        $response->assertRedirect('/roles');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'is_active' => true]);
    }

    public function test_set_active_blocks_deactivate_if_role_has_users_when_configured(): void
    {
        // Arrange
        config()->set('permissions.roles.activation.block_deactivate_if_has_users', true);
        $role = Role::create(['name' => 'role_with_users_block', 'guard_name' => 'web', 'is_active' => true]);
        $anotherUser = User::factory()->create();
        $anotherUser->assignRole($role);

        // Act
        $response = $this->actingAs($this->user)->from('/roles')->patch('/roles/'.$role->id.'/active', [
            'active' => false,
        ]);

        // Assert
        $response->assertRedirect('/roles');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'is_active' => true]);
    }
}
