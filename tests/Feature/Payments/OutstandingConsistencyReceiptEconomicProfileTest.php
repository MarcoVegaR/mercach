<?php

declare(strict_types=1);

use App\Contracts\Services\EconomicProfileServiceInterface;
use App\Contracts\Services\FxRateServiceInterface;
use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\CompanyBankAccount;
use App\Models\Local;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\FxConversionHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function seedBasicsForOutstandingConsistency(): void
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
        Database\Seeders\BanksSeeder::class,
        Database\Seeders\FxRatesOctober2025Seeder::class,
    ]);

    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    test()->actingAs($admin);
}

function mkLocalForOutstandingConsistency(): Local
{
    $market = \App\Models\Market::query()->first() ?: \App\Models\Market::create(['code' => 'M-TST', 'name' => 'Market Test', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::query()->first() ?: \App\Models\LocalType::create(['code' => 'LT', 'name' => 'LT', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::query()->first() ?: \App\Models\LocalStatus::create(['code' => 'LST', 'name' => 'LST', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::query()->first() ?: \App\Models\LocalLocation::create(['code' => 'LOC', 'name' => 'LOC', 'is_active' => true]);

    return Local::create([
        'code' => 'L-OC-1',
        'name' => 'Local OC 1',
        'market_id' => $market->id,
        'local_type_id' => $lt->id,
        'local_status_id' => $ls->id,
        'local_location_id' => $ll->id,
        'area_m2' => 10,
        'is_active' => true,
    ]);
}

function mkCompanyAccountForOutstandingConsistency(): CompanyBankAccount
{
    $bank = \App\Models\Bank::query()->first() ?: \App\Models\Bank::create(['code' => 'BANKT', 'bank_code' => '156', 'name' => 'Banco Test', 'is_active' => true]);

    return CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01560011223344556677',
        'phone_number' => '584241112233',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);
}

it('keeps outstanding consistent across receipt (FX at payment date) and economic profile (FX at view date) for partial FX payments', function () {
    seedBasicsForOutstandingConsistency();

    $local = mkLocalForOutstandingConsistency();
    $acc = mkCompanyAccountForOutstandingConsistency();

    $issuedId = (int) (ChargeStatus::query()->where('code', 'ISSUED')->value('id') ?? 0);

    $charge = Charge::create([
        'market_id' => $local->market_id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'kind' => 'RENT_EUR_M2',
        'currency' => 'EUR',
        'amount_minor' => 1551,
        'period' => Carbon::parse('2025-12-01'),
        'issued_on' => Carbon::parse('2025-12-01'),
        'due_on' => Carbon::parse('2025-12-10'),
        'charge_status_id' => $issuedId,
        'source' => 'TEST',
    ]);

    $paidOn = '2025-12-12';
    $viewAt = '2026-01-21';
    $tz = (string) config('app.timezone', 'America/Caracas');

    $payment = Payment::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => (int) ($acc->getAttribute('bank_id') ?? 0),
        'payer_document_type' => 'V',
        'payer_document_number' => '11111111',
        'reference' => 'OC-0001',
        'amount_bs_minor' => 300000,
        'paid_on' => $paidOn,
        'status' => 'APPLIED',
        'method' => 'PMOV',
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->getKey(),
        'charge_id' => $charge->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'amount_bs_minor' => 300000,
    ]);

    $open = test()->getJson(route('payments.open-charges', [
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'paid_on' => $paidOn,
    ]));
    $open->assertOk();

    $row = collect($open->json('items'))->firstWhere('charge_id', $charge->id);
    expect($row)->not->toBeNull();

    $outstandingCcyMinor = (int) ($row['outstanding_currency_minor'] ?? 0);
    $outstandingBsMinorAtPaidOn = (int) ($row['outstanding_bs_minor'] ?? 0);

    $fxHelper = app(FxConversionHelper::class);
    $appliedCurrencyMinor = $fxHelper->sumAllocationsInCurrency((int) $charge->getKey(), 'EUR');
    $creditedCurrencyMinor = $fxHelper->sumCreditsInCurrency((int) $charge->getKey(), 'EUR', new \DateTimeImmutable($viewAt));
    $expectedOutstandingCurrencyMinor = max(0, (int) $charge->getAttribute('amount_minor') - $appliedCurrencyMinor - $creditedCurrencyMinor);

    expect($outstandingCcyMinor)->toBe($expectedOutstandingCurrencyMinor);

    $receiptOutstandingBsMinor = $fxHelper->chargeOutstandingVes($charge, new \DateTimeImmutable($paidOn));
    $receiptOutstandingCurrencyMinor = $fxHelper->fromVes($receiptOutstandingBsMinor, 'EUR', new \DateTimeImmutable($paidOn));
    expect((int) ($receiptOutstandingCurrencyMinor ?? 0))->toBe($outstandingCcyMinor);

    $eco = app(EconomicProfileServiceInterface::class)->forLocal($local->id, Carbon::parse($viewAt, $tz), []);
    $ecoRow = collect($eco['tables']['charges_open'] ?? [])->firstWhere('charge_id', $charge->id);
    expect($ecoRow)->not->toBeNull();

    expect((int) ($ecoRow['outstanding_minor'] ?? 0))->toBe($outstandingCcyMinor);

    /** @var FxRateServiceInterface $fx */
    $fx = app(FxRateServiceInterface::class);
    $rateAtView = $fx->resolveAt('EUR', Carbon::parse($viewAt, $tz));
    $rateToVesView = $rateAtView ? (float) $rateAtView->getAttribute('rate_to_ves') : null;
    expect($rateToVesView)->not->toBeNull();

    $expectedBsAtView = (int) intdiv($outstandingCcyMinor * (int) round(((float) $rateToVesView) * 100), 100);
    expect((int) ($ecoRow['outstanding_bs_minor'] ?? 0))->toBe($expectedBsAtView);

    $rateAtPaidOn = $fx->resolveAt('EUR', Carbon::parse($paidOn, $tz));
    $rateToVesPaidOn = $rateAtPaidOn ? (float) $rateAtPaidOn->getAttribute('rate_to_ves') : null;
    expect($rateToVesPaidOn)->not->toBeNull();
    $expectedBsAtPaidOn = (int) intdiv($outstandingCcyMinor * (int) round(((float) $rateToVesPaidOn) * 100), 100);
    expect($outstandingBsMinorAtPaidOn)->toBe($expectedBsAtPaidOn);

    expect($outstandingBsMinorAtPaidOn)->toBeGreaterThan(0);
});
