<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\CompanyBankAccount;
use App\Models\Local;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function seedBasicsForPaymentsFx(): void
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
        Database\Seeders\FxRatesOctober2025Seeder::class,
    ]);
}

function statusIdFx(string $code): int
{
    return (int) (ChargeStatus::query()->where('code', $code)->value('id') ?? 0);
}

it('computes applied and outstanding in charge currency using FX at each payment date', function () {
    seedBasicsForPaymentsFx();

    // Create a Local debtor
    $market = \App\Models\Market::first();
    $lt = \App\Models\LocalType::first();
    $ls = \App\Models\LocalStatus::first();
    $ll = \App\Models\LocalLocation::first();

    $local = Local::create([
        'code' => 'L-100', 'name' => 'Local FX',
        'market_id' => $market->id,
        'local_type_id' => $lt->id,
        'local_status_id' => $ls->id,
        'local_location_id' => $ll->id,
        'area_m2' => 10, 'is_active' => true,
    ]);

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

    // Create EUR charge = €4.56
    $charge = Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'kind' => 'RENT_EUR_FIXED',
        'currency' => 'EUR',
        'amount_minor' => 456,
        'period' => Carbon::parse('2025-10-01'),
        'issued_on' => Carbon::parse('2025-10-01'),
        'due_on' => Carbon::parse('2025-10-10'),
        'charge_status_id' => statusIdFx('ISSUED'),
        'source' => 'TEST',
    ]);

    // Two payments with allocations in different dates
    $p1 = Payment::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => '111111', 'amount_bs_minor' => 50000, // 500,00 Bs
        'paid_on' => '2025-10-10', 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);
    $p2 = Payment::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => '222222', 'amount_bs_minor' => 20000, // 200,00 Bs
        'paid_on' => '2025-10-15', 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);

    PaymentAllocation::create([
        'payment_id' => $p1->getKey(),
        'charge_id' => $charge->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'amount_bs_minor' => 50000,
    ]);
    PaymentAllocation::create([
        'payment_id' => $p2->getKey(),
        'charge_id' => $charge->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'amount_bs_minor' => 20000,
    ]);

    // Auth as seeded admin user
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    test()->actingAs($admin);

    // Request open charges as of 2025-10-24
    $res = test()->getJson(route('payments.open-charges', [
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id, 'paid_on' => '2025-10-24',
    ]));
    $res->assertOk();
    $items = collect($res->json('items'));
    $row = $items->firstWhere('charge_id', $charge->id);
    expect($row)->not->toBeNull();

    // Expected applied in EUR using seeded rates
    $rate_1010 = 223.64; // EUR 2025-10-10
    $rate_1015 = 230.45; // EUR 2025-10-15
    $appliedMinor = (int) round((50000 / 100.0) / $rate_1010 * 100) + (int) round((20000 / 100.0) / $rate_1015 * 100);
    expect((int) $row['applied_currency_minor'])->toBe($appliedMinor);

    $outstandingMinor = max(0, 456 - $appliedMinor);
    expect((int) $row['outstanding_currency_minor'])->toBe($outstandingMinor);
});
