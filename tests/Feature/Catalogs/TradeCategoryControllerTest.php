<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\TradeCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TradeCategoryControllerTest extends TestCase
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

    public function test_index_shows_items_with_authorization(): void
    {
        TradeCategory::create(['code' => 'ALM', 'name' => 'Alimentos', 'description' => 'desc', 'is_active' => true]);
        TradeCategory::create(['code' => 'TEC', 'name' => 'Tecnologia', 'description' => null, 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/catalogs/trade-category');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/trade-category/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_enforces_unique_code_case_insensitive(): void
    {
        TradeCategory::create(['code' => 'AAA', 'name' => 'A', 'description' => null, 'is_active' => true]);

        $fail = $this->actingAs($this->user)->from('/catalogs/trade-category/create')->post('/catalogs/trade-category', [
            'code' => ' aaa ',
            'name' => 'Duplicado',
            'description' => '  con espacios  ',
            'is_active' => '1',
        ]);
        $fail->assertRedirect('/catalogs/trade-category/create');
        $fail->assertSessionHasErrors(['code']);

        $ok = $this->actingAs($this->user)->post('/catalogs/trade-category', [
            'code' => '  ab12  ',
            'name' => '  Nombre  ',
            'description' => '  Desc  ',
            'is_active' => false,
        ]);
        $ok->assertRedirect('/catalogs/trade-category');
        $ok->assertSessionHas('success');

        $this->assertDatabaseHas('trade_categories', [
            'code' => 'AB12',
            'name' => 'Nombre',
            'description' => 'Desc',
            'deleted_at' => null,
        ]);
    }

    public function test_update_rejects_duplicate_code_case_insensitive(): void
    {
        $a = TradeCategory::create(['code' => 'AAA', 'name' => 'A', 'description' => null, 'is_active' => true]);
        $b = TradeCategory::create(['code' => 'BBB', 'name' => 'B', 'description' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->from('/catalogs/trade-category/'.$b->id.'/edit')->put('/catalogs/trade-category/'.$b->id, [
            'code' => 'aaa',
            'name' => 'B Mod',
            'description' => '  d  ',
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/trade-category/'.$b->id.'/edit');
        $resp->assertSessionHasErrors(['code']);

        $resp2 = $this->actingAs($this->user)->put('/catalogs/trade-category/'.$b->id, [
            'code' => 'bbx',
            'name' => 'B Mod 2',
            'description' => '  D2  ',
            'is_active' => false,
        ]);
        $resp2->assertRedirect('/catalogs/trade-category');
        $this->assertDatabaseHas('trade_categories', ['id' => $b->id, 'code' => 'BBX', 'name' => 'B Mod 2', 'description' => 'D2', 'is_active' => false]);
    }

    public function test_set_active_works_and_forbidden_without_permission(): void
    {
        $item = TradeCategory::create(['code' => 'TOG', 'name' => 'Toggle', 'description' => null, 'is_active' => false]);

        $ok = $this->actingAs($this->user)->patch('/catalogs/trade-category/'.$item->id.'/active', ['active' => true]);
        $ok->assertRedirect('/catalogs/trade-category');
        $this->assertDatabaseHas('trade_categories', ['id' => $item->id, 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.trade-category.view')->first();
        $user2->givePermissionTo($permView);

        $forbidden = $this->actingAs($user2)->patch('/catalogs/trade-category/'.$item->id.'/active', ['active' => false]);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('trade_categories', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $item = TradeCategory::create(['code' => 'DEL', 'name' => 'Delete Me', 'description' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/trade-category/'.$item->id);
        $resp->assertRedirect('/catalogs/trade-category');
        $resp->assertSessionHas('success');

        $this->assertSoftDeleted('trade_categories', ['id' => $item->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $a = TradeCategory::create(['code' => 'B1', 'name' => 'B1', 'description' => null, 'is_active' => true]);
        $b = TradeCategory::create(['code' => 'B2', 'name' => 'B2', 'description' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->post('/catalogs/trade-category/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $resp->assertRedirect('/catalogs/trade-category');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('trade_categories', ['id' => $a->id]);
        $this->assertSoftDeleted('trade_categories', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        TradeCategory::create(['code' => 'EXP', 'name' => 'Exportable', 'description' => null, 'is_active' => true]);

        $csv = $this->actingAs($this->user)->get('/catalogs/trade-category/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $json = $this->actingAs($this->user)->get('/catalogs/trade-category/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
    }

    public function test_selected_returns_requested_rows(): void
    {
        $i1 = TradeCategory::create(['code' => 'S1', 'name' => 'Sel1', 'description' => null, 'is_active' => true]);
        $i2 = TradeCategory::create(['code' => 'S2', 'name' => 'Sel2', 'description' => null, 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/trade-category/selected?ids[]='.$i1->id.'&ids[]='.$i2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
