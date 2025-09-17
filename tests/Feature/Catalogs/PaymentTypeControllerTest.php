<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\PaymentType;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentTypeControllerTest extends TestCase
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
        PaymentType::create(['code' => 'CASH', 'name' => 'Efectivo', 'is_active' => true]);
        PaymentType::create(['code' => 'CARD', 'name' => 'Tarjeta', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/catalogs/payment-type');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/payment-type/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_enforces_unique_code_case_insensitive(): void
    {
        PaymentType::create(['code' => 'CASH', 'name' => 'Efectivo', 'is_active' => true]);

        $fail = $this->actingAs($this->user)->from('/catalogs/payment-type/create')->post('/catalogs/payment-type', [
            'code' => ' cash ',
            'name' => 'Duplicado',
            'is_active' => '1',
        ]);
        $fail->assertRedirect('/catalogs/payment-type/create');
        $fail->assertSessionHasErrors(['code']);

        $ok = $this->actingAs($this->user)->post('/catalogs/payment-type', [
            'code' => '  tran  ',
            'name' => '  Transferencia  ',
            'is_active' => false,
        ]);
        $ok->assertRedirect('/catalogs/payment-type');
        $ok->assertSessionHas('success');

        $this->assertDatabaseHas('payment_types', [
            'code' => 'TRAN',
            'name' => 'Transferencia',
            'deleted_at' => null,
        ]);
    }

    public function test_update_rejects_duplicate_code_case_insensitive(): void
    {
        $a = PaymentType::create(['code' => 'CASH', 'name' => 'Efectivo', 'is_active' => true]);
        $b = PaymentType::create(['code' => 'CARD', 'name' => 'Tarjeta', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->from('/catalogs/payment-type/'.$b->id.'/edit')->put('/catalogs/payment-type/'.$b->id, [
            'code' => 'cash',
            'name' => 'Tarjeta mod',
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/payment-type/'.$b->id.'/edit');
        $resp->assertSessionHasErrors(['code']);

        $resp2 = $this->actingAs($this->user)->put('/catalogs/payment-type/'.$b->id, [
            'code' => 'trx',
            'name' => 'Transfer',
            'is_active' => false,
        ]);
        $resp2->assertRedirect('/catalogs/payment-type');
        $this->assertDatabaseHas('payment_types', ['id' => $b->id, 'code' => 'TRX', 'name' => 'Transfer', 'is_active' => false]);
    }

    public function test_set_active_works_and_forbidden_without_permission(): void
    {
        $item = PaymentType::create(['code' => 'TMP', 'name' => 'Temporal', 'is_active' => false]);

        $ok = $this->actingAs($this->user)->patch('/catalogs/payment-type/'.$item->id.'/active', ['active' => true]);
        $ok->assertRedirect('/catalogs/payment-type');
        $this->assertDatabaseHas('payment_types', ['id' => $item->id, 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.payment-type.view')->first();
        $user2->givePermissionTo($permView);

        $forbidden = $this->actingAs($user2)->patch('/catalogs/payment-type/'.$item->id.'/active', ['active' => false]);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('payment_types', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $item = PaymentType::create(['code' => 'DEL', 'name' => 'Delete Me', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/payment-type/'.$item->id);
        $resp->assertRedirect('/catalogs/payment-type');
        $resp->assertSessionHas('success');

        $this->assertSoftDeleted('payment_types', ['id' => $item->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $a = PaymentType::create(['code' => 'B1', 'name' => 'B1', 'is_active' => true]);
        $b = PaymentType::create(['code' => 'B2', 'name' => 'B2', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->post('/catalogs/payment-type/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $resp->assertRedirect('/catalogs/payment-type');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('payment_types', ['id' => $a->id]);
        $this->assertSoftDeleted('payment_types', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        PaymentType::create(['code' => 'EXP', 'name' => 'Exportable', 'is_active' => true]);

        $csv = $this->actingAs($this->user)->get('/catalogs/payment-type/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $json = $this->actingAs($this->user)->get('/catalogs/payment-type/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
    }

    public function test_selected_returns_requested_rows(): void
    {
        $i1 = PaymentType::create(['code' => 'S1', 'name' => 'Sel1', 'is_active' => true]);
        $i2 = PaymentType::create(['code' => 'S2', 'name' => 'Sel2', 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/payment-type/selected?ids[]='.$i1->id.'&ids[]='.$i2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
