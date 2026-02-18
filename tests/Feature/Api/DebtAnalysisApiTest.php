<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DebtAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoints_require_authentication(): void
    {
        $endpoints = [
            '/api/debt-analysis/delinquent-concessionaires',
            '/api/debt-analysis/delinquent-locals',
            '/api/debt-analysis/solvent-concessionaires',
            '/api/debt-analysis/distributions',
            '/api/debt-analysis/export?scope=concessionaires&format=csv',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertStatus(401);
        }
    }

    public function test_endpoints_require_dashboard_finance_permission(): void
    {
        $user = User::factory()->create();

        $endpoints = [
            '/api/debt-analysis/delinquent-concessionaires',
            '/api/debt-analysis/delinquent-locals',
            '/api/debt-analysis/solvent-concessionaires',
            '/api/debt-analysis/distributions',
            '/api/debt-analysis/export?scope=concessionaires&format=csv',
        ];

        foreach ($endpoints as $endpoint) {
            $this->actingAs($user)->getJson($endpoint)->assertForbidden();
        }
    }

    public function test_endpoints_return_expected_structure_with_permission(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'dashboard.view.finance', 'guard_name' => 'web']);
        $user->givePermissionTo('dashboard.view.finance');

        $this->actingAs($user)
            ->getJson('/api/debt-analysis/delinquent-concessionaires')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'summary',
                'generated_at',
            ]);

        $this->actingAs($user)
            ->getJson('/api/debt-analysis/delinquent-locals')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'summary',
                'generated_at',
            ]);

        $this->actingAs($user)
            ->getJson('/api/debt-analysis/solvent-concessionaires')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'generated_at',
            ]);

        $this->actingAs($user)
            ->getJson('/api/debt-analysis/distributions?force=1')
            ->assertOk()
            ->assertJsonStructure([
                'by_aging',
                'by_market',
                'by_local_type',
                'by_local_type_bs',
                'fx_rate',
                'generated_at',
            ]);
    }

    public function test_delinquent_locals_validates_and_accepts_min_days_filter(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'dashboard.view.finance', 'guard_name' => 'web']);
        $user->givePermissionTo('dashboard.view.finance');

        $this->actingAs($user)
            ->getJson('/api/debt-analysis/delinquent-locals?min_days=0')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/debt-analysis/delinquent-locals?min_days=-1')
            ->assertStatus(422);
    }

    public function test_export_returns_csv_with_permission(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'dashboard.view.finance', 'guard_name' => 'web']);
        $user->givePermissionTo('dashboard.view.finance');

        $this->actingAs($user)
            ->get('/api/debt-analysis/export?scope=concessionaires&format=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
