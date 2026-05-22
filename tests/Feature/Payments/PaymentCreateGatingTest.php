<?php

declare(strict_types=1);

use App\Models\Audit;
use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function _seedBankAndCompany(): array
{
    $bank = Bank::create([
        'code' => 'BANKTEST',
        'bank_code' => '156',
        'name' => 'Banco Prueba',
        'is_active' => true,
    ]);

    $acc = CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01560030680000776369',
        'phone_number' => '584242223334',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);

    return [$bank, $acc];
}

function _baseGatewayConfig(): void
{
    config([
        'services.bank_gateway.scheme' => 'https',
        'services.bank_gateway.host' => 'www8.100x100banco.com',
        'services.bank_gateway.path' => '/100p2pCert/api/v1/ValTrxIn',
        'services.bank_gateway.key' => 'test-key',
        'services.bank_gateway.secret' => base64_encode('secret-bytes'),
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

it('does not create TRF when gateway not OK and audits verify_failed', function () {
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    _baseGatewayConfig();
    [$bank, $acc] = _seedBankAndCompany();

    Http::fake(fn () => Http::response(['sRespCode' => '830', 'sRespDesc' => 'No existe la transaccion indicada'], 200));

    $payload = [
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'TRANSFER',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12062754',
        'payer_account_number' => '01050026551026379636',
        'payer_phone_e164' => '',
        'reference' => '51618841',
        'amount_bs_minor' => 510070,
        'paid_on' => now()->toDateString(),
        'fx_rate_id' => null,
    ];

    $this->post(route('payments.store'), $payload)->assertRedirect();

    expect(Payment::query()->count())->toBe(0);
    expect(Audit::query()->where('event', 'payment.verify_failed')->count())->toBeGreaterThan(0);
});

it('creates PMOV as CONFIRMED when gateway OK and redirects to apply', function () {
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    _baseGatewayConfig();
    [$bank, $acc] = _seedBankAndCompany();

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $data = $request->data();
        expect($data['sTrxType'])->toBe('300');

        return Http::response(['sRespCode' => '00', 'sRespDesc' => 'Aprobado'], 200);
    });

    $payload = [
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'PMOV',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12062754',
        'payer_account_number' => '00000000000000000000',
        'payer_phone_e164' => '584212223334',
        'reference' => '123456',
        'amount_bs_minor' => 150000,
        'paid_on' => now()->toDateString(),
        'fx_rate_id' => null,
    ];

    $resp = $this->post(route('payments.store'), $payload);
    $resp->assertRedirect();

    $p = Payment::query()->first();
    expect($p)->not->toBeNull();
    expect($p->status)->toBe('CONFIRMED');
    expect($p->gateway_resp_code)->toBe('00');
});

it('audits duplicate idempotent create and keeps single record', function () {
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    _baseGatewayConfig();
    [$bank, $acc] = _seedBankAndCompany();

    Http::fake(fn () => Http::response(['sRespCode' => '00', 'sRespDesc' => 'Aprobado'], 200));

    $payload = [
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'PMOV',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12062754',
        'payer_account_number' => '00000000000000000000',
        'payer_phone_e164' => '584212223334',
        'reference' => '123456',
        'amount_bs_minor' => 150000,
        'paid_on' => now()->toDateString(),
        'fx_rate_id' => null,
    ];

    $this->post(route('payments.store'), $payload)->assertRedirect();
    $this->post(route('payments.store'), $payload)->assertRedirect();

    expect(Payment::query()->count())->toBe(1);
    expect(Audit::query()->where('event', 'payment.idempotent_duplicate')->count())->toBeGreaterThan(0);
});

it('allows recreating a transfer payment when the previous matching payment was soft deleted', function () {
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    _baseGatewayConfig();
    [$bank, $acc] = _seedBankAndCompany();

    Http::fake(fn () => Http::response(['sRespCode' => '00', 'sRespDesc' => 'Aprobado'], 200));

    $payload = [
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 123,
        'company_bank_account_id' => $acc->id,
        'method' => 'TRANSFER',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '17168356',
        'payer_account_number' => '01020105580000569965',
        'payer_phone_e164' => '',
        'reference' => '99708301',
        'amount_bs_minor' => 50000000,
        'paid_on' => '2026-05-06',
        'fx_rate_id' => null,
    ];

    $this->post(route('payments.store'), $payload)->assertRedirect();

    $original = Payment::query()->where('reference', '99708301')->firstOrFail();
    $originalKey = (string) $original->getAttribute('idempotency_key');
    $original->delete();

    $this->post(route('payments.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payments = Payment::withTrashed()->where('reference', '99708301')->orderBy('id')->get();

    expect($payments)->toHaveCount(2);
    expect($payments[0]->trashed())->toBeTrue();
    expect($payments[1]->trashed())->toBeFalse();
    expect($payments[1]->getAttribute('idempotency_key'))->not->toBe($originalKey);
});

it('allows reusing DEB reference when previous payment is voided', function () {
    $this->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\PaymentStatusesSeeder::class,
        Database\Seeders\PaymentTypesSeeder::class,
    ]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);

    [$bank, $acc] = _seedBankAndCompany();
    $acc->setAttribute('allow_debit', true);
    $acc->save();

    $ref = '23432432';

    $old = Payment::create([
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'DEB',
        'origin_bank_id' => null,
        'payer_document_type' => 'V',
        'payer_document_number' => '9966862',
        'payer_account_number' => null,
        'payer_phone_e164' => null,
        'reference' => $ref,
        'amount_bs_minor' => 6007300,
        'paid_on' => '2025-12-01',
        'fx_rate_id' => null,
        'status' => 'VOID',
        'voided_at' => now(),
    ]);
    expect($old)->not->toBeNull();

    $payload = [
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'local_id' => null,
        'company_bank_account_id' => $acc->id,
        'method' => 'DEB',
        'origin_bank_id' => null,
        'payer_document_type' => 'V',
        'payer_document_number' => '9966862',
        'payer_account_number' => null,
        'payer_phone_e164' => '',
        'reference' => $ref,
        // Change amount to avoid idempotency_key collision
        'amount_bs_minor' => 6007301,
        'paid_on' => '2025-12-01',
        'fx_rate_id' => null,
    ];

    $this->post(route('payments.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Payment::query()->count())->toBe(2);
    expect(Payment::query()->where('reference', $ref)->whereNull('voided_at')->count())->toBe(1);
});
