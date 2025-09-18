<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\LocalLocation;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocalLocationsTest extends TestCase
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
        LocalLocation::create(['code' => 'PB', 'name' => 'Planta baja', 'is_active' => true]);
        LocalLocation::create(['code' => 'P1', 'name' => 'Piso 1', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/catalogs/local-location');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('catalogs/local-location/index')
            ->has('rows')
            ->has('meta')
        );
    }

    public function test_store_normalizes_and_persists_uppercase_and_trim(): void
    {
        $resp = $this->actingAs($this->user)->post('/catalogs/local-location', [
            'code' => '  pb  ', // -> PB
            'name' => '  Planta baja  ', // -> trimmed
            'is_active' => '1',
        ]);

        $resp->assertRedirect('/catalogs/local-location');
        $resp->assertSessionHas('success');

        $this->assertDatabaseHas('local_locations', [
            'code' => 'PB',
            'name' => 'Planta baja',
            'deleted_at' => null,
        ]);
    }

    public function test_unique_soft_deletes_allows_recreate(): void
    {
        $a = LocalLocation::create(['code' => 'PB', 'name' => 'Planta baja', 'is_active' => true]);

        // Soft-delete through controller (policy + service)
        $del = $this->actingAs($this->user)->delete('/catalogs/local-location/'.$a->id);
        $del->assertRedirect('/catalogs/local-location');
        $this->assertSoftDeleted('local_locations', ['id' => $a->id]);

        // Re-create with same code should be allowed due to unique ignoring soft-deletes
        $resp = $this->actingAs($this->user)->post('/catalogs/local-location', [
            'code' => 'PB',
            'name' => 'Planta baja 2',
            'is_active' => true,
        ]);
        $resp->assertRedirect('/catalogs/local-location');

        $this->assertDatabaseHas('local_locations', [
            'code' => 'PB',
            'name' => 'Planta baja 2',
            'deleted_at' => null,
        ]);
    }

    public function test_update_changes_name_and_status_without_code_conflict(): void
    {
        $p1 = LocalLocation::create(['code' => 'P1', 'name' => 'Piso 1', 'is_active' => true]);
        $p2 = LocalLocation::create(['code' => 'P2', 'name' => 'Piso 2', 'is_active' => true]);

        // Update P2 without touching code to a duplicate -> should be OK
        $resp = $this->actingAs($this->user)->put('/catalogs/local-location/'.$p2->id, [
            'code' => 'P2',
            'name' => 'Piso 2 Mod',
            'is_active' => false,
        ]);
        $resp->assertRedirect('/catalogs/local-location');
        $this->assertDatabaseHas('local_locations', ['id' => $p2->id, 'code' => 'P2', 'name' => 'Piso 2 Mod', 'is_active' => false]);

        // Try to duplicate code to P1 -> should fail validation
        $fail = $this->actingAs($this->user)->from('/catalogs/local-location/'.$p2->id.'/edit')->put('/catalogs/local-location/'.$p2->id, [
            'code' => 'p1', // will normalize to P1 and collide
            'name' => 'X',
            'is_active' => true,
        ]);
        $fail->assertRedirect('/catalogs/local-location/'.$p2->id.'/edit');
        $fail->assertSessionHasErrors(['code']);
    }

    public function test_index_filters_is_active_and_q(): void
    {
        LocalLocation::create(['code' => 'P1', 'name' => 'Piso 1', 'is_active' => true]);
        LocalLocation::create(['code' => 'P2', 'name' => 'Piso 2', 'is_active' => false]);

        // is_active=true should exclude P2
        $resp1 = $this->actingAs($this->user)->get('/catalogs/local-location?filters[is_active]=true');
        $resp1->assertOk();
        $resp1->assertInertia(fn (Assert $page) => $page
            ->where('rows', function ($rows) {
                $rows = collect($rows);

                return $rows->contains(fn ($r) => $r['code'] === 'P1')
                    && ! $rows->contains(fn ($r) => $r['code'] === 'P2');
            })
        );

        // q=P1 should return P1
        $resp2 = $this->actingAs($this->user)->get('/catalogs/local-location?q=P1');
        $resp2->assertOk();
        $resp2->assertInertia(fn (Assert $page) => $page
            ->where('rows', fn ($rows) => collect($rows)->contains(fn ($r) => $r['code'] === 'P1'))
        );
    }

    public function test_set_active_toggles_and_forbidden_without_permission(): void
    {
        $item = LocalLocation::create(['code' => 'TG', 'name' => 'Toggle', 'is_active' => false]);

        $ok = $this->actingAs($this->user)->patch('/catalogs/local-location/'.$item->id.'/active', ['active' => true]);
        $ok->assertRedirect('/catalogs/local-location');
        $this->assertDatabaseHas('local_locations', ['id' => $item->id, 'is_active' => true]);

        $user2 = User::factory()->create();
        $permView = Permission::where('name', 'catalogs.local-location.view')->first();
        $user2->givePermissionTo($permView);

        $forbidden = $this->actingAs($user2)->patch('/catalogs/local-location/'.$item->id.'/active', ['active' => false]);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('local_locations', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_and_restore_via_bulk(): void
    {
        $item = LocalLocation::create(['code' => 'DL', 'name' => 'Delete', 'is_active' => true]);

        $del = $this->actingAs($this->user)->delete('/catalogs/local-location/'.$item->id);
        $del->assertRedirect('/catalogs/local-location');
        $this->assertSoftDeleted('local_locations', ['id' => $item->id]);

        $restore = $this->actingAs($this->user)->post('/catalogs/local-location/bulk', [
            'action' => 'restore',
            'ids' => [$item->id],
        ]);
        $restore->assertRedirect('/catalogs/local-location');
        $this->assertDatabaseHas('local_locations', ['id' => $item->id, 'deleted_at' => null]);
    }

    public function test_export_supports_csv_and_json(): void
    {
        LocalLocation::create(['code' => 'EX', 'name' => 'Export', 'is_active' => true]);

        $csv = $this->actingAs($this->user)->get('/catalogs/local-location/export?format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $json = $this->actingAs($this->user)->get('/catalogs/local-location/export?format=json');
        $json->assertOk();
        $json->assertHeader('content-type', 'application/json');
    }

    public function test_selected_returns_requested_rows(): void
    {
        $i1 = LocalLocation::create(['code' => 'S1', 'name' => 'Sel1', 'is_active' => true]);
        $i2 = LocalLocation::create(['code' => 'S2', 'name' => 'Sel2', 'is_active' => false]);

        $resp = $this->actingAs($this->user)->get('/catalogs/local-location/selected?ids[]='.$i1->id.'&ids[]='.$i2->id);
        $resp->assertOk();
        $resp->assertJson(fn ($json) => $json
            ->has('rows', 2)
            ->etc()
        );
    }
}
