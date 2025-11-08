<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function mkPortalUserAlloc(bool $withPerm = true, bool $withLink = true): array
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
    ]);

    $user = \App\Models\User::create([
        'name' => 'Portal Alloc',
        'email' => 'portal.alloc.'.uniqid().'@mailinator.com',
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

    $dt = \App\Models\DocumentType::first() ?: \App\Models\DocumentType::create(['code' => 'V', 'name' => 'V', 'is_active' => true]);
    $ct = \App\Models\ConcessionaireType::first() ?: \App\Models\ConcessionaireType::create(['code' => 'NAT', 'name' => 'Natural', 'is_active' => true]);
    $c = \App\Models\Concessionaire::create([
        'concessionaire_type_id' => $ct->getKey(),
        'full_name' => 'Concesionario Portal',
        'document_type_id' => $dt->getKey(),
        'document_number' => (string) random_int(1000000, 99999999),
        'fiscal_address' => 'X',
        'email' => 'concesionario.'.uniqid().'@mailinator.com',
        'is_active' => true,
    ]);

    if ($withLink) {
        $c->users()->syncWithoutDetaching([$user->id => ['is_primary' => true, 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now()]]);
    }

    return [$user, $c];
}

function mkBankAccAlloc(): array
{
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

    return [$bank, $acc];
}

function mkContextAlloc(): array
{
    $market = \App\Models\Market::create(['code' => 'M-PAL', 'name' => 'Mkt Portal', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT-PAL', 'name' => 'LT', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LS-PAL', 'name' => 'LS', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LL-PAL', 'name' => 'LL', 'is_active' => true]);
    $local = \App\Models\Local::create([
        'code' => 'L-PAL-1', 'name' => 'Local PAL 1',
        'market_id' => $market->id,
        'local_type_id' => $lt->id,
        'local_status_id' => $ls->id,
        'local_location_id' => $ll->id,
        'area_m2' => 10,
        'is_active' => true,
    ]);

    return [$market, $local];
}

it('portal previewAllocations validates outstanding and credit usage', function () {
    [$user, $conces] = mkPortalUserAlloc();
    $this->actingAs($user);
    [$bank, $acc] = mkBankAccAlloc();
    [$market, $local] = mkContextAlloc();

    $payment = \App\Models\Payment::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $conces->getKey(),
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => 'PAL-PRV', 'amount_bs_minor' => 1000, 'paid_on' => now()->toDateString(), 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);

    $statusIssued = (int) (\App\Models\ChargeStatus::query()->where('code', 'ISSUED')->value('id') ?? 0);
    $ch = \App\Models\Charge::create([
        'market_id' => $market->id, 'local_id' => $local->id,
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $conces->getKey(),
        'origin_debtor_type' => 'CONCESSIONAIRE', 'origin_debtor_id' => $conces->getKey(),
        'currency' => 'VES', 'amount_minor' => 3000,
        'period' => Carbon::parse('2025-10-01'), 'issued_on' => Carbon::parse('2025-10-01'), 'due_on' => Carbon::parse('2025-10-10'),
        'charge_status_id' => $statusIssued, 'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $ch->setAttribute('amount_bs_minor_issued', 3000);
    $ch->save();

    // Exceeds outstanding
    $r1 = $this->postJson(route('portal.payments.allocations.preview', ['payment' => $payment->getKey()]), [
        'items' => [['charge_id' => $ch->getKey(), 'amount_bs_minor' => 4000]],
    ]);
    $r1->assertOk();
    expect((bool) $r1->json('ok'))->toBeFalse();

    // Exceeds available without credit
    $r2 = $this->postJson(route('portal.payments.allocations.preview', ['payment' => $payment->getKey()]), [
        'items' => [['charge_id' => $ch->getKey(), 'amount_bs_minor' => 1500]],
        'use_credit' => false,
    ]);
    $r2->assertOk();
    expect(collect($r2->json('errors')))->toContain('Total a aplicar supera el disponible (pago + crédito a favor).');

    // With credit available, valid
    \App\Models\CustomerCredit::create(['debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $conces->getKey(), 'currency' => 'VES', 'balance_minor' => 1000, 'status' => 'OPEN']);
    $r3 = $this->postJson(route('portal.payments.allocations.preview', ['payment' => $payment->getKey()]), [
        'items' => [['charge_id' => $ch->getKey(), 'amount_bs_minor' => 1500]],
        'use_credit' => true,
    ]);
    $r3->assertOk();
    expect((bool) $r3->json('ok'))->toBeTrue();
});

it('portal suggestAllocations suggests FIFO correctly', function () {
    [$user, $conces] = mkPortalUserAlloc();
    $this->actingAs($user);
    [$bank, $acc] = mkBankAccAlloc();
    [$market, $local] = mkContextAlloc();

    $payment = \App\Models\Payment::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $conces->getKey(),
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => 'PAL-SUG', 'amount_bs_minor' => 5000, 'paid_on' => now()->toDateString(), 'status' => 'CONFIRMED', 'method' => 'PMOV',
    ]);

    $statusIssued = (int) (\App\Models\ChargeStatus::query()->where('code', 'ISSUED')->value('id') ?? 0);
    $c1 = \App\Models\Charge::create([
        'market_id' => $market->id, 'local_id' => $local->id,
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $conces->getKey(),
        'origin_debtor_type' => 'CONCESSIONAIRE', 'origin_debtor_id' => $conces->getKey(),
        'currency' => 'VES', 'amount_minor' => 3000,
        'period' => Carbon::parse('2025-08-01'), 'issued_on' => Carbon::parse('2025-08-01'), 'due_on' => Carbon::parse('2025-08-10'),
        'charge_status_id' => $statusIssued, 'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $c1->setAttribute('amount_bs_minor_issued', 3000);
    $c1->save();
    $c2 = \App\Models\Charge::create([
        'market_id' => $market->id, 'local_id' => $local->id,
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $conces->getKey(),
        'origin_debtor_type' => 'CONCESSIONAIRE', 'origin_debtor_id' => $conces->getKey(),
        'currency' => 'VES', 'amount_minor' => 4000,
        'period' => Carbon::parse('2025-09-01'), 'issued_on' => Carbon::parse('2025-09-01'), 'due_on' => Carbon::parse('2025-09-10'),
        'charge_status_id' => $statusIssued, 'kind' => 'RENT', 'source' => 'TEST',
    ]);
    $c2->setAttribute('amount_bs_minor_issued', 4000);
    $c2->save();

    $r = $this->postJson(route('portal.payments.allocations.suggest', ['payment' => $payment->getKey()]), ['strategy' => 'fifo']);
    $r->assertOk();
    $byId = collect($r->json('items'))->keyBy('charge_id');
    expect((int) $byId[$c1->id]['amount_bs_minor'])->toBe(3000);
    expect((int) $byId[$c2->id]['amount_bs_minor'])->toBe(2000);
});
