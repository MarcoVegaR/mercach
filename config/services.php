<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // BCV official rates provider (HTML scraping)
    'bcv' => [
        'url' => env('BCV_URL', 'https://www.bcv.org.ve/'),
        'timeout' => env('BCV_TIMEOUT', 15),
        'retry_attempts' => env('BCV_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('BCV_RETRY_DELAY', 60), // seconds
        'verify' => env('BCV_VERIFY_SSL', false),
        'timezone' => env('APP_TIMEZONE', 'America/Caracas'),
    ],

    // 100% Banco - Validación de Transferencia y Pago Móvil (ValTrxIn)
    'bank_gateway' => [
        'scheme' => env('BANK_GATEWAY_SCHEME', 'https'),
        'host' => env('BANK_GATEWAY_HOST', 'www8.100x100banco.com'),
        // Cert path as per email; production may be '/100p2p/api/v1/ValTrxIn'
        'path' => env('BANK_GATEWAY_PATH', '/100p2pCert/api/v1/ValTrxIn'),
        'key' => env('BANK_GATEWAY_KEY', ''),
        // Encoding for x-api-key header (plain | base64)
        'key_encoding' => env('BANK_GATEWAY_KEY_ENCODING', 'plain'),
        'secret' => env('BANK_GATEWAY_SECRET', ''),
        'merchant_id' => env('BANK_GATEWAY_MERCHANT_ID', ''), // e.g., 341433
        'terminal_id' => env('BANK_GATEWAY_TERMINAL_ID', ''), // e.g., userc2p
        'timeout' => env('BANK_GATEWAY_TIMEOUT', 30),
        'verify' => env('BANK_GATEWAY_VERIFY_SSL', true),
        // Some specs concatenate without separators; make configurable
        'signature_newlines' => env('BANK_GATEWAY_SIGNATURE_NEWLINES', false),
        // Signing mode (A = base64(hex(sha256(body))) and base64(hex(hmac)))
        'signature_mode' => env('BANK_GATEWAY_SIGNATURE_MODE', 'A'),
        // Secret encoding (plain | base64)
        'secret_encoding' => env('BANK_GATEWAY_SECRET_ENCODING', 'plain'),
        // After decoding secret, optionally hex-decode to bytes (none | hex)
        'secret_post_decode' => env('BANK_GATEWAY_SECRET_POST_DECODE', 'none'),
        // Whether to append charset to Content-Type
        'content_type_charset' => env('BANK_GATEWAY_CONTENT_TYPE_CHARSET', false),
        // Whether to strip the leading slash from path when signing
        'signature_strip_leading_slash' => env('BANK_GATEWAY_SIGNATURE_STRIP_LEADING_SLASH', false),
    ],

];
