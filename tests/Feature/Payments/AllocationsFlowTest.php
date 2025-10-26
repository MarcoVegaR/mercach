<?php

declare(strict_types=1);

use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\CustomerCredit;
use App\Models\Local;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function loginAdminAlloc(): void
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

function mkCompanyAcc(): array
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

function idChargeStatus(string $code): int
{
    return (int) (ChargeStatus::query()->where('code', $code)->value('id') ?? 0);
}

it('suggests allocations FIFO and Proportional according to outstanding and available', function () {
    loginAdminAlloc();
    [$bank, $acc] = mkCompanyAcc();

    $market = \App\Models\Market::create(['code' => 'M-TST', 'name' => 'Market Test', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT', 'name' => 'LT', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LST', 'name' => 'LST', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LOC', 'name' => 'LOC', 'is_active' => true]);
    $local = Local::create(['code' => 'L-100', 'name' => 'Local 100', 'market_id' => $market->id, 'local_type_id' => $lt->id, 'local_status_id' => $ls->id, 'local_location_id' => $ll->id, 'area_m2' => 10, 'is_active' => true]);

    // Two charges with known outstanding in Bs
    $cA = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL', 'origin_debtor_id' => $local->id,
        'currency' => 'VES', 'amount_minor' => 6000,
        'period' => Carbon::parse('2025-09-01'), 'issued_on' => Carbon::parse('2025-09-01'),
        'due_on' => Carbon::parse('2025-09-10'), 'charge_status_id' => idChargeStatus('ISSUED'),
        'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $cA->setAttribute('amount_bs_minor_issued', 6000);
    $cA->save();
    $cB = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL', 'origin_debtor_id' => $local->id,
        'currency' => 'VES', 'amount_minor' => 4000,
        'period' => Carbon::parse('2025-10-01'), 'issued_on' => Carbon::parse('2025-10-01'),
        'due_on' => Carbon::parse('2025-10-10'), 'charge_status_id' => idChargeStatus('ISSUED'),
        'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $cB->setAttribute('amount_bs_minor_issued', 4000);
    $cB->save();

    $payment = Payment::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '12345678',
        'reference' => '000001',
        'amount_bs_minor' => 7000,
        'paid_on' => '2025-10-12',
        'status' => 'CONFIRMED',
        'method' => 'PMOV',
    ]);

    // FIFO: should fully take cA (6000) then partial cB (1000)
    $resF = $this->postJson(route('payments.allocations.suggest', ['payment' => $payment->getKey()]), ['strategy' => 'fifo']);
    $resF->assertOk();
    $byId = collect($resF->json('items'))->keyBy('charge_id');
    expect((int) $byId[$cA->id]['amount_bs_minor'])->toBe(6000);
    expect((int) $byId[$cB->id]['amount_bs_minor'])->toBe(1000);

    // Proportional with less available: reduce payment funds to 5000 and re-suggest
    $payment->setAttribute('amount_bs_minor', 5000);
    $payment->save();
    $resP = $this->postJson(route('payments.allocations.suggest', ['payment' => $payment->getKey()]), ['strategy' => 'proportional']);
    $resP->assertOk();
    $byIdP = collect($resP->json('items'))->keyBy('charge_id');
    // Expected 3000 and 2000 split
    expect((int) $byIdP[$cA->id]['amount_bs_minor'])->toBeGreaterThanOrEqual(3000);
    expect((int) $byIdP[$cB->id]['amount_bs_minor'])->toBeGreaterThanOrEqual(2000);
    expect((int) $resP->json('summary.suggested_bs_minor'))->toBe(5000);
});

it('validates preview against outstanding and available funds, including optional credit', function () {
    loginAdminAlloc();
    [$bank, $acc] = mkCompanyAcc();
    $market = \App\Models\Market::create(['code' => 'M-TST2', 'name' => 'Market Test 2', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT2', 'name' => 'LT2', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LST2', 'name' => 'LST2', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LOC2', 'name' => 'LOC2', 'is_active' => true]);
    $local = Local::create(['code' => 'L-200', 'name' => 'Local 200', 'market_id' => $market->id, 'local_type_id' => $lt->id, 'local_status_id' => $ls->id, 'local_location_id' => $ll->id, 'area_m2' => 10, 'is_active' => true]);

    $c = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL', 'origin_debtor_id' => $local->id,
        'currency' => 'VES', 'amount_minor' => 6000,
        'period' => Carbon::parse('2025-10-01'), 'issued_on' => Carbon::parse('2025-10-01'),
        'due_on' => Carbon::parse('2025-10-10'), 'charge_status_id' => idChargeStatus('ISSUED'),
        'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $c->setAttribute('amount_bs_minor_issued', 6000);
    $c->save();

    $payment = Payment::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '12345678', 'reference' => '000010',
        'amount_bs_minor' => 1000, 'paid_on' => '2025-10-12', 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);

    // Request exceeds outstanding -> invalid
    $resp1 = $this->postJson(route('payments.allocations.preview', ['payment' => $payment->getKey()]), [
        'items' => [['charge_id' => $c->id, 'amount_bs_minor' => 7000]],
    ]);
    $resp1->assertOk();
    expect((bool) $resp1->json('ok'))->toBeFalse();

    // Request exceeds available without credit
    $resp2 = $this->postJson(route('payments.allocations.preview', ['payment' => $payment->getKey()]), [
        'items' => [['charge_id' => $c->id, 'amount_bs_minor' => 2000]],
        'use_credit' => false,
    ]);
    $resp2->assertOk();
    expect(collect($resp2->json('errors')))->toContain('Total a aplicar supera el disponible (pago + crédito a favor).');

    // With credit available, valid
    CustomerCredit::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id, 'currency' => 'VES', 'balance_minor' => 1000, 'status' => 'OPEN',
    ]);
    $resp3 = $this->postJson(route('payments.allocations.preview', ['payment' => $payment->getKey()]), [
        'items' => [['charge_id' => $c->id, 'amount_bs_minor' => 1500]],
        'use_credit' => true,
    ]);
    $resp3->assertOk();
    expect((bool) $resp3->json('ok'))->toBeTrue();
});

it('stores allocations idempotently and updates statuses; creates credit on leftover with no open charges', function () {
    loginAdminAlloc();
    [$bank, $acc] = mkCompanyAcc();
    $market = \App\Models\Market::create(['code' => 'M-TST3', 'name' => 'Market Test 3', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT3', 'name' => 'LT3', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LST3', 'name' => 'LST3', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LOC3', 'name' => 'LOC3', 'is_active' => true]);
    $local = Local::create(['code' => 'L-300', 'name' => 'Local 300', 'market_id' => $market->id, 'local_type_id' => $lt->id, 'local_status_id' => $ls->id, 'local_location_id' => $ll->id, 'area_m2' => 10, 'is_active' => true]);

    // one small and one exact
    $c1 = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL', 'origin_debtor_id' => $local->id,
        'currency' => 'VES', 'amount_minor' => 3000,
        'period' => Carbon::parse('2025-08-01'), 'issued_on' => Carbon::parse('2025-08-01'),
        'due_on' => Carbon::parse('2025-08-10'), 'charge_status_id' => idChargeStatus('ISSUED'),
        'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $c1->setAttribute('amount_bs_minor_issued', 3000);
    $c1->save();
    $c2 = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL', 'origin_debtor_id' => $local->id,
        'currency' => 'VES', 'amount_minor' => 4000,
        'period' => Carbon::parse('2025-09-01'), 'issued_on' => Carbon::parse('2025-09-01'),
        'due_on' => Carbon::parse('2025-09-10'), 'charge_status_id' => idChargeStatus('ISSUED'),
        'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $c2->setAttribute('amount_bs_minor_issued', 4000);
    $c2->save();

    $payment = Payment::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '12345678', 'reference' => '000020',
        'amount_bs_minor' => 6000, 'paid_on' => '2025-10-12', 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);

    $items = [['charge_id' => $c1->id, 'amount_bs_minor' => 3000], ['charge_id' => $c2->id, 'amount_bs_minor' => 3000]];
    $key = 'idem-123';

    // First store
    $res1 = $this->postJson(route('payments.allocations.store', ['payment' => $payment->getKey()]), ['items' => $items, 'idempotency_key' => $key]);
    $res1->assertRedirect();

    // Second identical store should be idempotent
    $res2 = $this->postJson(route('payments.allocations.store', ['payment' => $payment->getKey()]), ['items' => array_reverse($items), 'idempotency_key' => $key]);
    $res2->assertRedirect();

    // Allocations sum equals requested once
    $sum = (int) PaymentAllocation::query()->where('payment_id', $payment->getKey())->sum('amount_bs_minor');
    expect($sum)->toBe(6000);

    // Status transitions
    $c1->refresh();
    $c2->refresh();
    $payment->refresh();
    expect($payment->status)->toBe('APPLIED');
    // c1 settled, c2 partial (since 3000/4000)
    $settledId = idChargeStatus('SETTLED');
    $partialId = idChargeStatus('PARTIAL');
    expect((int) $c1->getAttribute('charge_status_id'))->toBe($settledId)->and((int) $c2->getAttribute('charge_status_id'))->toBe($partialId);

    // Overpayment scenario -> create credit when no open charges
    $payment2 = Payment::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '12345678', 'reference' => '000021',
        'amount_bs_minor' => 2000, 'paid_on' => '2025-10-12', 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);
    // Apply to settle remaining c2 (1000) and create credit for leftover (1000) since no other issued/partial charges
    $res3 = $this->postJson(route('payments.allocations.store', ['payment' => $payment2->getKey()]), ['items' => [['charge_id' => $c2->id, 'amount_bs_minor' => 1000]]]);
    $res3->assertRedirect();
    $payment2->refresh();
    expect($payment2->status)->toBe('APPLIED');
    // credit exists with balance 1000
    $credit = CustomerCredit::query()->where('source_payment_id', $payment2->getKey())->first();
    expect($credit)->not()->toBeNull();
    expect((int) $credit->getAttribute('balance_minor'))->toBe(1000);
});
