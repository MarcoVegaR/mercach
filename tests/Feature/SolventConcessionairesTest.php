<?php

declare(strict_types=1);

use App\Contracts\Services\FxRateServiceInterface;
use App\Models\Bank;
use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\CompanyBankAccount;
use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\DocumentType;
use App\Models\FxRate;
use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\TradeCategory;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\DebtAnalysisService;
use App\Services\EconomicProfileService;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-13 10:00:00');
    $this->seed([
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\ContractStatusesSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\ConcessionaireTypesSeeder::class,
        Database\Seeders\DocumentTypesSeeder::class,
        Database\Seeders\ContractTypesSeeder::class,
        Database\Seeders\ContractModalitiesSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\TradeCategoriesSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
    ]);

    $this->person = Concessionaire::query()->create([
        'concessionaire_type_id' => ConcessionaireType::query()->value('id'),
        'document_type_id' => DocumentType::query()->value('id'),
        'document_number' => '12345678',
        'full_name' => 'Cesionario principal',
        'fiscal_address' => 'Chacao',
        'email' => 'principal@example.test',
        'is_active' => true,
    ]);
    $this->local = Local::query()->create([
        'code' => 'SOL-01', 'name' => 'Local de prueba',
        'market_id' => Market::query()->value('id'),
        'local_type_id' => LocalType::query()->value('id'),
        'local_status_id' => LocalStatus::query()->value('id'),
        'local_location_id' => LocalLocation::query()->value('id'),
        'area_m2' => 10, 'is_active' => true,
    ]);
    $this->contract = Contract::factory()->create([
        'contract_type_id' => ContractType::query()->value('id'),
        'contract_modality_id' => ContractModality::query()->value('id'),
        'trade_category_id' => TradeCategory::query()->value('id'),
        'contract_status_id' => ContractStatus::query()->where('code', 'VIG')->value('id'),
        'start_date' => '2026-01-01', 'end_date' => null,
    ]);
    $this->contract->concessionaires()->attach($this->person, ['is_primary' => true]);
    $this->contract->locals()->attach($this->local);

    $this->mock(FxRateServiceInterface::class)
        ->shouldReceive('resolveAt')
        ->andReturnUsing(fn (string $currency, DateTimeInterface $at): FxRate => new FxRate([
            'rate_to_ves' => $at->format('Y-m-d') < '2026-11-13' ? 2 : 4,
        ]));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function solventTestCharge(array $attributes = []): Charge
{
    return Charge::query()->create(array_replace([
        'market_id' => test()->local->market_id,
        'local_id' => test()->local->id,
        'contract_id' => test()->contract->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => test()->local->id,
        'origin_debtor_type' => 'LOCAL', 'origin_debtor_id' => test()->local->id,
        'kind' => 'RENT_EUR_M2', 'currency' => 'VES', 'amount_minor' => 10000,
        'period' => '2026-10-01', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-06',
        'charge_status_id' => ChargeStatus::query()->where('code', 'ISSUED')->value('id'),
        'source' => 'TEST',
    ], $attributes));
}

function solventTestPayment(Charge $charge, int $amount): Payment
{
    $bank = Bank::query()->create(['code' => '9999', 'name' => 'Banco prueba', 'is_active' => true]);
    $account = CompanyBankAccount::query()->create([
        'bank_id' => $bank->id, 'account_number' => '00000000000000000009',
        'account_holder_name' => 'Cuenta prueba', 'document_type' => 'J',
        'document_number' => '123456789', 'is_active' => true,
    ]);
    $payment = Payment::query()->create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => test()->person->id,
        'company_bank_account_id' => $account->id, 'origin_bank_id' => $bank->id,
        'payer_document_number' => '12345678', 'reference' => 'SOL-PAY',
        'amount_bs_minor' => $amount, 'paid_on' => '2026-10-10',
    ]);
    PaymentAllocation::query()->create([
        'payment_id' => $payment->id, 'charge_id' => $charge->id,
        'local_id' => test()->local->id, 'debtor_type' => 'LOCAL',
        'debtor_id' => test()->local->id, 'amount_bs_minor' => $amount,
    ]);

    return $payment;
}

function solventTestIds(array $filters = []): array
{
    return array_column(app(DebtAnalysisService::class)->getSolventConcessionaires($filters)['data'], 'id');
}

it('excludes every signer of a local with overdue debt', function (): void {
    $secondary = $this->person->replicate()->fill([
        'document_number' => '22334455', 'full_name' => 'Cesionario secundario', 'email' => 'secondary@example.test',
    ]);
    $secondary->save();
    $this->contract->concessionaires()->attach($secondary, ['is_primary' => false]);
    solventTestCharge();

    foreach ([$this->person, $secondary] as $person) {
        expect(app(EconomicProfileService::class)->forConcessionaire($person->id)['summary_bs']['overdue_bs_minor'])
            ->toBe(10000);
    }
    expect(solventTestIds())->toBeEmpty();
});

it('includes secondary signers when the shared local has no overdue balance', function (): void {
    $secondary = $this->person->replicate()->fill([
        'document_number' => '22334455', 'email' => 'secondary@example.test',
    ]);
    $secondary->save();
    $this->contract->concessionaires()->attach($secondary, ['is_primary' => false]);
    expect(solventTestIds())->toEqualCanonicalizing([$this->person->id, $secondary->id]);
});

it('uses the current locals even when charges belong to a previous contract', function (): void {
    $oldContract = $this->contract->replicate()->fill([
        'number' => 'SOL-OLD',
        'contract_status_id' => ContractStatus::query()->where('code', 'TERM')->value('id'),
        'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
    ]);
    $oldContract->save();
    solventTestCharge(['contract_id' => $oldContract->id]);

    expect(app(EconomicProfileService::class)->forConcessionaire($this->person->id)['summary_bs']['overdue_bs_minor'])
        ->toBe(10000);
    expect(solventTestIds())->toBeEmpty();
});

it('requires an active contract even when there is no debt', function (string $case): void {
    match ($case) {
        'none' => $this->contract->concessionaires()->detach(),
        'terminated' => $this->contract->update([
            'contract_status_id' => ContractStatus::query()->where('code', 'TERM')->value('id'),
        ]),
        'future' => $this->contract->update(['start_date' => '2026-12-01']),
        'expired_dates' => $this->contract->update(['end_date' => '2026-10-31']),
        'deleted' => $this->contract->delete(),
    };

    expect(solventTestIds())->toBeEmpty();
})->with(['none', 'terminated', 'future', 'expired_dates', 'deleted']);

it('retains VENC contracts as active until explicitly terminated', function (): void {
    $this->contract->update([
        'contract_status_id' => ContractStatus::query()->where('code', 'VENC')->value('id'),
        'end_date' => '2026-10-31',
    ]);
    expect(solventTestIds())->toBe([$this->person->id]);
});

it('checks every current local without duplicating people linked through multiple contracts', function (): void {
    $secondLocal = $this->local->replicate()->fill(['code' => 'SOL-02']);
    $secondLocal->save();
    $this->contract->locals()->attach($secondLocal);
    $secondContract = $this->contract->replicate()->fill(['number' => 'SOL-SECOND']);
    $secondContract->save();
    $secondContract->concessionaires()->attach($this->person, ['is_primary' => true]);
    $secondContract->locals()->attach($this->local);

    expect(solventTestIds())->toBe([$this->person->id]);

    solventTestCharge(['local_id' => $secondLocal->id, 'debtor_id' => $secondLocal->id]);

    expect(solventTestIds())->toBeEmpty();
});

it('does not treat old debt as solvent through a recent months filter', function (): void {
    solventTestCharge(['period' => '2025-01-01', 'due_on' => '2025-01-06']);

    expect(solventTestIds(['months_solvent' => 1]))->toBeEmpty();
});

it('checks all eligible charges rather than only the first 500 display rows', function (): void {
    $charge = solventTestCharge(['kind' => 'ADJ', 'amount_minor' => 0]);
    $attributes = $charge->getAttributes();
    unset($attributes['id']);
    Charge::query()->insert(array_fill(0, 500, $attributes));
    solventTestCharge(['period' => '2026-11-01', 'due_on' => '2026-11-06']);

    expect(solventTestIds())->toBeEmpty();
});

it('excludes deleted people and ignores debts of deleted locals', function (): void {
    solventTestCharge();
    $this->local->delete();
    expect(solventTestIds())->toBe([$this->person->id]);

    $this->person->delete();
    expect(solventTestIds())->toBeEmpty();
});

it('ignores future, canceled, deleted and uncollectible charges', function (string $case): void {
    $charge = solventTestCharge();
    match ($case) {
        'future' => $charge->update(['due_on' => '2026-11-14']),
        'canceled' => $charge->update([
            'charge_status_id' => ChargeStatus::query()->where('code', 'CANCELED')->value('id'),
        ]),
        'deleted' => $charge->delete(),
        'uncollectible' => $charge->update(['uncollectible_at' => now()]),
    };

    expect(app(EconomicProfileService::class)->forConcessionaire($this->person->id)['summary_bs']['overdue_bs_minor'])
        ->toBe(0);
    expect(solventTestIds())->toBe([$this->person->id]);
})->with(['future', 'canceled', 'deleted', 'uncollectible']);

it('excludes direct overdue debt including charges due today', function (): void {
    solventTestCharge([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $this->person->id,
        'due_on' => '2026-11-13',
    ]);
    $profile = app(EconomicProfileService::class)->forConcessionaire($this->person->id);
    expect($profile['summary_bs']['overdue_bs_minor'])->toBe(10000)
        ->and($profile['summary_fx']['rent_m2']['overdue_minor'])->toBe(10000);
    expect(solventTestIds())->toBeEmpty();
});

it('checks the remaining balance using the payment date exchange rate', function (string $currency, int $paymentAmount): void {
    $charge = solventTestCharge(['currency' => $currency]);
    solventTestPayment($charge, $paymentAmount);

    expect(app(EconomicProfileService::class)->forConcessionaire($this->person->id)['summary_bs']['overdue_bs_minor'])
        ->toBe(0);
    expect(solventTestIds())->toBe([$this->person->id]);
})->with([
    'bolivares' => ['VES', 10000],
    'euros' => ['EUR', 20000],
    'dollars' => ['USD', 20000],
]);

it('resolves each currency and date only once across a large report', function (): void {
    $charge = solventTestCharge(['kind' => 'ADJ', 'currency' => 'EUR']);
    $attributes = $charge->getAttributes();
    unset($attributes['id']);
    Charge::query()->insert(array_fill(0, 1000, $attributes));
    $chargeIds = Charge::query()->where('id', '<>', $charge->id)->pluck('id');

    $payment = solventTestPayment($charge, 16000);
    $payment->update(['amount_bs_minor' => 16000 * 1001]);
    PaymentAllocation::query()->insert($chargeIds->map(fn (int $id): array => [
        'payment_id' => $payment->id, 'charge_id' => $id,
        'local_id' => $this->local->id, 'debtor_type' => 'LOCAL',
        'debtor_id' => $this->local->id, 'amount_bs_minor' => 16000,
    ])->all());

    $credit = CustomerCredit::query()->create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $this->person->id,
        'currency' => 'EUR', 'balance_minor' => 0, 'status' => 'CLOSED',
    ]);
    CreditApplication::query()->insert($chargeIds->push($charge->id)->map(fn (int $id): array => [
        'customer_credit_id' => $credit->id, 'charge_id' => $id, 'amount_minor' => 2000,
    ])->all());

    $resolutions = [];
    $this->mock(FxRateServiceInterface::class)
        ->shouldReceive('resolveAt')
        ->andReturnUsing(function (string $currency, DateTimeInterface $at) use (&$resolutions): FxRate {
            $resolutions[] = $currency.':'.$at->format('Y-m-d');

            return new FxRate(['rate_to_ves' => $at->format('Y-m-d') < '2026-11-13' ? 2 : 4]);
        });

    expect(solventTestIds())->toBe([$this->person->id]);
    expect($resolutions)->toHaveCount(3)
        ->toEqualCanonicalizing(['EUR:2026-11-13', 'USD:2026-11-13', 'EUR:2026-10-10']);
});

it('does not count allocations from voided or deleted payments', function (string $case): void {
    $charge = solventTestCharge();
    $payment = solventTestPayment($charge, 10000);
    if ($case === 'voided') {
        $payment->update(['voided_at' => now()]);
    } else {
        $payment->delete();
    }
    expect(solventTestIds())->toBeEmpty();
})->with(['voided', 'deleted']);

it('subtracts applied credits and excludes partial outstanding balances', function (int $creditAmount, bool $solvent): void {
    $charge = solventTestCharge([
        'charge_status_id' => ChargeStatus::query()->where('code', 'PARTIAL')->value('id'),
    ]);
    $credit = CustomerCredit::query()->create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $this->person->id,
        'currency' => 'VES', 'balance_minor' => 0, 'status' => 'CLOSED',
    ]);
    CreditApplication::query()->create([
        'customer_credit_id' => $credit->id, 'charge_id' => $charge->id, 'amount_minor' => $creditAmount,
    ]);

    expect(solventTestIds())->toBe($solvent ? [$this->person->id] : []);
})->with([[10000, true], [5000, false]]);

it('paginates and searches only solvent active concessionaires', function (): void {
    $user = User::factory()->create();
    Permission::findOrCreate('dashboard.view.finance', 'web');
    $user->givePermissionTo('dashboard.view.finance');
    $secondary = $this->person->replicate()->fill([
        'document_number' => '22334455', 'full_name' => 'Otro principal', 'email' => 'secondary@example.test',
    ]);
    $secondary->save();
    $this->contract->concessionaires()->attach($secondary, ['is_primary' => false]);

    $this->actingAs($user)->getJson('/api/debt-analysis/solvent-concessionaires?per_page=1&search=principal')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->person->id);

    $this->getJson('/api/debt-analysis/solvent-concessionaires?per_page=1&search=principal&page=2')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $secondary->id);

    solventTestCharge();

    $this->getJson('/api/debt-analysis/solvent-concessionaires?per_page=1&search=principal')
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
});

it('keeps dashboard counts aligned with the shared report classification', function (string $case, int $active, int $delinquent): void {
    $charge = solventTestCharge(['currency' => 'EUR']);

    match ($case) {
        'overdue' => null,
        'direct' => $charge->update(['debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $this->person->id]),
        'paid' => solventTestPayment($charge, 20000),
        'future_charge' => $charge->update(['due_on' => '2026-11-14']),
        'uncollectible' => $charge->update(['uncollectible_at' => now()]),
        'deleted_charge' => $charge->delete(),
        'canceled' => $charge->update([
            'charge_status_id' => ChargeStatus::query()->where('code', 'CANCELED')->value('id'),
        ]),
        'extended' => $this->contract->update([
            'contract_status_id' => ContractStatus::query()->where('code', 'EXT')->value('id'),
        ]),
        'expired' => $this->contract->update([
            'contract_status_id' => ContractStatus::query()->where('code', 'VENC')->value('id'),
            'end_date' => '2026-10-31',
        ]),
        'terminated' => $this->contract->update([
            'contract_status_id' => ContractStatus::query()->where('code', 'TERM')->value('id'),
        ]),
        'future_contract' => $this->contract->update(['start_date' => '2026-12-01']),
        'deleted_person' => $this->person->delete(),
        'without_contract' => $this->contract->concessionaires()->detach(),
    };

    $metrics = app(DashboardService::class)->getDebtMetrics([], true);
    $report = app(DebtAnalysisService::class)->getSolventConcessionaires(['per_page' => 1]);

    expect($metrics['delinquent_count'])->toBe($delinquent)
        ->and($metrics['solvent_count'])->toBe($active - $delinquent)
        ->and($metrics['solvent_count'])->toBe($report['meta']['total'])
        ->and($metrics['morosidad_rate'])->toEqual($active > 0 ? round($delinquent / $active * 100, 1) : 0.0);
})->with([
    ['overdue', 1, 1],
    ['direct', 1, 1],
    ['paid', 1, 0],
    ['future_charge', 1, 0],
    ['uncollectible', 1, 0],
    ['deleted_charge', 1, 0],
    ['canceled', 1, 0],
    ['extended', 1, 1],
    ['expired', 1, 1],
    ['terminated', 0, 0],
    ['future_contract', 0, 0],
    ['deleted_person', 0, 0],
    ['without_contract', 0, 0],
]);

it('counts shared debt for every signer without changing any financial totals', function (): void {
    solventTestCharge(['currency' => 'EUR']);
    $dashboard = app(DashboardService::class);
    $before = $dashboard->getDebtMetrics([], true);

    $secondary = $this->person->replicate()->fill([
        'document_number' => '22334455', 'email' => 'secondary@example.test',
    ]);
    $secondary->save();
    $this->contract->concessionaires()->attach($secondary, ['is_primary' => false]);
    $secondContract = $this->contract->replicate()->fill(['number' => 'SOL-SECOND']);
    $secondContract->save();
    $secondContract->concessionaires()->attach($this->person, ['is_primary' => true]);
    $secondContract->locals()->attach($this->local);

    $after = $dashboard->getDebtMetrics([], true);
    $financialTotals = fn (array $metrics): array => array_filter(
        $metrics,
        fn (string $key): bool => str_starts_with($key, 'total_'),
        ARRAY_FILTER_USE_KEY,
    );

    expect($before['delinquent_count'])->toBe(1)
        ->and($after['delinquent_count'])->toBe(2)
        ->and($after['solvent_count'])->toBe(0)
        ->and($after['morosidad_rate'])->toEqual(100.0)
        ->and($after['total_debt_eur_minor'])->toBe(10000)
        ->and($after['total_debt_bs_minor'])->toBe(40000)
        ->and($financialTotals($after))->toBe($financialTotals($before))
        ->and(solventTestIds())->toBeEmpty();
});

it('uses the same denominator for solvent and delinquent active signers', function (): void {
    $secondary = $this->person->replicate()->fill([
        'document_number' => '22334455', 'email' => 'secondary@example.test',
    ]);
    $secondary->save();
    $this->contract->concessionaires()->attach($secondary, ['is_primary' => false]);
    solventTestCharge([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $this->person->id,
    ]);

    $metrics = app(DashboardService::class)->getDebtMetrics([], true);
    $report = app(DebtAnalysisService::class)->getSolventConcessionaires([]);
    expect($metrics['delinquent_count'])->toBe(1)
        ->and($metrics['solvent_count'])->toBe(1)
        ->and($metrics['morosidad_rate'])->toEqual(50.0)
        ->and($report['meta']['total'])->toBe(1)
        ->and($report['data'][0]['id'])->toBe($secondary->id);
});
