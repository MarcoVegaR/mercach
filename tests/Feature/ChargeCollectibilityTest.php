<?php

declare(strict_types=1);

use App\Exceptions\DomainActionException;
use App\Models\Bank;
use App\Models\Charge;
use App\Models\ChargeCollectibilityEvent;
use App\Models\ChargeStatus;
use App\Models\CompanyBankAccount;
use App\Models\Concessionaire;
use App\Models\Contract;
use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\ChargeCollectibilityService;
use App\Services\Payments\AllocationProcessor;
use Database\Seeders\ChargeStatusesSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function collectibilityUser(array $permissions = []): User
{
    test()->seed([
        PermissionsSeeder::class,
        ChargeStatusesSeeder::class,
    ]);

    $user = User::factory()->create();
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function collectibilityLocal(): Local
{
    $market = Market::create(['code' => fake()->unique()->bothify('MC-###'), 'name' => 'Mercado Incobrables', 'address' => 'X', 'is_active' => true]);
    $type = LocalType::create(['code' => fake()->unique()->bothify('LT-###'), 'name' => 'Tipo', 'is_active' => true]);
    $status = LocalStatus::create(['code' => fake()->unique()->bothify('LS-###'), 'name' => 'Estado', 'is_active' => true]);
    $location = LocalLocation::create(['code' => fake()->unique()->bothify('LL-###'), 'name' => 'Ubicacion', 'is_active' => true]);

    return Local::create([
        'code' => fake()->unique()->bothify('L-###'),
        'name' => 'Local Incobrable',
        'market_id' => $market->id,
        'local_type_id' => $type->id,
        'local_status_id' => $status->id,
        'local_location_id' => $location->id,
        'area_m2' => 10,
        'is_active' => true,
    ]);
}

function collectibilityCharge(Local $local, string $statusCode = 'ISSUED', int $amountMinor = 10000, array $overrides = []): Charge
{
    $statusId = (int) ChargeStatus::query()->where('code', $statusCode)->value('id');

    return Charge::create(array_merge([
        'market_id' => $local->market_id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'kind' => 'RENT_EUR_FIXED',
        'currency' => 'VES',
        'amount_minor' => $amountMinor,
        'amount_bs_minor_issued' => $amountMinor,
        'period' => Carbon::parse('2026-07-01'),
        'issued_on' => Carbon::parse('2026-07-01'),
        'due_on' => Carbon::parse('2026-07-10'),
        'charge_status_id' => $statusId,
        'source' => 'TEST',
    ], $overrides));
}

function collectibilityConcessionaire(string $name): Concessionaire
{
    $now = now();

    $concessionaireTypeId = DB::table('concessionaire_types')->insertGetId([
        'code' => fake()->unique()->bothify('CT-###'),
        'name' => 'Natural',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $documentTypeId = DB::table('document_types')->insertGetId([
        'code' => fake()->unique()->bothify('DT-###'),
        'name' => 'Cedula',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $phoneAreaCodeId = DB::table('phone_area_codes')->insertGetId([
        'code' => fake()->unique()->numerify('###'),
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return Concessionaire::create([
        'concessionaire_type_id' => $concessionaireTypeId,
        'full_name' => $name,
        'document_type_id' => $documentTypeId,
        'document_number' => fake()->unique()->numerify('########'),
        'fiscal_address' => 'Direccion fiscal',
        'email' => fake()->unique()->safeEmail(),
        'phone_area_code_id' => $phoneAreaCodeId,
        'phone_number' => fake()->numerify('#######'),
        'is_active' => true,
    ]);
}

function collectibilityContractFor(Local $local, Concessionaire $concessionaire, string $number): Contract
{
    $now = now();
    $contractTypeId = DB::table('contract_types')->insertGetId([
        'code' => fake()->unique()->bothify('CRT-###'),
        'name' => 'Contrato',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $contractStatusId = DB::table('contract_statuses')->insertGetId([
        'code' => fake()->unique()->bothify('CRS-###'),
        'name' => 'Activo',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $contractModalityId = DB::table('contract_modalities')->insertGetId([
        'code' => fake()->unique()->bothify('CRM-###'),
        'name' => 'Fijo',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $tradeCategoryId = DB::table('trade_categories')->insertGetId([
        'code' => fake()->unique()->bothify('TRC-###'),
        'name' => 'General',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $contract = Contract::factory()->create([
        'number' => $number,
        'contract_type_id' => $contractTypeId,
        'contract_status_id' => $contractStatusId,
        'contract_modality_id' => $contractModalityId,
        'trade_category_id' => $tradeCategoryId,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $contract->locals()->attach($local->id);
    $contract->concessionaires()->attach($concessionaire->id, ['is_primary' => true]);

    return $contract;
}

function collectibilityPayment(Local $local): Payment
{
    $bank = Bank::create(['code' => fake()->unique()->bothify('BANK###'), 'bank_code' => fake()->unique()->numerify('###'), 'name' => 'Banco Incobrables', 'is_active' => true]);
    $account = CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => fake()->unique()->numerify('####################'),
        'phone_number' => '584241112233',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => fake()->unique()->numerify('#########'),
        'is_active' => true,
    ]);

    return Payment::create([
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'company_bank_account_id' => $account->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12345678',
        'reference' => fake()->unique()->numerify('COL######'),
        'amount_bs_minor' => 10000,
        'paid_on' => '2026-07-15',
        'status' => 'CONFIRMED',
        'method' => 'PMOV',
    ]);
}

it('marks an open charge as uncollectible and stores a historical snapshot', function () {
    $user = collectibilityUser(['charges.collectibility.mark']);
    $charge = collectibilityCharge(collectibilityLocal(), 'ISSUED', 12345);

    $this->actingAs($user)
        ->post(route('charges.mark-uncollectible', $charge), ['reason' => 'Gestion agotada'])
        ->assertRedirect();

    $charge->refresh();
    expect($charge->uncollectible_at)->not->toBeNull()
        ->and($charge->uncollectible_reason)->toBe('Gestion agotada');

    $event = ChargeCollectibilityEvent::query()->where('charge_id', $charge->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->action)->toBe(ChargeCollectibilityEvent::ActionMarkedUncollectible)
        ->and($event->outstanding_amount_minor)->toBe(12345)
        ->and($event->outstanding_bs_minor)->toBe(12345);
});

it('rejects terminal charges and keeps bulk marking atomic', function () {
    $user = collectibilityUser();
    $local = collectibilityLocal();
    $issued = collectibilityCharge($local, 'ISSUED', 10000);
    $settled = collectibilityCharge($local, 'SETTLED', 10000);

    expect(fn () => app(ChargeCollectibilityService::class)->markUncollectible(
        [(int) $issued->id, (int) $settled->id],
        'Gestion agotada',
        $user,
    ))->toThrow(DomainActionException::class);

    expect($issued->fresh()->uncollectible_at)->toBeNull()
        ->and($settled->fresh()->uncollectible_at)->toBeNull()
        ->and(ChargeCollectibilityEvent::query()->count())->toBe(0);
});

it('restores an uncollectible charge as collectible and keeps history', function () {
    $user = collectibilityUser(['charges.collectibility.mark', 'charges.collectibility.restore']);
    $charge = collectibilityCharge(collectibilityLocal(), 'PARTIAL', 10000);

    app(ChargeCollectibilityService::class)->markUncollectible([(int) $charge->id], 'Gestion agotada', $user);

    $this->actingAs($user)
        ->post(route('charges.restore-collectible', $charge), ['reason' => 'Acuerdo de pago'])
        ->assertRedirect();

    expect($charge->fresh()->uncollectible_at)->toBeNull()
        ->and(ChargeCollectibilityEvent::query()->where('charge_id', $charge->id)->count())->toBe(2)
        ->and(ChargeCollectibilityEvent::query()->where('charge_id', $charge->id)->latest('id')->value('action'))->toBe(ChargeCollectibilityEvent::ActionRestored);
});

it('reports current uncollectible charges totals', function () {
    config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

    $user = collectibilityUser(['reports.uncollectible_charges.view']);
    $charge = collectibilityCharge(collectibilityLocal(), 'ISSUED', 10000);
    app(ChargeCollectibilityService::class)->markUncollectible([(int) $charge->id], 'Gestion agotada', $user);

    $this->actingAs($user)
        ->get(route('reports.uncollectible-charges'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/uncollectible-charges')
            ->where('totals.count', 1)
            ->where('totals.declared_outstanding_bs_minor', 10000)
            ->where('rows.0.charge_id', $charge->id)
        );
});

it('reports the historical concessionaire from the charge contract', function () {
    config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

    $user = collectibilityUser(['reports.uncollectible_charges.view']);
    $local = collectibilityLocal();
    $oldConcessionaire = collectibilityConcessionaire('Cesionario Historico');
    $newConcessionaire = collectibilityConcessionaire('Cesionario Nuevo');
    $oldContract = collectibilityContractFor($local, $oldConcessionaire, 'OLD-001');
    collectibilityContractFor($local, $newConcessionaire, 'NEW-001');

    $charge = collectibilityCharge($local, 'ISSUED', 10000, [
        'contract_id' => $oldContract->id,
    ]);
    app(ChargeCollectibilityService::class)->markUncollectible([(int) $charge->id], 'Gestion agotada', $user);

    $this->actingAs($user)
        ->get(route('reports.uncollectible-charges'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/uncollectible-charges')
            ->where('rows.0.concessionaire_name', 'Cesionario Historico')
            ->where('rows.0.kind_label', 'Alquiler fijo')
            ->where('rows.0.current_outstanding_amount_minor', 10000)
        );
});

it('rejects payment allocations to an uncollectible charge', function () {
    $user = collectibilityUser();
    $local = collectibilityLocal();
    $charge = collectibilityCharge($local, 'ISSUED', 10000);
    $payment = collectibilityPayment($local);

    app(ChargeCollectibilityService::class)->markUncollectible([(int) $charge->id], 'Gestion agotada', $user);

    expect(fn () => app(AllocationProcessor::class)->process($payment, [
        ['charge_id' => (int) $charge->id, 'amount_bs_minor' => 10000],
    ]))->toThrow(DomainActionException::class, 'Debe restaurarse antes de aplicar pagos');

    expect(PaymentAllocation::query()->where('payment_id', $payment->id)->count())->toBe(0);
});

it('exports uncollectible charges as csv', function () {
    $user = collectibilityUser(['reports.uncollectible_charges.export']);
    $charge = collectibilityCharge(collectibilityLocal(), 'ISSUED', 10000);
    app(ChargeCollectibilityService::class)->markUncollectible([(int) $charge->id], 'Gestion agotada', $user);

    $this->actingAs($user)
        ->get(route('reports.uncollectible-charges.export', ['format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('exports uncollectible charges as pdf', function () {
    $user = collectibilityUser(['reports.uncollectible_charges.export']);
    $charge = collectibilityCharge(collectibilityLocal(), 'ISSUED', 10000);
    app(ChargeCollectibilityService::class)->markUncollectible([(int) $charge->id], 'Gestion agotada', $user);

    $this->actingAs($user)
        ->get(route('reports.uncollectible-charges.export', ['format' => 'pdf']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('exposes uncollectible metrics for dashboard', function () {
    $user = collectibilityUser(['dashboard.view.finance']);
    $charge = collectibilityCharge(collectibilityLocal(), 'ISSUED', 10000);
    app(ChargeCollectibilityService::class)->markUncollectible([(int) $charge->id], 'Gestion agotada', $user);

    $this->actingAs($user)
        ->getJson(route('api.dashboard.charges.uncollectible-metrics'))
        ->assertOk()
        ->assertJsonPath('current_count', 1)
        ->assertJsonPath('current_outstanding_bs_minor', 10000)
        ->assertJsonPath('declared_count', 1)
        ->assertJsonPath('declared_outstanding_bs_minor', 10000)
        ->assertJsonPath('restored_count', 0);
});
