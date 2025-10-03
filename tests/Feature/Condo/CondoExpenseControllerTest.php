<?php

declare(strict_types=1);

namespace Tests\Feature\Condo;

use App\Models\CondoExpense;
use App\Models\CondoPeriod;
use App\Models\ExpenseType;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class CondoExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions used by condo expenses endpoints
        $perms = [
            'condo_period.view',
            'condo_period.update',
            'condo_period.finalize',
            'condo_period.reopen',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        $role = SpatieRole::create(['name' => 'condo_admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());
        $this->admin->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Minimal catalog to satisfy FKs when needed
        ExpenseType::firstOrCreate(['code' => 'GEN'], ['name' => 'General', 'is_active' => true]);
        LocalType::firstOrCreate(['code' => 'LOC'], ['name' => 'Local', 'is_active' => true]);
        LocalStatus::firstOrCreate(['code' => 'DISP'], ['name' => 'Disponible', 'is_active' => true]);
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

    public function test_index_lists_expenses_json(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m);
        CondoExpense::create([
            'condo_period_id' => $p->getKey(),
            'expense_type_id' => ExpenseType::first()->id,
            'amount_usd_minor' => 123_45,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->admin)->get(route('condo.periods.expenses.index', ['condo_period' => $p->getKey()]));
        $resp->assertOk();
        $resp->assertJsonStructure(['rows', 'meta' => ['total', 'pageIndex', 'pageSize']]);
    }

    public function test_store_blocks_when_final(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m, 'FINAL');

        $resp = $this->actingAs($this->admin)->postJson(route('condo.periods.expenses.store', ['condo_period' => $p->getKey()]), [
            'expense_type_id' => ExpenseType::first()->id,
            'amount_usd' => '10.00',
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonFragment(['message' => 'El período está finalizado y no puede modificarse.']);
    }

    public function test_store_creates_expense_and_returns_totals(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m);

        $resp = $this->actingAs($this->admin)->postJson(route('condo.periods.expenses.store', ['condo_period' => $p->getKey()]), [
            'expense_type_id' => ExpenseType::first()->id,
            'amount_usd' => '15.50',
        ]);
        $resp->assertOk();
        $resp->assertJsonStructure(['success', 'id', 'totals' => ['expenses_count', 'total_usd_minor']]);
        $this->assertDatabaseHas('condo_expenses', ['condo_period_id' => $p->getKey(), 'amount_usd_minor' => 1550]);
    }

    public function test_update_blocks_when_final(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m);
        $e = CondoExpense::create([
            'condo_period_id' => $p->getKey(),
            'expense_type_id' => ExpenseType::first()->id,
            'amount_usd_minor' => 500,
            'is_active' => true,
        ]);
        $p->update(['status' => 'FINAL']);

        $resp = $this->actingAs($this->admin)->putJson(route('condo.expenses.update', ['condo_expense' => $e->getKey()]), [
            'expense_type_id' => ExpenseType::first()->id,
            'amount_usd' => '7.00',
        ]);
        $resp->assertStatus(422)->assertJsonFragment(['message' => 'El período está finalizado y no puede modificarse.']);
    }

    public function test_destroy_deletes_and_returns_totals(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriod($m);
        $e = CondoExpense::create([
            'condo_period_id' => $p->getKey(),
            'expense_type_id' => ExpenseType::first()->id,
            'amount_usd_minor' => 500,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->admin)->delete(route('condo.expenses.destroy', ['condo_expense' => $e->getKey()]));
        $resp->assertOk();
        $resp->assertJsonStructure(['success', 'totals' => ['expenses_count', 'total_usd_minor']]);
        $this->assertSoftDeleted('condo_expenses', ['id' => $e->getKey()]);
    }
}
