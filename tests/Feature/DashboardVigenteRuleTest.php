<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DashboardService;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardVigenteRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_available_uses_not_exists_vigente_rule(): void
    {
        // Seed minimal catalogs required for FKs
        $this->seed(\Database\Seeders\MarketsSeeder::class);
        $this->seed(\Database\Seeders\LocalTypesSeeder::class);
        $this->seed(\Database\Seeders\LocalStatusesSeeder::class);
        $this->seed(\Database\Seeders\LocalLocationSeeder::class);
        $this->seed(\Database\Seeders\ContractStatusesSeeder::class);

        // Resolve catalog IDs
        $marketId = (int) DB::table('markets')->value('id');
        $localTypeId = (int) DB::table('local_types')->value('id');
        $localStatusId = (int) DB::table('local_statuses')->where('code', 'DISP')->value('id');
        $localLocationId = (int) DB::table('local_locations')->value('id');
        $statusVigId = (int) DB::table('contract_statuses')->where('code', 'VIG')->value('id');

        // Create two locals
        $local1Id = (int) DB::table('locals')->insertGetId([
            'code' => 'L-100',
            'name' => 'Local 100',
            'market_id' => $marketId,
            'local_type_id' => $localTypeId,
            'local_status_id' => $localStatusId,
            'local_location_id' => $localLocationId,
            'area_m2' => 10.0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $local2Id = (int) DB::table('locals')->insertGetId([
            'code' => 'L-200',
            'name' => 'Local 200',
            'market_id' => $marketId,
            'local_type_id' => $localTypeId,
            'local_status_id' => $localStatusId,
            'local_location_id' => $localLocationId,
            'area_m2' => 12.0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fix reference date
        Carbon::setTestNow(Carbon::parse('2025-09-30'));
        $today = Carbon::now()->toDateString();

        // Create a vigente contract attached to local1
        // Also seed required contract catalogs to satisfy FKs
        $this->seed(\Database\Seeders\ContractTypesSeeder::class);
        $this->seed(\Database\Seeders\ContractModalitiesSeeder::class);
        $this->seed(\Database\Seeders\TradeCategoriesSeeder::class);

        $contractTypeId = (int) DB::table('contract_types')->value('id');
        $contractModalityId = (int) DB::table('contract_modalities')->value('id');
        $tradeCategoryId = (int) DB::table('trade_categories')->value('id');

        $contractId = (int) DB::table('contracts')->insertGetId([
            'number' => 'C-001',
            'contract_type_id' => $contractTypeId,
            'contract_status_id' => $statusVigId,
            'contract_modality_id' => $contractModalityId,
            'trade_category_id' => $tradeCategoryId,
            'start_date' => $today,
            'end_date' => null,
            'billing_day' => null,
            'monthly_price_eur' => null,
            'pdf_path' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contract_local')->insert([
            'contract_id' => $contractId,
            'local_id' => $local1Id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run service
        $service = app(DashboardService::class);
        Cache::flush();
        $kpis = $service->getKpis();

        // local1 has vigente contract, local2 no: available should be 1
        $this->assertArrayHasKey('locals', $kpis);
        $this->assertSame(1, (int) ($kpis['locals']['available'] ?? -1));
    }
}
