<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\TradeCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocalControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->seed(PermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::where('name', 'admin')->first());
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_delete_blocked_when_has_contracts(): void
    {
        // Pre-reqs
        $disp = LocalStatus::create(['code' => 'DISP', 'name' => 'Disponible', 'is_active' => true]);
        $market = Market::create(['code' => 'M1', 'name' => 'Mercado 1', 'address' => 'Dir 1', 'is_active' => true]);
        $ltype = LocalType::create(['code' => 'LT1', 'name' => 'Tipo 1', 'is_active' => true]);
        $lloc = LocalLocation::create(['code' => 'LOC1', 'name' => 'Ubic 1', 'is_active' => true]);

        $local = Local::create([
            'code' => 'L1',
            'name' => 'Local 1',
            'market_id' => $market->id,
            'local_type_id' => $ltype->id,
            'local_status_id' => $disp->id,
            'local_location_id' => $lloc->id,
            'area_m2' => 10.0,
            'is_active' => true,
        ]);

        $type = ContractType::create(['code' => 'ARR', 'name' => 'Arriendo', 'is_active' => true]);
        $vig = ContractStatus::create(['code' => 'VIG', 'name' => 'Vigente', 'is_active' => true]);
        $mod = ContractModality::create(['code' => 'TFIJA', 'name' => 'Tasa fija', 'is_active' => true]);
        $cat = TradeCategory::create(['code' => 'RC1', 'name' => 'Rubro 1', 'description' => 'D', 'is_active' => true]);

        $contract = Contract::create([
            'number' => 'C-L1',
            'contract_type_id' => $type->id,
            'contract_status_id' => $vig->id,
            'contract_modality_id' => $mod->id,
            'trade_category_id' => $cat->id,
            'start_date' => '2025-01-01',
            'end_date' => null,
            'is_active' => true,
        ]);
        $contract->locals()->attach($local->id);

        $resp = $this->actingAs($this->user)->delete('/catalogs/local/'.$local->id);
        $resp->assertRedirect('/catalogs/local');
        $resp->assertSessionHas('error');
        $this->assertDatabaseHas('locals', ['id' => $local->id, 'deleted_at' => null]);
    }
}
