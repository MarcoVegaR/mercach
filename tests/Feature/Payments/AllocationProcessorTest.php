<?php

declare(strict_types=1);

use App\Enums\ChargeStatusCode;
use App\Models\Charge;
use App\Models\CompanyBankAccount;
use App\Models\CustomerCredit;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function seedAllocTestDependencies(): array
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\PaymentStatusesSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
    ]);

    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    test()->actingAs($admin);

    $market = \App\Models\Market::create(['code' => 'M-TST', 'name' => 'Market Test', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::first();
    $ls = \App\Models\LocalStatus::first();
    $ll = \App\Models\LocalLocation::first();

    $local = \App\Models\Local::create([
        'code' => 'L-TEST',
        'name' => 'Local Test',
        'market_id' => $market->id,
        'local_type_id' => $lt->id,
        'local_status_id' => $ls->id,
        'local_location_id' => $ll->id,
        'area_m2' => 10,
        'is_active' => true,
    ]);

    $bank = \App\Models\Bank::first() ?: \App\Models\Bank::create([
        'code' => 'BNKT',
        'bank_code' => '0156',
        'name' => 'Test Bank',
        'is_active' => true,
    ]);

    $acc = CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01560011223344556677',
        'phone_number' => '584241112233',
        'account_holder_name' => 'Cuenta Test',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);

    return [$market, $local, $bank, $acc, $admin];
}

it('applies payment to charges and transitions status', function () {
    [$market, $local, $bank, $acc, $admin] = seedAllocTestDependencies();

    $issuedId = ChargeStatusCode::ISSUED->id();
    $partialId = ChargeStatusCode::PARTIAL->id();
    $settledId = ChargeStatusCode::SETTLED->id();

    // Create charge 100.00 Bs
    $charge = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'kind' => 'TEST',
        'currency' => 'VES',
        'amount_minor' => 10000,
        'period' => Carbon::parse('2025-01-01'),
        'issued_on' => Carbon::parse('2025-01-01'),
        'due_on' => Carbon::parse('2025-01-15'),
        'charge_status_id' => $issuedId,
        'source' => 'TEST',
    ]);
    $charge->setAttribute('amount_bs_minor_issued', 10000);
    $charge->save();

    // Create payment 60.00 Bs (partial)
    $payment = Payment::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '11111111',
        'reference' => '000001',
        'amount_bs_minor' => 6000,
        'paid_on' => '2025-01-10',
        'status' => 'CONFIRMED',
        'method' => 'DEB',
    ]);

    // Apply allocation via service
    /** @var \App\Contracts\Services\PaymentServiceInterface $svc */
    $svc = app(\App\Contracts\Services\PaymentServiceInterface::class);
    $result = $svc->storeAllocations($payment->getKey(), [
        ['charge_id' => $charge->id, 'amount_bs_minor' => 6000],
    ]);

    // Verify allocation was created
    $alloc = PaymentAllocation::where('payment_id', $payment->id)
        ->where('charge_id', $charge->id)
        ->first();
    expect($alloc)->not->toBeNull();
    expect((int) $alloc->amount_bs_minor)->toBe(6000);

    // Charge should be PARTIAL (6000 of 10000 paid)
    $charge->refresh();
    expect((int) $charge->charge_status_id)->toBe($partialId);

    // Payment should be APPLIED (all funds distributed, even if charge has outstanding)
    $payment->refresh();
    expect((string) $payment->status)->toBe('APPLIED');
});

it('marks payment as APPLIED when fully allocated', function () {
    [$market, $local, $bank, $acc, $admin] = seedAllocTestDependencies();

    $issuedId = ChargeStatusCode::ISSUED->id();
    $settledId = ChargeStatusCode::SETTLED->id();

    // Create charge 100.00 Bs
    $charge = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'kind' => 'TEST',
        'currency' => 'VES',
        'amount_minor' => 10000,
        'period' => Carbon::parse('2025-01-01'),
        'issued_on' => Carbon::parse('2025-01-01'),
        'due_on' => Carbon::parse('2025-01-15'),
        'charge_status_id' => $issuedId,
        'source' => 'TEST',
    ]);
    $charge->setAttribute('amount_bs_minor_issued', 10000);
    $charge->save();

    // Create payment exactly 100.00 Bs
    $payment = Payment::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '11111111',
        'reference' => '000002',
        'amount_bs_minor' => 10000,
        'paid_on' => '2025-01-10',
        'status' => 'CONFIRMED',
        'method' => 'DEB',
    ]);

    /** @var \App\Contracts\Services\PaymentServiceInterface $svc */
    $svc = app(\App\Contracts\Services\PaymentServiceInterface::class);
    $svc->storeAllocations($payment->getKey(), [
        ['charge_id' => $charge->id, 'amount_bs_minor' => 10000],
    ]);

    // Charge should be SETTLED
    $charge->refresh();
    expect((int) $charge->charge_status_id)->toBe($settledId);
    expect($charge->settled_on)->not->toBeNull();

    // Payment should be APPLIED
    $payment->refresh();
    expect((string) $payment->status)->toBe('APPLIED');
});

it('creates customer credit on overpayment when no outstanding charges', function () {
    [$market, $local, $bank, $acc, $admin] = seedAllocTestDependencies();

    $issuedId = ChargeStatusCode::ISSUED->id();

    // Create charge 50.00 Bs
    $charge = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'kind' => 'TEST',
        'currency' => 'VES',
        'amount_minor' => 5000,
        'period' => Carbon::parse('2025-01-01'),
        'issued_on' => Carbon::parse('2025-01-01'),
        'due_on' => Carbon::parse('2025-01-15'),
        'charge_status_id' => $issuedId,
        'source' => 'TEST',
    ]);
    $charge->setAttribute('amount_bs_minor_issued', 5000);
    $charge->save();

    // Create payment 100.00 Bs (overpayment of 50.00)
    $payment = Payment::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '11111111',
        'reference' => '000003',
        'amount_bs_minor' => 10000,
        'paid_on' => '2025-01-10',
        'status' => 'CONFIRMED',
        'method' => 'DEB',
    ]);

    /** @var \App\Contracts\Services\PaymentServiceInterface $svc */
    $svc = app(\App\Contracts\Services\PaymentServiceInterface::class);
    $svc->storeAllocations($payment->getKey(), [
        ['charge_id' => $charge->id, 'amount_bs_minor' => 5000],
    ]);

    // Should create customer credit for the remaining 50.00
    $credit = CustomerCredit::where('debtor_type', 'LOCAL')
        ->where('debtor_id', $local->id)
        ->where('status', 'OPEN')
        ->first();

    expect($credit)->not->toBeNull();
    expect((int) $credit->balance_minor)->toBe(5000);
    expect((int) $credit->source_payment_id)->toBe($payment->id);

    // Payment should be APPLIED
    $payment->refresh();
    expect((string) $payment->status)->toBe('APPLIED');
});

it('rejects allocation for non-CONFIRMED payment', function () {
    [$market, $local, $bank, $acc, $admin] = seedAllocTestDependencies();

    $issuedId = ChargeStatusCode::ISSUED->id();

    $charge = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'kind' => 'TEST',
        'currency' => 'VES',
        'amount_minor' => 10000,
        'period' => Carbon::parse('2025-01-01'),
        'issued_on' => Carbon::parse('2025-01-01'),
        'due_on' => Carbon::parse('2025-01-15'),
        'charge_status_id' => $issuedId,
        'source' => 'TEST',
    ]);

    // Payment in REGISTERED status (not CONFIRMED)
    $payment = Payment::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '11111111',
        'reference' => '000004',
        'amount_bs_minor' => 10000,
        'paid_on' => '2025-01-10',
        'status' => 'REGISTERED',
        'method' => 'DEB',
    ]);

    /** @var \App\Contracts\Services\PaymentServiceInterface $svc */
    $svc = app(\App\Contracts\Services\PaymentServiceInterface::class);

    expect(fn () => $svc->storeAllocations($payment->getKey(), [
        ['charge_id' => $charge->id, 'amount_bs_minor' => 10000],
    ]))->toThrow(\App\Exceptions\DomainActionException::class);
});

it('rejects allocation for charge not belonging to debtor', function () {
    [$market, $local, $bank, $acc, $admin] = seedAllocTestDependencies();

    $issuedId = ChargeStatusCode::ISSUED->id();

    // Create another local
    $local2 = \App\Models\Local::create([
        'code' => 'L-OTHER',
        'name' => 'Other Local',
        'market_id' => $market->id,
        'local_type_id' => \App\Models\LocalType::first()->id,
        'local_status_id' => \App\Models\LocalStatus::first()->id,
        'local_location_id' => \App\Models\LocalLocation::first()->id,
        'area_m2' => 15,
        'is_active' => true,
    ]);

    // Charge belongs to local2
    $charge = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local2->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local2->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local2->id,
        'kind' => 'TEST',
        'currency' => 'VES',
        'amount_minor' => 10000,
        'period' => Carbon::parse('2025-01-01'),
        'issued_on' => Carbon::parse('2025-01-01'),
        'due_on' => Carbon::parse('2025-01-15'),
        'charge_status_id' => $issuedId,
        'source' => 'TEST',
    ]);

    // Payment belongs to local (not local2)
    $payment = Payment::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '11111111',
        'reference' => '000005',
        'amount_bs_minor' => 10000,
        'paid_on' => '2025-01-10',
        'status' => 'CONFIRMED',
        'method' => 'DEB',
    ]);

    /** @var \App\Contracts\Services\PaymentServiceInterface $svc */
    $svc = app(\App\Contracts\Services\PaymentServiceInterface::class);

    expect(fn () => $svc->storeAllocations($payment->getKey(), [
        ['charge_id' => $charge->id, 'amount_bs_minor' => 10000],
    ]))->toThrow(\App\Exceptions\DomainActionException::class);
});
