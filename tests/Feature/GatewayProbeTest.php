<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('gateway probe returns business JSON (830) with HTTP 200', function () {
    // Auth as admin
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);
    // Arrange config to avoid reading real .env
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

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        // Assert headers minimal
        expect($request->hasHeader('x-api-key'))->toBeTrue();
        expect($request->hasHeader('Date'))->toBeTrue();
        expect($request->header('Content-Type')[0] ?? '')->toBe('application/json');
        // Body is JSON, assert required keys
        $data = $request->data();
        expect($data)->toHaveKeys([
            'sMerchantId', 'sTrxId', 'sTrxType', 'sBankId', 'sDocumentId', 'sFromAcctNo', 'sToAcctNo', 'nAmount', 'sReferenceNo', 'sDateTrx', 'sTerminalId',
        ]);

        // Respond with business JSON 830
        return Http::response(['sRespCode' => '830', 'sRespDesc' => 'No existe la transaccion indicada'], 200);
    });

    // Default probe (no query params) should return 200 with business JSON 830
    $res = $this->getJson(route('payments.gateway-probe'));
    $res->assertOk();
    $res->assertJsonFragment([
        'http_status' => 200,
        'sRespCode' => '830',
        'sRespDesc' => 'No existe la transaccion indicada',
    ]);
});

it('gateway probe builds TRANSFER 211 payload deterministically (accounts + reference)', function () {
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);

    config([
        'services.bank_gateway.scheme' => 'https',
        'services.bank_gateway.host' => 'www8.100x100banco.com',
        'services.bank_gateway.path' => '/100p2pCert/api/v1/ValTrxIn',
        'services.bank_gateway.key' => 'test-key',
        'services.bank_gateway.secret' => base64_encode('secret-bytes'),
        'services.bank_gateway.merchant_id' => '341433',
        'services.bank_gateway.terminal_id' => 'userc2p',
        'services.bank_gateway.signature_newlines' => false,
        'services.bank_gateway.secret_encoding' => 'base64',
        'services.bank_gateway.content_type_charset' => false,
        'services.bank_gateway.signature_strip_leading_slash' => false,
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        expect($request->hasHeader('x-api-key'))->toBeTrue();
        expect(($request->header('Content-Type')[0] ?? ''))->toBe('application/json');
        $data = $request->data();
        expect($data['sTrxType'] ?? null)->toBe('211');
        expect(strlen((string) ($data['sFromAcctNo'] ?? '')))->toBe(20);
        expect(strlen((string) ($data['sToAcctNo'] ?? '')))->toBe(20);
        expect(preg_match('/^\d{6,8}$/', (string) ($data['sReferenceNo'] ?? '')))->toBe(1);

        // Respond business JSON
        return Http::response(['sRespCode' => '830', 'sRespDesc' => 'No existe la transaccion indicada'], 200);
    });

    $q = [
        'sTrxType' => '211',
        'sBankId' => '156',
        'sDocumentId' => 'V12345678',
        'sFromAcctNo' => '01560011223344556677',
        'sToAcctNo' => '01560099887766554433',
        'nAmount' => '1500.00',
        'sReferenceNo' => '12345678',
        'sDateTrx' => '2025-10-10',
        'sTrxId' => '2025101000000101',
    ];
    $res = $this->getJson(route('payments.gateway-probe', $q));
    $res->assertOk()->assertJsonFragment(['http_status' => 200, 'sRespCode' => '830']);
});

it('gateway probe builds PMOV 300 payload deterministically (phones + ref=0)', function () {
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);

    config([
        'services.bank_gateway.scheme' => 'https',
        'services.bank_gateway.host' => 'www8.100x100banco.com',
        'services.bank_gateway.path' => '/100p2pCert/api/v1/ValTrxIn',
        'services.bank_gateway.key' => 'test-key',
        'services.bank_gateway.secret' => base64_encode('secret-bytes'),
        'services.bank_gateway.merchant_id' => '341433',
        'services.bank_gateway.terminal_id' => 'userc2p',
        'services.bank_gateway.signature_newlines' => false,
        'services.bank_gateway.secret_encoding' => 'base64',
        'services.bank_gateway.content_type_charset' => false,
        'services.bank_gateway.signature_strip_leading_slash' => false,
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $data = $request->data();
        expect($data['sTrxType'] ?? null)->toBe('300');
        expect(preg_match('/^58\d{10}$/', (string) ($data['sFromAcctNo'] ?? '')))->toBe(1);
        expect(preg_match('/^58\d{10}$/', (string) ($data['sToAcctNo'] ?? '')))->toBe(1);
        expect(preg_match('/^\d{6,8}$/', (string) ($data['sReferenceNo'] ?? '')))->toBe(1);

        return Http::response(['sRespCode' => '991', 'sRespDesc' => 'Error general'], 200);
    });

    $q = [
        'sTrxType' => '300',
        'sBankId' => '156',
        'sDocumentId' => 'V12345678',
        'sFromAcctNo' => '584241112233',
        'sToAcctNo' => '584242223334',
        'nAmount' => '1500.00',
        'sReferenceNo' => '123456',
        'sDateTrx' => '2025-10-10',
        'sTrxId' => '2025101000000102',
    ];
    $res = $this->getJson(route('payments.gateway-probe', $q));
    $res->assertOk()->assertJsonFragment(['http_status' => 200, 'sRespCode' => '991']);
});
