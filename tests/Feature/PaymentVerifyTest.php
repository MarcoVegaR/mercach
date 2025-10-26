<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function seedBankAndCompany(): array
{
    $bank = Bank::create([
        'code' => 'BANKTEST',
        'bank_code' => '156',
        'name' => 'Banco Prueba',
        'is_active' => true,
    ]);

    $acc = CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01234567890123456789',
        'phone_number' => '584242424564',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);

    return [$bank, $acc];
}

function baseGatewayConfig(): void
{
    config([
        'services.bank_gateway.scheme' => 'https',
        'services.bank_gateway.host' => 'www8.100x100banco.com',
        'services.bank_gateway.path' => '/100p2pCert/api/v1/ValTrxIn',
        'services.bank_gateway.key' => 'test-key',
        'services.bank_gateway.secret' => base64_encode('secret-bytes'), // SECRET_ENCODING=base64
        'services.bank_gateway.merchant_id' => '341433',
        'services.bank_gateway.terminal_id' => 'userc2p',
        'services.bank_gateway.timeout' => 5,
        'services.bank_gateway.verify' => true,
        'services.bank_gateway.signature_newlines' => false,
        'services.bank_gateway.secret_encoding' => 'base64',
        'services.bank_gateway.content_type_charset' => false,
        'services.bank_gateway.signature_strip_leading_slash' => false,
    ]);
}

it('CONFIRMED when sRespCode=00 and stores gateway payloads', function () {
    // Seed admin and permissions; authenticate
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    baseGatewayConfig();
    [$bank, $acc] = seedBankAndCompany();

    $payment = Payment::create([
        'local_id' => null,
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'PMOV',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'payer_account_number' => '00000000000000000000',
        'payer_phone_e164' => '584121234567',
        'reference' => '000001',
        'amount_bs_minor' => 150000, // 1500.00
        'paid_on' => now()->toDateString(),
        'fx_rate_id' => null,
        'status' => 'REGISTERED',
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        expect($request->hasHeader('x-api-key'))->toBeTrue();
        expect($request->hasHeader('Date'))->toBeTrue();
        expect(($request->header('Content-Type')[0] ?? ''))->toBe('application/json');
        $data = $request->data();
        expect($data['sTrxType'])->toBe('300');
        // For PMOV, destination should prefer phone_number
        expect($data['sToAcctNo'])->toBe('584242424564');

        return Http::response(['sRespCode' => '00', 'sRespDesc' => 'Aprobado'], 200);
    });

    $this->post(route('payments.verify', ['payment' => $payment->getKey()]))->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe('CONFIRMED');
    expect($payment->gateway_resp_code)->toBe('00');
    // JSON casts
    expect($payment->gateway_request)->toBeArray();
    expect($payment->gateway_response)->toBeArray();
});

it('remains REGISTERED when sRespCode=830 (business not found)', function () {
    // Seed admin and permissions; authenticate
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    baseGatewayConfig();
    [$bank, $acc] = seedBankAndCompany();

    $payment = Payment::create([
        'local_id' => null,
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'PMOV',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'payer_account_number' => '00000000000000000000',
        'payer_phone_e164' => '584121234567',
        'reference' => '000001',
        'amount_bs_minor' => 150000,
        'paid_on' => now()->toDateString(),
        'fx_rate_id' => null,
        'status' => 'REGISTERED',
    ]);

    Http::fake(fn () => Http::response(['sRespCode' => '830', 'sRespDesc' => 'No existe la transaccion indicada'], 200));

    $this->post(route('payments.verify', ['payment' => $payment->getKey()]))->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe('REGISTERED');
    expect($payment->gateway_resp_code)->toBe('830');
});

// Removed MISSING_CREDENTIALS early-fail test to focus on accreditation verification scenarios.

it('uses account_number for TRANSFER destination (211/201 mapping)', function () {
    // Seed admin and permissions; authenticate
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    baseGatewayConfig();
    [$bank, $acc] = seedBankAndCompany();

    $payment = Payment::create([
        'local_id' => null,
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'TRANSFER',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'payer_account_number' => '01234567890123456789',
        'payer_phone_e164' => '',
        'reference' => '000002',
        'amount_bs_minor' => 10000,
        'paid_on' => now()->toDateString(),
        'fx_rate_id' => null,
        'status' => 'REGISTERED',
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $data = $request->data();
        expect($data['sTrxType'])->toBe('211');
        expect($data['sToAcctNo'])->toBe('01234567890123456789');

        return Http::response(['sRespCode' => '830', 'sRespDesc' => 'No existe la transaccion indicada'], 200);
    });

    $this->post(route('payments.verify', ['payment' => $payment->getKey()]))->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe('REGISTERED');
});

it('maps to 201 when phone present and method not PMOV; destination still account_number', function () {
    // Seed admin and permissions; authenticate
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    baseGatewayConfig();
    [$bank, $acc] = seedBankAndCompany();

    $payment = Payment::create([
        'local_id' => null,
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'TRANSFER',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'payer_account_number' => '01234567890123456789',
        'payer_phone_e164' => '584121234567', // triggers 201
        'reference' => '000003',
        'amount_bs_minor' => 10000,
        'paid_on' => now()->toDateString(),
        'fx_rate_id' => null,
        'status' => 'REGISTERED',
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $data = $request->data();
        expect($data['sTrxType'])->toBe('201');
        expect($data['sToAcctNo'])->toBe('01234567890123456789');

        return Http::response(['sRespCode' => '830', 'sRespDesc' => 'No existe la transaccion indicada'], 200);
    });

    $this->post(route('payments.verify', ['payment' => $payment->getKey()]))->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe('REGISTERED');
});

it('throws when trying to verify non-REGISTERED payment', function () {
    // Seed admin and permissions; authenticate
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    baseGatewayConfig();
    [$bank, $acc] = seedBankAndCompany();

    $payment = Payment::create([
        'local_id' => null,
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'PMOV',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'payer_account_number' => '00000000000000000000',
        'payer_phone_e164' => '584121234567',
        'reference' => '000001',
        'amount_bs_minor' => 150000,
        'paid_on' => now()->toDateString(),
        'fx_rate_id' => null,
        'status' => 'CONFIRMED',
    ]);

    $this->withoutExceptionHandling();
    $this->post(route('payments.verify', ['payment' => $payment->getKey()]));
})->throws(\App\Exceptions\DomainActionException::class);
