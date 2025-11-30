<?php

declare(strict_types=1);

namespace Tests\Feature\Condo;

use App\Models\CondoExpense;
use App\Models\CondoPeriod;
use App\Models\ExpenseType;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class CondoPeriodControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions for condo module
        $perms = [
            'condo_period.view',
            'condo_period.create',
            'condo_period.update',
            'condo_period.delete',
            'condo_period.export',
            'condo_period.finalize',
            'condo_period.reopen',
            'condo_period.setActive',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Admin with all
        $this->admin = User::factory()->create();
        $role = SpatieRole::create(['name' => 'condo_admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());
        $this->admin->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Minimal catalog: ExpenseType for FK
        ExpenseType::firstOrCreate(['code' => 'GEN'], ['name' => 'General', 'is_active' => true]);
    }

    private function createMarket(): Market
    {
        return Market::create([
            'code' => 'MKT-1',
            'name' => 'Mercado de Chacao',
            'address' => 'X',
            'is_active' => true,
        ]);
    }

    private function createPeriodDraft(Market $market): CondoPeriod
    {
        return CondoPeriod::create([
            'market_id' => $market->getKey(),
            'period' => now()->startOfMonth()->format('Y-m-d'),
            'status' => 'DRAFT',
            'is_active' => true,
        ]);
    }

    public function test_index_shows_periods_with_stats_excluding_soft_deleted(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriodDraft($m);
        // Two expenses, one soft-deleted
        CondoExpense::create([
            'condo_period_id' => $p->getKey(),
            'expense_type_id' => ExpenseType::first()->id,
            'amount_usd_minor' => 100_00,
            'is_active' => true,
        ]);
        $e2 = CondoExpense::create([
            'condo_period_id' => $p->getKey(),
            'expense_type_id' => ExpenseType::first()->id,
            'amount_usd_minor' => 50_00,
            'is_active' => true,
        ]);
        $e2->delete();

        $resp = $this->actingAs($this->admin)->get('/condo/periods');
        $resp->assertOk();
        $resp->assertInertia(fn (AssertableInertia $page) => $page
            ->component('condo/periods/index')
            ->has('stats', fn (AssertableInertia $s) => $s
                ->where('total_usd_minor', 100_00)
                ->etc()
            )
        );
    }

    public function test_show_workspace_renders(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriodDraft($m);

        $resp = $this->actingAs($this->admin)->get(route('condo.periods.show', ['condo_period' => $p->getKey()]));
        $resp->assertOk();
        $resp->assertInertia(fn (AssertableInertia $page) => $page->component('condo/periods/workspace'));
    }

    public function test_finalize_and_reopen_routes(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriodDraft($m);

        // Need at least one expense to finalize
        CondoExpense::create([
            'condo_period_id' => $p->getKey(),
            'expense_type_id' => ExpenseType::first()->id,
            'amount_usd_minor' => 500,
            'is_active' => true,
        ]);

        $final = $this->actingAs($this->admin)->post(route('condo.periods.finalize', ['condo_period' => $p->getKey()]));
        $final->assertRedirect();

        $p->refresh();
        $this->assertEquals('FINAL', $p->getAttribute('status'));

        $reopen = $this->actingAs($this->admin)->post(route('condo.periods.reopen', ['condo_period' => $p->getKey()]));
        $reopen->assertRedirect();
        $p->refresh();
        $this->assertEquals('DRAFT', $p->getAttribute('status'));
    }

    public function test_set_active_toggles(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriodDraft($m);

        $resp = $this->actingAs($this->admin)->patch(route('condo.periods.setActive', ['condo_period' => $p->getKey()]), ['active' => false]);
        $resp->assertRedirect();
        $p->refresh();
        $this->assertFalse((bool) $p->getAttribute('is_active'));
    }

    public function test_destroy_forbidden_when_final(): void
    {
        $m = $this->createMarket();
        $p = $this->createPeriodDraft($m);
        $p->update(['status' => 'FINAL']);

        $resp = $this->actingAs($this->admin)->delete(route('condo.periods.destroy', ['condo_period' => $p->getKey()]));
        $resp->assertForbidden();
    }
}
