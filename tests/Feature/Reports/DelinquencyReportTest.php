<?php

declare(strict_types=1);

use App\Models\Charge;
use App\Models\CustomerCredit;
use App\Models\Payment;
use App\Models\User;
use App\Services\DelinquencyReportPdfGenerator;
use App\Services\Reports\DelinquencyReportQuery;
use Carbon\CarbonImmutable;
use Database\Seeders\ChargeStatusesSeeder;
use Database\Seeders\ContractStatusesSeeder;
use Database\Seeders\PaymentStatusesSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function delinquencyUser(array $permissions = []): User
{
    test()->seed([
        PermissionsSeeder::class,
        ChargeStatusesSeeder::class,
        PaymentStatusesSeeder::class,
    ]);

    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function delinquencyCatalog(): array
{
    test()->seed([
        ChargeStatusesSeeder::class,
        ContractStatusesSeeder::class,
        PaymentStatusesSeeder::class,
    ]);

    $marketId = (int) DB::table('markets')->insertGetId([
        'code' => 'MRD',
        'name' => 'Mercado Reporte Deuda',
        'address' => 'Zona de pruebas',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $localTypeId = (int) DB::table('local_types')->insertGetId([
        'code' => 'KIO',
        'name' => 'Kiosco',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $localStatusId = (int) DB::table('local_statuses')->insertGetId([
        'code' => 'OCC',
        'name' => 'Ocupado',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $localLocationId = (int) DB::table('local_locations')->insertGetId([
        'code' => 'PB',
        'name' => 'Planta Baja',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $documentTypeId = (int) DB::table('document_types')->insertGetId([
        'code' => 'J',
        'name' => 'RIF',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $concessionaireTypeId = (int) DB::table('concessionaire_types')->insertGetId([
        'code' => 'JUR',
        'name' => 'Jurídico',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contractTypeId = (int) DB::table('contract_types')->insertGetId([
        'code' => 'CONC',
        'name' => 'Concesión',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contractModalityId = (int) DB::table('contract_modalities')->insertGetId([
        'code' => 'M2',
        'name' => 'Metro cuadrado',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tradeCategoryId = (int) DB::table('trade_categories')->insertGetId([
        'code' => 'FOOD',
        'name' => 'Alimentos',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contractStatusId = (int) DB::table('contract_statuses')->where('code', 'VIG')->value('id');
    $issuedStatusId = (int) DB::table('charge_statuses')->where('code', 'ISSUED')->value('id');
    $partialStatusId = (int) DB::table('charge_statuses')->where('code', 'PARTIAL')->value('id');
    $confirmedStatusId = (int) DB::table('payment_statuses')->where('code', 'CONF')->value('id');

    $bankId = (int) DB::table('banks')->insertGetId([
        'code' => 'BDR',
        'bank_code' => '199',
        'name' => 'Banco Deuda Reporte',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $companyBankAccountId = (int) DB::table('company_bank_accounts')->insertGetId([
        'bank_id' => $bankId,
        'account_number' => '01990011223344556677',
        'phone_number' => '584241998877',
        'account_holder_name' => 'Cuenta Reporte',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact(
        'marketId',
        'localTypeId',
        'localStatusId',
        'localLocationId',
        'documentTypeId',
        'concessionaireTypeId',
        'contractTypeId',
        'contractModalityId',
        'tradeCategoryId',
        'contractStatusId',
        'issuedStatusId',
        'partialStatusId',
        'confirmedStatusId',
        'bankId',
        'companyBankAccountId',
    );
}

function createDelinquencyConcessionaire(array $catalog, string $name, string $document): int
{
    return (int) DB::table('concessionaires')->insertGetId([
        'concessionaire_type_id' => $catalog['concessionaireTypeId'],
        'full_name' => $name,
        'document_type_id' => $catalog['documentTypeId'],
        'document_number' => $document,
        'fiscal_address' => 'Dirección fiscal',
        'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createDelinquencyLocal(array $catalog, string $code, string $name): int
{
    return (int) DB::table('locals')->insertGetId([
        'code' => $code,
        'name' => $name,
        'market_id' => $catalog['marketId'],
        'local_type_id' => $catalog['localTypeId'],
        'local_status_id' => $catalog['localStatusId'],
        'local_location_id' => $catalog['localLocationId'],
        'area_m2' => 10,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function attachDelinquencyContract(array $catalog, int $concessionaireId, int $localId, string $number): int
{
    $contractId = (int) DB::table('contracts')->insertGetId([
        'number' => $number,
        'contract_type_id' => $catalog['contractTypeId'],
        'contract_status_id' => $catalog['contractStatusId'],
        'contract_modality_id' => $catalog['contractModalityId'],
        'trade_category_id' => $catalog['tradeCategoryId'],
        'start_date' => '2026-01-01',
        'end_date' => null,
        'billing_day' => 5,
        'monthly_price_eur' => 100,
        'is_active' => true,
        'has_active_procedure' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('contract_local')->insert([
        'contract_id' => $contractId,
        'local_id' => $localId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('concessionaire_contract')->insert([
        'contract_id' => $contractId,
        'concessionaire_id' => $concessionaireId,
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $contractId;
}

function createDelinquencyCharge(array $catalog, array $overrides = []): Charge
{
    $localId = (int) ($overrides['local_id'] ?? $overrides['debtor_id'] ?? 1);

    return Charge::create(array_merge([
        'market_id' => $catalog['marketId'],
        'local_id' => $localId,
        'contract_id' => null,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $localId,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $localId,
        'kind' => 'RENT_EUR_FIXED',
        'currency' => 'VES',
        'amount_minor' => 10000,
        'amount_bs_minor_issued' => 10000,
        'period' => '2026-05-01',
        'issued_on' => '2026-05-01',
        'due_on' => '2026-05-10',
        'charge_status_id' => $catalog['issuedStatusId'],
        'source' => 'MANUAL',
    ], $overrides));
}

function createDelinquencyPayment(array $catalog, string $debtorType, int $debtorId, int $amount): Payment
{
    return Payment::create([
        'debtor_type' => $debtorType,
        'debtor_id' => $debtorId,
        'company_bank_account_id' => $catalog['companyBankAccountId'],
        'method' => 'TRF',
        'origin_bank_id' => $catalog['bankId'],
        'payer_document_type' => 'J',
        'payer_document_number' => fake()->numerify('#########'),
        'payer_account_number' => '01020000000000000000',
        'reference' => fake()->numerify('######'),
        'amount_bs_minor' => $amount,
        'paid_on' => '2026-06-15',
        'status' => 'CONFIRMED',
    ]);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-24 10:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('requires the report view permission', function () {
    $user = delinquencyUser();

    $this->actingAs($user)
        ->get(route('reports.delinquency'))
        ->assertForbidden();
});

it('renders overdue concessionaires ordered by age before amount', function () {
    config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

    $user = delinquencyUser(['reports.delinquency.view']);
    $catalog = delinquencyCatalog();

    $oldConcessionaireId = createDelinquencyConcessionaire($catalog, 'Antigua Deuda C.A.', 'J100000001');
    $largeConcessionaireId = createDelinquencyConcessionaire($catalog, 'Monto Mayor C.A.', 'J100000002');
    $oldLocalId = createDelinquencyLocal($catalog, 'A-01', 'Local Antiguo');
    $largeLocalId = createDelinquencyLocal($catalog, 'B-01', 'Local Monto');
    $oldContractId = attachDelinquencyContract($catalog, $oldConcessionaireId, $oldLocalId, 'RPT-001');
    $largeContractId = attachDelinquencyContract($catalog, $largeConcessionaireId, $largeLocalId, 'RPT-002');

    createDelinquencyCharge($catalog, [
        'local_id' => $oldLocalId,
        'contract_id' => $oldContractId,
        'debtor_id' => $oldLocalId,
        'origin_debtor_id' => $oldLocalId,
        'amount_minor' => 10000,
        'amount_bs_minor_issued' => 10000,
        'due_on' => '2026-02-01',
    ]);
    createDelinquencyCharge($catalog, [
        'local_id' => $largeLocalId,
        'contract_id' => $largeContractId,
        'debtor_id' => $largeLocalId,
        'origin_debtor_id' => $largeLocalId,
        'amount_minor' => 90000,
        'amount_bs_minor_issued' => 90000,
        'due_on' => '2026-06-01',
    ]);

    $response = $this->actingAs($user)->get(route('reports.delinquency', [
        'scope' => 'concessionaire',
        'debt_type' => 'overdue',
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('reports/delinquency')
        ->where('filters.scope', 'concessionaire')
        ->where('filters.debt_type', 'overdue')
        ->where('meta.total', 2)
        ->where('rows.0.debtor_name', 'Antigua Deuda C.A.')
        ->where('rows.0.final_due_bs_minor', 10000)
        ->where('rows.1.debtor_name', 'Monto Mayor C.A.')
        ->where('rows.1.final_due_bs_minor', 90000)
    );
});

it('labels unassigned recovered debt as recovered by the market', function () {
    $catalog = delinquencyCatalog();
    $localId = createDelinquencyLocal($catalog, 'REC-01', 'Local Recuperado');

    createDelinquencyCharge($catalog, [
        'local_id' => $localId,
        'contract_id' => null,
        'debtor_id' => $localId,
        'origin_debtor_id' => $localId,
        'amount_minor' => 10000,
        'amount_bs_minor_issued' => 10000,
        'due_on' => '2026-05-01',
    ]);

    $row = (new DelinquencyReportQuery)
        ->withFilters(['scope' => 'concessionaire', 'debt_type' => 'overdue'])
        ->rows()
        ->first();

    expect($row['debtor_id'])->toBe(0)
        ->and($row['debtor_name'])->toBe('Recuperados por el Mercado')
        ->and($row['local_codes'])->toContain('REC-01');
});

it('keeps concessionaire payments out of local scope', function () {
    $catalog = delinquencyCatalog();
    $concessionaireId = createDelinquencyConcessionaire($catalog, 'Cesionario con Pago C.A.', 'J100000003');
    $localId = createDelinquencyLocal($catalog, 'C-01', 'Local sin prorrateo');
    $contractId = attachDelinquencyContract($catalog, $concessionaireId, $localId, 'RPT-003');

    createDelinquencyCharge($catalog, [
        'local_id' => $localId,
        'contract_id' => $contractId,
        'debtor_id' => $localId,
        'origin_debtor_id' => $localId,
        'amount_minor' => 10000,
        'amount_bs_minor_issued' => 10000,
        'due_on' => '2026-05-01',
    ]);
    createDelinquencyPayment($catalog, 'CONCESSIONAIRE', $concessionaireId, 10000);

    $query = (new DelinquencyReportQuery)
        ->withFilters(['scope' => 'local', 'debt_type' => 'overdue']);

    $rows = $query->rows();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['debtor_id'])->toBe($localId)
        ->and($rows->first()['payments_available_bs_minor'])->toBe(0)
        ->and($rows->first()['final_due_bs_minor'])->toBe(10000);
});

it('subtracts local confirmed unapplied payments and open credits in local scope', function () {
    $catalog = delinquencyCatalog();
    $concessionaireId = createDelinquencyConcessionaire($catalog, 'Cesionario Local C.A.', 'J100000004');
    $localId = createDelinquencyLocal($catalog, 'D-01', 'Local con saldos');
    $contractId = attachDelinquencyContract($catalog, $concessionaireId, $localId, 'RPT-004');

    createDelinquencyCharge($catalog, [
        'local_id' => $localId,
        'contract_id' => $contractId,
        'debtor_id' => $localId,
        'origin_debtor_id' => $localId,
        'amount_minor' => 20000,
        'amount_bs_minor_issued' => 20000,
        'due_on' => '2026-05-01',
    ]);
    createDelinquencyPayment($catalog, 'LOCAL', $localId, 3000);
    CustomerCredit::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $localId,
        'currency' => 'VES',
        'balance_minor' => 2000,
        'status' => 'OPEN',
        'created_from' => 'TEST',
    ]);

    $row = (new DelinquencyReportQuery)
        ->withFilters(['scope' => 'local', 'debt_type' => 'overdue'])
        ->rows()
        ->first();

    expect($row['gross_selected_bs_minor'])->toBe(20000)
        ->and($row['payments_available_bs_minor'])->toBe(3000)
        ->and($row['credits_open_bs_minor'])->toBe(2000)
        ->and($row['final_due_bs_minor'])->toBe(15000);
});

it('exports the delinquency report as pdf', function () {
    $user = delinquencyUser(['reports.delinquency.export']);
    $catalog = delinquencyCatalog();
    $concessionaireId = createDelinquencyConcessionaire($catalog, 'PDF Deuda C.A.', 'J100000005');
    $localId = createDelinquencyLocal($catalog, 'E-01', 'Local PDF');
    $contractId = attachDelinquencyContract($catalog, $concessionaireId, $localId, 'RPT-005');

    createDelinquencyCharge($catalog, [
        'local_id' => $localId,
        'contract_id' => $contractId,
        'debtor_id' => $localId,
        'origin_debtor_id' => $localId,
        'amount_minor' => 10000,
        'amount_bs_minor_issued' => 10000,
        'due_on' => '2026-05-01',
    ]);

    $this->mock(DelinquencyReportPdfGenerator::class, function ($mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->withArgs(function (array $data): bool {
                expect(data_get($data, 'filters.scope'))->toBe('concessionaire');
                expect(data_get($data, 'totals.debtors_count'))->toBe(1);
                expect(data_get($data, 'row_limit'))->toBe(25);

                return true;
            })
            ->andReturn([
                'raw' => "%PDF-1.4\n%",
                'filename' => 'reporte_morosidad.pdf',
            ]);
    });

    $response = $this->actingAs($user)->get(route('reports.delinquency.export', [
        'scope' => 'concessionaire',
        'debt_type' => 'overdue',
        'limit' => 25,
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

it('caps pdf data to one hundred rows', function () {
    $data = (new DelinquencyReportQuery)
        ->withFilters(['scope' => 'concessionaire', 'debt_type' => 'overdue'])
        ->dataForPdf(250);

    expect($data['row_limit'])->toBe(100);
});

it('renders the delinquency pdf without charge detail sections', function () {
    $html = view('pdf.delinquency_report', [
        'data' => [
            'generated_at' => '2026-06-24 10:00:00',
            'row_limit' => 1000,
            'rows_truncated' => false,
        ],
        'filters' => [
            'scope' => 'concessionaire',
            'debt_type' => 'overdue',
            'cutoff_date' => '2026-06-24',
            'cutoff_at' => '2026-06-24 10:00:00',
        ],
        'rows' => [[
            'debtor_name' => 'Cesionario PDF',
            'debtor_document' => 'J100000006',
            'market_names' => 'Mercado PDF',
            'local_codes' => 'F-01',
            'locals_count' => 1,
            'selected_charge_count' => 2,
            'max_days_overdue' => 45,
            'oldest_due_on' => '2026-05-10',
            'gross_selected_bs_minor' => 30000,
            'final_due_bs_minor' => 25000,
        ]],
        'totals' => [
            'debtors_count' => 1,
            'charges_count' => 2,
            'gross_selected_bs_minor' => 30000,
            'final_due_bs_minor' => 25000,
            'credits_open_bs_minor' => 2000,
            'payments_available_bs_minor' => 3000,
            'max_days_overdue' => 45,
        ],
        'letterhead_base64' => null,
        'letterhead_mime' => null,
        'logo_base64' => null,
        'logo_mime' => null,
    ])->render();

    expect($html)
        ->toContain('Reporte de morosidad')
        ->toContain('Ranking de deudores')
        ->toContain('No se muestran detalles de cargos')
        ->not->toContain('Detalle de cargos');
});
