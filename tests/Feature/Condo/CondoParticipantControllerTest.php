<?php

declare(strict_types=1);

namespace Tests\Feature\Condo;

use App\Models\CondoParticipant;
use App\Models\CondoPeriod;
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

class CondoParticipantControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $perms = [
            'condo_period.view',
            'condo_period.update',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $this->admin = User::factory()->create();
        $role = SpatieRole::create(['name' => 'condo_admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());
        $this->admin->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Minimal catalogs
        LocalType::firstOrCreate(['code' => 'LOC'], ['name' => 'Local', 'is_active' => true]);
        LocalStatus::firstOrCreate(['code' => 'DISP'], ['name' => 'Disponible', 'is_active' => true]);
        LocalStatus::firstOrCreate(['code' => 'OCUP'], ['name' => 'Ocupado', 'is_active' => true]);
        LocalLocation::firstOrCreate(['code' => 'INT'], ['name' => 'Interior', 'is_active' => true]);
    }

    private function createMarket(): Market
    {
        return Market::create(['code' => 'MKT', 'name' => 'Sede', 'address' => 'X', 'is_active' => true]);
    }

    private function createPeriod(Market $m, string $status = 'DRAFT'): CondoPeriod
    {
        return CondoPeriod::create([
            'market_id' => $m->getKey(),
            'period' => now()->startOfMonth()->format('Y-m-d'),
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function createLocal(Market $m, string $code, string $statusCode = 'DISP'): Local
    {
        $status = LocalStatus::where('code', $statusCode)->firstOrFail();

        return Local::create([
            'code' => $code,
            'name' => $code,
            'market_id' => $m->getKey(),
            'local_type_id' => LocalType::first()->id,
            'local_status_id' => $status->id,
            'local_location_id' => LocalLocation::first()->id,
            'area_m2' => 2.50,
            'is_active' => true,
        ]);
    }

    public function test_index_lists_excluded_participants(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m);
        $l = $this->createLocal($m, 'AM-01');
        CondoParticipant::create([
            'condo_period_id' => $p->getKey(),
            'local_id' => $l->getKey(),
            'area_m2_snapshot' => '2.50',
            'included' => false,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->admin)->get(route('condo.periods.participants.index', ['condo_period' => $p->getKey()]));
        $resp->assertOk()->assertJsonStructure(['rows', 'meta' => ['total', 'pageIndex', 'pageSize']]);
    }

    public function test_store_excludes_selected_locals(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m);
        $l1 = $this->createLocal($m, 'A-01');
        $l2 = $this->createLocal($m, 'B-01');

        $resp = $this->actingAs($this->admin)->post(route('condo.periods.participants.store', ['condo_period' => $p->getKey()]), [
            'local_ids' => [$l1->getKey(), $l2->getKey()],
        ]);
        $resp->assertOk()->assertJsonStructure(['success', 'totals' => ['participants_count']]);

        $this->assertDatabaseHas('condo_participants', ['condo_period_id' => $p->getKey(), 'local_id' => $l1->getKey(), 'included' => false]);
        $this->assertDatabaseHas('condo_participants', ['condo_period_id' => $p->getKey(), 'local_id' => $l2->getKey(), 'included' => false]);
    }

    public function test_store_blocks_when_final(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m, 'FINAL');
        $l = $this->createLocal($m, 'C-01');

        $resp = $this->actingAs($this->admin)->post(route('condo.periods.participants.store', ['condo_period' => $p->getKey()]), [
            'local_ids' => [$l->getKey()],
        ]);
        $resp->assertStatus(422)->assertJsonFragment(['message' => 'El período está finalizado y no puede modificarse.']);
    }

    public function test_exclude_all_only_excludes_available_locals(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m);
        $l1 = $this->createLocal($m, 'D-01', 'DISP');
        $l2 = $this->createLocal($m, 'E-01', 'DISP');
        $l3 = $this->createLocal($m, 'F-01', 'OCUP');

        $resp = $this->actingAs($this->admin)->post(route('condo.periods.participants.excludeAll', ['condo_period' => $p->getKey()]));
        $resp->assertOk()->assertJsonStructure(['success', 'totals' => ['participants_count']]);

        $this->assertDatabaseHas('condo_participants', ['condo_period_id' => $p->getKey(), 'local_id' => $l1->getKey(), 'included' => false]);
        $this->assertDatabaseHas('condo_participants', ['condo_period_id' => $p->getKey(), 'local_id' => $l2->getKey(), 'included' => false]);
        $this->assertDatabaseMissing('condo_participants', ['condo_period_id' => $p->getKey(), 'local_id' => $l3->getKey()]);
    }

    public function test_destroy_includes_participant(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m);
        $l = $this->createLocal($m, 'G-01');
        $cp = CondoParticipant::create([
            'condo_period_id' => $p->getKey(),
            'local_id' => $l->getKey(),
            'area_m2_snapshot' => '2.50',
            'included' => false,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->admin)->delete(route('condo.participants.destroy', ['condo_participant' => $cp->getKey()]));
        $resp->assertOk();
        $this->assertSoftDeleted('condo_participants', ['id' => $cp->getKey()]);
    }

    public function test_destroy_blocks_when_final(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m, 'FINAL');
        $l = $this->createLocal($m, 'H-01');
        $cp = CondoParticipant::create([
            'condo_period_id' => $p->getKey(),
            'local_id' => $l->getKey(),
            'area_m2_snapshot' => '2.50',
            'included' => false,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->admin)->delete(route('condo.participants.destroy', ['condo_participant' => $cp->getKey()]));
        $resp->assertStatus(422)->assertJsonFragment(['message' => 'El período está finalizado y no puede modificarse.']);
    }

    public function test_index_search_filters_by_code_or_name(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m);
        $l1 = $this->createLocal($m, 'AM-03');
        $l2 = $this->createLocal($m, 'D-03');
        foreach ([$l1, $l2] as $lx) {
            CondoParticipant::create([
                'condo_period_id' => $p->getKey(),
                'local_id' => $lx->getKey(),
                'area_m2_snapshot' => '2.27',
                'included' => false,
                'is_active' => true,
            ]);
        }

        $resp = $this->actingAs($this->admin)->get(route('condo.periods.participants.index', ['condo_period' => $p->getKey(), 'q' => '03']));
        $resp->assertOk();
        $body = $resp->json();
        $this->assertGreaterThanOrEqual(2, $body['meta']['total'] ?? 0);
    }
}
