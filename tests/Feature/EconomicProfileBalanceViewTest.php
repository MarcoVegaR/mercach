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

it('renders the statement pdf with executive sections', function () {
    $html = view('pdf.economic_profile_statement', [
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
        'included_local_codes' => ['BM-17'],
        'at' => '2026-06-30',
        'summary_bs' => [
            'gross_debt_bs_minor' => 360000,
            'gross_debt_overdue_bs_minor' => 360000,
            'credits_open_bs_minor' => 0,
            'eligible_payments_available_bs_minor' => 0,
            'payments_reconciliation_gap_bs_minor' => 0,
            'final_due_bs_minor' => 360000,
        ],
        'summary_fx' => [],
        'by_local' => [],
        'charges' => [[
            'local_id' => 17,
            'local_code' => 'BM-17',
            'local_type_name' => 'Local',
            'charge_id' => 1001,
            'kind' => 'RENT_EUR_M2',
            'trade_category_name' => 'Hortalizas',
            'currency' => 'EUR',
            'period' => '2026-06-01',
            'due_on' => '2026-06-10',
            'outstanding_minor' => 10000,
            'outstanding_bs_minor' => 360000,
        ]],
        'letterhead_base64' => null,
        'letterhead_mime' => null,
        'logo_base64' => null,
        'logo_mime' => null,
        'reconciliation' => [],
    ])->render();

    expect($html)
        ->toContain('Estado financiero del titular')
        ->toContain('Reconciliación de la deuda final')
        ->toContain('Resumen por concepto')
        ->toContain('Equivalente Bs')
        ->toContain('moneda origen se muestran para trazabilidad')
        ->toContain('Resumen por local')
        ->toContain('Detalle de cargos pendientes')
        ->toContain('Rubro')
        ->toContain('Hortalizas')
        ->toContain('Deuda final')
        ->toContain('Tasa de uso')
        ->toContain('Vencido')
        ->toContain('badge-rent');
});

it('renders the payment history pdf with executive sections', function () {
    $html = view('pdf.economic_profile_payment_history', [
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
        'included_local_codes' => ['BM-17'],
        'at' => '2026-06-30',
        'totals' => [
            'amount_bs_minor' => 150000,
            'amount_active_bs_minor' => 150000,
            'applied_bs_minor' => 100000,
            'available_bs_minor' => 50000,
            'eligible_available_bs_minor' => 50000,
            'converted_to_credit_bs_minor' => 0,
            'voided_count' => 0,
            'voided_bs_minor' => 0,
            'count' => 1,
        ],
        'payments' => [[
            'payment_id' => 1001,
            'paid_on' => '2026-06-15',
            'lifecycle_state' => 'partially_applied',
            'status' => 'CONC',
            'method' => 'TRF',
            'reference' => 'REF-1001',
            'local_summary_label' => 'BM-17',
            'cross_summary' => 'Aplicado a tasa de uso',
            'crossed_charge_count' => 1,
            'amount_bs_minor' => 150000,
            'crossed_bs_minor' => 100000,
            'eligible_available_bs_minor' => 50000,
            'converted_to_credit_bs_minor' => 0,
            'is_voided' => false,
        ]],
        'reconciliation' => [
            'summary_bs' => [
                'final_due_bs_minor' => 250000,
            ],
        ],
        'letterhead_base64' => null,
        'letterhead_mime' => null,
        'logo_base64' => null,
        'logo_mime' => null,
    ])->render();

    expect($html)
        ->toContain('Historial de pagos del titular')
        ->toContain('Ciclo de vida y reconciliación')
        ->toContain('Detalle de pagos')
        ->toContain('Disponible aplicable')
        ->toContain('Aplicado a tasa de uso');
});
