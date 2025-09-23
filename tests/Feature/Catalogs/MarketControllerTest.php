<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Exceptions\DomainActionException;
use App\Models\Market;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable Inertia page existence check
        config(['inertia.testing.ensure_pages_exist' => false]);

        // Seed permissions and create admin user with all permissions
        $this->seed(PermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::where('name', 'admin')->first());

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_index_shows_markets_with_authorization(): void
    {
        Market::create(['code' => 'MK1', 'name' => 'Market One', 'address' => 'Addr 1', 'is_active' => true]);
        Market::create(['code' => 'MK2', 'name' => 'Market Two', 'address' => 'Addr 2', 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/market');
        $resp->assertOk();
        $resp->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/market/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_enforces_unique_code_case_insensitive(): void
    {
        // Existing
        Market::create(['code' => 'ATL', 'name' => 'Atlanta', 'address' => 'Ave 1', 'is_active' => true]);

        // Attempt duplicate with different case -> should fail
        $fail = $this->actingAs($this->user)->from('/catalogs/market/create')->post('/catalogs/market', [
            'code' => ' atl ', // -> ATL
            'name' => 'Duplicate',
            'address' => 'Some Address',
            'is_active' => '1',
        ]);
        $fail->assertRedirect('/catalogs/market/create');
        $fail->assertSessionHasErrors(['code']);

        // Create with new code -> normalization
        $ok = $this->actingAs($this->user)->post('/catalogs/market', [
            'code' => '  m2  ', // -> M2
            'name' => '  Name Trim  ',
            'address' => '  Addr Trim  ',
            'is_active' => true,
        ]);
        $ok->assertRedirect('/catalogs/market');
        $ok->assertSessionHas('success');

        $this->assertDatabaseHas('markets', [
            'code' => 'M2',
            'name' => 'Name Trim',
            'address' => 'Addr Trim',
            'deleted_at' => null,
        ]);
    }

    public function test_store_allows_reuse_of_code_after_soft_delete(): void
    {
        $m = Market::create(['code' => 'DEL', 'name' => 'Delete', 'address' => 'X', 'is_active' => true]);
        $m->delete();

        $resp = $this->actingAs($this->user)->post('/catalogs/market', [
            'code' => 'del', // -> DEL, but previous is soft-deleted
            'name' => 'Another',
            'address' => 'Yard',
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/market');
        $this->assertDatabaseHas('markets', ['code' => 'DEL', 'name' => 'Another', 'deleted_at' => null]);
    }

    public function test_update_rejects_duplicate_code_case_insensitive(): void
    {
        $a = Market::create(['code' => 'AAA', 'name' => 'A Uno', 'address' => 'A', 'is_active' => true]);
        $b = Market::create(['code' => 'BBB', 'name' => 'B Uno', 'address' => 'B', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->from('/catalogs/market/'.$b->id.'/edit')->put('/catalogs/market/'.$b->id, [
            'code' => 'aaa', // duplicate on update
            'name' => 'B Mod',
            'address' => 'B2',
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/market/'.$b->id.'/edit');
        $resp->assertSessionHasErrors(['code']);

        // Valid update
        $resp2 = $this->actingAs($this->user)->put('/catalogs/market/'.$b->id, [
            'code' => 'bbx', // -> BBX
            'name' => 'B Mod 2',
            'address' => 'B300',
            'is_active' => false,
        ]);
        $resp2->assertRedirect('/catalogs/market');
        $this->assertDatabaseHas('markets', ['id' => $b->id, 'code' => 'BBX', 'name' => 'B Mod 2', 'address' => 'B300', 'is_active' => false]);
    }

    public function test_update_blocks_code_change_if_has_dependencies_with_flash_error(): void
    {
        $m = Market::create(['code' => 'LOCK', 'name' => 'Locked', 'address' => 'Addr', 'is_active' => true]);

        // Rebind service to a test double that throws DomainActionException on code change
        $this->app->bind(\App\Contracts\Services\MarketServiceInterface::class, function ($app) {
            return new class($app->make(\App\Contracts\Repositories\MarketRepositoryInterface::class), $app) extends \App\Services\MarketService
            {
                protected function hasDependencies(\Illuminate\Database\Eloquent\Model $model): bool
                {
                    return true;
                }
            };
        });

        $resp = $this->actingAs($this->user)->from('/catalogs/market/'.$m->id.'/edit')->put('/catalogs/market/'.$m->id, [
            'code' => 'LOCK2',
            'name' => 'Locked',
            'address' => 'Addr',
            'is_active' => true,
        ]);

        $resp->assertRedirect('/catalogs/market/'.$m->id.'/edit');
        $resp->assertSessionHas('error');
    }

    public function test_index_filters_is_active_and_code_like(): void
    {
        $a = Market::create(['code' => 'AAA', 'name' => 'A Uno', 'address' => 'X', 'is_active' => true]);
        $b = Market::create(['code' => 'BBB', 'name' => 'B Uno', 'address' => 'Y', 'is_active' => false]);

        // is_active=true
        $resp1 = $this->actingAs($this->user)->get('/catalogs/market?filters[is_active]=true');
        $resp1->assertOk();
        $resp1->assertInertia(fn (Assert $page) => $page
            ->has('rows')
            ->where('rows.0.code', 'AAA')
        );

        // code_like=bb
        $resp2 = $this->actingAs($this->user)->get('/catalogs/market?filters[code_like]=bb');
        $resp2->assertOk();
        $resp2->assertInertia(fn (Assert $page) => $page
            ->has('rows')
            ->where('rows.0.code', 'BBB')
        );
    }

    public function test_set_active_toggles_with_permission(): void
    {
        $market = Market::create(['code' => 'TOG', 'name' => 'Toggle', 'address' => 'A', 'is_active' => false]);

        $resp = $this->actingAs($this->user)->patch('/catalogs/market/'.$market->id.'/active', ['active' => true]);
        $resp->assertRedirect('/catalogs/market');
        $this->assertDatabaseHas('markets', ['id' => $market->id, 'is_active' => true]);

        $resp2 = $this->actingAs($this->user)->patch('/catalogs/market/'.$market->id.'/active', ['active' => false]);
        $resp2->assertRedirect('/catalogs/market');
        $this->assertDatabaseHas('markets', ['id' => $market->id, 'is_active' => false]);
    }

    public function test_set_active_forbidden_without_permission(): void
    {
        $market = Market::create(['code' => 'NOP', 'name' => 'No Perm', 'address' => 'A', 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.market.view')->first();
        $user2->givePermissionTo($permView);

        $resp = $this->actingAs($user2)->patch('/catalogs/market/'.$market->id.'/active', ['active' => false]);
        $resp->assertForbidden();
        $this->assertDatabaseHas('markets', ['id' => $market->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $m = Market::create(['code' => 'DEL', 'name' => 'Delete', 'address' => 'Addr', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/market/'.$m->id);
        $resp->assertRedirect('/catalogs/market');
        $resp->assertSessionHas('success');

        $this->assertSoftDeleted('markets', ['id' => $m->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $a = Market::create(['code' => 'M1', 'name' => 'M1', 'address' => 'A', 'is_active' => true]);
        $b = Market::create(['code' => 'M2', 'name' => 'M2', 'address' => 'B', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->post('/catalogs/market/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);
        $resp->assertRedirect('/catalogs/market');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('markets', ['id' => $a->id]);
        $this->assertSoftDeleted('markets', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        Market::create(['code' => 'EXP', 'name' => 'Exportable', 'address' => 'A', 'is_active' => true]);

        $csv = $this->actingAs($this->user)->get('/catalogs/market/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Exportable', $csv->streamedContent());

        $json = $this->actingAs($this->user)->get('/catalogs/market/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
        $decoded = json_decode($json->streamedContent(), true);
        $this->assertTrue(collect($decoded)->contains(fn ($row) => in_array('Exportable', $row, true)));
    }

    public function test_selected_returns_requested_rows(): void
    {
        $m1 = Market::create(['code' => 'S1', 'name' => 'Sel1', 'address' => 'A', 'is_active' => true]);
        $m2 = Market::create(['code' => 'S2', 'name' => 'Sel2', 'address' => 'B', 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/market/selected?ids[]='.$m1->id.'&ids[]='.$m2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }

    public function test_show_displays_market_details(): void
    {
        $market = Market::create(['code' => 'MK1', 'name' => 'Market One', 'address' => 'Addr 1', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->get("/catalogs/market/{$market->id}");
        $resp->assertOk();
        $resp->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/market/show')
            ->has('item')
            ->has('meta')
            ->where('item.id', $market->id)
            ->where('item.code', 'MK1')
            ->where('item.name', 'Market One')
        );
    }

    public function test_show_loads_dynamic_data_with_query_params(): void
    {
        $market = Market::create(['code' => 'MK1', 'name' => 'Market One', 'address' => 'Addr 1', 'is_active' => true]);

        // Create some locals for this market
        $marketType = \App\Models\LocalType::create(['code' => 'LT', 'name' => 'Type', 'is_active' => true]);
        $marketStatus = \App\Models\LocalStatus::create(['code' => 'LS', 'name' => 'Status', 'is_active' => true]);
        $marketLocation = \App\Models\LocalLocation::create(['code' => 'LL', 'name' => 'Location', 'is_active' => true]);

        \App\Models\Local::create(['code' => 'L1', 'name' => 'Local 1', 'market_id' => $market->id, 'local_type_id' => $marketType->id, 'local_status_id' => $marketStatus->id, 'local_location_id' => $marketLocation->id, 'area_m2' => 10, 'is_active' => true]);
        \App\Models\Local::create(['code' => 'L2', 'name' => 'Local 2', 'market_id' => $market->id, 'local_type_id' => $marketType->id, 'local_status_id' => $marketStatus->id, 'local_location_id' => $marketLocation->id, 'area_m2' => 20, 'is_active' => true]);
        \App\Models\Local::create(['code' => 'L3', 'name' => 'Local 3', 'market_id' => $market->id, 'local_type_id' => $marketType->id, 'local_status_id' => $marketStatus->id, 'local_location_id' => $marketLocation->id, 'area_m2' => 30, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->get("/catalogs/market/{$market->id}?with[]=locals&withCount[]=locals");
        $resp->assertOk();
        $resp->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/market/show')
            ->has('item')
            ->has('meta')
            ->where('item.id', $market->id)
            ->has('item.locals', 3)
            ->where('meta.loaded_relations', ['locals'])
            ->where('meta.loaded_counts', ['locals'])
        );
    }

    public function test_show_forbidden_without_view_permission(): void
    {
        $market = Market::create(['code' => 'MK1', 'name' => 'Market One', 'address' => 'Addr 1', 'is_active' => true]);

        // Create user with no permissions instead of removing from admin
        $userWithoutPermission = User::factory()->create();

        $resp = $this->actingAs($userWithoutPermission)->get("/catalogs/market/{$market->id}");
        $resp->assertForbidden();
    }
}
