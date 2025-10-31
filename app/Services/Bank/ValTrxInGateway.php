<?php

declare(strict_types=1);

namespace App\Services\Bank;

use App\Contracts\Services\BankGatewayInterface;
use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\DocumentType;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ValTrxInGateway implements BankGatewayInterface
{
    public function verify(Payment $payment): array
    {
        $host = (string) config('services.bank_gateway.host', 'www8.100x100banco.com');
        $path = (string) config('services.bank_gateway.path', '/100p2pCert/api/v1/ValTrxIn');
        $scheme = (string) config('services.bank_gateway.scheme', 'https');
        $key = (string) config('services.bank_gateway.key');
        $keyEncoding = strtolower((string) config('services.bank_gateway.key_encoding', 'plain'));
        if ($keyEncoding === 'base64') {
            $decodedKey = base64_decode($key, true);
            if ($decodedKey !== false) {
                // Bank expects ASCII string in header; if bytes are ASCII-hex, this is fine
                $key = $decodedKey;
            }
        }
        $secret = (string) config('services.bank_gateway.secret');
        $merchantId = (string) config('services.bank_gateway.merchant_id');
        $terminalId = (string) config('services.bank_gateway.terminal_id');
        $timeout = (int) config('services.bank_gateway.timeout', 30);
        $verifySsl = (bool) config('services.bank_gateway.verify', true);
        $concatWithNewlines = (bool) config('services.bank_gateway.signature_newlines', false);
        $signatureMode = strtoupper((string) config('services.bank_gateway.signature_mode', 'A'));
        $secretEncoding = strtolower((string) config('services.bank_gateway.secret_encoding', 'plain'));
        $secretPostDecode = strtolower((string) config('services.bank_gateway.secret_post_decode', 'none'));
        $withCharset = (bool) config('services.bank_gateway.content_type_charset', false);
        $stripLeadingSlash = (bool) config('services.bank_gateway.signature_strip_leading_slash', false);

        // Resolve destination bank and account (company)
        $companyAccount = null;
        if ($payment->getAttribute('company_bank_account_id')) {
            $companyAccount = CompanyBankAccount::query()->find($payment->getAttribute('company_bank_account_id'));
        }

        $bankCode = null;
        if ($companyAccount) {
            $bank = Bank::query()->find($companyAccount->getAttribute('bank_id'));
            $bankCode = $bank?->getAttribute('bank_code');
            if (is_string($bankCode)) {
                $bankCode = trim($bankCode);
            }
        }

        // Resolve ORIGIN bank code (from origin_bank_id on payment)
        $originBankCode = null;
        $originBankId = (int) ($payment->getAttribute('origin_bank_id') ?? 0);
        if ($originBankId > 0) {
            $originBank = Bank::query()->find($originBankId);
            $originBankCode = $originBank?->getAttribute('bank_code');
            if (is_string($originBankCode)) {
                $originBankCode = trim($originBankCode);
            }
        }

        // Infer transaction type (fallback to payment_type_id if legacy 'method' missing)
        $methodCode = strtoupper((string) ($payment->getAttribute('method') ?? ''));
        if ($methodCode === '') {
            try {
                $ptId = (int) ($payment->getAttribute('payment_type_id') ?? 0);
                if ($ptId > 0) {
                    /** @var null|\App\Models\PaymentType $pt */
                    $pt = \App\Models\PaymentType::query()->find($ptId);
                    $methodCode = strtoupper((string) ($pt?->getAttribute('code') ?? ''));
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        // Normalize common PMOV synonyms to PMOV
        $method = in_array($methodCode, ['PAGOMOVIL', 'PAGO MOVIL', 'PAGO-MOVIL'], true) ? 'PMOV' : ($methodCode ?: 'TRANSFER');
        $trxType = in_array($method, ['PMOV'], true) ? 300 : 211; // Sólo 211 (transfer) o 300 (PMOV)

        // Build required fields
        $trxId = gmdate('Ymd').str_pad((string) $payment->getKey(), 8, '0', STR_PAD_LEFT);
        $docNum = (string) ($payment->getAttribute('payer_document_number') ?? '');
        $docType = (string) ($payment->getAttribute('payer_document_type') ?? '');
        if ($docType === '') {
            $docTypeId = (int) ($payment->getAttribute('payer_document_type_id') ?? 0);
            if ($docTypeId > 0) {
                try {
                    /** @var null|\App\Models\DocumentType $dt */
                    $dt = DocumentType::query()->find($docTypeId);
                    $docType = (string) ($dt?->getAttribute('code') ?? '');
                } catch (\Throwable $e) {
                    $docType = '';
                }
            }
        }
        if ($docType === '') {
            $docType = 'V';
        }
        $sDocumentId = strtoupper($docType).$docNum;

        // Normalize phone without '+' and non-digits; coerce local mobile 0XXXXXXXXXX -> 58XXXXXXXXXX
        $normalizePhone = static function ($raw): string {
            $d = preg_replace('/\D+/', '', ltrim((string) $raw, '+')) ?? '';
            if ($d === '') {
                return '';
            }
            if (str_starts_with($d, '58')) {
                return $d;
            } // already E.164 country code
            if (str_starts_with($d, '0') && strlen($d) === 11) {
                return '58'.substr($d, 1); // 0412xxxxxxx => 58412xxxxxxx
            }

            return $d;
        };
        $fromPhone = $normalizePhone($payment->getAttribute('payer_phone_e164') ?? '');

        $fromAcct = $trxType === 300
            ? $fromPhone
            : (preg_replace('/\D+/', '', (string) ($payment->getAttribute('payer_account_number') ?? '')) ?? '');
        $fromAcct = trim($fromAcct);

        // Prefer destination phone for PMOV; otherwise use account number
        if ($trxType === 300) {
            $toPhone = (string) ($companyAccount?->getAttribute('phone_number') ?? '');
            $toAcct = $normalizePhone($toPhone);
        } else {
            $toAcct = (string) ($companyAccount?->getAttribute('account_number') ?? '');
        }
        $toAcct = trim($toAcct);

        // Determine sBankId policy per trx type
        $pmovPolicy = strtolower((string) config('services.bank_gateway.pmov_sbankid', 'destination'));
        $sBankIdRaw = '';
        if ($trxType === 211) {
            // 211: ORIGEN requerido (o derivado de cuenta del pagador)
            $sBankIdRaw = is_string($originBankCode) && $originBankCode !== '' ? (string) $originBankCode : '';
            if ($sBankIdRaw === '') {
                $origin = $fromAcct;
                if ($origin !== '' && strlen($origin) >= 4) {
                    $sBankIdRaw = substr($origin, 0, 4);
                }
            }
            if ($sBankIdRaw === '') {
                return [
                    'ok' => false,
                    'code' => 'MISSING_ORIGIN_BANK',
                    'message' => 'Banco de origen inválido o sin bank_code (4 dígitos).',
                    'raw_request' => null,
                    'raw_response' => null,
                ];
            }
        } else { // 300 PMOV
            if ($pmovPolicy === 'origin') {
                $sBankIdRaw = is_string($originBankCode) ? (string) $originBankCode : '';
                if ($sBankIdRaw === '' && is_string($bankCode)) {
                    $sBankIdRaw = (string) $bankCode; // fallback destino
                }
            } else { // destination (default)
                $sBankIdRaw = is_string($bankCode) ? (string) $bankCode : '';
                if ($sBankIdRaw === '' && is_string($originBankCode)) {
                    $sBankIdRaw = (string) $originBankCode; // fallback origen
                }
            }
            if ($sBankIdRaw === '') {
                return [
                    'ok' => false,
                    'code' => 'MISSING_PMOV_BANK',
                    'message' => 'No se pudo determinar sBankId para Pago Móvil (origen/destino).',
                    'raw_request' => null,
                    'raw_response' => null,
                ];
            }
        }

        // Normalize to digits without leading zeros (bank expects 3-digit code like 105 for 0105)
        $sBankId = preg_replace('/\D+/', '', (string) $sBankIdRaw) ?? '';
        $sBankId = ltrim($sBankId, '0');

        // Amount with 2 decimals
        $amountMinor = (int) ($payment->getAttribute('amount_bs_minor') ?? 0);
        $nAmount = (float) ($amountMinor / 100);

        // Referencia: usar exactamente los dígitos ingresados por el usuario (6–12), sin truncar.
        // La validación del FormRequest ya garantiza 6–12 dígitos; aquí solo normalizamos a dígitos.
        $digits = static function ($s): string {
            return preg_replace('/\D+/', '', (string) $s) ?? '';
        };
        $reference = $digits($payment->getAttribute('reference') ?? '');
        $dateTrx = (string) ($payment->getAttribute('paid_on') ?? gmdate('Y-m-d'));

        // Build JSON payload in expected order
        $payload = [
            'sMerchantId' => $merchantId,
            'sTrxId' => $trxId,
            'sTrxType' => (string) $trxType,
            'sBankId' => $sBankId,
            'sDocumentId' => $sDocumentId,
            'sFromAcctNo' => $fromAcct,
            'sToAcctNo' => $toAcct,
            'nAmount' => $nAmount, // numeric; preserve two decimals in JSON below
            'sReferenceNo' => $reference,
            'sDateTrx' => $dateTrx,
            'sTerminalId' => $terminalId,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        // Date header in UTC per spec
        $dateHeader = gmdate('D, d M Y H:i:s').' GMT';

        // Step 3: sha256 of body; mode A = base64(hex), mode B = base64(raw)
        $shaRaw = hash('sha256', $body, true);
        $shaHex = bin2hex($shaRaw);
        $bodyHashB64 = base64_encode($signatureMode === 'A' ? $shaHex : $shaRaw);

        // Step 4: concatenate host + endpoint + date + bodyHash (configurable separator)
        $signPath = $path;
        if ($stripLeadingSlash) {
            $signPath = ltrim($signPath, '/');
        } else {
            if (! str_starts_with($signPath, '/')) {
                $signPath = '/'.$signPath;
            }
        }
        $message = $concatWithNewlines
            ? ($host."\n".$signPath."\n".$dateHeader."\n".$bodyHashB64)
            : ($host.$signPath.$dateHeader.$bodyHashB64);

        // Step 5: HMAC SHA256 with API-SECRET
        // Secret encoding: base64 means decode into raw bytes
        $secretKey = $secret;
        if ($secretEncoding === 'base64') {
            $decoded = base64_decode($secret, true);
            if ($decoded !== false) {
                $secretKey = $decoded;
            }
        }
        // If configured and decoded secret looks like ASCII hex, hex-decode to bytes
        $secretWasHex = false;
        if ($secretPostDecode === 'hex' && preg_match('/^[0-9a-f]+$/i', $secretKey) === 1 && (strlen($secretKey) % 2) === 0) {
            $hexBytes = @hex2bin($secretKey);
            if ($hexBytes !== false) {
                $secretKey = $hexBytes;
                $secretWasHex = true;
            }
        }

        $hmacRaw = hash_hmac('sha256', $message, $secretKey, true);
        $hmacHex = bin2hex($hmacRaw);
        $signature = base64_encode($signatureMode === 'A' ? $hmacHex : $hmacRaw);

        $url = $scheme.'://'.rtrim($host, '/').(str_starts_with($path, '/') ? $path : '/'.$path);

        // Log request (mask sensitive numbers)
        $mask = static function (?string $s, int $keep = 4): ?string {
            if (! is_string($s) || $s === '') {
                return $s;
            }
            $len = mb_strlen($s);
            if ($len <= $keep) {
                return str_repeat('*', $len);
            }

            return str_repeat('*', max(0, $len - $keep)).mb_substr($s, -$keep);
        };
        Log::info('bank.verify.request', [
            'payment_id' => (int) $payment->getKey(),
            'url' => $url,
            'method' => $method,
            'trx_type' => $trxType,
            // For clarity, include both destination and origin codes, and the final sBankId used
            'dest_bank_code' => (string) ($bankCode ?? ''),
            'origin_bank_code' => (string) ($originBankCode ?? ''),
            'sBankId' => (string) $sBankId,
            'sDocumentId_masked' => $mask($sDocumentId, 2),
            'sFromAcctNo_masked' => $mask($fromAcct),
            'sToAcctNo_masked' => $mask($toAcct),
            'nAmount' => $nAmount,
            'sReferenceNo' => $reference,
            'sDateTrx' => $dateTrx,
            'headers' => [
                'has_x_api_key' => $key !== '',
                'content_type' => 'application/json'.($withCharset ? '; charset=utf-8' : ''),
            ],
            'signature_mode' => $signatureMode,
            'signature_newlines' => $concatWithNewlines,
            'signature_strip_leading_slash' => $stripLeadingSlash,
            'secret_post_decode' => $secretPostDecode,
            'secret_was_hex' => $secretWasHex,
        ]);

        // Validate minimal credentials and destination data
        if ($key === '' || $secret === '' || $merchantId === '' || $terminalId === '') {
            return [
                'ok' => false,
                'code' => 'MISSING_CREDENTIALS',
                'message' => 'Faltan credenciales del gateway (key/secret/merchant_id/terminal_id).',
                'raw_request' => $body,
                'raw_response' => null,
            ];
        }
        if ($trxType === 300 && preg_match('/^58\d{10}$/', $toAcct) !== 1) {
            return [
                'ok' => false,
                'code' => 'MISSING_DESTINATION_PHONE',
                'message' => 'La cuenta receptora no posee teléfono Pago Móvil válido (58XXXXXXXXXX).',
                'raw_request' => $body,
                'raw_response' => null,
            ];
        }

        $contentType = 'application/json'.($withCharset ? '; charset=utf-8' : '');
        $rawRequest = $body;
        $rawResponse = null;
        $json = [];
        try {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'Date' => $dateHeader,
                'x-signature' => $signature,
                'Content-Type' => $contentType,
            ])->timeout($timeout)->withOptions(['verify' => $verifySsl])
                ->withBody($rawRequest, $contentType)
                ->post($url);

            $rawResponse = $response->body();
            try {
                $json = $response->json() ?? [];
            } catch (\Throwable $e) {
                $json = [];
            }

            $respCode = (string) ($json['sRespCode'] ?? ($json['code'] ?? (string) $response->status()));
            $respDesc = (string) ($json['sRespDesc'] ?? ($json['message'] ?? ''));
            $sReqId = (string) ($json['sReqId'] ?? '');
            $reqIdHash = $sReqId !== '' ? substr(hash('sha256', $sReqId), 0, 16) : null;
            $showSreqId = (bool) config('services.bank_gateway.log_show_sreqid', false);
            $ok = in_array($respCode, ['00', 'ACCP', '831'], true);

            Log::info('bank.verify.response', [
                'payment_id' => (int) $payment->getKey(),
                'http_status' => $response->status(),
                'code' => $respCode,
                'message_snippet' => mb_substr($respDesc, 0, 256),
                'raw_response_len' => mb_strlen($rawResponse),
                'ReqId' => $showSreqId ? ($sReqId !== '' ? $sReqId : null) : null,
                'ReqIdHash' => $reqIdHash,
            ]);

            // Optional fallback for PMOV: some affiliations expect sBankId=origin; others destination.
            // If we got a generic failure or not found, retry once with alternate policy.
            $finalOk = $ok;
            $finalCode = $respCode;
            $finalMsg = $respDesc;
            $finalRawRequest = $rawRequest;
            $finalRawResponse = $rawResponse;

            if (! $ok && $trxType === 300 && in_array($respCode, ['830', '991'], true)) {
                $altPolicy = $pmovPolicy === 'origin' ? 'destination' : 'origin';
                $altRaw = '';
                if ($altPolicy === 'origin' && is_string($originBankCode) && $originBankCode !== '') {
                    $altRaw = (string) $originBankCode;
                } elseif ($altPolicy === 'destination' && is_string($bankCode) && $bankCode !== '') {
                    $altRaw = (string) $bankCode;
                }
                if ($altRaw !== '') {
                    $altSBankId = ltrim(preg_replace('/\D+/', '', $altRaw) ?? '', '0');

                    // Build alternate payload
                    $altPayload = [
                        'sMerchantId' => $merchantId,
                        'sTrxId' => $trxId,
                        'sTrxType' => (string) $trxType,
                        'sBankId' => $altSBankId,
                        'sDocumentId' => $sDocumentId,
                        'sFromAcctNo' => $fromAcct,
                        'sToAcctNo' => $toAcct,
                        'nAmount' => $nAmount,
                        'sReferenceNo' => $reference,
                        'sDateTrx' => $dateTrx,
                        'sTerminalId' => $terminalId,
                    ];
                    $altBody = json_encode($altPayload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

                    $dateHeader2 = gmdate('D, d M Y H:i:s').' GMT';
                    $shaRaw2 = hash('sha256', $altBody, true);
                    $shaHex2 = bin2hex($shaRaw2);
                    $bodyHashB642 = base64_encode($signatureMode === 'A' ? $shaHex2 : $shaRaw2);
                    $signPath2 = $path;
                    if ($stripLeadingSlash) {
                        $signPath2 = ltrim($signPath2, '/');
                    } else {
                        if (! str_starts_with($signPath2, '/')) {
                            $signPath2 = '/'.$signPath2;
                        }
                    }
                    $message2 = $concatWithNewlines
                        ? ($host."\n".$signPath2."\n".$dateHeader2."\n".$bodyHashB642)
                        : ($host.$signPath2.$dateHeader2.$bodyHashB642);
                    $hmacRaw2 = hash_hmac('sha256', $message2, $secretKey, true);
                    $hmacHex2 = bin2hex($hmacRaw2);
                    $signature2 = base64_encode($signatureMode === 'A' ? $hmacHex2 : $hmacRaw2);

                    Log::info('bank.verify.retry', [
                        'payment_id' => (int) $payment->getKey(),
                        'reason_code' => $respCode,
                        'alt_policy' => $altPolicy,
                        'sBankId_alt' => $altSBankId,
                    ]);

                    $resp2 = Http::withHeaders([
                        'x-api-key' => $key,
                        'Date' => $dateHeader2,
                        'x-signature' => $signature2,
                        'Content-Type' => $contentType,
                    ])->timeout($timeout)->withOptions(['verify' => $verifySsl])
                        ->withBody($altBody, $contentType)
                        ->post($url);

                    $rawResponse2 = $resp2->body();
                    $json2 = [];
                    try {
                        $json2 = $resp2->json() ?? [];
                    } catch (\Throwable $e) {
                        $json2 = [];
                    }
                    $code2 = (string) ($json2['sRespCode'] ?? ($json2['code'] ?? (string) $resp2->status()));
                    $desc2 = (string) ($json2['sRespDesc'] ?? ($json2['message'] ?? ''));
                    $sReqId2 = (string) ($json2['sReqId'] ?? '');
                    $reqIdHash2 = $sReqId2 !== '' ? substr(hash('sha256', $sReqId2), 0, 16) : null;
                    $ok2 = in_array($code2, ['00', 'ACCP', '831'], true);

                    Log::info('bank.verify.response.retry', [
                        'payment_id' => (int) $payment->getKey(),
                        'http_status' => $resp2->status(),
                        'code' => $code2,
                        'message_snippet' => mb_substr($desc2, 0, 256),
                        'raw_response_len' => mb_strlen($rawResponse2),
                        'ReqId' => $showSreqId ? ($sReqId2 !== '' ? $sReqId2 : null) : null,
                        'ReqIdHash' => $reqIdHash2,
                    ]);

                    // Adopt retry result
                    $finalOk = $ok2;
                    $finalCode = $code2;
                    $finalMsg = $desc2;
                    $finalRawRequest = $altBody;
                    $finalRawResponse = $rawResponse2;
                }
            }

            return [
                'ok' => $finalOk,
                'code' => $finalCode,
                'message' => $finalMsg,
                'raw_request' => $finalRawRequest,
                'raw_response' => $finalRawResponse,
            ];
        } catch (\Throwable $e) {
            Log::error('bank.verify.http_exception', [
                'payment_id' => (int) $payment->getKey(),
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'code' => 'HTTP_CLIENT_ERROR',
                'message' => $e->getMessage(),
                'raw_request' => $rawRequest,
                'raw_response' => $rawResponse,
            ];
        }
    }
}
