<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\CondoParticipant;
use App\Models\CondoPeriod;
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
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_index_filter_disp_excludes_locals_with_venc_contract(): void
    {
        $disp = LocalStatus::create(['code' => 'DISP', 'name' => 'Disponible', 'is_active' => true]);
        $market = Market::create(['code' => 'M3', 'name' => 'Mercado 3', 'address' => 'Dir 3', 'is_active' => true]);
        $ltype = LocalType::create(['code' => 'LT3', 'name' => 'Tipo 3', 'is_active' => true]);
        $lloc = LocalLocation::create(['code' => 'LOC3', 'name' => 'Ubic 3', 'is_active' => true]);

        $freeLocal = Local::create([
            'code' => 'L-DISP',
            'name' => 'Local disponible real',
            'market_id' => $market->id,
            'local_type_id' => $ltype->id,
            'local_status_id' => $disp->id,
            'local_location_id' => $lloc->id,
            'area_m2' => 11.0,
            'is_active' => true,
        ]);

        $occupiedLocal = Local::create([
            'code' => 'L-VENC',
            'name' => 'Local con vencido',
            'market_id' => $market->id,
            'local_type_id' => $ltype->id,
            'local_status_id' => $disp->id,
            'local_location_id' => $lloc->id,
            'area_m2' => 12.0,
            'is_active' => true,
        ]);

        $type = ContractType::create(['code' => 'ARR2', 'name' => 'Arriendo 2', 'is_active' => true]);
        $venc = ContractStatus::create(['code' => 'VENC', 'name' => 'Vencido', 'is_active' => true]);
        $mod = ContractModality::create(['code' => 'TFI2', 'name' => 'Tasa fija 2', 'is_active' => true]);
        $cat = TradeCategory::create(['code' => 'RC2', 'name' => 'Rubro 2', 'description' => 'D2', 'is_active' => true]);

        $contract = Contract::create([
            'number' => 'C-VENC-L1',
            'contract_type_id' => $type->id,
            'contract_status_id' => $venc->id,
            'contract_modality_id' => $mod->id,
            'trade_category_id' => $cat->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'is_active' => true,
        ]);
        $contract->locals()->attach($occupiedLocal->id);

        $resp = $this->actingAs($this->user)
            ->get('/catalogs/local?filters[local_status_id]='.$disp->id);

        $resp->assertOk();
        $resp->assertInertia(fn (Assert $page) => $page
            ->where('rows', function ($rows) use ($freeLocal, $occupiedLocal) {
                $codes = collect($rows)->pluck('code')->values();

                return $codes->contains($freeLocal->code)
                    && ! $codes->contains($occupiedLocal->code);
            })
        );
    }

    public function test_delete_blocked_when_participates_in_final_condo(): void
    {
        // Pre-reqs
        $disp = LocalStatus::create(['code' => 'DISP', 'name' => 'Disponible', 'is_active' => true]);
        $market = Market::create(['code' => 'M2', 'name' => 'Mercado 2', 'address' => 'Dir 2', 'is_active' => true]);
        $ltype = LocalType::create(['code' => 'LT2', 'name' => 'Tipo 2', 'is_active' => true]);
        $lloc = LocalLocation::create(['code' => 'LOC2', 'name' => 'Ubic 2', 'is_active' => true]);

        $local = Local::create([
            'code' => 'L2',
            'name' => 'Local 2',
            'market_id' => $market->id,
            'local_type_id' => $ltype->id,
            'local_status_id' => $disp->id,
            'local_location_id' => $lloc->id,
            'area_m2' => 12.5,
            'is_active' => true,
        ]);

        $period = CondoPeriod::create([
            'market_id' => $market->id,
            'period' => now()->startOfMonth()->format('Y-m-d'),
            'status' => 'FINAL',
            'is_active' => true,
        ]);
        CondoParticipant::create([
            'condo_period_id' => $period->id,
            'local_id' => $local->id,
            'area_m2_snapshot' => '12.50',
            'included' => true,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/local/'.$local->id);
        $resp->assertRedirect('/catalogs/local');
        $resp->assertSessionHas('error');
        $this->assertDatabaseHas('locals', ['id' => $local->id, 'deleted_at' => null]);
    }
}
