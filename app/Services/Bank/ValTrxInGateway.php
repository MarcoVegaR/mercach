<?php

declare(strict_types=1);

namespace App\Services\Bank;

use App\Contracts\Services\BankGatewayInterface;
use App\Models\Bank;
use App\Models\CompanyBankAccount;
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
        $trxType = 211; // transfer with account by default
        $fromPhoneRaw = (string) ($payment->getAttribute('payer_phone_e164') ?? '');
        if (in_array($method, ['PMOV'], true)) {
            $trxType = 300; // Pago Móvil explícito
        } elseif (trim($fromPhoneRaw) !== '') {
            $trxType = 201; // Origen con teléfono (según especificación)
        }

        // Build required fields
        $trxId = gmdate('Ymd').str_pad((string) $payment->getKey(), 8, '0', STR_PAD_LEFT);
        $docType = (string) ($payment->getAttribute('payer_document_type') ?? 'V');
        $docNum = (string) ($payment->getAttribute('payer_document_number') ?? '');
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

        $fromAcct = $trxType === 300 || $trxType === 201
            ? $fromPhone
            : (preg_replace('/\D+/', '', (string) ($payment->getAttribute('payer_account_number') ?? '')) ?? '');
        $fromAcct = trim($fromAcct);

        // Prefer destination phone for PMOV; otherwise use account number
        if ($trxType === 300) {
            $toPhone = (string) ($companyAccount?->getAttribute('phone_number') ?? '');
            $toAcct = $toPhone !== '' ? $normalizePhone($toPhone) : (string) ($companyAccount?->getAttribute('account_number') ?? '');
        } else {
            $toAcct = (string) ($companyAccount?->getAttribute('account_number') ?? $companyAccount?->getAttribute('phone_number') ?? '');
        }
        $toAcct = trim($toAcct);

        // Amount with 2 decimals
        $amountMinor = (int) ($payment->getAttribute('amount_bs_minor') ?? 0);
        $nAmount = (float) ($amountMinor / 100);

        $reference = (string) ($payment->getAttribute('reference') ?? '');
        $dateTrx = (string) ($payment->getAttribute('paid_on') ?? gmdate('Y-m-d'));

        // Build JSON payload in expected order
        $payload = [
            'sMerchantId' => $merchantId,
            'sTrxId' => $trxId,
            'sTrxType' => $trxType,
            'sBankId' => (string) ($bankCode ?? ''),
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
            'bank_code' => (string) ($bankCode ?? ''),
            'sDocumentId' => $sDocumentId,
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

        // Validate minimal credentials
        if ($key === '' || $secret === '' || $merchantId === '' || $terminalId === '') {
            return [
                'ok' => false,
                'code' => 'MISSING_CREDENTIALS',
                'message' => 'Faltan credenciales del gateway (key/secret/merchant_id/terminal_id).',
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
            $ok = ($respCode === '00');

            Log::info('bank.verify.response', [
                'payment_id' => (int) $payment->getKey(),
                'http_status' => $response->status(),
                'code' => $respCode,
                'message_snippet' => mb_substr($respDesc, 0, 256),
                'raw_response_len' => mb_strlen($rawResponse),
            ]);

            return [
                'ok' => $ok,
                'code' => $respCode,
                'message' => $respDesc,
                'raw_request' => $rawRequest,
                'raw_response' => $rawResponse,
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
