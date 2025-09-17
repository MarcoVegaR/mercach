<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\ContractStatus;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractStatusControllerTest extends TestCase
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
        ContractStatus::create(['code' => 'ACTIVE', 'name' => 'Activo', 'is_active' => true]);
        ContractStatus::create(['code' => 'ENDED', 'name' => 'Finalizado', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/catalogs/contract-status');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/contract-status/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_enforces_unique_code_case_insensitive(): void
    {
        ContractStatus::create(['code' => 'ACTIVE', 'name' => 'Activo', 'is_active' => true]);

        $fail = $this->actingAs($this->user)->from('/catalogs/contract-status/create')->post('/catalogs/contract-status', [
            'code' => ' active ',
            'name' => 'Duplicado',
            'is_active' => '1',
        ]);
        $fail->assertRedirect('/catalogs/contract-status/create');
        $fail->assertSessionHasErrors(['code']);

        $ok = $this->actingAs($this->user)->post('/catalogs/contract-status', [
            'code' => '  expired  ',
            'name' => 'Expirado',
            'is_active' => false,
        ]);
        $ok->assertRedirect('/catalogs/contract-status');
        $ok->assertSessionHas('success');

        $this->assertDatabaseHas('contract_statuses', [
            'code' => 'EXPIRED',
            'name' => 'Expirado',
            'deleted_at' => null,
        ]);
    }

    public function test_update_rejects_duplicate_code_case_insensitive(): void
    {
        $a = ContractStatus::create(['code' => 'ACTIVE', 'name' => 'Activo', 'is_active' => true]);
        $b = ContractStatus::create(['code' => 'ENDED', 'name' => 'Finalizado', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->from('/catalogs/contract-status/'.$b->id.'/edit')->put('/catalogs/contract-status/'.$b->id, [
            'code' => 'active',
            'name' => 'Finalizado mod',
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/contract-status/'.$b->id.'/edit');
        $resp->assertSessionHasErrors(['code']);

        $resp2 = $this->actingAs($this->user)->put('/catalogs/contract-status/'.$b->id, [
            'code' => 'exp',
            'name' => 'Expirado',
            'is_active' => false,
        ]);
        $resp2->assertRedirect('/catalogs/contract-status');
        $this->assertDatabaseHas('contract_statuses', ['id' => $b->id, 'code' => 'EXP', 'name' => 'Expirado', 'is_active' => false]);
    }

    public function test_set_active_works_and_forbidden_without_permission(): void
    {
        $item = ContractStatus::create(['code' => 'TMP', 'name' => 'Temporal', 'is_active' => false]);

        $ok = $this->actingAs($this->user)->patch('/catalogs/contract-status/'.$item->id.'/active', ['active' => true]);
        $ok->assertRedirect('/catalogs/contract-status');
        $this->assertDatabaseHas('contract_statuses', ['id' => $item->id, 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.contract-status.view')->first();
        $user2->givePermissionTo($permView);

        $forbidden = $this->actingAs($user2)->patch('/catalogs/contract-status/'.$item->id.'/active', ['active' => false]);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('contract_statuses', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $item = ContractStatus::create(['code' => 'DEL', 'name' => 'Delete Me', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/contract-status/'.$item->id);
        $resp->assertRedirect('/catalogs/contract-status');
        $resp->assertSessionHas('success');

        $this->assertSoftDeleted('contract_statuses', ['id' => $item->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $a = ContractStatus::create(['code' => 'B1', 'name' => 'B1', 'is_active' => true]);
        $b = ContractStatus::create(['code' => 'B2', 'name' => 'B2', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->post('/catalogs/contract-status/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $resp->assertRedirect('/catalogs/contract-status');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('contract_statuses', ['id' => $a->id]);
        $this->assertSoftDeleted('contract_statuses', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        ContractStatus::create(['code' => 'EXP', 'name' => 'Exportable', 'is_active' => true]);

        $csv = $this->actingAs($this->user)->get('/catalogs/contract-status/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $json = $this->actingAs($this->user)->get('/catalogs/contract-status/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
    }

    public function test_selected_returns_requested_rows(): void
    {
        $i1 = ContractStatus::create(['code' => 'S1', 'name' => 'Sel1', 'is_active' => true]);
        $i2 = ContractStatus::create(['code' => 'S2', 'name' => 'Sel2', 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/contract-status/selected?ids[]='.$i1->id.'&ids[]='.$i2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
