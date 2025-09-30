<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardRankingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rankings_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/dashboard/rankings');

        $response->assertStatus(401);
    }

    public function test_rankings_endpoint_requires_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/rankings');

        $response->assertStatus(403);
    }

    public function test_rankings_endpoint_returns_contracts_data(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'dashboard.view.charts']);
        $user->givePermissionTo($permission);

        $response = $this->actingAs($user)->getJson('/api/dashboard/rankings?metric=contracts&order=top&limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'metric',
                'order',
                'items' => [
                    '*' => ['id', 'name', 'value'],
                ],
                'generated_at',
            ])
            ->assertJson([
                'metric' => 'contracts',
                'order' => 'top',
            ]);
    }

    public function test_rankings_endpoint_returns_m2_data(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'dashboard.view.charts']);
        $user->givePermissionTo($permission);

        $response = $this->actingAs($user)->getJson('/api/dashboard/rankings?metric=m2&order=top&limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'metric',
                'order',
                'items' => [
                    '*' => ['id', 'name', 'value'],
                ],
                'generated_at',
            ])
            ->assertJson([
                'metric' => 'm2',
                'order' => 'top',
            ]);
    }

    public function test_rankings_endpoint_respects_bottom_order(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'dashboard.view.charts']);
        $user->givePermissionTo($permission);

        $response = $this->actingAs($user)->getJson('/api/dashboard/rankings?metric=contracts&order=bottom&limit=5');

        $response->assertStatus(200)
            ->assertJson([
                'metric' => 'contracts',
                'order' => 'bottom',
            ]);
    }
}
