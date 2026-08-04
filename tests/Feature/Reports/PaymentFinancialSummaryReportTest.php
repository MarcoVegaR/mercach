<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentFinancialSummaryPdfGenerator;
use Database\Seeders\PaymentStatusesSeeder;
use Database\Seeders\PaymentTypesSeeder;
use Database\Seeders\PermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function paymentFinancialSummaryUser(array $permissions = []): User
{
    test()->seed([
        PermissionsSeeder::class,
        PaymentStatusesSeeder::class,
        PaymentTypesSeeder::class,
    ]);

    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function paymentFinancialSummaryAccount(string $code = 'BANKREPORT', string $bankCode = '156', string $name = 'Banco Reporte'): array
{
    $bank = Bank::create([
        'code' => $code,
        'bank_code' => $bankCode,
        'name' => $name,
        'is_active' => true,
    ]);

    $account = CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => $bankCode.'00112233445566777',
        'phone_number' => '584241112233',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);

    return [$bank, $account];
}

function createFinancialSummaryPayment(Bank $bank, CompanyBankAccount $account, array $overrides = []): Payment
{
    return Payment::create(array_merge([
        'debtor_type' => 'CONCESSIONAIRE',
        'debtor_id' => 1,
        'company_bank_account_id' => $account->id,
        'method' => 'TRF',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'payer_account_number' => '01020000000000000000',
        'reference' => fake()->numerify('######'),
        'amount_bs_minor' => 10000,
        'paid_on' => '2026-05-10',
        'status' => 'REGISTERED',
    ], $overrides));
}

it('requires the report view permission', function () {
    $user = paymentFinancialSummaryUser();

    $this->actingAs($user)
        ->get(route('reports.payment-financial-summary'))
        ->assertForbidden();
});

it('counts registered income and excludes exonerated, voided and deleted payments', function () {
    config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

    $user = paymentFinancialSummaryUser(['reports.payment_financial_summary.view']);
    [$bank, $account] = paymentFinancialSummaryAccount();

    createFinancialSummaryPayment($bank, $account, [
        'method' => 'TRF',
        'reference' => 'INC001',
        'amount_bs_minor' => 10000,
        'status' => 'REGISTERED',
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'PMOV',
        'reference' => 'INC002',
        'amount_bs_minor' => 20000,
        'status' => 'CONFIRMED',
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'EXO',
        'reference' => '',
        'amount_bs_minor' => 30000,
        'status' => 'APPLIED',
        'exoneration_reason' => 'Materiales entregados',
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'DEB',
        'reference' => 'VOID01',
        'amount_bs_minor' => 40000,
        'status' => 'VOID',
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'DEB',
        'reference' => 'VOID02',
        'amount_bs_minor' => 50000,
        'voided_at' => now(),
    ]);
    $deleted = createFinancialSummaryPayment($bank, $account, [
        'method' => 'TRF',
        'reference' => 'DEL001',
        'amount_bs_minor' => 60000,
    ]);
    $deleted->delete();

    $response = $this->actingAs($user)->get(route('reports.payment-financial-summary', [
        'filters' => [
            'report_type' => 'income',
            'group_by' => 'day',
            'paid_between' => ['from' => '2026-05-01', 'to' => '2026-05-31'],
        ],
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('reports/payment-financial-summary')
        ->where('totals.count', 2)
        ->where('totals.amount_bs_minor', 30000)
        ->where('rows.0.count', 2)
        ->where('rows.0.amount_bs_minor', 30000)
        ->where('rows.0.registered_count', 1)
        ->where('rows.0.confirmed_count', 1)
    );
});

it('filters the financial summary by receiver bank', function () {
    config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

    $user = paymentFinancialSummaryUser(['reports.payment_financial_summary.view']);
    [$firstBank, $firstAccount] = paymentFinancialSummaryAccount('BANKFIRST', '157', 'Banco Receptor Uno');
    [$secondBank, $secondAccount] = paymentFinancialSummaryAccount('BANKSECOND', '158', 'Banco Receptor Dos');

    createFinancialSummaryPayment($firstBank, $firstAccount, [
        'reference' => 'BANK001',
        'amount_bs_minor' => 10000,
    ]);
    createFinancialSummaryPayment($secondBank, $secondAccount, [
        'reference' => 'BANK002',
        'amount_bs_minor' => 25000,
    ]);

    $response = $this->actingAs($user)->get(route('reports.payment-financial-summary', [
        'filters' => [
            'report_type' => 'income',
            'group_by' => 'day',
            'paid_between' => ['from' => '2026-05-01', 'to' => '2026-05-31'],
            'bank_id' => $secondBank->id,
        ],
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('reports/payment-financial-summary')
        ->where('totals.count', 1)
        ->where('totals.amount_bs_minor', 25000)
        ->where('filters.bank_id', $secondBank->id)
        ->where('filters.bank_name', 'Banco Receptor Dos')
        ->where('rows.0.count', 1)
        ->where('rows.0.amount_bs_minor', 25000)
        ->where('filterOptions.banks.0.name', 'Banco Receptor Dos')
    );
});

it('reports exonerations by paid_on and ignores voided exonerations', function () {
    config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

    $user = paymentFinancialSummaryUser(['reports.payment_financial_summary.view']);
    [$bank, $account] = paymentFinancialSummaryAccount();

    createFinancialSummaryPayment($bank, $account, [
        'method' => 'EXO',
        'reference' => '',
        'amount_bs_minor' => 12500,
        'paid_on' => '2026-04-15',
        'status' => 'REGISTERED',
        'exoneration_reason' => 'Compensación autorizada',
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'EXO',
        'reference' => '',
        'amount_bs_minor' => 22500,
        'paid_on' => '2026-04-20',
        'status' => 'APPLIED',
        'exoneration_reason' => 'Material recibido',
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'EXO',
        'reference' => '',
        'amount_bs_minor' => 99999,
        'paid_on' => '2026-04-22',
        'status' => 'VOID',
        'exoneration_reason' => 'Anulada',
    ]);

    $response = $this->actingAs($user)->get(route('reports.payment-financial-summary', [
        'filters' => [
            'report_type' => 'exonerations',
            'group_by' => 'month',
            'paid_between' => ['from' => '2026-04-01', 'to' => '2026-04-30'],
        ],
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('totals.count', 2)
        ->where('totals.amount_bs_minor', 35000)
        ->where('rows.0.period_key', '2026-04')
        ->where('rows.0.count', 2)
    );
});

it('groups income weekly', function () {
    config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

    $user = paymentFinancialSummaryUser(['reports.payment_financial_summary.view']);
    [$bank, $account] = paymentFinancialSummaryAccount();

    createFinancialSummaryPayment($bank, $account, [
        'method' => 'TRF',
        'paid_on' => '2026-05-04',
        'amount_bs_minor' => 10000,
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'PMOV',
        'paid_on' => '2026-05-10',
        'amount_bs_minor' => 15000,
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'DEB',
        'paid_on' => '2026-05-11',
        'amount_bs_minor' => 20000,
    ]);

    $response = $this->actingAs($user)->get(route('reports.payment-financial-summary', [
        'filters' => [
            'report_type' => 'income',
            'group_by' => 'week',
            'paid_between' => ['from' => '2026-05-01', 'to' => '2026-05-31'],
        ],
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('meta.total', 2)
        ->where('rows.0.period_key', '2026-05-04')
        ->where('rows.0.count', 2)
        ->where('rows.0.amount_bs_minor', 25000)
        ->where('rows.1.period_key', '2026-05-11')
        ->where('rows.1.count', 1)
    );
});

it('exports the financial summary as pdf', function () {
    $user = paymentFinancialSummaryUser(['reports.payment_financial_summary.export']);
    [$bank, $account] = paymentFinancialSummaryAccount();
    createFinancialSummaryPayment($bank, $account, ['amount_bs_minor' => 10000]);

    $this->mock(PaymentFinancialSummaryPdfGenerator::class, function ($mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->withArgs(function (array $data): bool {
                expect(data_get($data, 'filters.report_type'))->toBe('income');
                expect(data_get($data, 'totals.count'))->toBe(1);
                expect(data_get($data, 'details.0.receiver_bank_name'))->toBe('Banco Reporte');

                return true;
            })
            ->andReturn([
                'raw' => "%PDF-1.4\n%",
                'filename' => 'reporte_ingresos.pdf',
            ]);
    });

    $response = $this->actingAs($user)->get(route('reports.payment-financial-summary.export', [
        'filters' => [
            'report_type' => 'income',
            'group_by' => 'day',
            'paid_between' => ['from' => '2026-05-01', 'to' => '2026-05-31'],
        ],
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

it('renders the financial summary pdf with executive sections', function () {
    $html = view('pdf.payment_financial_summary', [
        'data' => [
            'generated_at' => '2026-06-23 10:00:00',
            'detail_limit' => 500,
            'details_truncated' => false,
        ],
        'filters' => [
            'report_type' => 'income',
            'group_by' => 'day',
            'paid_from' => '2026-06-01',
            'paid_to' => '2026-06-30',
            'method' => null,
            'bank_id' => 1,
            'bank_name' => 'Banco Reporte',
        ],
        'rows' => [[
            'period_label' => '23/06/2026',
            'count' => 2,
            'amount_bs_minor' => 30000,
            'average_bs_minor' => 15000,
            'registered_count' => 1,
            'confirmed_count' => 1,
            'applied_count' => 0,
        ]],
        'totals' => [
            'count' => 2,
            'amount_bs_minor' => 30000,
            'average_bs_minor' => 15000,
            'status_breakdown' => [[
                'code' => 'REG',
                'name' => 'Registrado',
                'count' => 1,
                'amount_bs_minor' => 10000,
            ]],
            'method_breakdown' => [[
                'code' => 'TRF',
                'name' => 'Transferencia',
                'count' => 2,
                'amount_bs_minor' => 30000,
            ]],
        ],
        'details' => [[
            'id' => 10,
            'paid_on' => '2026-06-23',
            'status_code' => 'REG',
            'status_name' => 'Registrado',
            'method_code' => 'TRF',
            'method_name' => 'Transferencia',
            'debtor_name' => 'Cesionario de Prueba',
            'receiver_bank_name' => 'Banco Reporte',
            'reference' => 'ABC123',
            'amount_bs_minor' => 10000,
        ]],
        'letterhead_base64' => null,
        'letterhead_mime' => null,
        'logo_base64' => null,
        'logo_mime' => null,
    ])->render();

    expect($html)
        ->toContain('Resumen por estado y método')
        ->toContain('Evolución por período')
        ->toContain('Detalle de registros')
        ->toContain('Banco receptor')
        ->toContain('Total ingresos')
        ->toContain('Banco Reporte')
        ->toContain('Transferencia');
});

it('filters the payments index by payment type', function () {
    config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

    $user = paymentFinancialSummaryUser(['catalogs.payment.view']);
    [$bank, $account] = paymentFinancialSummaryAccount();

    createFinancialSummaryPayment($bank, $account, [
        'method' => 'TRF',
        'reference' => 'TRF001',
        'amount_bs_minor' => 10000,
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'PMOV',
        'reference' => 'PMOV001',
        'amount_bs_minor' => 20000,
    ]);

    $response = $this->actingAs($user)->get(route('payments.index', [
        'filters' => ['method' => 'PMOV'],
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('catalogs/payment/index')
        ->has('rows', 1)
        ->where('rows.0.reference', 'PMOV001')
    );
});

it('excludes exonerations from the dashboard payment trend', function () {
    $user = paymentFinancialSummaryUser(['dashboard.view.finance']);
    [$bank, $account] = paymentFinancialSummaryAccount();
    $today = now()->toDateString();

    createFinancialSummaryPayment($bank, $account, [
        'method' => 'TRF',
        'paid_on' => $today,
        'amount_bs_minor' => 10000,
    ]);
    createFinancialSummaryPayment($bank, $account, [
        'method' => 'EXO',
        'paid_on' => $today,
        'reference' => '',
        'amount_bs_minor' => 25000,
        'exoneration_reason' => 'Compensación',
    ]);

    $response = $this->actingAs($user)->getJson('/api/dashboard/payment/trend?group=day&days=2');

    $response->assertOk();
    $item = collect($response->json('items'))->firstWhere('month', $today);

    expect($item)->not->toBeNull();
    expect($item['count'])->toBe(1);
    expect($item['amount_bs_minor'])->toBe(10000);
});
