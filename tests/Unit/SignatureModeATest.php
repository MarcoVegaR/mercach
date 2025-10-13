<?php

it('builds Mode A canonical and signature with expected lengths', function () {
    $host = 'www8.100x100banco.com';
    $path = '/100p2pCert/api/v1/ValTrxIn';
    $date = 'Fri, 10 Oct 2025 13:59:59 GMT';

    $payload = [
        'sMerchantId' => '341433',
        'sTrxId' => '2025101000000001',
        'sTrxType' => '300',
        'sBankId' => '156',
        'sDocumentId' => 'V12345678',
        'sFromAcctNo' => '584121234567',
        'sToAcctNo' => '584242424564',
        'nAmount' => 1500.00,
        'sReferenceNo' => '0',
        'sDateTrx' => '2025-10-10',
        'sTerminalId' => 'userc2p',
    ];

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

    // Mode A: Base64(HEX(SHA256(body)))
    $shaRaw = hash('sha256', $body, true);
    $shaHex = bin2hex($shaRaw);
    $bodyHashB64 = base64_encode($shaHex);

    // canonical = host + path + Date + body_hash_b64
    $canonical = $host.$path.$date.$bodyHashB64;

    // SECRET_BYTES = base64_decode(API_SECRET) una sola vez (usar secreto de prueba)
    $secretBytes = base64_decode(base64_encode('dummy-secret-bytes-1234567890'));

    // x-signature = Base64( HMAC-SHA256(canonical, SECRET_BYTES) en HEX )
    $hmacRaw = hash_hmac('sha256', $canonical, $secretBytes, true);
    $hmacHex = bin2hex($hmacRaw);
    $signature = base64_encode($hmacHex);

    // Asserts: body_hash_b64 y signature deben medir ~88 chars (64 bytes hex -> base64)
    expect(strlen($bodyHashB64))->toBe(88);
    expect(strlen($signature))->toBe(88);
    expect($canonical)->toContain($host, $path, $date, $bodyHashB64);
});
