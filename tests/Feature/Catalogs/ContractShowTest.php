<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\DocumentType;
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

class ContractShowTest extends TestCase
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

    private function makePrereqs(): array
    {
        $type = ContractType::create(['code' => 'ARR', 'name' => 'Arriendo', 'is_active' => true]);
        $vig = ContractStatus::create(['code' => 'VIG', 'name' => 'Vigente', 'is_active' => true]);
        $mod = ContractModality::create(['code' => 'TFIJA', 'name' => 'Tasa Fija', 'is_active' => true]);
        $cat = TradeCategory::create(['code' => 'RC1', 'name' => 'Rubro 1', 'description' => 'D', 'is_active' => true]);

        $disp = LocalStatus::create(['code' => 'DISP', 'name' => 'Disponible', 'is_active' => true]);
        $market = Market::create(['code' => 'M1', 'name' => 'Mercado 1', 'address' => 'Dir 1', 'is_active' => true]);
        $ltype = LocalType::create(['code' => 'LT1', 'name' => 'Tipo 1', 'is_active' => true]);
        $lloc = LocalLocation::create(['code' => 'LOC1', 'name' => 'Ubic 1', 'is_active' => true]);

        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'RIF', 'mask' => 'J-########', 'is_active' => true]);
        $ctype = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);

        return compact('type', 'vig', 'mod', 'cat', 'disp', 'market', 'ltype', 'lloc', 'doc', 'ctype');
    }

    public function test_show_returns_item_with_relations_and_permissions(): void
    {
        $p = $this->makePrereqs();

        // Create relations
        $local1 = Local::create([
            'code' => 'L1',
            'name' => 'Local 1',
            'market_id' => $p['market']->id,
            'local_type_id' => $p['ltype']->id,
            'local_status_id' => $p['disp']->id,
            'local_location_id' => $p['lloc']->id,
            'area_m2' => 10.0,
            'is_active' => true,
        ]);
        $local2 = Local::create([
            'code' => 'L2',
            'name' => 'Local 2',
            'market_id' => $p['market']->id,
            'local_type_id' => $p['ltype']->id,
            'local_status_id' => $p['disp']->id,
            'local_location_id' => $p['lloc']->id,
            'area_m2' => 8.5,
            'is_active' => true,
        ]);

        $c1 = Concessionaire::create([
            'concessionaire_type_id' => $p['ctype']->id,
            'full_name' => 'ACME',
            'document_type_id' => $p['doc']->id,
            'document_number' => 'J123',
            'fiscal_address' => 'Dir 1',
            'email' => 'a@a.com',
            'is_active' => true,
        ]);
        $c2 = Concessionaire::create([
            'concessionaire_type_id' => $p['ctype']->id,
            'full_name' => 'FOO BAR',
            'document_type_id' => $p['doc']->id,
            'document_number' => 'J456',
            'fiscal_address' => 'Dir 2',
            'email' => 'b@b.com',
            'is_active' => true,
        ]);

        $contract = Contract::create([
            'number' => 'C-SHOW',
            'contract_type_id' => $p['type']->id,
            'contract_status_id' => $p['vig']->id,
            'contract_modality_id' => $p['mod']->id,
            'trade_category_id' => $p['cat']->id,
            'start_date' => '2025-01-01',
            'end_date' => null,
            'billing_day' => 1,
            'monthly_price_eur' => 250.00,
            'is_active' => true,
        ]);

        // Attach relations
        $contract->locals()->attach([$local1->id, $local2->id]);
        $contract->concessionaires()->attach([
            $c1->id => ['is_primary' => true],
            $c2->id => ['is_primary' => false],
        ]);

        $response = $this->actingAs($this->user)->get('/catalogs/contract/'.$contract->id);
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/contract/show')
            ->has('item', fn (Assert $item) => $item
                ->where('id', $contract->id)
                ->where('number', 'C-SHOW')
                ->where('contract_status_code', 'VIG')
                ->has('locals_selected', 2)
                ->has('concessionaires_selected', 2)
                ->where('concessionaire_primary_id', $c1->id)
                ->etc()
            )
            ->where('hasEditRoute', true)
            ->where('canDelete', true)
        );
    }
}
