<?php

declare(strict_types=1);

it('does not duplicate the first payment movement when grouping balance rows', function () {
    $html = view('pdf.economic_profile_balance', [
        'scope' => 'concessionaire',
        'scope_label' => 'Cesionario',
        'scope_id' => 260,
        'header' => [
            'full_name' => 'VICTOR MANUEL SEVILLA MARIN',
            'document' => [
                'type_code' => 'V',
                'number' => '12748824',
            ],
        ],
        'included_local_codes' => ['BM-17', 'BM-19'],
        'at' => '2026-05-20',
        'data' => [
            'summary' => [
                'total_charges_bs' => 5000,
                'payments_applied_bs' => 3000,
                'credits_applied_bs' => 0,
                'final_balance_bs' => 2000,
                'final_due_bs' => 2000,
                'gross_debt_bs' => 2000,
                'credits_open_bs' => 0,
                'eligible_payments_available_bs' => 0,
                'payments_available_bs' => 0,
                'payments_registered_bs' => 3000,
            ],
            'movements' => [
                [
                    'date' => '2026-05-01',
                    'type' => 'Cargo',
                    'reference' => '2026-05',
                    'description' => 'Tasa de uso · BM-17',
                    'currency' => 'EUR',
                    'amount_minor' => 5000,
                    'debit' => 5000,
                    'credit' => 0,
                    'balance' => 5000,
                ],
                [
                    'date' => '2026-05-15',
                    'type' => 'Pago',
                    'reference' => 'MERCACH-2026-000785',
                    'description' => 'Pago aplicado a Tasa de uso',
                    'currency' => 'VES',
                    'amount_minor' => 1000,
                    'debit' => 0,
                    'credit' => 1000,
                    'balance' => 4000,
                ],
                [
                    'date' => '2026-05-15',
                    'type' => 'Pago',
                    'reference' => 'MERCACH-2026-000785',
                    'description' => 'Pago aplicado a Tasa de uso',
                    'currency' => 'VES',
                    'amount_minor' => 2000,
                    'debit' => 0,
                    'credit' => 2000,
                    'balance' => 2000,
                ],
            ],
            'totals_by_currency' => [],
        ],
    ])->render();

    expect($html)
        ->toContain('Pago aplicado a Tasa de uso (2 aplicaciones)')
        ->toContain('30,00')
        ->toContain('20,00')
        ->not->toContain('40,00');
});
