<?php

declare(strict_types=1);

namespace App\Services\Bank;

use Illuminate\Support\Facades\Http;

/**
 * Service for probing bank gateway connectivity.
 *
 * Handles HMAC signature generation and request construction
 * for testing gateway connectivity without DB writes.
 */
class GatewayProbeService
{
    private string $host;

    private string $path;

    private string $scheme;

    private string $key;

    private string $secret;

    private string $merchantId;

    private string $terminalId;

    private int $timeout;

    private bool $verifySsl;

    private bool $concatWithNewlines;

    private string $signatureMode;

    private string $secretEncoding;

    private string $secretPostDecode;

    private bool $withCharset;

    private bool $stripLeadingSlash;

    public function __construct()
    {
        $this->host = (string) config('services.bank_gateway.host');
        $this->path = (string) config('services.bank_gateway.path');
        $this->scheme = (string) config('services.bank_gateway.scheme', 'https');

        $key = (string) config('services.bank_gateway.key');
        $keyEncoding = strtolower((string) config('services.bank_gateway.key_encoding', 'plain'));
        if ($keyEncoding === 'base64') {
            $decodedKey = base64_decode($key, true);
            if ($decodedKey !== false) {
                $key = $decodedKey;
            }
        }
        $this->key = $key;

        $this->secret = (string) config('services.bank_gateway.secret');
        $this->merchantId = (string) config('services.bank_gateway.merchant_id');
        $this->terminalId = (string) config('services.bank_gateway.terminal_id');
        $this->timeout = (int) config('services.bank_gateway.timeout', 30);
        $this->verifySsl = (bool) config('services.bank_gateway.verify', true);
        $this->concatWithNewlines = (bool) config('services.bank_gateway.signature_newlines', false);
        $this->signatureMode = strtoupper((string) config('services.bank_gateway.signature_mode', 'A'));
        $this->secretEncoding = strtolower((string) config('services.bank_gateway.secret_encoding', 'plain'));
        $this->secretPostDecode = strtolower((string) config('services.bank_gateway.secret_post_decode', 'none'));
        $this->withCharset = (bool) config('services.bank_gateway.content_type_charset', false);
        $this->stripLeadingSlash = (bool) config('services.bank_gateway.signature_strip_leading_slash', false);
    }

    /**
     * Execute gateway probe.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function probe(array $params): array
    {
        $trxTypeStr = strtoupper((string) ($params['trx_type'] ?? '300'));
        $bankId = (string) ($params['bank_id'] ?? '156');
        $docId = (string) ($params['document_id'] ?? 'V12345678');
        $amount = round((float) ($params['amount'] ?? 1500.00), 2);
        $dateTrx = (string) ($params['date_trx'] ?? gmdate('Y-m-d'));
        $trxId = (string) ($params['trx_id'] ?? gmdate('Ymd').'00000001');

        $payload = $trxTypeStr === '211'
            ? $this->buildTransferPayload($params, $bankId, $docId, $amount, $dateTrx, $trxId)
            : $this->buildPagomovilPayload($params, $bankId, $docId, $amount, $dateTrx, $trxId);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $dateHeader = gmdate('D, d M Y H:i:s').' GMT';

        $bodyHashB64 = $this->computeBodyHash($body);
        $signature = $this->computeSignature($dateHeader, $bodyHashB64);

        $url = $this->scheme.'://'.rtrim($this->host, '/').(str_starts_with($this->path, '/') ? $this->path : '/'.$this->path);
        $contentType = 'application/json'.($this->withCharset ? '; charset=utf-8' : '');

        $response = Http::withHeaders([
            'x-api-key' => $this->key,
            'Date' => $dateHeader,
            'x-signature' => $signature,
            'Content-Type' => $contentType,
        ])->timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl])
            ->withBody($body, $contentType)
            ->post($url);

        $rawResponse = $response->body();
        $json = [];
        try {
            $json = $response->json() ?? [];
        } catch (\Throwable) {
            $json = [];
        }

        return [
            'http_status' => $response->status(),
            'sRespCode' => $json['sRespCode'] ?? null,
            'sRespDesc' => $json['sRespDesc'] ?? ($json['message'] ?? null),
            'raw_request' => $body,
            'raw_response' => $rawResponse,
            'debug' => [
                'parsed' => $payload,
                'date' => $dateHeader,
                'body_hash_b64' => $bodyHashB64,
                'signature' => $signature,
                'x_signature_len' => strlen($signature),
                'canonical' => $this->buildCanonicalMessage($dateHeader, $bodyHashB64),
                'url' => $url,
                'host' => $this->host,
                'sign_path' => $this->getSignPath(),
                'concat_newlines' => $this->concatWithNewlines,
                'secret_encoding' => $this->secretEncoding,
                'signature_mode' => $this->signatureMode,
                'secret_post_decode' => $this->secretPostDecode,
            ],
        ];
    }

    /**
     * Build transfer (211) payload.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildTransferPayload(array $params, string $bankId, string $docId, float $amount, string $dateTrx, string $trxId): array
    {
        $from = $this->digits((string) ($params['from_acct'] ?? '01560011223344556677'));
        $to = $this->digits((string) ($params['to_acct'] ?? '01560099887766554433'));
        $refRaw = $this->normalizeReference((string) ($params['reference'] ?? '123456'));

        return [
            'sMerchantId' => $this->merchantId,
            'sTrxId' => $trxId,
            'sTrxType' => '211',
            'sBankId' => $bankId,
            'sDocumentId' => $docId,
            'sFromAcctNo' => $from,
            'sToAcctNo' => $to,
            'nAmount' => $amount,
            'sReferenceNo' => $refRaw,
            'sDateTrx' => $dateTrx,
            'sTerminalId' => $this->terminalId,
        ];
    }

    /**
     * Build pago movil (300) payload.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildPagomovilPayload(array $params, string $bankId, string $docId, float $amount, string $dateTrx, string $trxId): array
    {
        $from = $this->normalizePhone((string) ($params['from_phone'] ?? ''));
        $to = $this->normalizePhone((string) ($params['to_phone'] ?? ''));
        $refRaw = $this->normalizeReference((string) ($params['reference'] ?? ''));

        return [
            'sMerchantId' => $this->merchantId,
            'sTrxId' => $trxId,
            'sTrxType' => '300',
            'sBankId' => $bankId,
            'sDocumentId' => $docId,
            'sFromAcctNo' => $from,
            'sToAcctNo' => $to,
            'nAmount' => $amount,
            'sReferenceNo' => $refRaw,
            'sDateTrx' => $dateTrx,
            'sTerminalId' => $this->terminalId,
        ];
    }

    /**
     * Compute body hash (SHA256 + base64).
     */
    private function computeBodyHash(string $body): string
    {
        $shaRaw = hash('sha256', $body, true);
        $shaHex = bin2hex($shaRaw);

        return base64_encode($this->signatureMode === 'A' ? $shaHex : $shaRaw);
    }

    /**
     * Compute HMAC signature.
     */
    private function computeSignature(string $dateHeader, string $bodyHashB64): string
    {
        $message = $this->buildCanonicalMessage($dateHeader, $bodyHashB64);
        $secretKey = $this->resolveSecretKey();

        $hmacRaw = hash_hmac('sha256', $message, $secretKey, true);
        $hmacHex = bin2hex($hmacRaw);

        return base64_encode($this->signatureMode === 'A' ? $hmacHex : $hmacRaw);
    }

    /**
     * Build canonical message for signature.
     */
    private function buildCanonicalMessage(string $dateHeader, string $bodyHashB64): string
    {
        $signPath = $this->getSignPath();

        return $this->concatWithNewlines
            ? ($this->host."\n".$signPath."\n".$dateHeader."\n".$bodyHashB64)
            : ($this->host.$signPath.$dateHeader.$bodyHashB64);
    }

    /**
     * Get signing path (with optional strip leading slash).
     */
    private function getSignPath(): string
    {
        $signPath = $this->path;
        if ($this->stripLeadingSlash) {
            return ltrim($signPath, '/');
        }

        return str_starts_with($signPath, '/') ? $signPath : '/'.$signPath;
    }

    /**
     * Resolve secret key with encoding options.
     */
    private function resolveSecretKey(): string
    {
        $secretKey = $this->secret;

        if ($this->secretEncoding === 'base64') {
            $decoded = base64_decode($this->secret, true);
            if ($decoded !== false) {
                $secretKey = $decoded;
            }
        }

        if ($this->secretPostDecode === 'hex'
            && preg_match('/^[0-9a-f]+$/i', $secretKey) === 1
            && (strlen($secretKey) % 2) === 0
        ) {
            $hexBytes = @hex2bin($secretKey);
            if ($hexBytes !== false) {
                $secretKey = $hexBytes;
            }
        }

        return $secretKey;
    }

    /**
     * Extract digits from string.
     */
    private function digits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    /**
     * Normalize phone number to E.164 format.
     */
    private function normalizePhone(string $raw): string
    {
        $d = ltrim($this->digits($raw), '+');
        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '58')) {
            return $d;
        }
        if (str_starts_with($d, '0') && strlen($d) === 11) {
            return '58'.substr($d, 1);
        }

        return $d;
    }

    /**
     * Normalize reference to 6-8 digits.
     */
    private function normalizeReference(string $ref): string
    {
        $refRaw = $this->digits($ref);
        if (strlen($refRaw) < 6) {
            $refRaw = str_pad($refRaw, 6, '0', STR_PAD_LEFT);
        }
        if (strlen($refRaw) > 8) {
            $refRaw = substr($refRaw, 0, 8);
        }

        return $refRaw;
    }
}
