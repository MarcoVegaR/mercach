<?php

declare(strict_types=1);

use App\Contracts\Services\EconomicProfileServiceInterface;
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
use App\Models\DocumentType;
use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\TradeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function seedEconomicProfileScopeCatalogs(): array
{
    test()->seed([
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\ContractStatusesSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\ConcessionaireTypesSeeder::class,
        Database\Seeders\DocumentTypesSeeder::class,
        Database\Seeders\ContractTypesSeeder::class,
        Database\Seeders\ContractModalitiesSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\TradeCategoriesSeeder::class,
        Database\Seeders\BanksSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
        Database\Seeders\PaymentStatusesSeeder::class,
        Database\Seeders\PaymentTypesSeeder::class,
    ]);

    $bank = Bank::query()->first();

    return [
        'market_id' => (int) Market::query()->value('id'),
        'local_status_id' => (int) LocalStatus::query()->value('id'),
        'local_type_id' => (int) LocalType::query()->value('id'),
        'local_location_id' => (int) LocalLocation::query()->value('id'),
        'concessionaire_type_id' => (int) ConcessionaireType::query()->value('id'),
        'document_type_id' => (int) DocumentType::query()->value('id'),
        'contract_type_id' => (int) ContractType::query()->value('id'),
        'contract_modality_id' => (int) ContractModality::query()->value('id'),
        'trade_category_id' => (int) TradeCategory::query()->value('id'),
        'contract_vig_id' => (int) ContractStatus::query()->where('code', 'VIG')->value('id'),
        'contract_term_id' => (int) ContractStatus::query()->where('code', 'TERM')->value('id'),
        'charge_settled_id' => (int) ChargeStatus::query()->where('code', 'SETTLED')->value('id'),
        'bank_id' => (int) $bank?->getKey(),
        'company_bank_account_id' => (int) CompanyBankAccount::create([
            'bank_id' => (int) $bank?->getKey(),
            'account_number' => '00000000000000000009',
            'account_holder_name' => 'Cuenta Perfil Economico',
            'document_type' => 'J',
            'document_number' => '123456789',
            'is_active' => true,
        ])->getKey(),
    ];
}

function makeScopeConcessionaire(array $catalogs, string $name, string $document): Concessionaire
{
    return Concessionaire::create([
        'concessionaire_type_id' => $catalogs['concessionaire_type_id'],
        'document_type_id' => $catalogs['document_type_id'],
        'document_number' => $document,
        'full_name' => $name,
        'fiscal_address' => 'Direccion de prueba',
        'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        'is_active' => true,
    ]);
}

function makeScopeLocal(array $catalogs, string $code): Local
{
    return Local::create([
        'code' => $code,
        'name' => $code,
        'market_id' => $catalogs['market_id'],
        'local_type_id' => $catalogs['local_type_id'],
        'local_status_id' => $catalogs['local_status_id'],
        'local_location_id' => $catalogs['local_location_id'],
        'area_m2' => 10,
        'is_active' => true,
    ]);
}

function makeScopeContract(array $catalogs, Concessionaire $concessionaire, Local $local, string $number, int $statusId, string $startDate, ?string $endDate): Contract
{
    $contract = Contract::create([
        'number' => $number,
        'contract_type_id' => $catalogs['contract_type_id'],
        'contract_status_id' => $statusId,
        'contract_modality_id' => $catalogs['contract_modality_id'],
        'trade_category_id' => $catalogs['trade_category_id'],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'billing_day' => 1,
        'monthly_price_eur' => 100,
        'is_active' => true,
    ]);

    $contract->concessionaires()->attach($concessionaire->getKey(), ['is_primary' => true]);
    $contract->locals()->attach($local->getKey());

    return $contract;
}

it('does not expose payments applied to ceded locals as available balance', function () {
    $catalogs = seedEconomicProfileScopeCatalogs();
    $today = Carbon::parse('2026-06-23')->startOfDay();

    $oldConcessionaire = makeScopeConcessionaire($catalogs, 'Cesionario Anterior', '90000001');
    $newConcessionaire = makeScopeConcessionaire($catalogs, 'Cesionario Actual', '90000002');
    $local = makeScopeLocal($catalogs, 'EP-SCOPE-01');

    $oldContract = makeScopeContract(
        $catalogs,
        $oldConcessionaire,
        $local,
        'EP-SCOPE-OLD',
        $catalogs['contract_term_id'],
        '2026-01-01',
        '2026-05-31',
    );

    makeScopeContract(
        $catalogs,
        $newConcessionaire,
        $local,
        'EP-SCOPE-NEW',
        $catalogs['contract_vig_id'],
        '2026-06-01',
        null,
    );

    $charge = Charge::create([
        'market_id' => $catalogs['market_id'],
        'local_id' => $local->getKey(),
        'contract_id' => $oldContract->getKey(),
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->getKey(),
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->getKey(),
        'kind' => 'RENT_EUR_FIXED',
        'currency' => 'VES',
        'amount_minor' => 100000,
        'amount_bs_minor_issued' => 100000,
        'period' => '2026-05-01',
        'issued_on' => '2026-05-01',
        'due_on' => '2026-05-06',
        'settled_on' => '2026-05-10',
        'charge_status_id' => $catalogs['charge_settled_id'],
        'source' => 'TEST',
    ]);

    $payment = Payment::create([
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => $oldConcessionaire->getKey(),
        'company_bank_account_id' => $catalogs['company_bank_account_id'],
        'origin_bank_id' => $catalogs['bank_id'],
        'payer_document_type' => 'V',
        'payer_document_number' => '90000001',
        'reference' => 'EP-SCOPE-001',
        'amount_bs_minor' => 100000,
        'paid_on' => '2026-05-10',
        'status' => 'APPLIED',
        'method' => 'PMOV',
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->getKey(),
        'charge_id' => $charge->getKey(),
        'local_id' => $local->getKey(),
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->getKey(),
        'amount_bs_minor' => 100000,
    ]);

    $service = app(EconomicProfileServiceInterface::class);
    $profile = $service->forConcessionaire((int) $oldConcessionaire->getKey(), $today);
    $reconciliation = $service->getReconciliation('CONCESSIONAIRE', (int) $oldConcessionaire->getKey(), $today);

    expect((int) $profile['summary_bs']['payments_available_bs_minor'])->toBe(0);
    expect((int) $reconciliation['summary_bs']['payments_registered_bs_minor'])->toBe(100000);
    expect((int) $reconciliation['summary_bs']['payments_applied_bs_minor'])->toBe(0);
    expect((int) $reconciliation['summary_bs']['payments_available_bs_minor'])->toBe(0);
    expect((int) $reconciliation['summary_bs']['eligible_payments_available_bs_minor'])->toBe(0);
    expect((int) $reconciliation['summary_bs']['payments_reconciliation_gap_bs_minor'])->toBe(100000);
    expect((int) $reconciliation['breakdown']['payments_available_raw_bs_minor'])->toBe(100000);
    expect((int) $reconciliation['breakdown']['payments_reconciliation_gap_bs_minor'])->toBe(100000);
});

it('keeps confirmed unapplied payments available for use', function () {
    $catalogs = seedEconomicProfileScopeCatalogs();
    $today = Carbon::parse('2026-06-23')->startOfDay();

    $concessionaire = makeScopeConcessionaire($catalogs, 'Cesionario Con Saldo Real', '90000003');
    $local = makeScopeLocal($catalogs, 'EP-SCOPE-02');

    makeScopeContract(
        $catalogs,
        $concessionaire,
        $local,
        'EP-SCOPE-ACTIVE',
        $catalogs['contract_vig_id'],
        '2026-01-01',
        null,
    );

    Payment::create([
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => $concessionaire->getKey(),
        'company_bank_account_id' => $catalogs['company_bank_account_id'],
        'origin_bank_id' => $catalogs['bank_id'],
        'payer_document_type' => 'V',
        'payer_document_number' => '90000003',
        'reference' => 'EP-SCOPE-002',
        'amount_bs_minor' => 75000,
        'paid_on' => '2026-06-10',
        'status' => 'CONFIRMED',
        'method' => 'PMOV',
    ]);

    $reconciliation = app(EconomicProfileServiceInterface::class)
        ->getReconciliation('CONCESSIONAIRE', (int) $concessionaire->getKey(), $today);

    expect((int) $reconciliation['summary_bs']['payments_registered_bs_minor'])->toBe(75000);
    expect((int) $reconciliation['summary_bs']['payments_applied_bs_minor'])->toBe(0);
    expect((int) $reconciliation['summary_bs']['payments_available_bs_minor'])->toBe(75000);
    expect((int) $reconciliation['summary_bs']['eligible_payments_available_bs_minor'])->toBe(75000);
    expect((int) $reconciliation['summary_bs']['payments_reconciliation_gap_bs_minor'])->toBe(0);
});
