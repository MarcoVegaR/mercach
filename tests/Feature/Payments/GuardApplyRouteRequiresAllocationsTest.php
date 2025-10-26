<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginAdminApplyGuard(): void
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\PaymentStatusesSeeder::class,
        Database\Seeders\BanksSeeder::class,
    ]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    test()->actingAs($admin);
}

it('does not allow applying a payment with no allocations', function () {
    loginAdminApplyGuard();

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
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'reference' => 'APPLY-000',
        'amount_bs_minor' => 5000,
        'paid_on' => now()->toDateString(),
        'status' => 'CONFIRMED',
        'method' => 'PMOV',
    ]);

    // No allocations exist yet; applying should not change status to APPLIED
    $resp = $this->post(route('payments.apply', ['payment' => $payment->getKey()]));
    $resp->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe('CONFIRMED');
});
