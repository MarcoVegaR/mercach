<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Contracts\Services\FxRateServiceInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalPaymentStoreRequest;
use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\CreditApplication;
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
        // Use current date to find active contracts (not paid_on date)
        // This allows applying old payments to current active contracts
        $referenceDate = Carbon::now();

        $locals = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cc.concessionaire_id', (int) $cid)
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereDate('c.start_date', '<=', $referenceDate->toDateString())
            ->where(function ($q) use ($referenceDate) {
                $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $referenceDate->toDateString());
            })
            ->pluck('l.id')
            ->unique()
            ->values()
            ->all();

        // Build query for BOTH concessionaire charges AND local charges
        $q = Charge::query()->where(function ($query) use ($cid, $locals) {
            // Include concessionaire-level charges
            $query->where(function ($q) use ($cid) {
                $q->where('debtor_type', 'CONCESSIONAIRE')
                    ->where('debtor_id', (int) $cid);
            });
            // Include local-level charges (if any locals found)
            if (! empty($locals)) {
                $query->orWhere(function ($q) use ($locals) {
                    $q->where('debtor_type', 'LOCAL')
                        ->whereIn('debtor_id', $locals);
                });
            }
        });

        try {
            $statusIds = ChargeStatus::query()->whereIn('code', ['ISSUED', 'PARTIAL'])->pluck('id')->filter()->values()->all();
            if (! empty($statusIds)) {
                $q->whereIn('charge_status_id', $statusIds);
            }
        } catch (\Throwable $e) {
        }
        if (! empty($data['currency'])) {
            $q->where('currency', strtoupper((string) $data['currency']));
        }
        if (! empty($data['kind'])) {
            $q->where('kind', strtoupper((string) $data['kind']));
        }
        if (! empty($data['period_from'])) {
            $from = Carbon::createFromFormat('Y-m', (string) $data['period_from'])->startOfMonth()->toDateString();
            $q->whereDate('period', '>=', $from);
        }
        if (! empty($data['period_to'])) {
            $to = Carbon::createFromFormat('Y-m', (string) $data['period_to'])->endOfMonth()->toDateString();
            $q->whereDate('period', '<=', $to);
        }
        if (! empty($data['overdue_only'])) {
            $q->whereDate('due_on', '<', $paidOn->toDateString());
        }

        $charges = $q->orderBy('period')->limit(500)->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'period', 'due_on', 'local_id', 'kind', 'debtor_id', 'debtor_type']);

        $ids = $charges->pluck('id')->all();
        $allocByCharge = PaymentAllocation::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_bs_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
        $creditByCharge = CreditApplication::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');

        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);
        $items = [];
        foreach ($charges as $c) {
            $currency = (string) ($c->getAttribute('currency') ?? '');
            $amountMinor = (int) $c->getAttribute('amount_minor');
            $amountBsMinorIssued = $c->getAttribute('amount_bs_minor_issued');
            $amountBsMinor = is_numeric($amountBsMinorIssued) ? (int) $amountBsMinorIssued : null;
            if ($amountBsMinor === null) {
                $rate = $fx->resolveAt($currency, $paidOn);
                $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                $amountBsMinor = $rateToVes !== null ? (int) round(($amountMinor / 100.0) * $rateToVes * 100) : null;
            }
            $allocated = (int) ($allocByCharge[(int) $c->getAttribute('id')] ?? 0);
            $credited = (int) ($creditByCharge[(int) $c->getAttribute('id')] ?? 0);
            $outstanding = $amountBsMinor !== null ? max(0, $amountBsMinor - $allocated - $credited) : 0;
            $outstandingOriginal = $amountMinor;
            if (($allocated > 0 || $credited > 0) && $amountBsMinor > 0) {
                $outstandingOriginal = (int) round($amountMinor * ($outstanding / $amountBsMinor));
            }

            $items[] = [
                'charge_id' => (int) $c->getAttribute('id'),
                'period' => (string) ($c->getAttribute('period') ?? ''),
                'due_on' => (string) ($c->getAttribute('due_on') ?? ''),
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'amount_bs_minor' => $amountBsMinor,
                'outstanding_minor' => $outstandingOriginal,
                'outstanding_bs_minor' => $outstanding,
                'kind' => (string) ($c->getAttribute('kind') ?? ''),
            ];
        }

        return response()->json(['items' => $items]);
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

        $paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));
        $amountPayment = (int) $payment->getAttribute('amount_bs_minor');
        $currentAssigned = (int) PaymentAllocation::query()->where('payment_id', $payment->getKey())->sum('amount_bs_minor');
        $available = max(0, $amountPayment - $currentAssigned);
        $useCredit = (bool) ($data['use_credit'] ?? false);
        $creditAvailable = 0;
        if ($useCredit) {
            $creditAvailable = (int) CustomerCredit::query()
                ->where('debtor_type', 'CONCESSIONAIRE')
                ->where('debtor_id', (int) $cid)
                ->where('status', 'OPEN')
                ->sum('balance_minor');
        }

        /** @var array<int, array{charge_id:int, amount_bs_minor:int}> $itemsData */
        $itemsData = $data['items'];
        $byChargeRequested = collect($itemsData)->keyBy('charge_id');

        $charges = Charge::query()->whereIn('id', $byChargeRequested->keys())->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued']);
        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);
        $allocByCharge = PaymentAllocation::query()->whereIn('charge_id', $byChargeRequested->keys())->selectRaw('charge_id, SUM(amount_bs_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
        $creditByCharge = CreditApplication::query()->whereIn('charge_id', $byChargeRequested->keys())->selectRaw('charge_id, SUM(amount_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');

        $errors = [];
        $totalRequested = 0;
        $itemsResp = [];
        foreach ($charges as $c) {
            $cidCharge = (int) $c->getAttribute('id');
            $req = (int) ($byChargeRequested[$cidCharge]['amount_bs_minor'] ?? 0);
            $totalRequested += $req;
            $currency = (string) $c->getAttribute('currency');
            $amountMinor = (int) $c->getAttribute('amount_minor');
            $amountBsMinorIssued = $c->getAttribute('amount_bs_minor_issued');
            $amountBsMinor = is_numeric($amountBsMinorIssued) ? (int) $amountBsMinorIssued : null;
            if ($amountBsMinor === null) {
                $rate = $fx->resolveAt($currency, $paidOn);
                $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                $amountBsMinor = $rateToVes !== null ? (int) round(($amountMinor / 100.0) * $rateToVes * 100) : null;
            }
            $allocated = (int) ($allocByCharge[$cidCharge] ?? 0);
            $credited = (int) ($creditByCharge[$cidCharge] ?? 0);
            $outstanding = $amountBsMinor !== null ? max(0, $amountBsMinor - $allocated - $credited) : 0;

            $valid = $req <= $outstanding;
            $msg = $valid ? null : 'Monto supera saldo (Bs).';
            if (! $valid) {
                $errors[] = "Charge {$cidCharge}: monto supera saldo (Bs).";
            }

            $itemsResp[] = [
                'charge_id' => $cidCharge,
                'requested' => $req,
                'outstanding' => $outstanding,
                'valid' => $valid,
                'message' => $msg,
            ];
        }
        $limit = $available + ($useCredit ? $creditAvailable : 0);
        if ($totalRequested > $limit) {
            $errors[] = 'Total a aplicar supera el disponible (pago + crédito a favor).';
        }

        return response()->json([
            'ok' => empty($errors),
            'errors' => $errors,
            'available_bs_minor' => $available,
            'requested_bs_minor' => $totalRequested,
            'summary' => [
                'available_bs_minor' => $available,
                'credit_available_bs_minor' => $creditAvailable,
                'requested_bs_minor' => $totalRequested,
                'after_available_bs_minor' => max(0, $available - $totalRequested),
                'after_total_available_bs_minor' => max(0, ($available + $creditAvailable) - $totalRequested),
            ],
            'items' => $itemsResp,
        ]);
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
        $strategy = $data['strategy'] ?? 'fifo';

        $paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));
        $amountPayment = (int) $payment->getAttribute('amount_bs_minor');
        $currentAssigned = (int) PaymentAllocation::query()->where('payment_id', $payment->getKey())->sum('amount_bs_minor');
        $available = max(0, $amountPayment - $currentAssigned);
        if ($available === 0) {
            return response()->json(['items' => [], 'summary' => ['available_bs_minor' => 0, 'suggested_bs_minor' => 0, 'after_available_bs_minor' => 0]]);
        }

        $locals = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cc.concessionaire_id', (int) $cid)
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereDate('c.start_date', '<=', $paidOn->toDateString())
            ->where(function ($q) use ($paidOn) {
                $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $paidOn->toDateString());
            })
            ->pluck('l.id')
            ->unique()
            ->values()
            ->all();

        $q = Charge::query()->where(function ($query) use ($cid, $locals) {
            // Include concessionaire-level charges
            $query->where(function ($q) use ($cid) {
                $q->where('debtor_type', 'CONCESSIONAIRE')
                    ->where('debtor_id', (int) $cid);
            });
            // Include local-level charges (if any locals found)
            if (! empty($locals)) {
                $query->orWhere(function ($q) use ($locals) {
                    $q->where('debtor_type', 'LOCAL')
                        ->whereIn('debtor_id', $locals);
                });
            }
        });
        try {
            $ids = ChargeStatus::query()->whereIn('code', ['ISSUED', 'PARTIAL'])->pluck('id')->filter()->values()->all();
            if (! empty($ids)) {
                $q->whereIn('charge_status_id', $ids);
            }
        } catch (\Throwable $e) {
        }
        if (! empty($data['currency'])) {
            $q->where('currency', strtoupper((string) $data['currency']));
        }
        if (! empty($data['kind'])) {
            $q->where('kind', strtoupper((string) $data['kind']));
        }
        if (! empty($data['period_from'])) {
            $from = Carbon::createFromFormat('Y-m', (string) $data['period_from'])->startOfMonth()->toDateString();
            $q->whereDate('period', '>=', $from);
        }
        if (! empty($data['period_to'])) {
            $to = Carbon::createFromFormat('Y-m', (string) $data['period_to'])->endOfMonth()->toDateString();
            $q->whereDate('period', '<=', $to);
        }
        if (! empty($data['overdue_only'])) {
            $q->whereDate('due_on', '<', $paidOn->toDateString());
        }

        $charges = $q->orderBy('period')->limit(500)->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'period', 'due_on']);
        $ids = $charges->pluck('id')->all();
        $allocByCharge = PaymentAllocation::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_bs_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
        $creditByCharge = CreditApplication::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);

        $rows = [];
        foreach ($charges as $c) {
            $currency = (string) ($c->getAttribute('currency') ?? '');
            $amountMinor = (int) $c->getAttribute('amount_minor');
            $amountBsMinorIssued = $c->getAttribute('amount_bs_minor_issued');
            $amountBsMinor = is_numeric($amountBsMinorIssued) ? (int) $amountBsMinorIssued : null;
            if ($amountBsMinor === null) {
                $rate = $fx->resolveAt($currency, $paidOn);
                $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                $amountBsMinor = $rateToVes !== null ? (int) round(($amountMinor / 100.0) * $rateToVes * 100) : null;
            }
            $allocated = (int) ($allocByCharge[(int) $c->getAttribute('id')] ?? 0);
            $credited = (int) ($creditByCharge[(int) $c->getAttribute('id')] ?? 0);
            $outstanding = $amountBsMinor !== null ? max(0, $amountBsMinor - $allocated - $credited) : 0;
            $rows[] = ['charge_id' => (int) $c->getAttribute('id'), 'outstanding' => $outstanding, 'due_on' => (string) ($c->getAttribute('due_on') ?? ''), 'period' => (string) $c->getAttribute('period')];
        }

        $items = [];
        if ($strategy === 'fifo') {
            usort($rows, fn ($a, $b) => strcmp((string) $a['due_on'], (string) $b['due_on']));
            $remaining = $available;
            foreach ($rows as $r) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min($remaining, (int) $r['outstanding']);
                if ($take > 0) {
                    $items[] = ['charge_id' => (int) $r['charge_id'], 'amount_bs_minor' => $take];
                    $remaining -= $take;
                }
            }
        } else {
            $remaining = $available;
            $totalOut = array_reduce($rows, fn ($a, $r) => $a + (int) $r['outstanding'], 0);
            if ($totalOut > 0) {
                foreach ($rows as $r) {
                    $out = (int) $r['outstanding'];
                    if ($out <= 0) {
                        continue;
                    }
                    $share = (int) floor(($out / $totalOut) * $remaining);
                    if ($share > 0) {
                        $items[] = ['charge_id' => (int) $r['charge_id'], 'amount_bs_minor' => min($share, $out)];
                    }
                }
                $assigned = array_reduce($items, fn ($a, $it) => $a + (int) $it['amount_bs_minor'], 0);
                $residual = max(0, $remaining - $assigned);
                if ($residual > 0) {
                    foreach ($rows as $r) {
                        if ($residual <= 0) {
                            break;
                        }
                        $cidRow = (int) $r['charge_id'];
                        $curr = 0;
                        foreach ($items as &$it) {
                            if ((int) $it['charge_id'] === $cidRow) {
                                $curr = (int) $it['amount_bs_minor'];
                                break;
                            }
                        }
                        if ($curr < (int) $r['outstanding']) {
                            $items[] = ['charge_id' => $cidRow, 'amount_bs_minor' => $curr + 1];
                            $residual--;
                        }
                    }
                }
            }
        }

        $suggested = array_reduce($items, fn ($a, $it) => $a + (int) $it['amount_bs_minor'], 0);

        return response()->json([
            'items' => $items,
            'summary' => [
                'available_bs_minor' => $available,
                'suggested_bs_minor' => $suggested,
                'after_available_bs_minor' => max(0, $available - $suggested),
            ],
        ]);
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
