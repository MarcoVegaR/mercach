<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\PhoneAreaCode;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhoneAreaCodeControllerTest extends TestCase
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

    public function test_index_shows_items_with_authorization(): void
    {
        PhoneAreaCode::create(['code' => '0412', 'is_active' => true]);
        PhoneAreaCode::create(['code' => '0414', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/catalogs/phone-area-code');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/phone-area-code/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_validates_exact_four_digits_and_normalizes(): void
    {
        // Invalid: not 4 digits
        $bad1 = $this->actingAs($this->user)->from('/catalogs/phone-area-code/create')->post('/catalogs/phone-area-code', [
            'code' => '123',
            'is_active' => true,
        ]);
        $bad1->assertRedirect('/catalogs/phone-area-code/create');
        $bad1->assertSessionHasErrors(['code']);

        // Invalid: too long
        $bad2 = $this->actingAs($this->user)->from('/catalogs/phone-area-code/create')->post('/catalogs/phone-area-code', [
            'code' => '12345',
            'is_active' => true,
        ]);
        $bad2->assertRedirect('/catalogs/phone-area-code/create');
        $bad2->assertSessionHasErrors(['code']);

        // Invalid: non numeric
        $bad3 = $this->actingAs($this->user)->from('/catalogs/phone-area-code/create')->post('/catalogs/phone-area-code', [
            'code' => '12a4',
            'is_active' => true,
        ]);
        $bad3->assertRedirect('/catalogs/phone-area-code/create');
        $bad3->assertSessionHasErrors(['code']);

        // Valid: trimmed and saved
        $ok = $this->actingAs($this->user)->post('/catalogs/phone-area-code', [
            'code' => '  0580  ',
            'is_active' => false,
        ]);
        $ok->assertRedirect('/catalogs/phone-area-code');
        $ok->assertSessionHas('success');
        $this->assertDatabaseHas('phone_area_codes', ['code' => '0580', 'is_active' => false]);
    }

    public function test_update_enforces_regex_and_uniqueness(): void
    {
        $a = PhoneAreaCode::create(['code' => '0412', 'is_active' => true]);
        $b = PhoneAreaCode::create(['code' => '0414', 'is_active' => true]);

        // Duplicate code to existing
        $dup = $this->actingAs($this->user)->from('/catalogs/phone-area-code/'.$b->id.'/edit')->put('/catalogs/phone-area-code/'.$b->id, [
            'code' => '0412',
            'is_active' => true,
        ]);
        $dup->assertRedirect('/catalogs/phone-area-code/'.$b->id.'/edit');
        $dup->assertSessionHasErrors(['code']);

        // Invalid regex
        $bad = $this->actingAs($this->user)->from('/catalogs/phone-area-code/'.$b->id.'/edit')->put('/catalogs/phone-area-code/'.$b->id, [
            'code' => 'abcd',
            'is_active' => true,
        ]);
        $bad->assertRedirect('/catalogs/phone-area-code/'.$b->id.'/edit');
        $bad->assertSessionHasErrors(['code']);

        // Valid update
        $ok = $this->actingAs($this->user)->put('/catalogs/phone-area-code/'.$b->id, [
            'code' => '0581',
            'is_active' => false,
        ]);
        $ok->assertRedirect('/catalogs/phone-area-code');
        $this->assertDatabaseHas('phone_area_codes', ['id' => $b->id, 'code' => '0581', 'is_active' => false]);
    }

    public function test_set_active_works_and_forbidden_without_permission(): void
    {
        $item = PhoneAreaCode::create(['code' => '0999', 'is_active' => false]);

        $ok = $this->actingAs($this->user)->patch('/catalogs/phone-area-code/'.$item->id.'/active', ['active' => true]);
        $ok->assertRedirect('/catalogs/phone-area-code');
        $this->assertDatabaseHas('phone_area_codes', ['id' => $item->id, 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.phone-area-code.view')->first();
        $user2->givePermissionTo($permView);

        $forbidden = $this->actingAs($user2)->patch('/catalogs/phone-area-code/'.$item->id.'/active', ['active' => false]);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('phone_area_codes', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $item = PhoneAreaCode::create(['code' => '0777', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->delete('/catalogs/phone-area-code/'.$item->id);
        $resp->assertRedirect('/catalogs/phone-area-code');
        $resp->assertSessionHas('success');

        $this->assertSoftDeleted('phone_area_codes', ['id' => $item->id]);
    }

    public function test_bulk_delete_by_ids(): void
    {
        $a = PhoneAreaCode::create(['code' => '0570', 'is_active' => true]);
        $b = PhoneAreaCode::create(['code' => '0571', 'is_active' => true]);

        $resp = $this->actingAs($this->user)->post('/catalogs/phone-area-code/bulk', [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $resp->assertRedirect('/catalogs/phone-area-code');
        $resp->assertSessionHas('success');
        $this->assertSoftDeleted('phone_area_codes', ['id' => $a->id]);
        $this->assertSoftDeleted('phone_area_codes', ['id' => $b->id]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        PhoneAreaCode::create(['code' => '0860', 'is_active' => true]);

        $csv = $this->actingAs($this->user)->get('/catalogs/phone-area-code/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $json = $this->actingAs($this->user)->get('/catalogs/phone-area-code/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
    }

    public function test_selected_returns_requested_rows(): void
    {
        $i1 = PhoneAreaCode::create(['code' => '0901', 'is_active' => true]);
        $i2 = PhoneAreaCode::create(['code' => '0902', 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/phone-area-code/selected?ids[]='.$i1->id.'&ids[]='.$i2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
