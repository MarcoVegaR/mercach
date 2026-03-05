<?php

declare(strict_types=1);

use App\Contracts\Services\Charges\ChargesOrchestratorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function loginAdminChargesRent(): void
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\TradeCategoriesSeeder::class,
        Database\Seeders\ContractStatusesSeeder::class,
        Database\Seeders\ContractModalitiesSeeder::class,
        Database\Seeders\ContractTypesSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
    ]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    test()->actingAs($admin);
}

it('runs RENT_EUR_M2 when market has current tariff and eligible contracts exist', function () {
    loginAdminChargesRent();

    $period = '2026-02-15'; // fixed period for deterministic eligibility checks
    $periodStart = \Illuminate\Support\Carbon::parse($period)->startOfMonth()->toDateString();

    $market = \App\Models\Market::create(['code' => 'M-RM2', 'name' => 'Market M2', 'address' => 'X', 'is_active' => true]);

    // Current tariff > 0
    DB::table('market_tariffs')->insert([
        'market_id' => $market->id,
        'valid_from' => $periodStart,
        'price_per_m2_eur_minor' => 1000,
        'is_current' => true,
        'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // One eligible M2 contract
    $lt = \App\Models\LocalType::create(['code' => 'LT-M2', 'name' => 'LT-M2', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LS-M2', 'name' => 'LS-M2', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LL-M2', 'name' => 'LL-M2', 'is_active' => true]);
    $local = \App\Models\Local::create([
        'code' => 'L-M2-1', 'name' => 'Local M2 1',
        'market_id' => $market->id,
        'local_type_id' => $lt->id,
        'local_status_id' => $ls->id,
        'local_location_id' => $ll->id,
        'area_m2' => 12.0,
        'is_active' => true,
    ]);

    $statusId = (int) DB::table('contract_statuses')->where('code', 'VIG')->value('id');
    $modM2 = (int) DB::table('contract_modalities')->where('code', 'M2')->value('id');
    $typeConv = (int) DB::table('contract_types')->where('code', 'CONV')->value('id');
    $tradeCat = (int) DB::table('trade_categories')->value('id');

    $contractId = DB::table('contracts')->insertGetId([
        'number' => 'M2-001',
        'contract_status_id' => $statusId,
        'contract_modality_id' => $modM2,
        'contract_type_id' => $typeConv,
        'trade_category_id' => $tradeCat,
        'start_date' => $periodStart,
        'end_date' => null,
        'monthly_price_eur' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('contract_local')->insert([
        'contract_id' => $contractId,
        'local_id' => $local->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Mock orchestrator
    $this->mock(ChargesOrchestratorInterface::class, function ($m) {
        $m->shouldReceive('run')->andReturn([
            'generated' => 5,
            'upserted' => 0,
            'skipped' => 0,
            'errors' => [],
            'totalMinor' => 12345,
        ]);
    });

    $res = $this->post(route('charges.run.execute'), [
        'type' => 'RENT_EUR_M2',
        'market_id' => $market->id,
        'period' => $period,
    ]);
    $res->assertRedirect(route('charges.index'));
    $res->assertSessionHas('success');
});

it('preflight blocks RENT_EUR_M2 when no current tariff', function () {
    loginAdminChargesRent();

    $market = \App\Models\Market::create(['code' => 'M-NOT', 'name' => 'Market No Tariff', 'address' => 'X', 'is_active' => true]);

    $res = $this->post(route('charges.run.execute'), [
        'type' => 'RENT_EUR_M2',
        'market_id' => $market->id,
        'period' => now()->toDateString(),
    ]);
    $res->assertSessionHasErrors();
});

it('runs RENT_EUR_FIXED with eligible contracts and no market required', function () {
    loginAdminChargesRent();

    $period = '2026-02-15'; // fixed period for deterministic eligibility checks
    $periodStart = \Illuminate\Support\Carbon::parse($period)->startOfMonth()->toDateString();

    // Minimal context for local
    $market = \App\Models\Market::create(['code' => 'M-FIX', 'name' => 'Market Fixed', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT-FIX', 'name' => 'LT-FIX', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LS-FIX', 'name' => 'LS-FIX', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LL-FIX', 'name' => 'LL-FIX', 'is_active' => true]);
    $local = \App\Models\Local::create([
        'code' => 'L-FIX-1', 'name' => 'Local FIX 1', 'market_id' => $market->id,
        'local_type_id' => $lt->id, 'local_status_id' => $ls->id, 'local_location_id' => $ll->id,
        'area_m2' => 15.0, 'is_active' => true,
    ]);

    $statusId = (int) DB::table('contract_statuses')->where('code', 'VIG')->value('id');
    $modTF = (int) DB::table('contract_modalities')->where('code', 'TFIJA')->value('id');
    $typeContr = (int) DB::table('contract_types')->where('code', 'CONTR')->value('id');
    $tradeCat = (int) DB::table('trade_categories')->value('id');

    $contractId = DB::table('contracts')->insertGetId([
        'number' => 'FX-001',
        'contract_status_id' => $statusId,
        'contract_modality_id' => $modTF,
        'contract_type_id' => $typeContr,
        'trade_category_id' => $tradeCat,
        'start_date' => $periodStart,
        'end_date' => null,
        'monthly_price_eur' => 500.00,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('contract_local')->insert([
        'contract_id' => $contractId,
        'local_id' => $local->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Mock orchestrator
    $this->mock(ChargesOrchestratorInterface::class, function ($m) {
        $m->shouldReceive('run')->andReturn([
            'generated' => 3,
            'upserted' => 1,
            'skipped' => 0,
            'errors' => [],
            'totalMinor' => 50000,
        ]);
    });

    $res = $this->post(route('charges.run.execute'), [
        'type' => 'RENT_EUR_FIXED',
        'period' => $period,
    ]);
    $res->assertRedirect(route('charges.index'));
    $res->assertSessionHas('success');
});
