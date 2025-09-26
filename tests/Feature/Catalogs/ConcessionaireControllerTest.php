<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\DocumentType;
use App\Models\PhoneAreaCode;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConcessionaireControllerTest extends TestCase
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
        $type = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);
        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'RIF', 'mask' => 'J-########', 'is_active' => true]);
        $area = PhoneAreaCode::create(['code' => '212', 'is_active' => true]);

        Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'Acme Ltd',
            'document_type_id' => $doc->id,
            'document_number' => 'J12345678',
            'fiscal_address' => 'Dir 1',
            'email' => 'acme@example.com',
            'phone_area_code_id' => $area->id,
            'phone_number' => '1234567',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/catalogs/concessionaire');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/concessionaire/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_unique_document_and_email(): void
    {
        $type = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);
        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'RIF', 'mask' => 'J-########', 'is_active' => true]);
        $area = PhoneAreaCode::create(['code' => '212', 'is_active' => true]);

        // Existing
        Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'Existing',
            'document_type_id' => $doc->id,
            'document_number' => 'J12345678',
            'fiscal_address' => 'Dir 1',
            'email' => 'existing@gmail.com',
            'phone_area_code_id' => $area->id,
            'phone_number' => '1234567',
            'is_active' => true,
        ]);

        // Duplicate document and email with different case/whitespace -> should fail
        $fail = $this->actingAs($this->user)->from('/catalogs/concessionaire/create')->post('/catalogs/concessionaire', [
            'concessionaire_type_id' => $type->id,
            'full_name' => '  Nuevo  ',
            'document_type_id' => $doc->id,
            'document_number' => '  j12345678  ', // -> J12345678
            'fiscal_address' => '  Dir XX  ',
            'email' => '  EXISTING@GMAIL.COM  ', // -> existing@gmail.com
            'phone_area_code_id' => $area->id,
            'phone_number' => '1234567',
            'is_active' => true,
        ]);
        $fail->assertRedirect('/catalogs/concessionaire/create');
        $fail->assertSessionHasErrors(['document_number', 'email']);

        // Valid
        $ok = $this->actingAs($this->user)->post('/catalogs/concessionaire', [
            'concessionaire_type_id' => $type->id,
            'full_name' => '  Nuevo  ',
            'document_type_id' => $doc->id,
            'document_number' => '  j87654321  ', // -> J87654321
            'fiscal_address' => '  Dir XX  ',
            'email' => '  new@gmail.com  ', // -> new@gmail.com
            'phone_area_code_id' => $area->id,
            'phone_number' => '1234567',
            'is_active' => false,
        ]);
        $ok->assertRedirect('/catalogs/concessionaire');
        $ok->assertSessionHas('success');

        $this->assertDatabaseHas('concessionaires', [
            'document_number' => 'J87654321',
            'email' => 'new@gmail.com',
            'full_name' => 'Nuevo',
            'deleted_at' => null,
        ]);
    }

    public function test_update_rejects_duplicate_document_and_email(): void
    {
        $type = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);
        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'RIF', 'mask' => 'J-########', 'is_active' => true]);

        $a = Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'A',
            'document_type_id' => $doc->id,
            'document_number' => 'J11111111',
            'fiscal_address' => 'Dir A',
            'email' => 'a@gmail.com',
            'phone_area_code_id' => null,
            'phone_number' => null,
            'is_active' => true,
        ]);
        $b = Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'B',
            'document_type_id' => $doc->id,
            'document_number' => 'J22222222',
            'fiscal_address' => 'Dir B',
            'email' => 'b@gmail.com',
            'phone_area_code_id' => null,
            'phone_number' => null,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->user)->from('/catalogs/concessionaire/'.$b->id.'/edit')->put('/catalogs/concessionaire/'.$b->id, [
            '_version' => $b->updated_at?->toISOString(),
            'concessionaire_type_id' => $type->id,
            'full_name' => 'B Mod',
            'document_type_id' => $doc->id,
            'document_number' => 'j11111111',
            'fiscal_address' => 'Dir B',
            'email' => 'A@GMAIL.COM',
            'phone_area_code_id' => null,
            'phone_number' => null,
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/concessionaire/'.$b->id.'/edit');
        $resp->assertSessionHasErrors(['document_number', 'email']);

        $resp2 = $this->actingAs($this->user)->put('/catalogs/concessionaire/'.$b->id, [
            '_version' => $b->updated_at?->toISOString(),
            'concessionaire_type_id' => $type->id,
            'full_name' => 'B Mod 2',
            'document_type_id' => $doc->id,
            'document_number' => 'j33333333',
            'fiscal_address' => 'Dir B',
            'email' => 'b2@gmail.com',
            'phone_area_code_id' => null,
            'phone_number' => null,
            'is_active' => false,
        ]);
        $resp2->assertRedirect('/catalogs/concessionaire');
        $this->assertDatabaseHas('concessionaires', ['id' => $b->id, 'document_number' => 'J33333333', 'email' => 'b2@gmail.com', 'is_active' => false]);
    }

    public function test_set_active_and_forbidden_without_permission(): void
    {
        $type = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);
        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'RIF', 'mask' => 'J-########', 'is_active' => true]);

        $item = Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'T',
            'document_type_id' => $doc->id,
            'document_number' => 'J99999999',
            'fiscal_address' => 'Dir',
            'email' => 't@example.com',
            'phone_area_code_id' => null,
            'phone_number' => null,
            'is_active' => false,
        ]);

        $ok = $this->actingAs($this->user)->patch('/catalogs/concessionaire/'.$item->id.'/active', ['active' => true]);
        $ok->assertRedirect('/catalogs/concessionaire');
        $this->assertDatabaseHas('concessionaires', ['id' => $item->id, 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.concessionaire.view')->first();
        $user2->givePermissionTo($permView);

        $forbidden = $this->actingAs($user2)->patch('/catalogs/concessionaire/'.$item->id.'/active', ['active' => false]);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('concessionaires', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $type = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);
        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'RIF', 'mask' => 'J-########', 'is_active' => true]);

        $item = Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'Del',
            'document_type_id' => $doc->id,
            'document_number' => 'J00000123',
            'fiscal_address' => 'Dir',
            'email' => 'del@example.com',
            'phone_area_code_id' => null,
            'phone_number' => null,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/concessionaire/'.$item->id);
        $resp->assertRedirect('/catalogs/concessionaire');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('concessionaires', ['id' => $item->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $type = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);
        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'RIF', 'mask' => 'J-########', 'is_active' => true]);

        $a = Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'A',
            'document_type_id' => $doc->id,
            'document_number' => 'J11111111',
            'fiscal_address' => 'Dir',
            'email' => 'a@example.com',
            'is_active' => true,
        ]);
        $b = Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'B',
            'document_type_id' => $doc->id,
            'document_number' => 'J22222222',
            'fiscal_address' => 'Dir',
            'email' => 'b@example.com',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->user)->post('/catalogs/concessionaire/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);
        $resp->assertRedirect('/catalogs/concessionaire');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('concessionaires', ['id' => $a->id]);
        $this->assertSoftDeleted('concessionaires', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        $type = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);
        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'RIF', 'mask' => 'J-########', 'is_active' => true]);

        Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'E',
            'document_type_id' => $doc->id,
            'document_number' => 'J12121212',
            'fiscal_address' => 'Dir',
            'email' => 'e@example.com',
            'is_active' => true,
        ]);

        $csv = $this->actingAs($this->user)->get('/catalogs/concessionaire/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $json = $this->actingAs($this->user)->get('/catalogs/concessionaire/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
    }

    public function test_selected_returns_requested_rows(): void
    {
        $type = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);
        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'RIF', 'mask' => 'J-########', 'is_active' => true]);

        $i1 = Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'S1',
            'document_type_id' => $doc->id,
            'document_number' => 'J00000001',
            'fiscal_address' => 'Dir',
            'email' => 's1@example.com',
            'is_active' => true,
        ]);
        $i2 = Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'S2',
            'document_type_id' => $doc->id,
            'document_number' => 'J00000002',
            'fiscal_address' => 'Dir',
            'email' => 's2@example.com',
            'is_active' => false,
        ]);

        $resp = $this->actingAs($this->user)->get('/catalogs/concessionaire/selected?ids[]='.$i1->id.'&ids[]='.$i2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
