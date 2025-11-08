<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function mkPortalUserRcv(bool $withPerm = true, bool $withLink = true): array
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
    ]);

    $user = \App\Models\User::create([
        'name' => 'Portal User',
        'email' => 'portal.user.'.uniqid().'@mailinator.com',
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
    $concessionaire = \App\Models\Concessionaire::create([
        'concessionaire_type_id' => $ct->getKey(),
        'full_name' => 'Eva Portal',
        'document_type_id' => $dt->getKey(),
        'document_number' => (string) random_int(1000000, 99999999),
        'fiscal_address' => 'X',
        'email' => 'eva.portal.'.uniqid().'@mailinator.com',
        'phone_area_code_id' => null,
        'phone_number' => null,
        'is_active' => true,
    ]);
    if ($withLink) {
        $concessionaire->users()->syncWithoutDetaching([$user->id => ['is_primary' => true, 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now()]]);
    }

    return [$user, $concessionaire];
}

function mkBankAndAccRcv(): array
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

it('allows portal user to download own receipt PDF', function () {
    [$user, $c] = mkPortalUserRcv(withPerm: true, withLink: true);
    $this->actingAs($user);
    [$bank, $acc] = mkBankAndAccRcv();

    // Payment for this concessionaire
    $payment = \App\Models\Payment::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $c->getKey(),
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => 'RCV-001', 'amount_bs_minor' => 1000, 'paid_on' => now()->toDateString(), 'status' => 'APPLIED', 'method' => 'PMOV',
    ]);

    // Create receipt with existing PDF path
    $receipt = \App\Models\Receipt::create([
        'payment_id' => $payment->getKey(),
        'market_id' => null,
        'scope' => 'PAYMENT',
        'series_code' => 'PRT-'.date('Y'),
        'number_seq' => 1,
        'receipt_number' => 'PRT-'.date('Y').'-000001',
        'issued_at' => now(),
        'status' => 'ACTIVE',
        'allocations_hash' => hash('sha256', 'x'),
        'public_token' => bin2hex(random_bytes(24)),
    ]);
    $path = 'receipts/'.date('Y').'/'.$receipt->getAttribute('receipt_number').'.pdf';
    Storage::disk('local')->makeDirectory('receipts/'.date('Y'));
    Storage::disk('local')->put($path, "%PDF-1.4\n%");
    $receipt->setAttribute('pdf_path', $path);
    $receipt->save();

    $res = $this->get(route('portal.receipts.download', ['receipt' => $receipt->getKey()]));
    $res->assertOk();
    $res->assertHeader('Content-Type', 'application/pdf');
});

it('blocks download of other concessionaire receipt', function () {
    [$user, $c] = mkPortalUserRcv(withPerm: true, withLink: true);
    $this->actingAs($user);
    [$bank, $acc] = mkBankAndAccRcv();

    $dt = \App\Models\DocumentType::first() ?: \App\Models\DocumentType::create(['code' => 'E', 'name' => 'E', 'is_active' => true]);
    $ct = \App\Models\ConcessionaireType::first() ?: \App\Models\ConcessionaireType::create(['code' => 'JUR', 'name' => 'Juridica', 'is_active' => true]);
    $other = \App\Models\Concessionaire::create([
        'concessionaire_type_id' => $ct->getKey(), 'full_name' => 'Otro Concesionario', 'document_type_id' => $dt->getKey(),
        'document_number' => '8888888', 'fiscal_address' => 'X', 'email' => 'other@mailinator.com', 'is_active' => true,
    ]);

    $payment = \App\Models\Payment::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $other->getKey(),
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => 'RCV-002', 'amount_bs_minor' => 1000, 'paid_on' => now()->toDateString(), 'status' => 'APPLIED', 'method' => 'PMOV',
    ]);
    $receipt = \App\Models\Receipt::create([
        'payment_id' => $payment->getKey(), 'market_id' => null, 'scope' => 'PAYMENT',
        'series_code' => 'PRT-'.date('Y'), 'number_seq' => 2,
        'receipt_number' => 'PRT-'.date('Y').'-000002', 'issued_at' => now(), 'status' => 'ACTIVE',
        'allocations_hash' => hash('sha256', 'y'), 'public_token' => bin2hex(random_bytes(24)),
        'pdf_path' => null,
    ]);

    $this->get(route('portal.receipts.download', ['receipt' => $receipt->getKey()]))->assertStatus(403);
});

it('generates PDF on the fly when missing and then downloads', function () {
    [$user, $c] = mkPortalUserRcv(withPerm: true, withLink: true);
    $this->actingAs($user);
    [$bank, $acc] = mkBankAndAccRcv();

    $payment = \App\Models\Payment::create([
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => $c->getKey(),
        'company_bank_account_id' => $acc->id, 'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => 'RCV-003', 'amount_bs_minor' => 1000, 'paid_on' => now()->toDateString(), 'status' => 'APPLIED', 'method' => 'PMOV',
    ]);
    $receipt = \App\Models\Receipt::create([
        'payment_id' => $payment->getKey(), 'market_id' => null, 'scope' => 'PAYMENT',
        'series_code' => 'PRT-'.date('Y'), 'number_seq' => 3,
        'receipt_number' => 'PRT-'.date('Y').'-000003', 'issued_at' => now(), 'status' => 'ACTIVE',
        'allocations_hash' => hash('sha256', 'z'), 'public_token' => bin2hex(random_bytes(24)),
        'pdf_path' => null,
    ]);

    // Mock generator to set a path and write file
    $this->mock(\App\Services\ReceiptPdfGenerator::class, function ($m) use ($receipt) {
        $path = 'receipts/'.date('Y').'/'.$receipt->getAttribute('receipt_number').'.pdf';
        Storage::disk('local')->makeDirectory('receipts/'.date('Y'));
        Storage::disk('local')->put($path, "%PDF-1.4\n%");
        $m->shouldReceive('render')->andReturn([
            'pdf_path' => $path,
            'pdf_sha256' => hash('sha256', 'dummy'),
            'rendered_at' => now()->toDateTimeString(),
        ]);
    });

    $res = $this->get(route('portal.receipts.download', ['receipt' => $receipt->getKey()]));
    $res->assertOk();
    $res->assertHeader('Content-Type', 'application/pdf');
});
