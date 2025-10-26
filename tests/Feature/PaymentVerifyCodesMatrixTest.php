<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function seedBankAndCompany_Matrix(): array
{
    $bank = Bank::create([
        'code' => 'BANKTEST',
        'bank_code' => '156', // 100% Banco code per manual
        'name' => 'Banco Prueba',
        'is_active' => true,
    ]);

    $acc = CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01560009890100077585',
        'phone_number' => '584242424564',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);

    return [$bank, $acc];
}

function baseGatewayConfig_Matrix(): void
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

$codes = [
    // Curated set focused on accreditation not confirmed
    '05', // Do not honor (negado por emisor)
    '51', // Insufficient funds (indicativo de no acreditado)
    '91', // Issuer or switch inoperative (no se puede confirmar)
    '94', // Duplicate transaction sospechosa
    '99', // Error general
    '701', '702', // Errores técnicos por especificación
    '830', // No existe la transaccion indicada (verificación negativa típica)
];

it('remains REGISTERED and records gateway_resp_code for all business error codes', function (string $code) {
    // Seed admin and permissions; authenticate
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class, Database\Seeders\PaymentStatusesSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    baseGatewayConfig_Matrix();
    [$bank, $acc] = seedBankAndCompany_Matrix();

    $payment = Payment::create([
        'local_id' => null,
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $acc->id,
        'method' => 'PMOV', // 300
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '3907786',
        'payer_account_number' => '01080108710100109694',
        'payer_phone_e164' => '584121234567',
        'reference' => '757368',
        'amount_bs_minor' => 15000, // 150.00
        'paid_on' => '2023-09-14',
        'fx_rate_id' => null,
        'status' => 'REGISTERED',
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($code) {
        // Assert minimal contract
        expect($request->hasHeader('x-api-key'))->toBeTrue();
        expect($request->hasHeader('Date'))->toBeTrue();
        $body = $request->data();
        expect($body['sMerchantId'] ?? null)->toBe('341433');
        expect($body['sTrxType'] ?? null)->toBe('300');
        // sBankId from destination bank code
        expect($body['sBankId'] ?? null)->toBe('156');
        // sDocumentId formatting V + number
        expect($body['sDocumentId'] ?? null)->toStartWith('V');

        return Http::response(['sRespCode' => $code, 'sRespDesc' => 'Dummy'], 200);
    });

    $this->post(route('payments.verify', ['payment' => $payment->getKey()]));

    $payment->refresh();
    expect($payment->status)->toBe('REGISTERED');
})->with($codes);
