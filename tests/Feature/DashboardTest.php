<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());
        // Ensure permission exists and grant it
        Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']);
        $user->givePermissionTo('dashboard.view');

        $this->get('/dashboard')->assertOk();
    }

    public function test_forbidden_without_permission()
    {
        $this->actingAs(User::factory()->create());
        $this->get('/dashboard')->assertForbidden();
    }
}
