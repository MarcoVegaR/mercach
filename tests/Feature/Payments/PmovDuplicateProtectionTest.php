<?php

declare(strict_types=1);

use App\Contracts\Services\PaymentServiceInterface;
use App\Exceptions\DomainActionException;
use App\Http\Requests\PaymentStoreRequest;
use App\Http\Requests\PaymentUpdateRequest;
use App\Http\Requests\Portal\PortalPaymentStoreRequest;
use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->seed([Database\Seeders\PaymentStatusesSeeder::class, Database\Seeders\PaymentTypesSeeder::class]);
    $bank = Bank::create(['code' => 'PMOVTEST', 'bank_code' => '156', 'name' => 'Banco Test', 'is_active' => true]);
    $account = CompanyBankAccount::create([
        'bank_id' => $bank->id, 'account_number' => '01560030680000776369', 'phone_number' => '584242424564',
        'account_holder_name' => 'Cuenta Test', 'document_type' => 'J', 'document_number' => '123456789',
        'is_active' => true, 'allow_pmov' => true,
    ]);
    $this->payload = [
        'debtor_type' => 'CONCESSIONAIRE', 'debtor_id' => 1, 'company_bank_account_id' => $account->id,
        'method' => 'PMOV', 'origin_bank_id' => $bank->id, 'payer_document_type' => 'V',
        'payer_document_number' => '12345678', 'payer_phone_e164' => '584121234567',
        'payer_phone_area_code' => '0412', 'payer_phone_number' => '1234567',
        'reference' => '475690', 'amount_bs_minor' => 764442, 'paid_on' => now()->toDateString(),
    ];
    config([
        'services.bank_gateway.scheme' => 'https', 'services.bank_gateway.host' => 'bank.test',
        'services.bank_gateway.path' => '/ValTrxIn', 'services.bank_gateway.key' => 'test-key',
        'services.bank_gateway.secret' => base64_encode('test-secret'),
        'services.bank_gateway.merchant_id' => '328770', 'services.bank_gateway.terminal_id' => 'test-terminal',
    ]);
    Http::fake(fn () => Http::response(['sRespCode' => '00', 'sRespDesc' => 'Aprobada'], 200));
    $this->service = app(PaymentServiceInterface::class);
});

it('requires exactly six PMOV digits in both creation requests', function (string $requestClass, string $reference, bool $valid) {
    $request = $requestClass::create('/payments', 'POST', [...$this->payload, 'reference' => $reference]);
    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);
    expect($validator->errors()->has('reference'))->toBe(! $valid);
})->with(['admin' => [PaymentStoreRequest::class], 'portal' => [PortalPaymentStoreRequest::class]])
    ->with(['short' => ['12345', false], 'six' => ['001234', true], 'seven' => ['1234567', false], 'eight' => ['27475690', false]]);

it('rejects invalid PMOV references for internal callers without contacting the bank', function (string $reference) {
    expect(fn () => $this->service->createAndVerify([...$this->payload, 'reference' => $reference], ['ip' => '127.0.0.1']))
        ->toThrow(DomainActionException::class, 'exactamente 6');
    expect(Payment::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with(['12345', '1234567', '27475690', "123456\n"]);

it('blocks an active historical PMOV despite changed debtor document or explicit key', function () {
    $old = Payment::create([...$this->payload, 'reference' => '27475690', 'status' => 'CONFIRMED', 'idempotency_key' => hash('sha256', 'legacy')]);
    expect(fn () => $this->service->createAndVerify([
        ...$this->payload, 'debtor_id' => 2, 'payer_document_number' => '87654321', 'idempotency_key' => hash('sha256', 'explicit'),
    ], ['ip' => '127.0.0.1']))->toThrow(DomainActionException::class, 'pago #'.$old->id);
    Http::assertNothingSent();
    expect(Payment::query()->count())->toBe(1)->and($old->fresh()->reference)->toBe('27475690');
});

it('rejects long PMOV input and historical duplicates through the admin endpoint', function () {
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class]);
    $this->actingAs(\App\Models\User::query()->where('email', 'test@mailinator.com')->firstOrFail());
    $old = Payment::create([...$this->payload, 'reference' => '27475690', 'status' => 'CONFIRMED']);

    $this->post(route('payments.store'), [...$this->payload, 'reference' => '27475690'])
        ->assertSessionHasErrors('reference');
    $this->post(route('payments.store'), $this->payload)
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'pago #'.$old->id));

    expect(Payment::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('normalizes local payer phones when comparing historical payments', function () {
    $old = Payment::create([...$this->payload, 'reference' => '27475690', 'payer_phone_e164' => '04121234567', 'status' => 'REGISTERED']);
    expect(fn () => $this->service->createAndVerify($this->payload, ['ip' => '127.0.0.1']))
        ->toThrow(DomainActionException::class, 'pago #'.$old->id);
    Http::assertNothingSent();
});

it('blocks historical PMOV duplicates when the origin bank was not supplied', function () {
    $payload = [...$this->payload, 'origin_bank_id' => null];
    $old = Payment::create([...$payload, 'reference' => '27475690', 'status' => 'CONFIRMED']);
    expect(fn () => $this->service->createAndVerify($this->payload, ['ip' => '127.0.0.1']))
        ->toThrow(DomainActionException::class, 'pago #'.$old->id);
    Http::assertNothingSent();
});

it('requires an origin bank for PMOV in every request', function (string $requestClass) {
    $request = $requestClass::create('/payments', 'POST', [...$this->payload, 'origin_bank_id' => null]);
    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->errors()->has('origin_bank_id'))->toBeTrue();
})->with([PaymentStoreRequest::class, PaymentUpdateRequest::class, PortalPaymentStoreRequest::class]);

it('rejects a bankless PMOV from an internal caller without querying the gateway', function () {
    expect(fn () => $this->service->createAndVerify([...$this->payload, 'origin_bank_id' => null], ['ip' => '127.0.0.1']))
        ->toThrow(DomainActionException::class, 'Selecciona el banco de origen');

    expect(Payment::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('keeps the PMOV origin bank in the validated update payload', function () {
    $request = PaymentUpdateRequest::create('/payments', 'PUT', $this->payload);
    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->validated()['origin_bank_id'])->toBe($this->payload['origin_bank_id']);
});

it('allows identical references and amounts in different origin banks', function (bool $legacyBankless) {
    $first = Payment::create([
        ...$this->payload, 'origin_bank_id' => $legacyBankless ? null : $this->payload['origin_bank_id'],
        'reference' => '27475690', 'status' => 'CONFIRMED',
    ]);
    $otherBank = Bank::create(['code' => 'PMOVOTHER', 'bank_code' => '105', 'name' => 'Otro banco', 'is_active' => true]);
    $payload = [...$this->payload, 'origin_bank_id' => $otherBank->id];

    $result = $this->service->createAndVerify($payload);

    expect($result['status'])->toBe('CONFIRMED')
        ->and($result['id'])->not->toBe($first->id)
        ->and(Payment::query()->count())->toBe(2);
    Http::assertSentCount(1);
    expect(fn () => $this->service->createAndVerify($payload, ['ip' => '127.0.0.1']))->toThrow(DomainActionException::class, 'pago #'.$result['id']);
    Http::assertSentCount(1);
})->with([false, true]);

it('blocks manual verification of a bankless historical duplicate in its effective bank', function () {
    $confirmed = Payment::create([...$this->payload, 'status' => 'CONFIRMED']);
    $pending = Payment::create([...$this->payload, 'origin_bank_id' => null, 'reference' => '27475690', 'status' => 'REGISTERED']);

    expect(fn () => $this->service->verify($pending->id))->toThrow(DomainActionException::class, 'pago #'.$confirmed->id);
    Http::assertNothingSent();
});

it('protects FK-only historical PMOV payments and service inputs', function () {
    $old = Payment::create([...$this->payload, 'reference' => '27475690', 'status' => 'CONFIRMED']);
    Payment::query()->whereKey($old->id)->update(['method' => null]);
    $input = $this->payload;
    unset($input['method']);
    $input['payment_type_id'] = $old->payment_type_id;

    expect(fn () => $this->service->createAndVerify($input, ['ip' => '127.0.0.1']))
        ->toThrow(DomainActionException::class, 'pago #'.$old->id);

    $pending = Payment::create([...$this->payload, 'status' => 'REGISTERED']);
    Payment::query()->whereKey($pending->id)->update(['method' => null]);
    expect(fn () => $this->service->verify($pending->id))->toThrow(DomainActionException::class, 'pago #'.$old->id);
    Http::assertNothingSent();
});

it('allows recreation after void or deletion but blocks the next active duplicate', function (string $inactive) {
    $original = $this->service->createAndVerify($this->payload);
    $old = Payment::query()->findOrFail($original['id']);
    if ($inactive === 'deleted') {
        $old->delete();
    } else {
        $old->status = 'VOID';
        $old->voided_at = $inactive === 'status_only' ? null : now();
        $old->save();
    }
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(fn () => Http::response(['sRespCode' => '831', 'sRespDesc' => 'Transaccion ya fue validada'], 200));
    $replacement = $this->service->createAndVerify($this->payload);
    expect($replacement['status'])->toBe('CONFIRMED')->and($replacement['gateway_resp_code'])->toBe('831');
    Http::assertSentCount(1);
    expect(fn () => $this->service->createAndVerify($this->payload, ['ip' => '127.0.0.1']))
        ->toThrow(DomainActionException::class, 'pago #'.$replacement['id']);
    Http::assertSentCount(1);
    expect(Payment::withTrashed()->count())->toBe(2);
})->with(['voided', 'deleted', 'status_only']);

it('allows distinct operations sharing the last six digits', function (array $change) {
    Payment::create([...$this->payload, 'reference' => '27475690', 'status' => 'CONFIRMED']);
    $result = $this->service->createAndVerify([...$this->payload, ...$change]);
    expect($result['status'])->toBe('CONFIRMED');
    Http::assertSentCount(1);
})->with([
    'amount' => [['amount_bs_minor' => 764443]],
    'date' => [['paid_on' => '2026-01-01']],
    'phone' => [['payer_phone_e164' => '584129999999']],
]);

it('checks duplicates before manually verifying a historical registered payment', function () {
    Payment::create([...$this->payload, 'status' => 'CONFIRMED']);
    $pending = Payment::create([...$this->payload, 'reference' => '27475690', 'status' => 'REGISTERED']);
    expect(fn () => $this->service->verify($pending->id))->toThrow(DomainActionException::class, 'ya esta registrada');
    Http::assertNothingSent();
    expect($pending->fresh()->status)->toBe('REGISTERED');
});

it('sends the last six historical PMOV digits without rewriting the stored reference', function () {
    $pending = Payment::create([...$this->payload, 'reference' => '27001234', 'status' => 'REGISTERED']);
    $result = $this->service->verify($pending->id);
    Http::assertSent(fn ($request) => $request['sReferenceNo'] === '001234');
    expect($result['status'])->toBe('CONFIRMED')->and($pending->fresh()->reference)->toBe('27001234');
});

it('rebooks a voided historical PMOV with six digits and keeps 831 accepted', function () {
    $old = Payment::create([...$this->payload, 'reference' => '27475690', 'status' => 'APPLIED']);
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(fn () => Http::response(['sRespCode' => '831', 'sRespDesc' => 'Transaccion ya fue validada'], 200));

    $result = $this->service->voidRebook($old->id, ['paid_on' => $this->payload['paid_on'], 'reason' => 'Correccion']);

    expect($result['new']['reference'])->toBe('475690')
        ->and($result['new']['status'])->toBe('CONFIRMED')
        ->and($result['new']['gateway_resp_code'])->toBe('831')
        ->and($old->fresh()->status)->toBe('VOID')
        ->and($old->fresh()->reference)->toBe('27475690');
    Http::assertSentCount(1);
});

it('does not let a rebook key bypass another active PMOV and rolls back the void', function () {
    Payment::create([...$this->payload, 'status' => 'CONFIRMED']);
    $old = Payment::create([...$this->payload, 'reference' => '27475690', 'status' => 'APPLIED']);

    expect(fn () => $this->service->voidRebook($old->id, ['paid_on' => $this->payload['paid_on']]))
        ->toThrow(DomainActionException::class, 'ya esta registrada');

    expect($old->fresh()->status)->toBe('APPLIED');
    Http::assertNothingSent();
});

it('preserves leading zeros in the PMOV idempotency fingerprint', function () {
    $payload = [...$this->payload, 'reference' => '001234'];
    $result = $this->service->createAndVerify($payload);
    $expected = hash('sha256', json_encode([
        'm' => 'PMOV', 'c' => $payload['company_bank_account_id'], 'o' => $payload['origin_bank_id'],
        'p' => $payload['payer_phone_e164'], 'r' => '001234', 'a' => $payload['amount_bs_minor'],
        'd' => $payload['paid_on'], 't' => '300',
    ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

    expect(Payment::query()->findOrFail($result['id'])->idempotency_key)->toBe($expected);
    Http::assertSent(fn ($request) => $request['sReferenceNo'] === '001234');
});

it('refuses short historical PMOV references without contacting the bank', function () {
    $pending = Payment::create([...$this->payload, 'reference' => '12345', 'status' => 'REGISTERED']);
    $result = $this->service->verify($pending->id);
    expect($result['status'])->toBe('REGISTERED')->and($result['gateway_resp_code'])->toBe('REF_INV');
    Http::assertNothingSent();
});

it('preserves unchanged historical PMOV references on update only', function (string $reference, string $originalMethod, bool $valid) {
    $payment = Payment::create([...$this->payload, 'reference' => '27475690', 'method' => $originalMethod, 'status' => 'CONFIRMED']);
    $request = PaymentUpdateRequest::create('/payments/'.$payment->id, 'PUT', [...$this->payload, 'reference' => $reference]);
    $route = new Route('PUT', 'payments/{payment}', []);
    $route->bind(Request::create('/payments/'.$payment->id, 'PUT'));
    $route->setParameter('payment', $payment);
    $request->setRouteResolver(fn () => $route);
    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);
    expect($validator->errors()->has('reference'))->toBe(! $valid);
})->with([
    ['27475690', 'PMOV', true], ['28475690', 'PMOV', false], ['27475690', 'TRF', false], ['475690', 'PMOV', true],
]);

it('holds the operation lock during verification without locking unrelated operations', function () {
    $connection = (new Payment)->getConnection();
    if ($connection->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL advisory lock test.');
    }
    config(['database.connections.pmov_lock_test' => config('database.connections.'.$connection->getName())]);
    $other = DB::connection('pmov_lock_test');
    $identity = [
        'm' => 'PMOV', 'c' => $this->payload['company_bank_account_id'], 'o' => $this->payload['origin_bank_id'],
        'p' => $this->payload['payer_phone_e164'], 'r' => '475690', 'a' => 764442, 'd' => $this->payload['paid_on'], 't' => '300',
    ];
    $key = 'payment:pmov:'.json_encode($identity, JSON_THROW_ON_ERROR);
    try {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(function () use ($other, $key) {
            $other->transaction(function () use ($other, $key) {
                expect($other->selectOne('SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0)) AS acquired', [$key])->acquired)->toBeFalse();
                expect($other->selectOne('SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0)) AS acquired', [$key.':other'])->acquired)->toBeTrue();
            });

            return Http::response(['sRespCode' => '00', 'sRespDesc' => 'Aprobada'], 200);
        });
        $result = $this->service->createAndVerify($this->payload);
        expect($result['status'])->toBe('CONFIRMED');
        Http::assertSentCount(1);
    } finally {
        DB::purge('pmov_lock_test');
    }
});
