<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\DocumentType;
use App\Models\Local as LocalModel;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\TradeCategory;
use App\Services\DashboardService;
use App\Services\DebtAnalysisService;
use App\Services\EconomicProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('slow')]
class DebtReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private ?int $marketId = null;

    private ?int $ocupId = null;

    private ?int $vigId = null;

    private ?int $issuedId = null;

    private ?int $localTypeId = null;

    private ?int $localLocationId = null;

    private ?int $concessionaireTypeId = null;

    private ?int $documentTypeId = null;

    private ?int $contractTypeId = null;

    private ?int $contractModalityId = null;

    private ?int $tradeCategoryId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed only minimal catalogs needed for the test
        $this->seedMinimalCatalogs();
    }

    public function test_dashboard_total_overdue_matches_sum_of_charges_and_locals(): void
    {
        // Create test data programmatically instead of using full seeders
        $this->createTestDebtScenario();

        $dashboard = app(DashboardService::class);
        $metrics = $dashboard->getDebtMetrics();

        $totalOverdueEur = (int) ($metrics['total_overdue_eur_minor'] ?? 0);
        $totalOverdueUsd = (int) ($metrics['total_overdue_usd_minor'] ?? 0);

        $sumFromDb = (int) \DB::table('charges as ch')
            ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
            ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
            ->where('ch.due_on', '<', now()->toDateString())
            ->where('ch.currency', 'EUR')
            ->whereNull('ch.deleted_at')
            ->sum('ch.amount_minor');

        $this->assertSame($sumFromDb, $totalOverdueEur);

        $groupedByLocal = \DB::table('charges as ch')
            ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
            ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
            ->where('ch.due_on', '<', now()->toDateString())
            ->whereNull('ch.deleted_at')
            ->where('ch.debtor_type', 'LOCAL')
            ->where('ch.currency', 'EUR')
            ->groupBy('ch.debtor_id')
            ->selectRaw('ch.debtor_id, SUM(ch.amount_minor)::bigint as sum_eur_minor')
            ->get();

        $sumGrouped = (int) $groupedByLocal->sum('sum_eur_minor');
        $this->assertSame($sumFromDb, $sumGrouped);

        // USD split (condo vs fixed) should always reconcile to total USD overdue.
        $usdCondo = (int) ($metrics['total_overdue_usd_condo_minor'] ?? 0);
        $usdFixed = (int) ($metrics['total_overdue_usd_rent_fixed_minor'] ?? 0);
        $this->assertSame($totalOverdueUsd, $usdCondo + $usdFixed);
    }

    public function test_debt_analysis_rows_match_economic_profile_for_sample_local_and_concessionaire(): void
    {
        // Create test data programmatically
        $this->createTestDebtScenario();

        $economicProfile = app(EconomicProfileService::class);
        $debtAnalysis = app(DebtAnalysisService::class);

        $today = now()->startOfDay();

        // Tomar un local moroso directamente desde el análisis de deuda
        $localsData = $debtAnalysis->getDelinquentLocals(['page' => 1, 'per_page' => 1]);
        $localRows = $localsData['data'] ?? [];

        $this->assertNotEmpty($localRows);

        $rowLocal = $localRows[0];
        $localId = (int) ($rowLocal['id'] ?? 0);

        $this->assertGreaterThan(0, $localId);

        $profileLocal = $economicProfile->forLocal($localId, $today, ['overdue_only' => true]);
        $summaryBsLocal = $profileLocal['summary_bs'] ?? [];
        $summaryFxLocal = $profileLocal['summary_fx'] ?? [];

        $overdueBsLocal = (int) ($summaryBsLocal['overdue_bs_minor'] ?? 0);
        $overdueEurLocal = 0;
        if (isset($summaryFxLocal['rent']) && ($summaryFxLocal['rent']['currency'] ?? null) === 'EUR') {
            $overdueEurLocal = (int) ($summaryFxLocal['rent']['overdue_minor'] ?? 0);
        }

        $this->assertSame($overdueBsLocal, (int) ($rowLocal['debt_bs_minor'] ?? 0));
        $this->assertSame($overdueEurLocal, (int) ($rowLocal['debt_eur_minor'] ?? 0));

        // Tomar un concesionario moroso directamente desde el análisis de deuda
        $conData = $debtAnalysis->getDelinquentConcessionaires(['page' => 1, 'per_page' => 1]);
        $conRows = $conData['data'] ?? [];

        $this->assertNotEmpty($conRows);

        $rowCon = $conRows[0];
        $concessionaireId = (int) ($rowCon['id'] ?? 0);

        $this->assertGreaterThan(0, $concessionaireId);

        $profileCon = $economicProfile->forConcessionaire($concessionaireId, $today, ['overdue_only' => true]);
        $summaryBsCon = $profileCon['summary_bs'] ?? [];
        $summaryFxCon = $profileCon['summary_fx'] ?? [];

        $overdueBsCon = (int) ($summaryBsCon['overdue_bs_minor'] ?? 0);
        $overdueEurCon = 0;
        if (isset($summaryFxCon['rent']) && ($summaryFxCon['rent']['currency'] ?? null) === 'EUR') {
            $overdueEurCon = (int) ($summaryFxCon['rent']['overdue_minor'] ?? 0);
        }

        $this->assertSame($overdueBsCon, (int) ($rowCon['debt_bs_minor'] ?? 0));
        $this->assertSame($overdueEurCon, (int) ($rowCon['debt_eur_minor'] ?? 0));
    }

    public function test_delinquent_locals_returns_single_row_per_local_even_with_multiple_contracts(): void
    {
        $this->createTestDebtScenario();

        $targetLocal = LocalModel::where('code', 'TEST-01')->first();
        $this->assertNotNull($targetLocal);

        $altConcessionaire = Concessionaire::create([
            'concessionaire_type_id' => $this->concessionaireTypeId,
            'document_type_id' => $this->documentTypeId,
            'document_number' => '87654321',
            'full_name' => 'Second Concessionaire',
            'fiscal_address' => 'Second Address',
            'email' => 'second@example.com',
            'is_active' => true,
        ]);

        $altContract = Contract::create([
            'number' => 'TEST-C002',
            'contract_type_id' => $this->contractTypeId,
            'contract_modality_id' => $this->contractModalityId,
            'trade_category_id' => $this->tradeCategoryId,
            'contract_status_id' => $this->vigId,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'signed_at' => now()->subMonths(3),
        ]);

        $altContract->concessionaires()->attach($altConcessionaire->id, ['is_primary' => true]);
        $altContract->locals()->attach([(int) $targetLocal->id]);

        $period = now()->startOfMonth()->subMonthsNoOverflow(2);
        Charge::create([
            'debtor_type' => 'LOCAL',
            'debtor_id' => $targetLocal->id,
            'origin_debtor_type' => 'LOCAL',
            'origin_debtor_id' => $targetLocal->id,
            'local_id' => $targetLocal->id,
            'contract_id' => $altContract->id,
            'market_id' => $this->marketId,
            'charge_status_id' => $this->issuedId,
            'currency' => 'EUR',
            'kind' => 'ADJ',
            'source' => 'MANUAL',
            'period' => $period->format('Y-m-01'),
            'issued_on' => $period->format('Y-m-01'),
            'due_on' => $period->copy()->addDays(5)->toDateString(),
            'amount_minor' => 10000,
            'amount_bs_minor_issued' => 10000,
            'idempotency_key' => 'test-dup-local-charge',
        ]);

        $debtAnalysis = app(DebtAnalysisService::class);
        $rows = $debtAnalysis->getDelinquentLocals(['page' => 1, 'per_page' => 100])['data'] ?? [];

        $rowsForLocal = array_values(array_filter($rows, fn ($row) => (int) ($row['id'] ?? 0) === (int) $targetLocal->id));

        $this->assertCount(1, $rowsForLocal);
    }

    public function test_debt_analysis_and_dashboard_do_not_duplicate_local_debt_for_secondary_signers(): void
    {
        $this->createTestDebtScenario();

        $contract = Contract::where('number', 'TEST-C001')->first();
        $primary = Concessionaire::where('document_number', '12345678')->first();

        $this->assertNotNull($contract);
        $this->assertNotNull($primary);

        $secondary = Concessionaire::create([
            'concessionaire_type_id' => $this->concessionaireTypeId,
            'document_type_id' => $this->documentTypeId,
            'document_number' => '22334455',
            'full_name' => 'Secondary Signer',
            'fiscal_address' => 'Secondary Address',
            'email' => 'secondary-signer@example.com',
            'is_active' => true,
        ]);

        $contract->concessionaires()->attach($secondary->id, ['is_primary' => false]);

        $debtAnalysis = app(DebtAnalysisService::class);
        $payload = $debtAnalysis->getDelinquentConcessionaires(['page' => 1, 'per_page' => 100]);
        $rows = collect($payload['data'] ?? []);

        $this->assertSame(300000, (int) ($payload['summary']['total_debt_eur_minor'] ?? 0));
        $this->assertCount(1, $rows->where('id', (int) $primary->id)->values());
        $this->assertCount(0, $rows->where('id', (int) $secondary->id)->values());

        $primaryRow = $rows->firstWhere('id', (int) $primary->id);
        $this->assertSame(300000, (int) ($primaryRow['debt_eur_minor'] ?? 0));
        $this->assertSame(5, (int) ($primaryRow['charges_count'] ?? 0));

        $dashboard = app(DashboardService::class);
        $metrics = $dashboard->getDebtMetrics([], true);
        $overdueCounts = $dashboard->getOverdueCounts(1, true);

        $this->assertSame(300000, (int) ($metrics['total_overdue_eur_minor'] ?? 0));
        $this->assertSame(1, (int) ($metrics['delinquent_count'] ?? 0));
        $this->assertSame(1, (int) ($overdueCounts['concessionaires_count'] ?? 0));
    }

    public function test_delinquent_concessionaires_include_contract_null_and_direct_concessionaire_charges(): void
    {
        $this->createTestDebtScenario();

        $concessionaire = Concessionaire::where('document_number', '12345678')->first();
        $local = LocalModel::where('code', 'TEST-01')->first();

        $this->assertNotNull($concessionaire);
        $this->assertNotNull($local);

        $period = now()->startOfMonth()->subMonthsNoOverflow(1);

        Charge::create([
            'debtor_type' => 'LOCAL',
            'debtor_id' => $local->id,
            'origin_debtor_type' => 'LOCAL',
            'origin_debtor_id' => $local->id,
            'local_id' => $local->id,
            'contract_id' => null,
            'market_id' => $this->marketId,
            'charge_status_id' => $this->issuedId,
            'currency' => 'EUR',
            'kind' => 'ADJ',
            'source' => 'MANUAL',
            'period' => $period->format('Y-m-01'),
            'issued_on' => $period->format('Y-m-01'),
            'due_on' => $period->copy()->addDays(5)->toDateString(),
            'amount_minor' => 12000,
            'amount_bs_minor_issued' => 12000,
            'idempotency_key' => 'test-null-contract-charge',
        ]);

        Charge::create([
            'debtor_type' => 'CONCESSIONAIRE',
            'debtor_id' => $concessionaire->id,
            'origin_debtor_type' => 'CONCESSIONAIRE',
            'origin_debtor_id' => $concessionaire->id,
            'local_id' => null,
            'contract_id' => null,
            'market_id' => $this->marketId,
            'charge_status_id' => $this->issuedId,
            'currency' => 'USD',
            'kind' => 'FINE',
            'source' => 'MANUAL',
            'period' => $period->format('Y-m-01'),
            'issued_on' => $period->format('Y-m-01'),
            'due_on' => $period->copy()->addDays(5)->toDateString(),
            'amount_minor' => 3000,
            'amount_bs_minor_issued' => 3000,
            'idempotency_key' => 'test-direct-concessionaire-charge',
        ]);

        $debtAnalysis = app(DebtAnalysisService::class);
        $payload = $debtAnalysis->getDelinquentConcessionaires([
            'page' => 1,
            'per_page' => 100,
            'search' => (string) $concessionaire->document_number,
        ]);

        $rows = $payload['data'] ?? [];
        $this->assertNotEmpty($rows);

        $row = $rows[0];
        $this->assertSame((int) $concessionaire->id, (int) ($row['id'] ?? 0));
        $this->assertGreaterThanOrEqual(7, (int) ($row['charges_count'] ?? 0));
        $this->assertGreaterThan(0, (int) ($row['debt_usd_minor'] ?? 0));
    }

    public function test_debt_analysis_filters_are_applied(): void
    {
        $this->createTestDebtScenario();

        $debtAnalysis = app(DebtAnalysisService::class);

        $highMinDays = $debtAnalysis->getDelinquentConcessionaires([
            'page' => 1,
            'per_page' => 50,
            'min_days' => 5000,
        ]);
        $this->assertSame(0, (int) ($highMinDays['meta']['total'] ?? 0));

        $veryLowMaxDebt = $debtAnalysis->getDelinquentConcessionaires([
            'page' => 1,
            'per_page' => 50,
            'max_debt_eur' => 1,
        ]);
        $this->assertSame(0, (int) ($veryLowMaxDebt['meta']['total'] ?? 0));

        $marketFiltered = $debtAnalysis->getDelinquentConcessionaires([
            'page' => 1,
            'per_page' => 50,
            'market_id' => (int) $this->marketId,
        ]);
        $this->assertGreaterThan(0, (int) ($marketFiltered['meta']['total'] ?? 0));

        $invalidLocalType = $debtAnalysis->getDelinquentLocals([
            'page' => 1,
            'per_page' => 50,
            'local_type_id' => 999999,
        ]);
        $this->assertSame(0, (int) ($invalidLocalType['meta']['total'] ?? 0));

        $highLocalMinDebt = $debtAnalysis->getDelinquentLocals([
            'page' => 1,
            'per_page' => 50,
            'min_debt_eur' => 999999,
        ]);
        $this->assertSame(0, (int) ($highLocalMinDebt['meta']['total'] ?? 0));
    }

    public function test_distributions_apply_usd_credits_and_rates_consistently(): void
    {
        $local = LocalModel::create([
            'code' => 'USD-01',
            'name' => 'Local USD',
            'local_status_id' => $this->ocupId,
            'local_type_id' => $this->localTypeId,
            'market_id' => $this->marketId,
            'local_location_id' => $this->localLocationId,
            'area_m2' => 9.5,
        ]);

        $concessionaire = Concessionaire::create([
            'concessionaire_type_id' => $this->concessionaireTypeId,
            'document_type_id' => $this->documentTypeId,
            'document_number' => '44556677',
            'full_name' => 'Concessionaire USD',
            'fiscal_address' => 'USD Address',
            'email' => 'usd@example.com',
            'is_active' => true,
        ]);

        $contract = Contract::create([
            'number' => 'TEST-C-USD',
            'contract_type_id' => $this->contractTypeId,
            'contract_modality_id' => $this->contractModalityId,
            'trade_category_id' => $this->tradeCategoryId,
            'contract_status_id' => $this->vigId,
            'start_date' => now()->subMonths(4)->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'signed_at' => now()->subMonths(4),
        ]);

        $contract->concessionaires()->attach($concessionaire->id, ['is_primary' => true]);
        $contract->locals()->attach([$local->id]);

        $period = now()->startOfMonth()->subMonthsNoOverflow(2);

        $charge = Charge::create([
            'debtor_type' => 'LOCAL',
            'debtor_id' => $local->id,
            'origin_debtor_type' => 'LOCAL',
            'origin_debtor_id' => $local->id,
            'local_id' => $local->id,
            'contract_id' => $contract->id,
            'market_id' => $this->marketId,
            'charge_status_id' => $this->issuedId,
            'currency' => 'USD',
            'kind' => 'CONDO_USD',
            'source' => 'CONDO_RUN',
            'period' => $period->format('Y-m-01'),
            'issued_on' => $period->format('Y-m-01'),
            'due_on' => $period->copy()->addDays(5)->toDateString(),
            'amount_minor' => 10000,
            'amount_bs_minor_issued' => 10000,
            'idempotency_key' => 'test-usd-charge-with-credit',
        ]);

        $credit = CustomerCredit::create([
            'debtor_type' => 'LOCAL',
            'debtor_id' => $local->id,
            'currency' => 'USD',
            'balance_minor' => 2500,
        ]);

        CreditApplication::create([
            'customer_credit_id' => $credit->id,
            'payment_id' => null,
            'charge_id' => $charge->id,
            'amount_minor' => 2500,
        ]);

        $debtAnalysis = app(DebtAnalysisService::class);
        $dist = $debtAnalysis->getDistributions(true);

        $row = collect($dist['by_local_type_bs'] ?? [])->firstWhere('local_type_id', (int) $this->localTypeId);

        $this->assertNotNull($row);
        $this->assertSame(7500, (int) ($row['debt_usd_minor'] ?? 0));

        $usdRate = (float) (\DB::table('fx_rates')
            ->where('currency_code', 'USD')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('rate_date', 'desc')
            ->value('rate_to_ves') ?? 1.0);
        $usdRateMinor = (int) round($usdRate * 100);
        $expectedBs = (int) round(7500 * $usdRateMinor / 100);
        $fullBsWithoutCredit = (int) round(10000 * $usdRateMinor / 100);

        $this->assertSame($expectedBs, (int) ($row['debt_bs_minor'] ?? 0));
        $this->assertLessThan($fullBsWithoutCredit, (int) ($row['debt_bs_minor'] ?? 0));
    }

    /**
     * Seed only minimal catalog data needed for tests and cache IDs.
     */
    private function seedMinimalCatalogs(): void
    {
        // Seed catalogs in dependency order
        $this->seed(\Database\Seeders\LocalStatusesSeeder::class);
        $this->seed(\Database\Seeders\ContractStatusesSeeder::class);
        $this->seed(\Database\Seeders\ChargeStatusesSeeder::class);
        $this->seed(\Database\Seeders\ConcessionaireTypesSeeder::class);
        $this->seed(\Database\Seeders\DocumentTypesSeeder::class);
        $this->seed(\Database\Seeders\ContractTypesSeeder::class);
        $this->seed(\Database\Seeders\ContractModalitiesSeeder::class);
        $this->seed(\Database\Seeders\LocalTypesSeeder::class);
        $this->seed(\Database\Seeders\TradeCategoriesSeeder::class);
        $this->seed(\Database\Seeders\MarketsSeeder::class);
        $this->seed(\Database\Seeders\LocalLocationSeeder::class);
        $this->seed(\Database\Seeders\FxRatesOctober2025Seeder::class);

        // Cache frequently used IDs to avoid repeated queries
        $this->marketId = Market::first()?->id ?? 1;
        $this->ocupId = LocalStatus::where('code', 'OCUP')->value('id');
        $this->vigId = ContractStatus::where('code', 'VIG')->value('id');
        $this->issuedId = ChargeStatus::where('code', 'ISSUED')->value('id');
        $this->localTypeId = LocalType::first()?->id ?? 1;
        $this->localLocationId = LocalLocation::first()?->id ?? 1;
        $this->concessionaireTypeId = ConcessionaireType::first()?->id ?? 1;
        $this->documentTypeId = DocumentType::first()?->id ?? 1;
        $this->contractTypeId = ContractType::first()?->id ?? 1;
        $this->contractModalityId = ContractModality::first()?->id ?? 1;
        $this->tradeCategoryId = TradeCategory::first()?->id ?? 1;
    }

    /**
     * Create a minimal test scenario with overdue charges.
     */
    private function createTestDebtScenario(): void
    {
        // Create 2 locals using cached IDs
        $local1 = LocalModel::create([
            'code' => 'TEST-01',
            'name' => 'Local Test 1',
            'local_status_id' => $this->ocupId,
            'local_type_id' => $this->localTypeId,
            'market_id' => $this->marketId,
            'local_location_id' => $this->localLocationId,
            'area_m2' => 10.0,
        ]);

        $local2 = LocalModel::create([
            'code' => 'TEST-02',
            'name' => 'Local Test 2',
            'local_status_id' => $this->ocupId,
            'local_type_id' => $this->localTypeId,
            'market_id' => $this->marketId,
            'local_location_id' => $this->localLocationId,
            'area_m2' => 15.0,
        ]);

        // Create 1 concessionaire
        $concessionaire = Concessionaire::create([
            'concessionaire_type_id' => $this->concessionaireTypeId,
            'document_type_id' => $this->documentTypeId,
            'document_number' => '12345678',
            'full_name' => 'Test Concessionaire',
            'fiscal_address' => 'Test Address',
            'email' => 'test@example.com',
            'is_active' => true,
        ]);

        // Create 1 contract
        $contract = Contract::create([
            'number' => 'TEST-C001',
            'contract_type_id' => $this->contractTypeId,
            'contract_modality_id' => $this->contractModalityId,
            'trade_category_id' => $this->tradeCategoryId,
            'contract_status_id' => $this->vigId,
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'signed_at' => now()->subMonths(6),
        ]);

        // Attach concessionaire and locals to contract
        $contract->concessionaires()->attach($concessionaire->id, ['is_primary' => true]);
        $contract->locals()->attach([$local1->id, $local2->id]);

        // Create overdue charges for local 1 (3 months overdue @ 500 EUR each)
        $this->createOverdueCharges($local1, $contract, 3, 50000);

        // Create overdue charges for local 2 (2 months overdue @ 750 EUR each)
        $this->createOverdueCharges($local2, $contract, 2, 75000);

        // Total: (3 * 500) + (2 * 750) = 3000 EUR = 300000 minor
    }

    /**
     * Create overdue charges for a local.
     */
    private function createOverdueCharges(
        LocalModel $local,
        Contract $contract,
        int $monthsOverdue,
        int $amountMinor
    ): void {
        for ($i = 1; $i <= $monthsOverdue; $i++) {
            $period = now()->startOfMonth()->subMonthsNoOverflow($i);
            Charge::create([
                'debtor_type' => 'LOCAL',
                'debtor_id' => $local->id,
                'origin_debtor_type' => 'LOCAL',
                'origin_debtor_id' => $local->id,
                'local_id' => $local->id,
                'contract_id' => $contract->id,
                'market_id' => $this->marketId,
                'charge_status_id' => $this->issuedId,
                'currency' => 'EUR',
                'kind' => 'RENT_EUR_M2',
                'source' => 'RENT_RUN',
                'period' => $period->format('Y-m-01'),
                'issued_on' => $period->format('Y-m-01'),
                'due_on' => $period->copy()->addDays(5)->toDateString(),
                'amount_minor' => $amountMinor,
                'amount_bs_minor_issued' => $amountMinor,
                'idempotency_key' => "test-charge-{$local->code}-{$i}",
            ]);
        }
    }
}
