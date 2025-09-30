<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kpis_forbidden_without_permission(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/dashboard/kpis')->assertForbidden();
    }

    public function test_kpis_returns_expected_structure_with_permission(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'dashboard.view.cards', 'guard_name' => 'web']);
        $user->givePermissionTo('dashboard.view.cards');

        $response = $this->actingAs($user)->getJson('/api/dashboard/kpis');
        $response->assertOk();
        $response->assertJsonStructure([
            'users' => ['total'],
            'locals' => ['available'],
            'concessionaires' => ['active'],
            'contracts' => ['vigentes'],
            'generated_at',
        ]);
    }

    public function test_distribution_forbidden_without_permission(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/dashboard/locales-disponibles-distribucion?by=local_type_id')->assertForbidden();
    }

    public function test_distribution_returns_expected_structure_with_permission(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'dashboard.view.charts', 'guard_name' => 'web']);
        $user->givePermissionTo('dashboard.view.charts');

        $response = $this->actingAs($user)->getJson('/api/dashboard/locales-disponibles-distribucion?by=local_type_id');
        $response->assertOk();
        $response->assertJsonStructure([
            'by',
            'items',
            'total',
            'generated_at',
        ]);
    }
}
