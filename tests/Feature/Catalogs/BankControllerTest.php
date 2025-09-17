<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\Bank;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BankControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable Inertia page existence check
        config(['inertia.testing.ensure_pages_exist' => false]);

        // Seed permissions from config and create admin role with all permissions
        $this->seed(PermissionsSeeder::class);

        // Create a verified user and assign admin role (has all permissions)
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::where('name', 'admin')->first());

        // Reset permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_index_shows_banks_with_authorization(): void
    {
        Bank::create(['code' => 'BOD', 'name' => 'Banco Occidental', 'is_active' => true]);
        Bank::create(['code' => 'BNC', 'name' => 'Banco Nacional', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/catalogs/bank');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/bank/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_enforces_unique_code_case_insensitive(): void
    {
        // Existing record with upper-case code
        Bank::create(['code' => 'BOD', 'name' => 'Banco Occidental', 'is_active' => true]);

        // Attempt to create with same code in different case -> should fail
        $fail = $this->actingAs($this->user)->from('/catalogs/bank/create')->post('/catalogs/bank', [
            'code' => ' bod ', // will be trimmed+uppercased to BOD
            'name' => 'Banco Duplicado',
            'swift_bic' => 'abcde',
            'is_active' => '1',
        ]);
        $fail->assertRedirect('/catalogs/bank/create');
        $fail->assertSessionHasErrors(['code']);

        // Create with new code, expect normalization
        $ok = $this->actingAs($this->user)->post('/catalogs/bank', [
            'code' => '  ab12  ', // -> AB12
            'name' => '  Nombre con espacios  ', // -> trimmed
            'swift_bic' => '  ABCDEF12345  ',
            'is_active' => true,
        ]);
        $ok->assertRedirect('/catalogs/bank');
        $ok->assertSessionHas('success');

        $this->assertDatabaseHas('banks', [
            'code' => 'AB12',
            'name' => 'Nombre con espacios',
            'swift_bic' => 'ABCDEF12345',
            'deleted_at' => null,
        ]);
    }

    public function test_update_rejects_duplicate_code_case_insensitive(): void
    {
        $a = Bank::create(['code' => 'AAA', 'name' => 'A Uno', 'is_active' => true]);
        $b = Bank::create(['code' => 'BBB', 'name' => 'B Uno', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->from('/catalogs/bank/'.$b->id.'/edit')->put('/catalogs/bank/'.$b->id, [
            'code' => 'aaa', // duplicate of A after uppercase
            'name' => 'B Mod',
            'swift_bic' => null,
            'is_active' => true,
        ]);

        $resp->assertRedirect('/catalogs/bank/'.$b->id.'/edit');
        $resp->assertSessionHasErrors(['code']);

        // Valid update
        $resp2 = $this->actingAs($this->user)->put('/catalogs/bank/'.$b->id, [
            'code' => 'bbx',
            'name' => 'B Mod 2',
            'swift_bic' => null,
            'is_active' => false,
        ]);
        $resp2->assertRedirect('/catalogs/bank');
        $this->assertDatabaseHas('banks', ['id' => $b->id, 'code' => 'BBX', 'name' => 'B Mod 2', 'is_active' => false]);
    }

    public function test_set_active_toggles_with_permission(): void
    {
        $bank = Bank::create(['code' => 'TOG', 'name' => 'Toggle Bank', 'is_active' => false]);

        $resp = $this->actingAs($this->user)->patch('/catalogs/bank/'.$bank->id.'/active', ['active' => true]);
        $resp->assertRedirect('/catalogs/bank');
        $this->assertDatabaseHas('banks', ['id' => $bank->id, 'is_active' => true]);

        $resp2 = $this->actingAs($this->user)->patch('/catalogs/bank/'.$bank->id.'/active', ['active' => false]);
        $resp2->assertRedirect('/catalogs/bank');
        $this->assertDatabaseHas('banks', ['id' => $bank->id, 'is_active' => false]);
    }

    public function test_set_active_forbidden_without_permission(): void
    {
        $bank = Bank::create(['code' => 'NOP', 'name' => 'No Perm', 'is_active' => true]);

        $user2 = User::factory()->create();
        // Only grant view permission
        $permView = Permission::where('name', 'catalogs.bank.view')->first();
        $user2->givePermissionTo($permView);

        $resp = $this->actingAs($user2)->patch('/catalogs/bank/'.$bank->id.'/active', ['active' => false]);
        $resp->assertForbidden();
        $this->assertDatabaseHas('banks', ['id' => $bank->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $bank = Bank::create(['code' => 'DEL', 'name' => 'Delete Me', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/bank/'.$bank->id);
        $resp->assertRedirect('/catalogs/bank');
        $resp->assertSessionHas('success');

        $this->assertSoftDeleted('banks', ['id' => $bank->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $a = Bank::create(['code' => 'B1', 'name' => 'B1', 'is_active' => true]);
        $b = Bank::create(['code' => 'B2', 'name' => 'B2', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->post('/catalogs/bank/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $resp->assertRedirect('/catalogs/bank');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('banks', ['id' => $a->id]);
        $this->assertSoftDeleted('banks', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        Bank::create(['code' => 'EXP', 'name' => 'Exportable', 'is_active' => true]);

        // CSV
        $csv = $this->actingAs($this->user)->get('/catalogs/bank/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Exportable', $csv->streamedContent());

        // JSON
        $json = $this->actingAs($this->user)->get('/catalogs/bank/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
        $decoded = json_decode($json->streamedContent(), true);
        $this->assertTrue(collect($decoded)->contains(fn ($row) => in_array('Exportable', $row, true)));
    }

    public function test_selected_returns_requested_rows(): void
    {
        $b1 = Bank::create(['code' => 'S1', 'name' => 'Sel1', 'is_active' => true]);
        $b2 = Bank::create(['code' => 'S2', 'name' => 'Sel2', 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/bank/selected?ids[]='.$b1->id.'&ids[]='.$b2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
