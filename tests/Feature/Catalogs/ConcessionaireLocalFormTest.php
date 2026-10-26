<?php

declare(strict_types=1);

use App\Enums\ChargeStatusCode;
use App\Models\Bank;
use App\Models\Charge;
use App\Models\CompanyBankAccount;
use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\DocumentType;
use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\TradeCategory;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-26 10:00:00');
    $this->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\ContractStatusesSeeder::class,
        Database\Seeders\ContractTypesSeeder::class,
        Database\Seeders\ContractModalitiesSeeder::class,
        Database\Seeders\TradeCategoriesSeeder::class,
        Database\Seeders\ConcessionaireTypesSeeder::class,
        Database\Seeders\DocumentTypesSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
    ]);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('catalogs.concessionaire.view');
    $this->concessionaire = Concessionaire::query()->create([
        'concessionaire_type_id' => ConcessionaireType::query()->value('id'),
        'full_name' => 'María Cesionaria',
        'document_type_id' => DocumentType::query()->where('code', 'V')->value('id'),
        'document_number' => '12345678',
        'fiscal_address' => 'Chacao',
        'email' => 'maria@example.test',
        'is_active' => true,
    ]);
    $this->local = Local::query()->create([
        'code' => 'TEST-01', 'name' => 'Local de prueba',
        'market_id' => Market::query()->value('id'),
        'local_type_id' => LocalType::query()->value('id'),
        'local_status_id' => LocalStatus::query()->value('id'),
        'local_location_id' => LocalLocation::query()->value('id'),
        'area_m2' => 7.30, 'is_active' => true,
    ]);
    $this->contract = Contract::factory()->create([
        'contract_type_id' => ContractType::query()->value('id'),
        'contract_status_id' => ContractStatus::query()->where('code', 'VIG')->value('id'),
        'contract_modality_id' => ContractModality::query()->value('id'),
        'trade_category_id' => TradeCategory::query()->value('id'),
        'start_date' => '2024-01-01',
    ]);
    $this->contract->locals()->attach($this->local);
    $this->contract->concessionaires()->attach($this->concessionaire, ['is_primary' => true]);
    $this->formUrl = fn (string $type): string => route('catalogs.concessionaire.local-form', [
        'concessionaire' => $this->concessionaire,
        'local_code' => $this->local->code,
        'form_type' => $type,
    ]);
    $this->optionsUrl = route('catalogs.concessionaire.local-form-options', $this->concessionaire);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function localFormCharge(array $attributes = []): Charge
{
    $local = test()->local;

    return Charge::query()->create(array_merge([
        'market_id' => $local->market_id, 'local_id' => $local->id,
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL', 'origin_debtor_id' => $local->id,
        'kind' => 'CONDO_USD', 'currency' => 'USD', 'amount_minor' => 10000,
        'period' => '2026-09-01', 'issued_on' => '2026-09-01', 'due_on' => '2026-09-05',
        'charge_status_id' => ChargeStatusCode::ISSUED->id(), 'source' => 'CONDO_IMPORT',
    ], $attributes));
}

it('prints each local form as a single page pdf without changing the contract', function (string $type) {
    $before = $this->contract->fresh()->getAttributes();
    $response = $this->actingAs($this->user)->get(($this->formUrl)($type));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    preg_match_all('/\/Type\s*\/Page\b/', $response->getContent(), $pages);

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and($pages[0])->toHaveCount(1)
        ->and($this->contract->fresh()->getAttributes())->toBe($before)
        ->and(Charge::query()->count())->toBe(0);
})->with(['solvency', 'waiver']);

it('blocks solvency for overdue collectible balances in either concept or debtor type', function (string $kind, string $debtorType, string $dueOn) {
    localFormCharge([
        'kind' => $kind, 'debtor_type' => $debtorType,
        'debtor_id' => $debtorType === 'LOCAL' ? $this->local->id : $this->concessionaire->id,
        'due_on' => $dueOn,
    ]);

    $this->actingAs($this->user)->get(($this->formUrl)('solvency'))
        ->assertUnprocessable()->assertSeeText('tiene deuda vencida cobrable pendiente');
    $this->getJson($this->optionsUrl)->assertOk()->assertExactJson([
        'locals' => [['code' => 'TEST-01', 'can_issue_solvency' => false]],
    ]);
})->with([
    ['CONDO_USD', 'LOCAL', '2026-09-05'],
    ['RENT_EUR_M2', 'CONCESSIONAIRE', '2026-09-05'],
    ['CONDO_USD', 'LOCAL', '2026-10-26'],
]);

it('allows solvency for nonblocking charges', function (string $case) {
    $charge = localFormCharge(match ($case) {
        'uncollectible' => ['uncollectible_at' => now(), 'uncollectible_reason' => 'Deuda histórica'],
        'future' => ['due_on' => '2026-10-27'],
        'canceled' => ['charge_status_id' => ChargeStatusCode::CANCELED->id()],
        'settled' => ['charge_status_id' => ChargeStatusCode::SETTLED->id()],
        default => [],
    });
    if ($case === 'deleted') {
        $charge->delete();
    }

    $this->actingAs($this->user)->get(($this->formUrl)('solvency'))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->getJson($this->optionsUrl)->assertOk()->assertExactJson([
        'locals' => [['code' => 'TEST-01', 'can_issue_solvency' => true]],
    ]);
})->with(['uncollectible', 'future', 'canceled', 'settled', 'deleted']);

it('allows waiver despite overdue debt and leaves that debt untouched', function () {
    $charge = localFormCharge();
    $before = $charge->fresh()->getAttributes();
    $this->actingAs($this->user)->get(($this->formUrl)('waiver'))->assertOk();
    expect($charge->fresh()->getAttributes())->toBe($before);
});

it('checks only the selected local', function () {
    $other = $this->local->replicate();
    $other->code = 'TEST-02';
    $other->save();
    $this->contract->locals()->attach($other);
    localFormCharge(['local_id' => $other->id, 'debtor_id' => $other->id]);

    $this->actingAs($this->user)->get(($this->formUrl)('solvency'))->assertOk();
});

it('rejects locals not linked through an operational contract', function (string $case) {
    if ($case === 'unlinked') {
        $this->contract->locals()->detach($this->local);
    } elseif ($case === 'deleted') {
        $this->contract->delete();
    } else {
        $this->contract->update(['contract_status_id' => ContractStatus::query()->whereNotIn('code', ['VIG', 'VENC'])->value('id')]);
    }

    $this->actingAs($this->user)->get(($this->formUrl)('waiver'))->assertNotFound();
})->with(['unlinked', 'deleted', 'closed']);

it('requires view permission', function () {
    $this->actingAs(User::factory()->create())->get(($this->formUrl)('solvency'))->assertForbidden();
    $this->getJson($this->optionsUrl)->assertForbidden();
});

it('validates the local and form type', function () {
    $this->actingAs($this->user)->getJson(route('catalogs.concessionaire.local-form', [
        'concessionaire' => $this->concessionaire,
        'local_code' => 'MISSING', 'form_type' => 'invalid',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['local_code', 'form_type']);
});

it('uses the applied payment balance including partial and voided payments', function (int $paid, bool $voided, bool $blocked) {
    $charge = localFormCharge(['currency' => 'VES', 'charge_status_id' => ChargeStatusCode::PARTIAL->id()]);
    $bank = Bank::query()->create(['code' => 'TEST', 'bank_code' => '9999', 'name' => 'Banco de prueba', 'is_active' => true]);
    $account = CompanyBankAccount::query()->create([
        'bank_id' => $bank->id, 'account_number' => '01990011223344556677',
        'account_holder_name' => 'Mercado', 'document_type' => 'J', 'document_number' => '123456789', 'is_active' => true,
    ]);
    $payment = Payment::query()->create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $this->local->id,
        'company_bank_account_id' => $account->id, 'origin_bank_id' => $bank->id,
        'payer_document_number' => '12345678', 'reference' => 'TEST-123',
        'amount_bs_minor' => $paid, 'paid_on' => '2026-10-25',
        'voided_at' => $voided ? now() : null,
    ]);
    PaymentAllocation::query()->create([
        'payment_id' => $payment->id, 'charge_id' => $charge->id, 'amount_bs_minor' => $paid,
        'local_id' => $this->local->id, 'debtor_type' => 'LOCAL', 'debtor_id' => $this->local->id,
    ]);

    $response = $this->actingAs($this->user)->get(($this->formUrl)('solvency'));
    $blocked ? $response->assertUnprocessable() : $response->assertOk();
    $this->getJson($this->optionsUrl)->assertOk()->assertJsonPath('locals.0.can_issue_solvency', ! $blocked);
})->with([[5000, false, true], [10000, false, false], [10000, true, true]]);

it('allows a charge fully covered by an applied credit in its original currency', function () {
    $charge = localFormCharge();
    $credit = CustomerCredit::query()->create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $this->local->id,
        'currency' => 'USD', 'balance_minor' => 0, 'status' => 'USED',
    ]);
    CreditApplication::query()->create([
        'customer_credit_id' => $credit->id, 'charge_id' => $charge->id, 'amount_minor' => 10000,
    ]);

    $this->actingAs($this->user)->get(($this->formUrl)('solvency'))->assertOk();
});

it('renders the agreed wording and leaves the waiver signature date blank', function () {
    $data = [
        'concessionaire' => $this->concessionaire->load('documentType', 'phoneAreaCode'),
        'local' => $this->local, 'printed_at' => now(),
        'letterhead_base64' => 'letterhead-test', 'letterhead_mime' => 'image/png',
        'logo_base64' => 'logo-test', 'logo_mime' => 'image/png',
    ];
    $solvency = view('pdf.concessionaire_local_form', [...$data, 'form_type' => 'solvency'])->render();
    $waiver = view('pdf.concessionaire_local_form', [...$data, 'form_type' => 'waiver'])->render();

    expect($solvency)->toContain('YREANA LUCKERT', 'ARELIS CASTRO', 'V-13.864.150', 'V-11.199.235', 'obligaciones vencidas cobrables', 'TEST-01', 'María Cesionaria', 'letterhead-test', 'logo-test')
        ->and($waiver)->toContain('____ / ____ / ________', 'Observación administrativa', 'Consultor Jurídico', 'Dirección de Relaciones Comerciales', 'V-12345678', 'TEST-01');
});

it('returns only operational linked locals and indicates which ones have overdue debt', function () {
    $other = $this->local->replicate();
    $other->code = 'TEST-02';
    $other->save();
    $this->contract->locals()->attach($other);
    $unlinked = $this->local->replicate();
    $unlinked->code = 'UNLINKED';
    $unlinked->save();
    localFormCharge();

    $this->actingAs($this->user)->getJson($this->optionsUrl)->assertOk()->assertExactJson([
        'locals' => [
            ['code' => 'TEST-01', 'can_issue_solvency' => false],
            ['code' => 'TEST-02', 'can_issue_solvency' => true],
        ],
    ]);
});

it('rechecks the balance on generation even when the previous eligibility check allowed it', function () {
    $this->actingAs($this->user)->getJson($this->optionsUrl)->assertOk()->assertJsonPath('locals.0.can_issue_solvency', true);
    localFormCharge();
    $this->get(($this->formUrl)('solvency'))->assertUnprocessable();
});
