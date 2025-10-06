<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\ChargeStatus;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChargeStatusControllerTest extends TestCase
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
        ChargeStatus::create(['code' => 'PEN', 'name' => 'Pendiente', 'description' => null, 'is_active' => true]);
        ChargeStatus::create(['code' => 'PAG', 'name' => 'Pagado', 'description' => null, 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/catalogs/charge-status');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/charge-status/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_enforces_unique_code_case_insensitive(): void
    {
        ChargeStatus::create(['code' => 'AAA', 'name' => 'A', 'description' => null, 'is_active' => true]);

        $fail = $this->actingAs($this->user)->from('/catalogs/charge-status/create')->post('/catalogs/charge-status', [
            'code' => ' aaa ',
            'name' => 'Duplicado',
            'description' => '  con espacios  ',
            'is_active' => '1',
        ]);
        $fail->assertRedirect('/catalogs/charge-status/create');
        $fail->assertSessionHasErrors(['code']);

        $ok = $this->actingAs($this->user)->post('/catalogs/charge-status', [
            'code' => '  ab12  ',
            'name' => '  Nombre  ',
            'description' => '  Desc  ',
            'is_active' => false,
        ]);
        $ok->assertRedirect('/catalogs/charge-status');
        $ok->assertSessionHas('success');

        $this->assertDatabaseHas('charge_statuses', [
            'code' => 'AB12',
            'name' => 'Nombre',
            'description' => 'Desc',
            'deleted_at' => null,
        ]);
    }

    public function test_update_rejects_duplicate_code_case_insensitive(): void
    {
        $a = ChargeStatus::create(['code' => 'AAA', 'name' => 'A', 'description' => null, 'is_active' => true]);
        $b = ChargeStatus::create(['code' => 'BBB', 'name' => 'B', 'description' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->from('/catalogs/charge-status/'.$b->id.'/edit')->put('/catalogs/charge-status/'.$b->id, [
            'code' => 'aaa',
            'name' => 'B Mod',
            'description' => '  d  ',
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/charge-status/'.$b->id.'/edit');
        $resp->assertSessionHasErrors(['code']);

        $resp2 = $this->actingAs($this->user)->put('/catalogs/charge-status/'.$b->id, [
            'code' => 'bbx',
            'name' => 'B Mod 2',
            'description' => '  D2  ',
            'is_active' => false,
        ]);
        $resp2->assertRedirect('/catalogs/charge-status');
        $this->assertDatabaseHas('charge_statuses', ['id' => $b->id, 'code' => 'BBX', 'name' => 'B Mod 2', 'description' => 'D2', 'is_active' => false]);
    }

    public function test_set_active_works_and_forbidden_without_permission(): void
    {
        $item = ChargeStatus::create(['code' => 'TOG', 'name' => 'Toggle', 'description' => null, 'is_active' => false]);

        $ok = $this->actingAs($this->user)->patch('/catalogs/charge-status/'.$item->id.'/active', ['active' => true]);
        $ok->assertRedirect('/catalogs/charge-status');
        $this->assertDatabaseHas('charge_statuses', ['id' => $item->id, 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.charge-status.view')->first();
        $user2->givePermissionTo($permView);

        $forbidden = $this->actingAs($user2)->patch('/catalogs/charge-status/'.$item->id.'/active', ['active' => false]);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('charge_statuses', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $item = ChargeStatus::create(['code' => 'DEL', 'name' => 'Delete Me', 'description' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/charge-status/'.$item->id);
        $resp->assertRedirect('/catalogs/charge-status');
        $resp->assertSessionHas('success');

        $this->assertSoftDeleted('charge_statuses', ['id' => $item->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $a = ChargeStatus::create(['code' => 'B1', 'name' => 'B1', 'description' => null, 'is_active' => true]);
        $b = ChargeStatus::create(['code' => 'B2', 'name' => 'B2', 'description' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->post('/catalogs/charge-status/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $resp->assertRedirect('/catalogs/charge-status');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('charge_statuses', ['id' => $a->id]);
        $this->assertSoftDeleted('charge_statuses', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        ChargeStatus::create(['code' => 'EXP', 'name' => 'Exportable', 'description' => null, 'is_active' => true]);

        $csv = $this->actingAs($this->user)->get('/catalogs/charge-status/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $json = $this->actingAs($this->user)->get('/catalogs/charge-status/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
    }

    public function test_selected_returns_requested_rows(): void
    {
        $i1 = ChargeStatus::create(['code' => 'S1', 'name' => 'Sel1', 'description' => null, 'is_active' => true]);
        $i2 = ChargeStatus::create(['code' => 'S2', 'name' => 'Sel2', 'description' => null, 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/charge-status/selected?ids[]='.$i1->id.'&ids[]='.$i2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
