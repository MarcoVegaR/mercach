<?php

declare(strict_types=1);

use App\Contracts\Services\Charges\ChargesOrchestratorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginAdminCharges(): void
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\MarketsSeeder::class,
    ]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    test()->actingAs($admin);
}

it('requires period and market for CONDO_USD', function () {
    loginAdminCharges();
    $market = \App\Models\Market::create(['code' => 'M-COND', 'name' => 'Market Condo', 'address' => 'X', 'is_active' => true]);

    // Missing period
    $res1 = $this->post(route('charges.run.execute'), [
        'type' => 'CONDO_USD',
        'market_id' => $market->id,
    ]);
    $res1->assertSessionHasErrors();

    // Missing market
    $res2 = $this->post(route('charges.run.execute'), [
        'type' => 'CONDO_USD',
        'period' => '2025-10-01',
    ]);
    $res2->assertSessionHasErrors();
});

it('runs CONDO_USD with preflight passing and aggregates success message', function () {
    loginAdminCharges();

    // Market + Local to ensure area > 0
    $market = \App\Models\Market::create(['code' => 'M-COND2', 'name' => 'Market Condo 2', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT', 'name' => 'LT', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LST', 'name' => 'LST', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LOC', 'name' => 'LOC', 'is_active' => true]);
    \App\Models\Local::create([
        'code' => 'L-COND-1', 'name' => 'Local Condo 1',
        'market_id' => $market->id, 'local_type_id' => $lt->id, 'local_status_id' => $ls->id, 'local_location_id' => $ll->id,
        'area_m2' => 12.5, 'is_active' => true,
    ]);

    // Condo period FINAL with some expenses
    $period = '2025-10-01';
    $condo = \App\Models\CondoPeriod::create([
        'market_id' => $market->id,
        'period' => $period,
        'status' => 'FINAL',
        'is_active' => true,
    ]);
    \App\Models\CondoExpense::create([
        'condo_period_id' => $condo->getKey(),
        'expense_type_id' => \DB::table('expense_types')->first()->id ?? \App\Models\ExpenseType::create(['code' => 'GEN', 'name' => 'General', 'is_active' => true])->id,
        'amount_usd_minor' => 10000,
        'invoice_number' => 'INV-1',
        'is_active' => true,
    ]);

    // Mock orchestrator to capture call and return a result
    $this->mock(ChargesOrchestratorInterface::class, function ($m) {
        $m->shouldReceive('run')->andReturn([
            'generated' => 2,
            'upserted' => 0,
            'skipped' => 0,
            'errors' => [],
            'totalMinor' => 10000,
            'unitMinor' => 800, // cost per m2 to be appended
        ]);
    });

    $res = $this->post(route('charges.run.execute'), [
        'type' => 'CONDO_USD',
        'market_id' => $market->id,
        'period' => $period,
        'idempotency_key' => 'test-key',
    ]);
    $res->assertRedirect(route('charges.index'));
    $res->assertSessionHas('success');
});
