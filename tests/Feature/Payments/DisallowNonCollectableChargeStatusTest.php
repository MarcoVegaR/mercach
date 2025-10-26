<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\CompanyBankAccount;
use App\Models\Local;
use App\Models\Market;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function loginAdminAllocStatus(): void
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

it('rejects allocations to charges with non-collectable status (e.g., SETTLED)', function () {
    loginAdminAllocStatus();

    $market = Market::create(['code' => 'M-NCS', 'name' => 'Market NCS', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LTNCS', 'name' => 'LTNCS', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LSTNCS', 'name' => 'LSTNCS', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LOCNCS', 'name' => 'LOCNCS', 'is_active' => true]);

    $local = Local::create(['code' => 'L-NCS', 'name' => 'Local NCS', 'market_id' => $market->id, 'local_type_id' => $lt->id, 'local_status_id' => $ls->id, 'local_location_id' => $ll->id, 'area_m2' => 10, 'is_active' => true]);

    $bank = Bank::first() ?: Bank::create(['code' => 'BANKT', 'bank_code' => '156', 'name' => 'Banco Test', 'is_active' => true]);
    $acc = CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01560011223344556677',
        'phone_number' => '584241112233',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);

    $payment = Payment::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '12345678',
        'reference' => 'NCS-001', 'amount_bs_minor' => 5000, 'paid_on' => '2025-10-12', 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);

    // Charge is already SETTLED => outstanding should be 0
    $statusSettled = (int) (ChargeStatus::query()->where('code', 'SETTLED')->value('id') ?? 0);
    $charge = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL', 'origin_debtor_id' => $local->id,
        'currency' => 'VES', 'amount_minor' => 1000,
        'period' => Carbon::parse('2025-10-01'), 'issued_on' => Carbon::parse('2025-10-01'),
        'due_on' => Carbon::parse('2025-10-10'), 'charge_status_id' => $statusSettled,
        'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $charge->setAttribute('amount_bs_minor_issued', 1000);
    $charge->save();

    $resp = $this->postJson(route('payments.allocations.store', ['payment' => $payment->getKey()]), [
        'items' => [
            ['charge_id' => $charge->id, 'amount_bs_minor' => 1000],
        ],
    ]);

    $resp->assertStatus(422);
    $sum = (int) \App\Models\PaymentAllocation::query()->where('payment_id', $payment->getKey())->sum('amount_bs_minor');
    expect($sum)->toBe(0);
});
