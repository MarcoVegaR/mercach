<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function seedAndLoginAdminForReceipts(): void
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\UsersSeeder::class,
        Database\Seeders\PaymentStatusesSeeder::class,
        Database\Seeders\ChargeStatusesSeeder::class,
        Database\Seeders\BanksSeeder::class,
    ]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    test()->actingAs($admin);
}

function makeBasicPaymentForReceipts(): array
{
    $market = \App\Models\Market::create(['code' => 'M-UAT', 'name' => 'Market UAT', 'address' => 'X', 'is_active' => true]);
    $lt = \App\Models\LocalType::create(['code' => 'LT', 'name' => 'LT', 'is_active' => true]);
    $ls = \App\Models\LocalStatus::create(['code' => 'LST', 'name' => 'LST', 'is_active' => true]);
    $ll = \App\Models\LocalLocation::create(['code' => 'LOC', 'name' => 'LOC', 'is_active' => true]);
    $local = \App\Models\Local::create([
        'code' => 'L-UAT-1', 'name' => 'Local UAT 1',
        'market_id' => $market->id,
        'local_type_id' => $lt->id,
        'local_status_id' => $ls->id,
        'local_location_id' => $ll->id,
        'area_m2' => 10,
        'is_active' => true,
    ]);

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

    $chargeStatusId = (int) (\App\Models\ChargeStatus::query()->where('code', 'ISSUED')->value('id') ?? 0);
    $charge = \App\Models\Charge::create([
        'market_id' => $market->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'origin_debtor_type' => 'LOCAL',
        'origin_debtor_id' => $local->id,
        'currency' => 'VES',
        'amount_minor' => 10000,
        'period' => now()->startOfMonth(),
        'issued_on' => now()->startOfMonth(),
        'due_on' => now()->startOfMonth()->addDays(10),
        'charge_status_id' => $chargeStatusId,
        'kind' => 'RENT',
        'source' => 'TEST',
    ]);
    $charge->setAttribute('amount_bs_minor_issued', 10000);
    $charge->save();

    $payment = \App\Models\Payment::create([
        'debtor_type' => 'LOCAL', 'debtor_id' => $local->id,
        'company_bank_account_id' => $acc->id,
        'origin_bank_id' => $bank->id,
        'payer_document_type' => 'V', 'payer_document_number' => '11111111',
        'reference' => '000900',
        'amount_bs_minor' => 10000,
        'paid_on' => now()->toDateString(),
        'status' => 'CONFIRMED',
        'method' => 'PMOV',
    ]);

    \App\Models\PaymentAllocation::create([
        'payment_id' => $payment->getKey(),
        'charge_id' => $charge->id,
        'local_id' => $local->id,
        'debtor_type' => 'LOCAL',
        'debtor_id' => $local->id,
        'amount_bs_minor' => 10000,
    ]);

    // Mark payment APPLIED (simulating post-apply)
    $payment->setAttribute('status', 'APPLIED');
    $payment->save();

    return [$payment, $charge];
}

it('public show returns 200 for signed URL with valid QR HMAC', function () {
    seedAndLoginAdminForReceipts();
    [$payment] = makeBasicPaymentForReceipts();

    // Issue summary receipt (APPLIED required)
    /** @var \App\Contracts\Services\ReceiptServiceInterface $svc */
    $svc = app(\App\Contracts\Services\ReceiptServiceInterface::class);
    $receipt = $svc->issue((int) $payment->getKey());

    // Build signed URL with QR HMAC (sig) param
    $payload = [
        'uid' => (string) $receipt->getAttribute('public_token'),
        'num' => (string) $receipt->getAttribute('receipt_number'),
        'at' => (string) ($receipt->getAttribute('issued_at') ?? ''),
    ];
    $rawKey = (string) (config('app.qr_sign_key') ?: config('app.key'));
    if (str_starts_with($rawKey, 'base64:')) {
        $rawKey = base64_decode(substr($rawKey, 7)) ?: '';
    }
    $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', (string) $data, (string) $rawKey, true)), '+/', '-_'), '=');

    $url = URL::signedRoute('receipts.public.show', ['token' => (string) $receipt->getAttribute('public_token'), 'sig' => $sig]);

    $res = $this->get($url);
    $res->assertOk();
});

it('download returns application/pdf when file exists on storage', function () {
    seedAndLoginAdminForReceipts();
    [$payment] = makeBasicPaymentForReceipts();

    // Create a dummy receipt already with pdf_path stored
    $receipt = \App\Models\Receipt::create([
        'payment_id' => (int) $payment->getKey(),
        'market_id' => null,
        'scope' => 'PAYMENT',
        'series_code' => 'UAT-'.date('Y'),
        'number_seq' => 1,
        'receipt_number' => 'UAT-'.date('Y').'-000001',
        'issued_at' => now(),
        'status' => 'ACTIVE',
        'allocations_hash' => hash('sha256', 'x'),
        'public_token' => bin2hex(random_bytes(24)),
    ]);

    $path = 'receipts/'.date('Y').'/'.$receipt->getAttribute('receipt_number').'.pdf';
    Storage::disk('local')->makeDirectory('receipts');
    Storage::disk('local')->makeDirectory('receipts/'.date('Y'));
    Storage::disk('local')->put($path, "%PDF-1.4\n%âãÏÓ\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");

    $receipt->setAttribute('pdf_path', $path);
    $receipt->save();

    $res = $this->get(route('receipts.download', ['receipt' => (int) $receipt->getKey()]));
    $res->assertOk();
    $res->assertHeader('Content-Type', 'application/pdf');
});
