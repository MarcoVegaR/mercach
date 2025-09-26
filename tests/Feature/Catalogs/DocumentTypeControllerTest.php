<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentTypeControllerTest extends TestCase
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

    public function test_cannot_delete_when_active_concessionaires_exist(): void
    {
        $doc = DocumentType::create(['code' => 'RIF', 'name' => 'Registro Fiscal', 'mask' => 'J-########', 'is_active' => true]);
        $type = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);

        // Create active concessionaire referencing this document type
        Concessionaire::create([
            'concessionaire_type_id' => $type->id,
            'full_name' => 'ACME',
            'document_type_id' => $doc->id,
            'document_number' => 'J99999999',
            'fiscal_address' => 'Dir',
            'email' => 'acme@acme.com',
            'phone_area_code_id' => null,
            'phone_number' => null,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->user)->from('/catalogs/document-type')->delete('/catalogs/document-type/'.$doc->id);
        $resp->assertRedirect('/catalogs/document-type');
        $resp->assertSessionHasErrors();
        $this->assertDatabaseHas('document_types', ['id' => $doc->id, 'deleted_at' => null]);
    }

    public function test_index_shows_items_with_authorization(): void
    {
        DocumentType::create(['code' => 'RIF', 'name' => 'Registro Fiscal', 'mask' => 'J-########', 'is_active' => true]);
        DocumentType::create(['code' => 'CI', 'name' => 'Cédula', 'mask' => null, 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/catalogs/document-type');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/document-type/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_enforces_unique_code_case_insensitive_and_trims_mask(): void
    {
        DocumentType::create(['code' => 'RIF', 'name' => 'Registro Fiscal', 'mask' => 'J-########', 'is_active' => true]);

        // Duplicate code (case-insensitive)
        $fail = $this->actingAs($this->user)->from('/catalogs/document-type/create')->post('/catalogs/document-type', [
            'code' => ' rif ',
            'name' => 'Duplicado',
            'mask' => '  V-########  ',
            'is_active' => '1',
        ]);
        $fail->assertRedirect('/catalogs/document-type/create');
        $fail->assertSessionHasErrors(['code']);

        // Valid
        $ok = $this->actingAs($this->user)->post('/catalogs/document-type', [
            'code' => '  ci  ', // -> CI
            'name' => '  Cedula  ',
            'mask' => '  V-########  ', // -> V-########
            'is_active' => false,
        ]);
        $ok->assertRedirect('/catalogs/document-type');
        $ok->assertSessionHas('success');

        $this->assertDatabaseHas('document_types', [
            'code' => 'CI',
            'name' => 'Cedula',
            'mask' => 'V-########',
            'deleted_at' => null,
        ]);
    }

    public function test_update_rejects_duplicate_code_case_insensitive(): void
    {
        $a = DocumentType::create(['code' => 'RIF', 'name' => 'Registro Fiscal', 'mask' => 'J-########', 'is_active' => true]);
        $b = DocumentType::create(['code' => 'CI', 'name' => 'Cédula', 'mask' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->from('/catalogs/document-type/'.$b->id.'/edit')->put('/catalogs/document-type/'.$b->id, [
            'code' => 'rif',
            'name' => 'Cédula Mod',
            'mask' => '  V-########  ',
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/document-type/'.$b->id.'/edit');
        $resp->assertSessionHasErrors(['code']);

        $resp2 = $this->actingAs($this->user)->put('/catalogs/document-type/'.$b->id, [
            'code' => 'ced',
            'name' => 'Cédula Mod 2',
            'mask' => '  V-######  ',
            'is_active' => false,
        ]);
        $resp2->assertRedirect('/catalogs/document-type');
        $this->assertDatabaseHas('document_types', ['id' => $b->id, 'code' => 'CED', 'name' => 'Cédula Mod 2', 'mask' => 'V-######', 'is_active' => false]);
    }

    public function test_set_active_works_and_forbidden_without_permission(): void
    {
        $item = DocumentType::create(['code' => 'TMP', 'name' => 'Temporal', 'mask' => null, 'is_active' => false]);

        $ok = $this->actingAs($this->user)->patch('/catalogs/document-type/'.$item->id.'/active', ['active' => true]);
        $ok->assertRedirect('/catalogs/document-type');
        $this->assertDatabaseHas('document_types', ['id' => $item->id, 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.document-type.view')->first();
        $user2->givePermissionTo($permView);

        $forbidden = $this->actingAs($user2)->patch('/catalogs/document-type/'.$item->id.'/active', ['active' => false]);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('document_types', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $item = DocumentType::create(['code' => 'DEL', 'name' => 'Delete Me', 'mask' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/document-type/'.$item->id);
        $resp->assertRedirect('/catalogs/document-type');
        $resp->assertSessionHas('success');

        $this->assertSoftDeleted('document_types', ['id' => $item->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $a = DocumentType::create(['code' => 'B1', 'name' => 'B1', 'mask' => null, 'is_active' => true]);
        $b = DocumentType::create(['code' => 'B2', 'name' => 'B2', 'mask' => null, 'is_active' => true]);

        $resp = $this->actingAs($this->user)->post('/catalogs/document-type/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $resp->assertRedirect('/catalogs/document-type');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('document_types', ['id' => $a->id]);
        $this->assertSoftDeleted('document_types', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        DocumentType::create(['code' => 'EXP', 'name' => 'Exportable', 'mask' => null, 'is_active' => true]);

        $csv = $this->actingAs($this->user)->get('/catalogs/document-type/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $json = $this->actingAs($this->user)->get('/catalogs/document-type/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
    }

    public function test_selected_returns_requested_rows(): void
    {
        $i1 = DocumentType::create(['code' => 'S1', 'name' => 'Sel1', 'mask' => null, 'is_active' => true]);
        $i2 = DocumentType::create(['code' => 'S2', 'name' => 'Sel2', 'mask' => null, 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/document-type/selected?ids[]='.$i1->id.'&ids[]='.$i2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
