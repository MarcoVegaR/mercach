<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\TradeCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractControllerTest extends TestCase
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
        $term = ContractStatus::create(['code' => 'TERM', 'name' => 'Terminado', 'is_active' => true]);
        $mod = ContractModality::create(['code' => 'TFIJA', 'name' => 'Tasa fija', 'is_active' => true]);
        $cat = TradeCategory::create(['code' => 'RC1', 'name' => 'Rubro 1', 'description' => 'D', 'is_active' => true]);

        return compact('type', 'vig', 'term', 'mod', 'cat');
    }

    public function test_index_shows_items_with_authorization(): void
    {
        $this->makePrereqs();
        Contract::create([
            'number' => 'C-001',
            'contract_type_id' => ContractType::first()->id,
            'contract_status_id' => ContractStatus::where('code', 'VIG')->first()->id,
            'contract_modality_id' => ContractModality::first()->id,
            'trade_category_id' => TradeCategory::first()->id,
            'start_date' => '2025-01-01',
            'end_date' => null,
            'billing_day' => null,
            'monthly_price_eur' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/catalogs/contract');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/contract/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_destroy_is_forbidden_and_returns_flash_error(): void
    {
        $p = $this->makePrereqs();
        $c = Contract::create([
            'number' => 'C-DEL',
            'contract_type_id' => $p['type']->id,
            'contract_status_id' => $p['vig']->id,
            'contract_modality_id' => $p['mod']->id,
            'trade_category_id' => $p['cat']->id,
            'start_date' => '2025-01-01',
            'end_date' => null,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/contract/'.$c->id);
        $resp->assertRedirect('/catalogs/contract');
        $resp->assertSessionHas('error');
        $this->assertDatabaseHas('contracts', ['id' => $c->id, 'deleted_at' => null]);
    }

    public function test_set_active_requires_term_to_deactivate(): void
    {
        $p = $this->makePrereqs();
        $c = Contract::create([
            'number' => 'C-ACT',
            'contract_type_id' => $p['type']->id,
            'contract_status_id' => $p['vig']->id,
            'contract_modality_id' => $p['mod']->id,
            'trade_category_id' => $p['cat']->id,
            'start_date' => '2025-01-01',
            'end_date' => null,
            'is_active' => true,
        ]);

        // Try to deactivate while VIG -> error
        $fail = $this->actingAs($this->user)->patch('/catalogs/contract/'.$c->id.'/active', ['active' => false]);
        $fail->assertRedirect('/catalogs/contract');
        $fail->assertSessionHas('error');
        $this->assertDatabaseHas('contracts', ['id' => $c->id, 'is_active' => true]);

        // Change to TERM and now can deactivate
        $c->update(['contract_status_id' => $p['term']->id]);
        $ok = $this->actingAs($this->user)->patch('/catalogs/contract/'.$c->id.'/active', ['active' => false]);
        $ok->assertRedirect('/catalogs/contract');
        $ok->assertSessionHas('success');
        $this->assertDatabaseHas('contracts', ['id' => $c->id, 'is_active' => false]);
    }
}
