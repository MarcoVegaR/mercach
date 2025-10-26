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

function loginAdminAllocCrossDebtor(): void
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

it('rejects allocations to a charge that does not belong to the payment debtor domain', function () {
    loginAdminAllocCrossDebtor();

    // Minimal catalogs for locals
    $market = Market::create(['code' => 'M-CRS', 'name' => 'Market CRS', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LTCRS', 'name' => 'LTCRS', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LSTCRS', 'name' => 'LSTCRS', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LOCCRS', 'name' => 'LOCCRS', 'is_active' => true]);

    $local1 = Local::create(['code' => 'L-X1', 'name' => 'Local X1', 'market_id' => $market->id, 'local_type_id' => $lt->id, 'local_status_id' => $ls->id, 'local_location_id' => $ll->id, 'area_m2' => 10, 'is_active' => true]);
    $local2 = Local::create(['code' => 'L-X2', 'name' => 'Local X2', 'market_id' => $market->id, 'local_type_id' => $lt->id, 'local_status_id' => $ls->id, 'local_location_id' => $ll->id, 'area_m2' => 12, 'is_active' => true]);

    // Bank + company account
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

    // Payment for LOCAL1, already CONFIRMED (policy: only confirmed can be applied)
    $payment = Payment::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local1->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'reference' => 'CRS-001',
        'amount_bs_minor' => 5000,
        'paid_on' => '2025-10-12',
        'status' => 'CONFIRMED',
        'method' => 'PMOV',
    ]);

    // Charge belongs to LOCAL2 (different debtor)
    $charge = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local2->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local2->id,
        'origin_debtor_type' => 'LOCAL', 'origin_debtor_id' => $local2->id,
        'currency' => 'VES', 'amount_minor' => 5000,
        'period' => Carbon::parse('2025-10-01'), 'issued_on' => Carbon::parse('2025-10-01'),
        'due_on' => Carbon::parse('2025-10-10'), 'charge_status_id' => (int) (ChargeStatus::query()->where('code', 'ISSUED')->value('id') ?? 0),
        'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $charge->setAttribute('amount_bs_minor_issued', 5000);
    $charge->save();

    // Attempt to allocate payment of LOCAL1 to charge of LOCAL2
    $resp = $this->postJson(route('payments.allocations.store', ['payment' => $payment->getKey()]), [
        'items' => [
            ['charge_id' => $charge->id, 'amount_bs_minor' => 2000],
        ],
    ]);

    $resp->assertStatus(422);
    // Ensure no allocations were created
    $sum = (int) \App\Models\PaymentAllocation::query()->where('payment_id', $payment->getKey())->sum('amount_bs_minor');
    expect($sum)->toBe(0);
});
