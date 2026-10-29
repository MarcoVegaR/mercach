<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\CompanyBankAccount;
use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\FxRate;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\Reports\RevenueByConceptQuery;
use App\Services\RevenueByConceptPdfGenerator;
use Database\Seeders\ChargeStatusesSeeder;
use Database\Seeders\PaymentStatusesSeeder;
use Database\Seeders\PaymentTypesSeeder;
use Database\Seeders\PermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([PermissionsSeeder::class, ChargeStatusesSeeder::class, PaymentStatusesSeeder::class, PaymentTypesSeeder::class]);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('dashboard.view.finance');
    $this->actingAs($this->user);
});

function revenuePayment(array $overrides = []): Payment
{
    $bank = Bank::firstOrCreate(['code' => 'REVENUE'], ['bank_code' => '155', 'name' => 'Banco Reporte', 'is_active' => true]);
    $account = CompanyBankAccount::firstOrCreate(['account_number' => '01550011223344556677'], [
        'bank_id' => $bank->id, 'account_holder_name' => 'Cuenta Reporte',
        'document_type' => 'J', 'document_number' => '123456789012', 'is_active' => true,
    ]);

    return Payment::create(array_merge([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => 1,
        'company_bank_account_id' => $account->id, 'method' => 'TRF',
        'origin_bank_id' => $bank->id, 'payer_document_type' => 'V', 'payer_document_number' => '12345678',
        'reference' => fake()->unique()->numerify('########'), 'amount_bs_minor' => 1000000,
        'paid_on' => '2026-10-01', 'status' => 'APPLIED',
        'fx_rate_id' => revenueRate()->id,
    ], $overrides));
}

function revenueRate(string $date = '2026-10-01', string $rate = '100', string $currency = 'USD'): FxRate
{
    return FxRate::firstOrCreate(['currency_code' => $currency, 'value_date' => $date], [
        'rate_date' => $date, 'rate_to_ves' => $rate, 'operational_from' => $date.' 00:00:00',
        'source' => 'test', 'is_official' => true, 'is_active' => true,
    ]);
}

function revenueAllocation(Payment $payment, string $kind = 'CONDO_USD', int $amount = 200000): PaymentAllocation
{
    $market = Market::firstOrCreate(['code' => 'REV'], ['name' => 'Mercado Reporte', 'address' => 'Pruebas', 'is_active' => true]);
    $charge = Charge::create([
        'market_id' => $market->id,
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $payment->id,
        'origin_debtor_type' => 'CONCESSIONAIRE', 'origin_debtor_id' => $payment->id,
        'kind' => $kind, 'currency' => 'USD', 'amount_minor' => 1000000,
        'period' => '2025-01-01', 'issued_on' => '2025-01-01', 'due_on' => '2025-01-31',
        'charge_status_id' => ChargeStatus::query()->where('code', 'ISSUED')->value('id'), 'source' => 'MANUAL',
    ]);

    return PaymentAllocation::create([
        'payment_id' => $payment->id, 'charge_id' => $charge->id,
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $payment->debtor_id,
        'amount_bs_minor' => $amount,
    ]);
}

function revenueFilters(array $concepts = []): array
{
    return ['from' => '2026-10-01', 'to' => '2026-10-14', 'concepts' => $concepts];
}

it('requires the existing finance permission for the page and PDF', function (string $name) {
    $this->user->revokePermissionTo('dashboard.view.finance');
    $this->get(route($name))->assertForbidden();
})->with(['reports.revenue-by-concept', 'reports.revenue-by-concept.export']);

it('requires authentication for the report', function () {
    auth()->logout();
    $this->get(route('reports.revenue-by-concept'))->assertRedirect(route('login'));
});

it('reports only the selected part of a mixed payment using its historical rate', function () {
    $payment = revenuePayment();
    revenueAllocation($payment);
    revenueAllocation($payment, 'RENT_EUR_M2', 800000);
    revenueRate('2026-10-29', '900');

    $this->get(route('reports.revenue-by-concept', revenueFilters(['CONDO_USD'])))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('reports/revenue-by-concept')
        ->has('conceptOptions', 6)
        ->has('datePresets', 4)
        ->where('filters.concepts', ['CONDO_USD'])
        ->missing('rows')
        ->missing('summary')
        ->missing('totals')
        ->missing('reconciliation')
        );

    $data = (new RevenueByConceptQuery(revenueFilters(['CONDO_USD'])))->data();
    expect($data['rows'])->toHaveCount(1);
    expect($data['rows'][0])->toMatchArray(['amount_bs_minor' => 200000, 'amount_usd_minor' => 2000, 'rate' => 100]);
    expect($data['totals'])->toMatchArray(['amount_bs_minor' => 200000, 'amount_usd_minor' => 2000, 'payment_count' => 1]);
    expect($data['reconciliation'])->toBeNull();
});

it('sums historical USD values across dates and includes both date boundaries not charge dates', function () {
    $first = revenuePayment();
    revenueAllocation($first, amount: 100000);
    $last = revenuePayment(['paid_on' => '2026-10-14', 'fx_rate_id' => revenueRate('2026-10-14', '200')->id]);
    revenueAllocation($last, amount: 100000);
    revenueAllocation(revenuePayment(['paid_on' => '2026-09-30']));
    revenueAllocation(revenuePayment(['paid_on' => '2026-10-15']));

    $data = (new RevenueByConceptQuery(revenueFilters()))->data();
    expect($data['totals'])->toMatchArray(['payment_count' => 2, 'amount_bs_minor' => 200000, 'amount_usd_minor' => 1500]);
    expect($data['rows'])->toHaveCount(2);
});

it('supports several concepts and counts a mixed payment once in totals', function () {
    $payment = revenuePayment();
    revenueAllocation($payment);
    revenueAllocation($payment, 'RENT_EUR_M2', 700000);
    revenueAllocation($payment, 'FINE', 100000);
    $data = (new RevenueByConceptQuery(revenueFilters(['CONDO_USD', 'RENT_EUR_M2'])))->data();

    expect($data['summary'])->toHaveCount(2);
    expect($data['totals'])->toMatchArray(['payment_count' => 1, 'amount_bs_minor' => 900000, 'amount_usd_minor' => 9000]);
});

it('reconciles all concepts with received money without assigning unapplied funds', function () {
    $payment = revenuePayment();
    revenueAllocation($payment);
    revenueAllocation($payment, 'RENT_EUR_M2', 700000);
    revenuePayment(['amount_bs_minor' => 50000, 'status' => 'CONFIRMED']);

    $data = (new RevenueByConceptQuery(revenueFilters()))->data();
    expect($data['reconciliation'])->toBe([
        'payment_count' => 2, 'received_bs_minor' => 1050000,
        'applied_bs_minor' => 900000, 'unattributed_bs_minor' => 150000,
    ]);
});

it('excludes exonerations voided payments and deleted applications or charges', function (string $excluded) {
    revenueAllocation(revenuePayment());
    $payment = revenuePayment();
    $allocation = revenueAllocation($payment);
    match ($excluded) {
        'exoneration' => $payment->update(['method' => 'EXO']),
        'void_status' => $payment->update(['status' => 'VOID']),
        'void_date' => $payment->update(['voided_at' => now()]),
        'deleted_payment' => $payment->delete(),
        'deleted_allocation' => $allocation->delete(),
        'deleted_charge' => $allocation->charge->delete(),
    };

    $data = (new RevenueByConceptQuery(revenueFilters()))->data();
    expect($data['totals']['amount_bs_minor'])->toBe(200000);
    expect($data['rows'])->toHaveCount(1);
})->with(['exoneration', 'void_status', 'void_date', 'deleted_payment', 'deleted_allocation', 'deleted_charge']);

it('does not count applications of previous customer credit as new revenue', function () {
    $payment = revenuePayment();
    $allocation = revenueAllocation($payment);
    $credit = CustomerCredit::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => 1,
        'source_payment_id' => $payment->id, 'currency' => 'VES', 'balance_minor' => 10000,
        'status' => 'OPEN', 'created_from' => 'overpayment',
    ]);
    CreditApplication::create([
        'customer_credit_id' => $credit->id, 'payment_id' => $payment->id,
        'charge_id' => $allocation->charge_id, 'amount_minor' => 10000,
    ]);
    $data = (new RevenueByConceptQuery(revenueFilters()))->data();
    expect($data['totals']['amount_bs_minor'])->toBe(200000);
});

it('groups multiple charges before rounding USD per payment and concept', function () {
    $payment = revenuePayment(['fx_rate_id' => revenueRate('2026-10-02', '3')->id, 'paid_on' => '2026-10-02']);
    revenueAllocation($payment, amount: 1);
    revenueAllocation($payment, amount: 1);
    $data = (new RevenueByConceptQuery(revenueFilters()))->data();
    expect($data['rows'])->toHaveCount(1);
    expect($data['totals']['amount_bs_minor'])->toBe(2);
    expect($data['totals']['amount_usd_minor'])->toBe(1);
});

it('flags missing invalid foreign or future USD rates without showing partial totals', function (string $case) {
    revenueAllocation(revenuePayment());
    $rateId = match ($case) {
        'missing' => null,
        'zero' => revenueRate('2026-10-02', '0')->id,
        'foreign' => revenueRate('2026-10-02', '100', 'EUR')->id,
        'future' => revenueRate('2026-10-03', '100')->id,
    };
    $payment = revenuePayment(['fx_rate_id' => $rateId, 'paid_on' => '2026-10-02']);
    revenueAllocation($payment);
    $data = (new RevenueByConceptQuery(revenueFilters()))->data();
    expect($data['totals']['amount_bs_minor'])->toBe(400000);
    expect($data['totals']['amount_usd_minor'])->toBeNull();
    expect($data['totals']['missing_rate_count'])->toBe(1);
    expect($data['rows'][1]['amount_usd_minor'])->toBeNull();
})->with(['missing', 'zero', 'foreign', 'future']);

it('returns zero totals for an empty period', function () {
    $data = (new RevenueByConceptQuery(revenueFilters()))->data();
    expect($data['rows'])->toBe([]);
    expect($data['totals'])->toBe(['payment_count' => 0, 'amount_bs_minor' => 0, 'amount_usd_minor' => 0, 'missing_rate_count' => 0]);
});

it('defaults to this month without sending financial results to the screen', function () {
    $this->travelTo(now()->setDate(2026, 10, 29));
    $this->get(route('reports.revenue-by-concept'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.from', '2026-10-01')
        ->where('filters.to', '2026-10-29')
        ->missing('rows')
        ->missing('totals')
        ->missing('summary')
    );
});

it('treats selecting every concept as all concepts when generating the PDF', function () {
    revenueAllocation(revenuePayment());
    $this->mock(RevenueByConceptPdfGenerator::class, function ($mock): void {
        $mock->shouldReceive('render')->once()->withArgs(function (array $data): bool {
            expect($data['filters']['concepts'])->toBe([]);
            expect($data['reconciliation'])->not->toBeNull();

            return true;
        })->andReturn(['raw' => '%PDF-1.4 test', 'filename' => 'recaudacion.pdf']);
    });
    $this->get(route('reports.revenue-by-concept.export', revenueFilters(array_keys(RevenueByConceptQuery::CONCEPTS))))->assertOk();
});

it('validates filters for both screen and PDF', function (array $invalid, string $error, string $routeName) {
    $this->getJson(route($routeName, array_merge(revenueFilters(), $invalid)))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    [['from' => 'bad'], 'from'],
    [['to' => '2026-09-01'], 'to'],
    [['concepts' => ['NOT_A_CONCEPT']], 'concepts.0'],
    [['concepts' => ['FINE', 'FINE']], 'concepts.0'],
    [['concepts' => 'FINE'], 'concepts'],
])->with(['reports.revenue-by-concept', 'reports.revenue-by-concept.export']);

it('exports every matching row without pagination or truncation', function () {
    foreach (range(1, 26) as $index) {
        revenueAllocation(revenuePayment(['reference' => 'EXPORT'.$index]));
    }
    $data = (new RevenueByConceptQuery(revenueFilters()))->data();
    expect($data['rows'])->toHaveCount(26);

    $this->mock(RevenueByConceptPdfGenerator::class, function ($mock): void {
        $mock->shouldReceive('render')->once()->withArgs(function (array $data): bool {
            expect($data['rows'])->toHaveCount(26);
            expect($data['totals']['amount_bs_minor'])->toBe(5200000);
            expect($data['totals']['amount_usd_minor'])->toBe(52000);

            return true;
        })->andReturn(['raw' => '%PDF-1.4 test', 'filename' => 'recaudacion.pdf']);
    });

    $this->get(route('reports.revenue-by-concept.export', revenueFilters()))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('renders the institutional PDF with historical values and reconciliation', function () {
    revenueAllocation(revenuePayment());
    $data = (new RevenueByConceptQuery(revenueFilters()))->data();
    $html = view('pdf.revenue_by_concept', ['data' => $data, 'logo' => ['base64' => null], 'letterhead' => ['base64' => null]])->render();
    expect($html)->toContain('Recaudación por concepto', 'Gastos Comunes', '2.000,00', '20,00', 'Conciliación del período', 'Sin atribuir a conceptos');
    expect($html)->not->toContain('pagos distintos con aplicaciones', 'Bs corresponde al importe aplicado', 'Se excluyen exoneraciones');
    expect($data)->not->toHaveKeys(['conceptOptions', 'datePresets', 'meta']);
    $pdf = app(RevenueByConceptPdfGenerator::class)->render($data);
    expect($pdf['raw'])->toStartWith('%PDF');
});

it('displays rates with two decimals without rounding the rate used to calculate USD', function () {
    $payment = revenuePayment(['fx_rate_id' => revenueRate(rate: '860.173456')->id]);
    revenueAllocation($payment);
    $data = (new RevenueByConceptQuery(revenueFilters(['CONDO_USD'])))->data();
    $html = view('pdf.revenue_by_concept', ['data' => $data, 'logo' => ['base64' => null], 'letterhead' => ['base64' => null]])->render();

    expect($data['rows'][0]['rate'])->toBe(860.173456);
    expect($data['rows'][0]['amount_usd_minor'])->toBe(233);
    expect($data['concepts_label'])->toBe('Gastos Comunes');
    expect($html)->toContain('860,17')->not->toContain('860,173456');
});
