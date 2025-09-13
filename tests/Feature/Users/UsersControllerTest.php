<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class UsersControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions used by users module
        $perms = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.export',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Create admin with all perms
        $this->admin = User::factory()->create();
        $role = SpatieRole::create(['name' => 'test_admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());
        $this->admin->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_index_shows_users_with_authorization(): void
    {
        // Arrange
        User::factory()->count(2)->create();

        // Act
        $response = $this->actingAs($this->admin)->get('/users');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('users/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_index_forbidden_without_view_permission(): void
    {
        $user = User::factory()->create();

        $resp = $this->actingAs($user)->get('/users');
        $resp->assertForbidden();
    }

    public function test_export_returns_csv_when_authorized(): void
    {
        User::factory()->create(['name' => 'export_me']);

        $resp = $this->actingAs($this->admin)->get('/users/export?format=csv');
        $resp->assertOk();
        $resp->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $resp->assertHeader('content-disposition');
        $this->assertStringContainsString('export_me', $resp->streamedContent());
    }

    public function test_export_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        // Give only view permission
        $view = Permission::findByName('users.view', 'web');
        $user->givePermissionTo($view);

        $resp = $this->actingAs($user)->get('/users/export');
        $resp->assertForbidden();
    }
}
