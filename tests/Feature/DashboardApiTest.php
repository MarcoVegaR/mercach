<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_kpis_available_locals_uses_no_active_vig_contract_rule(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'dashboard.view.cards', 'guard_name' => 'web']);
        $user->givePermissionTo('dashboard.view.cards');

        $market = Market::create([
            'code' => 'M-TEST',
            'name' => 'Mercado Test',
            'address' => 'Av. Principal',
            'is_active' => true,
        ]);

        $localType = LocalType::create([
            'code' => 'LT-TEST',
            'name' => 'Tipo Test',
            'is_active' => true,
        ]);

        $localLocation = LocalLocation::create([
            'code' => 'LOC-TEST',
            'name' => 'Ubicación Test',
            'is_active' => true,
        ]);

        $disp = LocalStatus::create([
            'code' => 'DISP',
            'name' => 'Disponible',
            'is_active' => true,
        ]);

        $ocup = LocalStatus::create([
            'code' => 'OCUP',
            'name' => 'Ocupado',
            'is_active' => true,
        ]);

        // Disponible por regla canónica (sin contrato VIG activo).
        Local::create([
            'code' => 'A-01',
            'name' => 'Local Disponible',
            'market_id' => $market->id,
            'local_type_id' => $localType->id,
            'local_status_id' => $disp->id,
            'local_location_id' => $localLocation->id,
            'area_m2' => 10,
            'is_active' => true,
        ]);

        // También debe contar aunque el status catálogo no sea DISP.
        Local::create([
            'code' => 'B-01',
            'name' => 'Local Ocupado sin contrato',
            'market_id' => $market->id,
            'local_type_id' => $localType->id,
            'local_status_id' => $ocup->id,
            'local_location_id' => $localLocation->id,
            'area_m2' => 12,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/kpis');

        $response->assertOk()
            ->assertJsonPath('locals.available', 2);
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

    public function test_distribution_total_uses_no_active_vig_contract_rule(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'dashboard.view.charts', 'guard_name' => 'web']);
        $user->givePermissionTo('dashboard.view.charts');

        $market = Market::create([
            'code' => 'M-DIST',
            'name' => 'Mercado Dist',
            'address' => 'Av. Distribucion',
            'is_active' => true,
        ]);

        $localType = LocalType::create([
            'code' => 'LT-DIST',
            'name' => 'Tipo Dist',
            'is_active' => true,
        ]);

        $localLocation = LocalLocation::create([
            'code' => 'LOC-DIST',
            'name' => 'Ubicación Dist',
            'is_active' => true,
        ]);

        $disp = LocalStatus::create([
            'code' => 'DISP',
            'name' => 'Disponible',
            'is_active' => true,
        ]);

        $ocup = LocalStatus::create([
            'code' => 'OCUP',
            'name' => 'Ocupado',
            'is_active' => true,
        ]);

        Local::create([
            'code' => 'D-01',
            'name' => 'Disponible 01',
            'market_id' => $market->id,
            'local_type_id' => $localType->id,
            'local_status_id' => $disp->id,
            'local_location_id' => $localLocation->id,
            'area_m2' => 9,
            'is_active' => true,
        ]);

        Local::create([
            'code' => 'D-02',
            'name' => 'Ocupado 02',
            'market_id' => $market->id,
            'local_type_id' => $localType->id,
            'local_status_id' => $ocup->id,
            'local_location_id' => $localLocation->id,
            'area_m2' => 11,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/locales-disponibles-distribucion?by=local_type_id');

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('status_disp_id', $disp->id);

        $items = collect($response->json('items'));
        $typeRow = $items->firstWhere('id', $localType->id);

        $this->assertNotNull($typeRow);
        $this->assertSame(2, (int) ($typeRow['value'] ?? 0));
    }
}
