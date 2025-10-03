<?php

declare(strict_types=1);

namespace Tests\Feature\Condo;

use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class CondoLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'condo_period.view', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $role = SpatieRole::create(['name' => 'condo_admin', 'guard_name' => 'web']);
        $role->givePermissionTo('condo_period.view');
        $this->admin->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Minimal catalogs for Local
        LocalType::firstOrCreate(['code' => 'LOC'], ['name' => 'Local', 'is_active' => true]);
        LocalStatus::firstOrCreate(['code' => 'DISP'], ['name' => 'Disponible', 'is_active' => true]);
        LocalLocation::firstOrCreate(['code' => 'INT'], ['name' => 'Interior', 'is_active' => true]);
    }

    private function createMarket(): Market
    {
        return Market::create(['code' => 'MKT', 'name' => 'Sede', 'address' => 'X', 'is_active' => true]);
    }

    private function createLocal(Market $m, string $code, string $name): Local
    {
        return Local::create([
            'code' => $code,
            'name' => $name,
            'market_id' => $m->getKey(),
            'local_type_id' => LocalType::first()->id,
            'local_status_id' => LocalStatus::first()->id,
            'local_location_id' => LocalLocation::first()->id,
            'area_m2' => 1.00,
            'is_active' => true,
        ]);
    }

    public function test_locals_lookup_searches_by_code_only_and_respects_limit(): void
    {
        $m = $this->createMarket();
        $this->createLocal($m, 'AM-101', 'Alpha Uno');
        $this->createLocal($m, 'AM-102', 'Bravo Dos');

        // Search by code returns
        $resp1 = $this->actingAs($this->admin)->get('/condo/lookup/locals?market_id='.$m->getKey().'&q=AM-10&limit=1');
        $resp1->assertOk();
        $data1 = $resp1->json();
        $this->assertIsArray($data1['items'] ?? []);
        $this->assertCount(1, $data1['items']); // limit respected

        // Search by name should not match (code-only search)
        $resp2 = $this->actingAs($this->admin)->get('/condo/lookup/locals?market_id='.$m->getKey().'&q=Alpha');
        $resp2->assertOk();
        $data2 = $resp2->json();
        $this->assertSame([], $data2['items'] ?? []);
    }
}
