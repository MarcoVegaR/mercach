<?php

declare(strict_types=1);

use App\Services\Charges\CondoUsdCalculator;
use App\Services\Charges\RentFixedCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedChargeDueDatePrerequisites(): void
{
    test()->seed([
        Database\Seeders\ContractStatusesSeeder::class,
        Database\Seeders\ContractModalitiesSeeder::class,
        Database\Seeders\ContractTypesSeeder::class,
        Database\Seeders\TradeCategoriesSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\ExpenseTypesSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
    ]);
}

it('generates RENT_EUR_FIXED with due_on on day 6 of the month', function () {
    seedChargeDueDatePrerequisites();

    $market = \App\Models\Market::query()->firstOrFail();
    $localType = \App\Models\LocalType::query()->firstOrFail();
    $localStatus = \App\Models\LocalStatus::query()->firstOrFail();
    $localLocation = \App\Models\LocalLocation::query()->firstOrFail();

    $local = \App\Models\Local::create([
        'code' => 'DUE-FIX-01',
        'name' => 'Local Due Fixed',
        'market_id' => $market->id,
        'local_type_id' => $localType->id,
        'local_status_id' => $localStatus->id,
        'local_location_id' => $localLocation->id,
        'area_m2' => 12,
        'is_active' => true,
    ]);

    $statusVigId = (int) (DB::table('contract_statuses')->where('code', 'VIG')->value('id') ?? 0);
    $modFixedId = (int) (DB::table('contract_modalities')->where('code', 'TFIJA')->value('id') ?? 0);
    $typeContrId = (int) (DB::table('contract_types')->where('code', 'CONTR')->value('id') ?? 0);
    $tradeCategoryId = (int) (DB::table('trade_categories')->value('id') ?? 0);

    expect($statusVigId)->toBeGreaterThan(0);
    expect($modFixedId)->toBeGreaterThan(0);
    expect($typeContrId)->toBeGreaterThan(0);
    expect($tradeCategoryId)->toBeGreaterThan(0);

    $contractId = DB::table('contracts')->insertGetId([
        'number' => 'DUE-FIX-C-001',
        'contract_status_id' => $statusVigId,
        'contract_modality_id' => $modFixedId,
        'contract_type_id' => $typeContrId,
        'trade_category_id' => $tradeCategoryId,
        'start_date' => '2026-01-01',
        'end_date' => null,
        'billing_day' => 20,
        'monthly_price_eur' => 500,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('contract_local')->insert([
        'contract_id' => $contractId,
        'local_id' => $local->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rows = app(RentFixedCalculator::class)->calculate([
        'period' => '2026-02-01',
    ]);

    expect($rows)->not->toBeEmpty();
    expect(collect($rows)->pluck('due_on')->unique()->values()->all())->toBe(['2026-02-06']);
});

it('generates CONDO_USD with due_on on day 6 of the month', function () {
    seedChargeDueDatePrerequisites();

    $market = \App\Models\Market::query()->firstOrFail();
    $localType = \App\Models\LocalType::query()->firstOrFail();
    $localStatus = \App\Models\LocalStatus::query()->firstOrFail();
    $localLocation = \App\Models\LocalLocation::query()->firstOrFail();

    \App\Models\Local::create([
        'code' => 'DUE-CONDO-01',
        'name' => 'Local Due Condo',
        'market_id' => $market->id,
        'local_type_id' => $localType->id,
        'local_status_id' => $localStatus->id,
        'local_location_id' => $localLocation->id,
        'area_m2' => 10,
        'is_active' => true,
    ]);

    $periodStart = '2026-02-01';
    $condoPeriodId = DB::table('condo_periods')->insertGetId([
        'market_id' => $market->id,
        'period' => $periodStart,
        'status' => 'FINAL',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $expenseTypeId = (int) (DB::table('expense_types')->value('id') ?? 0);
    expect($expenseTypeId)->toBeGreaterThan(0);

    DB::table('condo_expenses')->insert([
        'condo_period_id' => $condoPeriodId,
        'expense_type_id' => $expenseTypeId,
        'amount_usd_minor' => 120000,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rows = app(CondoUsdCalculator::class)->calculate([
        'market_id' => $market->id,
        'period' => $periodStart,
    ]);

    expect($rows)->not->toBeEmpty();
    expect(collect($rows)->pluck('due_on')->unique()->values()->all())->toBe(['2026-02-06']);
});
