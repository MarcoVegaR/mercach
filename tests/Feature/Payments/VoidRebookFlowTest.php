<?php

declare(strict_types=1);

use App\Enums\ChargeStatusCode;
use App\Models\Charge;
use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\Local;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginAdminVoidRebook(): void
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\PaymentStatusesSeeder::class,
        Database\Seeders\BanksSeeder::class,
    ]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    test()->actingAs($admin);
}

function mkCompanyAccVoidRebook(): array
{
    $bank = \App\Models\Bank::first() ?: \App\Models\Bank::create([
        'code' => 'BANKT', 'bank_code' => '156', 'name' => 'Banco Test', 'is_active' => true,
    ]);
    $acc = \App\Models\CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01560011223344556677',
        'phone_number' => '584241112233',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);

    return [$bank, $acc];
}

it('voids an APPLIED DEB payment and rebooks it with corrected paid_on', function () {
    loginAdminVoidRebook();
    [$bank, $acc] = mkCompanyAccVoidRebook();

    $market = \App\Models\Market::create(['code' => 'M-RBK', 'name' => 'Market Rebook', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT-RBK', 'name' => 'LT-RBK', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LS-RBK', 'name' => 'LS-RBK', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LOC-RBK', 'name' => 'LOC-RBK', 'is_active' => true]);
    $local = Local::create([
        'code' => 'L-RBK',
        'name' => 'Local Rebook',
        'market_id' => $market->id,
        'local_type_id' => $lt->id,
        'local_status_id' => $ls->id,
        'local_location_id' => $ll->id,
        'area_m2' => 10,
        'is_active' => true,
    ]);

    $issuedId = ChargeStatusCode::ISSUED->id();
    $settledId = ChargeStatusCode::SETTLED->id();

    $charge = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'currency' => 'VES',
        'amount_minor' => 12000,
        'period' => \Illuminate\Support\Carbon::parse('2025-10-01'),
        'issued_on' => \Illuminate\Support\Carbon::parse('2025-10-01'),
        'due_on' => \Illuminate\Support\Carbon::parse('2025-10-10'),
        'charge_status_id' => $settledId,
        'source' => 'TEST',
        'kind' => 'RENT',
        'settled_on' => '2025-10-12',
    ]);
    $charge->setAttribute('amount_bs_minor_issued', 12000);
    $charge->save();

    $payment = Payment::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'local_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'reference' => 'RBK-0001',
        'amount_bs_minor' => 10500,
        'paid_on' => '2025-10-12',
        'status' => 'APPLIED',
        'method' => 'DEB',
    ]);

    $alloc = PaymentAllocation::create([
        'payment_id' => (int) $payment->getKey(),
        'charge_id' => (int) $charge->getKey(),
        'local_id' => (int) $local->getKey(),
        'debtor_type' => 'LOCAL',
        'debtor_id' => (int) $local->getKey(),
        'amount_bs_minor' => 10000,
    ]);

    $usedCredit = CustomerCredit::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'currency' => 'VES',
        'balance_minor' => 0,
        'status' => 'USED',
        'created_from' => 'manual',
    ]);

    $creditApp = CreditApplication::create([
        'customer_credit_id' => (int) $usedCredit->getKey(),
        'payment_id' => (int) $payment->getKey(),
        'charge_id' => (int) $charge->getKey(),
        'amount_minor' => 2000,
    ]);

    $createdCredit = CustomerCredit::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'source_payment_id' => (int) $payment->getKey(),
        'currency' => 'VES',
        'balance_minor' => 500,
        'status' => 'OPEN',
        'created_from' => 'overpayment',
    ]);

    /** @var \App\Contracts\Services\ReceiptServiceInterface $receiptSvc */
    $receiptSvc = app(\App\Contracts\Services\ReceiptServiceInterface::class);
    $receipt = $receiptSvc->issue((int) $payment->getKey());

    $resp = $this->post(route('payments.void-rebook', ['payment' => $payment->getKey()]), [
        'paid_on' => '2025-10-13',
        'reason' => 'Fecha incorrecta',
    ]);
    $resp->assertRedirect();

    $error = session('error');
    expect($error)->toBeNull();

    $payment->refresh();
    expect($payment->status)->toBe('VOID');
    expect($payment->voided_at)->not->toBeNull();
    expect((string) $payment->void_reason)->toContain('Fecha incorrecta');

    expect(PaymentAllocation::query()->where('payment_id', (int) $payment->getKey())->count())->toBe(0);
    expect(PaymentAllocation::withTrashed()->where('payment_id', (int) $payment->getKey())->count())->toBe(1);
    expect(PaymentAllocation::withTrashed()->whereKey((int) $alloc->getKey())->value('deleted_at'))->not->toBeNull();

    expect(CreditApplication::query()->where('payment_id', (int) $payment->getKey())->count())->toBe(0);
    expect(CreditApplication::withTrashed()->whereKey((int) $creditApp->getKey())->value('deleted_at'))->not->toBeNull();

    expect(CustomerCredit::query()->where('source_payment_id', (int) $payment->getKey())->count())->toBe(0);
    expect(CustomerCredit::withTrashed()->whereKey((int) $createdCredit->getKey())->value('deleted_at'))->not->toBeNull();

    $usedCredit->refresh();
    expect((int) $usedCredit->getAttribute('balance_minor'))->toBe(2000);
    expect((string) $usedCredit->getAttribute('status'))->toBe('OPEN');

    $receipt->refresh();
    expect((string) $receipt->getAttribute('status'))->toBe('VOIDED');
    expect($receipt->getAttribute('voided_at'))->not->toBeNull();
    expect((string) $receipt->getAttribute('void_reason'))->toContain('Fecha incorrecta');

    $charge->refresh();
    expect((int) $charge->getAttribute('charge_status_id'))->toBe($issuedId);
    expect($charge->getAttribute('settled_on'))->toBeNull();

    $newPayment = Payment::query()
        ->where('id', '!=', (int) $payment->getKey())
        ->orderByDesc('id')
        ->first();

    expect($newPayment)->not->toBeNull();
    expect((string) $newPayment->getAttribute('paid_on'))->toBe('2025-10-13');
    expect((string) $newPayment->getAttribute('method'))->toBe('DEB');
    expect((int) $newPayment->getAttribute('amount_bs_minor'))->toBe(10500);
    expect($newPayment->status)->toBe('CONFIRMED');

    expect(PaymentAllocation::query()->where('payment_id', (int) $newPayment->getKey())->count())->toBe(0);
    expect(Receipt::query()->where('payment_id', (int) $newPayment->getKey())->count())->toBe(0);
});
