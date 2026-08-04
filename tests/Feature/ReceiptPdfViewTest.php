<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;

it('renders the trade category in the payment receipt pdf detail', function () {
    $html = view('pdf.receipt', [
        'receipt' => (object) [
            'receipt_number' => 'REC-2026-000001',
            'issued_at' => Carbon::parse('2026-07-28 10:00:00'),
        ],
        'payment' => (object) [
            'method' => 'TRF',
            'reference' => 'REF-001',
            'paid_on' => '2026-07-28',
        ],
        'company_label' => 'Banco Receptor',
        'debtor_label' => 'Titular de prueba',
        'origin_bank_name' => 'Banco Origen',
        'items' => [[
            'charge_id' => 10,
            'period' => '2026-07-01',
            'concept' => 'Tasa de Uso • A-01',
            'trade_category_name' => 'Hortalizas',
            'kind' => 'RENT_EUR_M2',
            'currency' => 'EUR',
            'charge_amount_minor' => 10000,
            'balance_currency_minor' => 0,
        ]],
        'totals' => [
            'bs_minor' => 360000,
            'by_ccy_minor' => ['EUR' => 10000],
        ],
        'rates' => ['EUR' => 36],
        'rates_meta' => ['EUR' => ['source' => 'BCV']],
        'verify_url' => 'https://example.test/receipts/verify',
        'market_name' => 'Mercado Test',
        'market_address' => 'Direccion Test',
        'display_receipt_no' => '000001-07-2026',
    ])->render();

    expect($html)
        ->toContain('Rubro')
        ->toContain('Hortalizas');
});

it('renders the trade category in charge-scoped receipt pdf detail', function (string $view) {
    $payload = [
        'receipt' => (object) [
            'receipt_number' => 'REC-2026-000002',
            'issued_at' => Carbon::parse('2026-07-28 10:00:00'),
        ],
        'payment' => (object) [
            'method' => 'TRF',
            'reference' => 'REF-002',
            'paid_on' => '2026-07-28',
        ],
        'company_label' => 'Banco Receptor',
        'debtor_label' => 'Titular de prueba',
        'verify_url' => 'https://example.test/receipts/verify',
        'market_name' => 'Mercado Test',
        'market_address' => 'Direccion Test',
        'display_receipt_no' => '000002-07-2026',
        'charge' => [
            'id' => 11,
            'currency' => 'EUR',
            'amount_minor' => 10000,
            'bs_equiv_minor' => 360000,
            'period' => '2026-07-01',
            'kind' => 'RENT_EUR_M2',
            'trade_category_name' => 'Frutas',
        ],
        'applied' => [
            'bs_minor' => 360000,
            'currency_minor' => 10000,
        ],
        'balance' => [
            'bs_minor' => 0,
            'currency_minor' => 0,
        ],
        'rates' => ['EUR' => 36],
        'rates_meta' => ['EUR' => ['source' => 'BCV']],
        'local_label' => 'A-01 • Local A-01',
        'local_name' => 'Local A-01',
        'amount_letters_bs' => 'TRES MIL SEISCIENTOS BOLIVARES',
        'amount_letters_ccy' => 'CIEN EUROS',
        'receipt_type' => 'TASA POR USO DE BIEN PUBLICO',
        'receipt_heading' => 'Tasa por uso de bien publico',
        'gc' => [
            'items' => [],
            'totals' => ['usd_minor' => 0, 'bs_minor' => 0],
            'coef' => null,
            'area_local' => 0,
            'area_total' => 0,
        ],
    ];

    $html = view($view, $payload)->render();

    expect($html)
        ->toContain('Rubro')
        ->toContain('Frutas');
})->with([
    'use fee receipt' => 'pdf.receipt_use_fee',
    'common expenses receipt' => 'pdf.receipt_common_expenses',
]);
