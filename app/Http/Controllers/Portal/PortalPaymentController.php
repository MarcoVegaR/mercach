<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Contracts\Services\FxRateServiceInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalPaymentStoreRequest;
use App\Models\CustomerCredit;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PortalPaymentController extends Controller
{
    public function __construct(private PaymentServiceInterface $payments) {}

    public function create(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $cid = $user?->concessionaires()->pluck('concessionaires.id')->first();

        // Options: company accounts, banks
        $accounts = DB::table('company_bank_accounts as a')
            ->leftJoin('banks as b', 'b.id', '=', 'a.bank_id')
            ->orderBy('a.id')
            ->get(['a.id', 'a.account_number', 'b.name as bank_name'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => trim(($row->bank_name ? ($row->bank_name.' • ') : '').(string) $row->account_number),
            ])->all();
        $banks = DB::table('banks')->orderBy('name')->get(['id', 'name'])->map(fn ($b) => ['id' => (int) $b->id, 'name' => (string) $b->name])->all();
        $phoneAreaCodes = DB::table('phone_area_codes')->where('is_active', true)->orderBy('code')->get(['id', 'code'])->map(fn ($p) => ['id' => (int) $p->id, 'code' => (string) $p->code])->all();

        // Defaults from concessionaire
        $defaults = [
            'payer_document_type' => '',
            'payer_document_number' => '',
        ];
        if ($cid) {
            $c = DB::table('concessionaires as c')
                ->leftJoin('document_types as dt', 'dt.id', '=', 'c.document_type_id')
                ->where('c.id', $cid)
                ->first(['dt.code as doc_code', 'c.document_number']);
            if ($c) {
                $defaults['payer_document_type'] = (string) ($c->doc_code ?? '');
                $defaults['payer_document_number'] = (string) ($c->document_number ?? '');
            }
        }

        return Inertia::render('portal/payments/create-modern', [
            'options' => [
                'companyBankAccounts' => $accounts,
                'banks' => $banks,
                'phoneAreaCodes' => $phoneAreaCodes,
                // Solo Transfer y Pago Móvil permitidos en portal (verificación automática)
            ],
            'defaults' => $defaults,
        ]);
    }

    public function store(PortalPaymentStoreRequest $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $cid = $user?->concessionaires()->pluck('concessionaires.id')->first();
        if (! $cid) {
            abort(403);
        }

        $data = $request->validated();
        // Scope debtor to current concessionaire
        $payload = array_merge($data, [
            'debtor_type' => 'CONCESSIONAIRE',
            'debtor_id' => (int) $cid,
        ]);

        // Map method to payment_type_id (best effort)
        if (! empty($payload['method']) && empty($payload['payment_type_id'])) {
            try {
                $pt = DB::table('payment_types')->where('code', strtoupper((string) $payload['method']))->first(['id']);
                if ($pt) {
                    $payload['payment_type_id'] = (int) $pt->id;
                }
            } catch (\Throwable $e) {
            }
        }

        // Create and attempt verification via service
        try {
            $row = $this->payments->createAndVerify($payload, [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);

            return redirect()->route('portal.payments.apply', ['payment' => $row['id']])
                ->with('success', 'Pago registrado correctamente. Ahora puedes aplicarlo a tus deudas.');
        } catch (\App\Exceptions\DomainActionException $e) {
            // Validation failed - show user-friendly error
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Unexpected error
            \Log::error('Payment registration failed', [
                'message' => $e->getMessage(),
                'user_id' => (int) $user->id,
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Ocurrió un error al procesar el pago. Por favor intenta nuevamente o contacta soporte.');
        }
    }

    public function resolveFx(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'currency' => ['required', 'string', 'size:3'],
            'paid_on' => ['required', 'date'],
        ]);

        $paidOn = \Illuminate\Support\Carbon::parse((string) $request->string('paid_on'));
        $currency = strtoupper((string) $request->string('currency'));

        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);
        $rate = $fx->resolveAt($currency, $paidOn);

        return response()->json([
            'fx_rate_id' => $rate?->getAttribute('id'),
            'rate_to_ves' => $rate?->getAttribute('rate_to_ves'),
        ]);
    }

    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $cid = $user?->concessionaires()->pluck('concessionaires.id')->first();
        abort_if(! $cid, 403);

        $rows = Payment::query()
            ->where('debtor_type', 'CONCESSIONAIRE')
            ->where('debtor_id', (int) $cid)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'amount_bs_minor', 'paid_on', 'payment_status_id', 'method', 'reference', 'origin_bank_id', 'payer_phone_e164', 'payer_account_number']);

        $ids = $rows->pluck('id')->all();
        $appliedByPayment = empty($ids)
            ? collect()
            : PaymentAllocation::query()->whereIn('payment_id', $ids)
                ->selectRaw('payment_id, SUM(amount_bs_minor) as s')
                ->groupBy('payment_id')->pluck('s', 'payment_id');

        $bankIds = $rows->pluck('origin_bank_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
        $bankMap = empty($bankIds) ? collect() : DB::table('banks')->whereIn('id', $bankIds)->pluck('name', 'id');

        $items = $rows->map(function ($p) use ($appliedByPayment, $bankMap) {
            $amount = (int) $p->getAttribute('amount_bs_minor');
            $applied = (int) ($appliedByPayment[(int) $p->getKey()] ?? 0);
            $available = max(0, $amount - $applied);

            return [
                'id' => (int) $p->getKey(),
                'paid_on' => (string) ($p->getAttribute('paid_on') ?? ''),
                'amount_bs_minor' => $amount,
                'applied_bs_minor' => $applied,
                'available_bs_minor' => $available,
                'method' => (string) ($p->getAttribute('method') ?? ''),
                'status' => (string) ($p->getAttribute('status') ?? ''),
                'reference' => (string) ($p->getAttribute('reference') ?? ''),
                'origin_bank_name' => (string) ($bankMap[(int) ($p->getAttribute('origin_bank_id') ?? 0)] ?? ''),
                'payer_phone_e164' => (string) ($p->getAttribute('payer_phone_e164') ?? ''),
                'payer_account_number' => (string) ($p->getAttribute('payer_account_number') ?? ''),
            ];
        })->all();

        return Inertia::render('portal/payments/index-modern', [
            'items' => $items,
        ]);
    }

    public function applyPage(Request $request, Payment $payment): \Inertia\Response
    {
        $user = $request->user();
        $cid = $user?->concessionaires()->pluck('concessionaires.id')->first();
        abort_if(! $cid, 403);
        abort_unless((string) $payment->getAttribute('debtor_type') === 'CONCESSIONAIRE' && (int) $payment->getAttribute('debtor_id') === (int) $cid, 404);

        $creditSum = (int) CustomerCredit::query()
            ->where('debtor_type', 'CONCESSIONAIRE')
            ->where('debtor_id', (int) $cid)
            ->where('status', 'OPEN')
            ->sum('balance_minor');

        $amount = (int) $payment->getAttribute('amount_bs_minor');
        $applied = (int) PaymentAllocation::query()->where('payment_id', (int) $payment->getKey())->sum('amount_bs_minor');
        $available = max(0, $amount - $applied);

        return Inertia::render('portal/payments/apply-modern', [
            'payment' => [
                'id' => (int) $payment->getKey(),
                'status' => (string) ($payment->getAttribute('status') ?? ''),
                'paid_on' => (string) ($payment->getAttribute('paid_on') ?? ''),
                'amount_bs_minor' => $amount,
                'applied_bs_minor' => $applied,
                'available_bs_minor' => $available,
            ],
            'customer_credit_bs_minor' => $creditSum,
        ]);
    }

    public function openCharges(Request $request, Payment $payment): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $cid = $user?->concessionaires()->pluck('concessionaires.id')->first();
        abort_if(! $cid, 403);
        abort_unless((string) $payment->getAttribute('debtor_type') === 'CONCESSIONAIRE' && (int) $payment->getAttribute('debtor_id') === (int) $cid, 404);

        $data = $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'kind' => ['nullable', 'string', 'max:20'],
            'period_from' => ['nullable', 'date_format:Y-m'],
            'period_to' => ['nullable', 'date_format:Y-m'],
            'overdue_only' => ['nullable', 'boolean'],
        ]);

        $paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));

        /** @var \App\Services\Payments\OpenChargesQuery $query */
        $query = app(\App\Services\Payments\OpenChargesQuery::class);

        $result = $query
            ->forDebtor('CONCESSIONAIRE', (int) $cid)
            ->atDate($paidOn)
            ->filterCurrency($data['currency'] ?? null)
            ->filterKind($data['kind'] ?? null)
            ->filterPeriod($data['period_from'] ?? null, $data['period_to'] ?? null)
            ->overdueOnly((bool) ($data['overdue_only'] ?? false))
            ->execute();

        return response()->json($result);
    }

    public function previewAllocations(Request $request, Payment $payment): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $cid = $user?->concessionaires()->pluck('concessionaires.id')->first();
        abort_if(! $cid, 403);
        abort_unless((string) $payment->getAttribute('debtor_type') === 'CONCESSIONAIRE' && (int) $payment->getAttribute('debtor_id') === (int) $cid, 404);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.charge_id' => ['required', 'integer'],
            'items.*.amount_bs_minor' => ['required', 'integer', 'min:0'],
            'use_credit' => ['nullable', 'boolean'],
        ]);

        $result = $this->payments->previewAllocations(
            $payment->getKey(),
            $data['items'],
            ['use_credit' => (bool) ($data['use_credit'] ?? false)]
        );

        return response()->json($result);
    }

    public function suggestAllocations(Request $request, Payment $payment): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $cid = $user?->concessionaires()->pluck('concessionaires.id')->first();
        abort_if(! $cid, 403);
        abort_unless((string) $payment->getAttribute('debtor_type') === 'CONCESSIONAIRE' && (int) $payment->getAttribute('debtor_id') === (int) $cid, 404);

        $data = $request->validate([
            'strategy' => ['nullable', 'string', 'in:fifo,proportional'],
            'currency' => ['nullable', 'string', 'size:3'],
            'kind' => ['nullable', 'string', 'max:20'],
            'period_from' => ['nullable', 'date_format:Y-m'],
            'period_to' => ['nullable', 'date_format:Y-m'],
            'overdue_only' => ['nullable', 'boolean'],
        ]);

        $result = $this->payments->suggestAllocations($payment->getKey(), [
            'strategy' => $data['strategy'] ?? 'fifo',
            'currency' => $data['currency'] ?? null,
            'kind' => $data['kind'] ?? null,
            'period_from' => $data['period_from'] ?? null,
            'period_to' => $data['period_to'] ?? null,
            'overdue_only' => (bool) ($data['overdue_only'] ?? false),
        ]);

        return response()->json($result);
    }

    public function storeAllocations(Request $request, Payment $payment): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $cid = $user?->concessionaires()->pluck('concessionaires.id')->first();
        abort_if(! $cid, 403);
        abort_unless((string) $payment->getAttribute('debtor_type') === 'CONCESSIONAIRE' && (int) $payment->getAttribute('debtor_id') === (int) $cid, 404);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.charge_id' => ['required', 'integer'],
            'items.*.amount_bs_minor' => ['required', 'integer', 'min:0'],
            'use_credit' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->payments->storeAllocations(
                $payment->getKey(),
                $data['items'],
                [
                    'use_credit' => (bool) ($data['use_credit'] ?? false),
                    'idempotency_key' => (string) ($request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key') ?? ($data['idempotency_key'] ?? '')),
                ]
            );
        } catch (\App\Exceptions\DomainActionException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'errors' => [$e->getMessage()]], 422);
            }

            return redirect()->route('portal.payments.apply', ['payment' => $payment->getKey()])
                ->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('portal.receipts')
            ->with('success', 'Pago aplicado correctamente. Ahora puedes descargar tu recibo.');
    }
}
