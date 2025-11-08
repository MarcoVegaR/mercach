<?php

declare(strict_types=1);

use App\Contracts\Services\FxRateServiceInterface;
use App\Contracts\Services\PaymentServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mkPortalUser(bool $withPerm = true, bool $withLink = true): array
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
    ]);

    $user = \App\Models\User::create([
        'name' => 'Portal Tester',
        'email' => 'portal.tester+'.uniqid().'@mailinator.com',
        'password' => bcrypt('secret1234'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    if ($withPerm) {
        try {
            $user->givePermissionTo('portal.access');
        } catch (Throwable $e) {
        }
    }

    // Minimal docs and types
    $dt = \App\Models\DocumentType::first() ?: \App\Models\DocumentType::create(['code' => 'V', 'name' => 'V', 'mask' => null, 'is_active' => true]);
    $ct = \App\Models\ConcessionaireType::first() ?: \App\Models\ConcessionaireType::create(['code' => 'NAT', 'name' => 'Natural', 'is_active' => true]);

    $concessionaire = \App\Models\Concessionaire::create([
        'concessionaire_type_id' => $ct->getKey(),
        'full_name' => 'Test Concessionaire',
        'document_type_id' => $dt->getKey(),
        'document_number' => (string) random_int(1000000, 99999999),
        'fiscal_address' => 'X',
        'email' => 'concesionario+'.uniqid().'@mailinator.com',
        'phone_area_code_id' => null,
        'phone_number' => null,
        'is_active' => true,
    ]);

    if ($withLink) {
        $concessionaire->users()->syncWithoutDetaching([
            $user->id => [
                'is_primary' => true,
                'status' => 'active',
                'invited_at' => now(),
                'accepted_at' => now(),
                'created_by' => null,
            ],
        ]);
    }

    return [$user, $concessionaire];
}

function mkPortalBankAndAcc(): array
{
    $bank = \App\Models\Bank::first() ?: \App\Models\Bank::create([
        'code' => 'BANKT', 'bank_code' => '156', 'name' => 'Banco Test', 'is_active' => true,
    ]);
    $acc = \App\Models\CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01560011223344556677',
        'phone_number' => '584241112233',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);

    return [$bank, $acc];
}

it('portal routes require portal.access permission', function () {
    [$user] = mkPortalUser(withPerm: false, withLink: true);
    $this->actingAs($user);

    $res = $this->get(route('portal.payments.create'));
    $res->assertStatus(403);
});

it('portal routes require a linked concessionaire', function () {
    [$user] = mkPortalUser(withPerm: true, withLink: false);
    $this->actingAs($user);

    $res = $this->get(route('portal.payments.create'));
    $res->assertStatus(302);
});

it('store creates a payment via service and redirects to apply page', function () {
    [$user, $c] = mkPortalUser(withPerm: true, withLink: true);
    $this->actingAs($user);

    // Bank + company account for payload
    $bank = \App\Models\Bank::first() ?: \App\Models\Bank::create(['code' => 'BANKT', 'bank_code' => '156', 'name' => 'Banco Test', 'is_active' => true]);
    $acc = \App\Models\CompanyBankAccount::create([
        'bank_id' => $bank->id,
        'account_number' => '01560011223344556677',
        'phone_number' => '584241112233',
        'account_holder_name' => 'Cuenta Receptora',
        'document_type' => 'J',
        'document_number' => '123456789012',
        'is_active' => true,
    ]);

    // Mock PaymentService to avoid external gateway
    $this->mock(PaymentServiceInterface::class, function ($m) {
        $m->shouldReceive('createAndVerify')->andReturn(['id' => 987]);
    });

    $payload = [
        'company_bank_account_id' => $acc->id,
        'method' => 'PMOV',
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V',
        'payer_document_number' => '12062754',
        'payer_phone_area_code' => '0412',
        'payer_phone_number' => '1234567',
        'reference' => '123456',
        'amount_bs_minor' => 10000,
        'paid_on' => now()->toDateString(),
    ];

    $res = $this->post(route('portal.payments.store'), $payload);
    $res->assertRedirect(route('portal.payments.apply', ['payment' => 987]));
    $res->assertSessionHas('success');
});

it('apply page only allows owner concessionaire', function () {
    [$user, $c] = mkPortalUser(withPerm: true, withLink: true);
    $this->actingAs($user);

    // Payment for this concessionaire
    [$bank, $acc] = mkPortalBankAndAcc();
    $payment = \App\Models\Payment::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $c->getKey(),
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => 'APPLY-001',
        'amount_bs_minor' => 1000,
        'paid_on' => now()->toDateString(),
        'status' => 'CONFIRMED',
        'method' => 'PMOV',
    ]);

    $this->get(route('portal.payments.apply', ['payment' => $payment->getKey()]))->assertOk();

    // Another concessionaire
    $dt = \App\Models\DocumentType::first() ?: \App\Models\DocumentType::create(['code' => 'E', 'name' => 'E', 'mask' => null, 'is_active' => true]);
    $ct = \App\Models\ConcessionaireType::first() ?: \App\Models\ConcessionaireType::create(['code' => 'JUR', 'name' => 'Juridica', 'is_active' => true]);
    $c2 = \App\Models\Concessionaire::create([
        'concessionaire_type_id' => $ct->getKey(), 'full_name' => 'Other', 'document_type_id' => $dt->getKey(),
        'document_number' => '5555555', 'fiscal_address' => 'X', 'email' => 'x@y.z', 'is_active' => true,
    ]);
    $p2 = \App\Models\Payment::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $c2->getKey(),
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => 'APPLY-002', 'amount_bs_minor' => 1000, 'paid_on' => now()->toDateString(), 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);

    $this->get(route('portal.payments.apply', ['payment' => $p2->getKey()]))->assertStatus(404);
});

it('open-charges returns concessionaire-level charges and respects overdue filter', function () {
    [$user, $c] = mkPortalUser(withPerm: true, withLink: true);
    $this->actingAs($user);

    $this->seed([Database\Seeders\ChargeStatusesSeeder::class]);

    [$bank, $acc] = mkPortalBankAndAcc();
    $payment = \App\Models\Payment::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $c->getKey(),
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => 'OPEN-001', 'amount_bs_minor' => 0, 'paid_on' => now()->toDateString(), 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);

    $statusIssued = (int) (\App\Models\ChargeStatus::query()->where('code', 'ISSUED')->value('id') ?? 0);

    // Required context for charges table
    $market = \App\Models\Market::create(['code' => 'M-POR', 'name' => 'Market Portal', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT-P', 'name' => 'LT-P', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LS-P', 'name' => 'LS-P', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LL-P', 'name' => 'LL-P', 'is_active' => true]);
    $local = \App\Models\Local::create([
        'code' => 'L-POR-1', 'name' => 'Local Portal 1',
        'market_id' => $market->id,
        'local_type_id' => $lt->id,
        'local_status_id' => $ls->id,
        'local_location_id' => $ll->id,
        'area_m2' => 10,
        'is_active' => true,
    ]);

    $overdue = \App\Models\Charge::create([
        'market_id' => $market->id, 'local_id' => $local->id,
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $c->getKey(),
        'origin_debtor_type' => 'CONCESSIONAIRE', 'origin_debtor_id' => $c->getKey(),
        'currency' => 'VES', 'amount_minor' => 10000,
        'period' => now()->startOfMonth()->subMonth(), 'issued_on' => now()->startOfMonth()->subMonth(),
        'due_on' => now()->subDay(), 'charge_status_id' => $statusIssued,
        'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $overdue->setAttribute('amount_bs_minor_issued', 10000);
    $overdue->save();

    $notOver = \App\Models\Charge::create([
        'market_id' => $market->id, 'local_id' => $local->id,
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $c->getKey(),
        'origin_debtor_type' => 'CONCESSIONAIRE', 'origin_debtor_id' => $c->getKey(),
        'currency' => 'VES', 'amount_minor' => 5000,
        'period' => now()->startOfMonth(), 'issued_on' => now()->startOfMonth(),
        'due_on' => now()->addDays(5), 'charge_status_id' => $statusIssued,
        'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $notOver->setAttribute('amount_bs_minor_issued', 5000);
    $notOver->save();

    $res = $this->getJson(route('portal.payments.open-charges', ['payment' => $payment->getKey(), 'overdue_only' => 1]));
    $res->assertOk();
    $ids = collect($res->json('items'))->pluck('charge_id')->all();
    expect($ids)->toContain($overdue->id);
    expect($ids)->not->toContain($notOver->id);
});

it('resolve-fx returns fx_rate_id and rate_to_ves', function () {
    [$user] = mkPortalUser(withPerm: true, withLink: true);
    $this->actingAs($user);

    $today = now();
    $rate = \App\Models\FxRate::create([
        'currency_code' => 'USD',
        'rate_date' => $today->toDateString(),
        'value_date' => $today->toDateString(),
        'published_at' => $today,
        'rate_to_ves' => 36.5,
        'operational_from' => $today,
        'operational_to' => $today->copy()->addDay(),
        'source' => 'TEST',
        'is_official' => true,
        'is_active' => true,
    ]);

    $this->mock(FxRateServiceInterface::class, function ($m) use ($rate) {
        $m->shouldReceive('resolveAt')->andReturn($rate);
    });

    $res = $this->getJson(route('portal.payments.resolve-fx', ['currency' => 'USD', 'paid_on' => now()->toDateString()]));
    $res->assertOk();
    expect((int) $res->json('fx_rate_id'))->toBe($rate->getKey());
    expect((float) $res->json('rate_to_ves'))->toBe(36.5);
});

it('store-allocations calls service and redirects to receipts', function () {
    [$user, $c] = mkPortalUser(withPerm: true, withLink: true);
    $this->actingAs($user);

    [$bank, $acc] = mkPortalBankAndAcc();
    $payment = \App\Models\Payment::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $c->getKey(),
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => 'ALLOC-001', 'amount_bs_minor' => 1000, 'paid_on' => now()->toDateString(), 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);

    $this->mock(PaymentServiceInterface::class, function ($m) {
        $m->shouldReceive('storeAllocations')->once()->andReturn(['ok' => true]);
    });

    $payload = [
        'items' => [
            ['charge_id' => 1, 'amount_bs_minor' => 500],
        ],
        'use_credit' => false,
        'idempotency_key' => 'idem-portal-1',
    ];

    $res = $this->post(route('portal.payments.allocations.store', ['payment' => $payment->getKey()]), $payload);
    $res->assertRedirect(route('portal.receipts'));
});
