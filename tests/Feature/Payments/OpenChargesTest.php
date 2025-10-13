<?php

declare(strict_types=1);

use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\Local;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function loginAdminForPayments(): void
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
    ]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    test()->actingAs($admin);
}

function statusId(string $code): int
{
    return (int) (ChargeStatus::query()->where('code', $code)->value('id') ?? 0);
}

it('returns outstanding in Bs (issued - allocated - credited) for LOCAL debtor and respects overdue and currency/kind filters', function () {
    loginAdminForPayments();

    // Create minimal required catalogs
    $market = \App\Models\Market::create(['code' => 'M-TST', 'name' => 'Market Test', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT', 'name' => 'LT', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LST', 'name' => 'LST', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LOC', 'name' => 'LOC', 'is_active' => true]);

    // Create a Local debtor
    $local = Local::create([
        'code' => 'L-001', 'name' => 'Local 1',
        'market_id' => $market->id,
        'local_type_id' => $lt->id,
        'local_status_id' => $ls->id,
        'local_location_id' => $ll->id,
        'area_m2' => 10, 'is_active' => true,
    ]);

    // Create minimal company bank account + payment to satisfy FKs on allocations
    $bank = \App\Models\Bank::first() ?: \App\Models\Bank::create(['code' => 'BANKT', 'bank_code' => '156', 'name' => 'Banco Test', 'is_active' => true]);
    $acc = \App\Models\CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01560011223344556677',
        'phone_number' => '584241112233',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);
    $payment = \App\Models\Payment::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => '000000', 'amount_bs_minor' => 0,
        'paid_on' => '2025-10-12', 'status' => 'REGISTERED', 'method' => 'PMOV',
    ]);

    // Baseline issued in Bs to avoid FX
    $c1 = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'kind' => 'RENT',
        'currency' => 'VES',
        'amount_minor' => 10000,
        'period' => Carbon::parse('2025-10-01'),
        'issued_on' => Carbon::parse('2025-10-01'),
        'due_on' => Carbon::parse('2025-10-10'),
        'charge_status_id' => statusId('ISSUED'),
        'source' => 'TEST',
    ]);
    $c1->setAttribute('amount_bs_minor_issued', 10000); // Bs 100.00
    $c1->save();

    // Allocate part of c1 and apply credit to reduce outstanding
    PaymentAllocation::create([
        'payment_id' => $payment->getKey(),
        'charge_id' => $c1->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'amount_bs_minor' => 3000,
    ]);
    $cc = \App\Models\CustomerCredit::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id, 'currency' => 'VES', 'balance_minor' => 1000, 'status' => 'OPEN',
    ]);
    \App\Models\CreditApplication::create([
        'customer_credit_id' => $cc->getKey(),
        'payment_id' => $payment->getKey(),
        'charge_id' => $c1->id,
        'amount_minor' => 1000,
    ]);

    // Not overdue (future due); used to check overdue_only behavior
    $c2 = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'currency' => 'VES',
        'amount_minor' => 5000,
        'period' => Carbon::parse('2025-10-01'),
        'issued_on' => Carbon::parse('2025-10-01'),
        'due_on' => Carbon::parse('2025-10-15'),
        'kind' => 'COND',
        'charge_status_id' => statusId('ISSUED'),
        'source' => 'TEST',
    ]);
    $c2->setAttribute('amount_bs_minor_issued', 5000);
    $c2->save();

    // Different currency and kind
    $c3 = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'currency' => 'USD',
        'amount_minor' => 7000,
        'period' => Carbon::parse('2025-09-01'),
        'issued_on' => Carbon::parse('2025-09-01'),
        'due_on' => Carbon::parse('2025-09-15'),
        'kind' => 'OTHER',
        'charge_status_id' => statusId('ISSUED'),
        'source' => 'TEST',
    ]);
    $c3->setAttribute('amount_bs_minor_issued', 7000);
    $c3->save();

    $paidOn = '2025-10-12';

    // 1) No filters: c1 and c3 present. c2 is not overdue on paidOn.
    $res = $this->getJson(route('payments.open-charges', [
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id, 'paid_on' => $paidOn,
    ]));
    $res->assertOk();
    $items = $res->json('items');
    expect($items)->toBeArray();
    $byId = collect($items)->keyBy('charge_id');
    // c1 outstanding = 10000 - 3000 - 1000 = 6000
    expect((int) $byId[$c1->id]['outstanding_bs_minor'])->toBe(6000);
    // c3 outstanding = 7000
    expect((int) $byId[$c3->id]['outstanding_bs_minor'])->toBe(7000);

    // 2) overdue_only: includes c1 (due 10) but excludes c2 (due 15)
    $res2 = $this->getJson(route('payments.open-charges', [
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id, 'paid_on' => $paidOn, 'overdue_only' => 1,
    ]));
    $res2->assertOk();
    $ids2 = collect($res2->json('items'))->pluck('charge_id')->all();
    expect($ids2)->toContain($c1->id);
    expect($ids2)->not->toContain($c2->id);

    // 3) currency filter (USD): returns only c3
    $res3 = $this->getJson(route('payments.open-charges', [
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id, 'paid_on' => $paidOn, 'currency' => 'USD',
    ]));
    $res3->assertOk();
    $ids3 = collect($res3->json('items'))->pluck('charge_id')->all();
    expect($ids3)->toBe([$c3->id]);

    // 4) kind filter (COND): returns only c2
    $res4 = $this->getJson(route('payments.open-charges', [
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id, 'paid_on' => $paidOn, 'kind' => 'COND',
    ]));
    $res4->assertOk();
    $ids4 = collect($res4->json('items'))->pluck('charge_id')->all();
    expect($ids4)->toBe([$c2->id]);
});
