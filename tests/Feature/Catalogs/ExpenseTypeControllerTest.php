<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\ExpenseType;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseTypeControllerTest extends TestCase
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
        ExpenseType::create(['code' => 'CON', 'name' => 'Condominio', 'description' => 'desc', 'is_active' => true]);
        ExpenseType::create(['code' => 'LIM', 'name' => 'Limpieza', 'description' => null, 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/catalogs/expense-type');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/expense-type/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_enforces_unique_code_case_insensitive(): void
    {
        ExpenseType::create(['code' => 'AAA', 'name' => 'A', 'description' => null, 'is_active' => true]);

        $fail = $this->actingAs($this->user)->from('/catalogs/expense-type/create')->post('/catalogs/expense-type', [
            'code' => ' aaa ',
            'name' => 'Duplicado',
            'description' => '  con espacios  ',
            'is_active' => '1',
        ]);
        $fail->assertRedirect('/catalogs/expense-type/create');
        $fail->assertSessionHasErrors(['code']);

        $ok = $this->actingAs($this->user)->post('/catalogs/expense-type', [
            'code' => '  ab12  ',
            'name' => '  Nombre  ',
            'description' => '  Desc  ',
            'is_active' => false,
        ]);
        $ok->assertRedirect('/catalogs/expense-type');
        $ok->assertSessionHas('success');

        $this->assertDatabaseHas('expense_types', [
            'code' => 'AB12',
            'name' => 'Nombre',
            'description' => 'Desc',
            'deleted_at' => null,
        ]);
    }

    public function test_update_rejects_duplicate_code_case_insensitive(): void
    {
        $a = ExpenseType::create(['code' => 'AAA', 'name' => 'A', 'description' => null, 'is_active' => true]);
        $b = ExpenseType::create(['code' => 'BBB', 'name' => 'B', 'description' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->from('/catalogs/expense-type/'.$b->id.'/edit')->put('/catalogs/expense-type/'.$b->id, [
            'code' => 'aaa',
            'name' => 'B Mod',
            'description' => '  d  ',
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/expense-type/'.$b->id.'/edit');
        $resp->assertSessionHasErrors(['code']);

        $resp2 = $this->actingAs($this->user)->put('/catalogs/expense-type/'.$b->id, [
            'code' => 'bbx',
            'name' => 'B Mod 2',
            'description' => '  D2  ',
            'is_active' => false,
        ]);
        $resp2->assertRedirect('/catalogs/expense-type');
        $this->assertDatabaseHas('expense_types', ['id' => $b->id, 'code' => 'BBX', 'name' => 'B Mod 2', 'description' => 'D2', 'is_active' => false]);
    }

    public function test_set_active_works_and_forbidden_without_permission(): void
    {
        $item = ExpenseType::create(['code' => 'TOG', 'name' => 'Toggle', 'description' => null, 'is_active' => false]);

        $ok = $this->actingAs($this->user)->patch('/catalogs/expense-type/'.$item->id.'/active', ['active' => true]);
        $ok->assertRedirect('/catalogs/expense-type');
        $this->assertDatabaseHas('expense_types', ['id' => $item->id, 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.expense-type.view')->first();
        $user2->givePermissionTo($permView);

        $forbidden = $this->actingAs($user2)->patch('/catalogs/expense-type/'.$item->id.'/active', ['active' => false]);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('expense_types', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $item = ExpenseType::create(['code' => 'DEL', 'name' => 'Delete Me', 'description' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/expense-type/'.$item->id);
        $resp->assertRedirect('/catalogs/expense-type');
        $resp->assertSessionHas('success');

        $this->assertSoftDeleted('expense_types', ['id' => $item->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $a = ExpenseType::create(['code' => 'B1', 'name' => 'B1', 'description' => null, 'is_active' => true]);
        $b = ExpenseType::create(['code' => 'B2', 'name' => 'B2', 'description' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->post('/catalogs/expense-type/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $resp->assertRedirect('/catalogs/expense-type');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('expense_types', ['id' => $a->id]);
        $this->assertSoftDeleted('expense_types', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        ExpenseType::create(['code' => 'EXP', 'name' => 'Exportable', 'description' => null, 'is_active' => true]);

        $csv = $this->actingAs($this->user)->get('/catalogs/expense-type/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $json = $this->actingAs($this->user)->get('/catalogs/expense-type/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
    }

    public function test_selected_returns_requested_rows(): void
    {
        $i1 = ExpenseType::create(['code' => 'S1', 'name' => 'Sel1', 'description' => null, 'is_active' => true]);
        $i2 = ExpenseType::create(['code' => 'S2', 'name' => 'Sel2', 'description' => null, 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/expense-type/selected?ids[]='.$i1->id.'&ids[]='.$i2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
