<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Contracts\Services\EconomicProfileServiceInterface;
use App\Contracts\Services\FxRateServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentStatus;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function __construct(
        private EconomicProfileServiceInterface $economic,
        private FxRateServiceInterface $fx
    ) {}

    private ?int $voidStatusIdCached = null;

    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        abort_if(! (bool) ($user->is_active ?? true), 403);

        $now = Carbon::now();
        $at = $now->toDateString();

        $concessionaire = null;
        $profile = null;
        $cid = null;
        try {
            $cid = $user->concessionaires()->value('concessionaires.id');
            if ($cid) {
                $concessionaire = [
                    'id' => (int) $cid,
                    'full_name' => (string) optional($user->concessionaires()->where('concessionaires.id', $cid)->first())->getAttribute('full_name'),
                ];
                $profile = $this->economic->forConcessionaire((int) $cid, $now);
            }
        } catch (\Throwable $e) {
        }

        // FX Rates for today (EUR, USD)
        $fxRates = $this->loadFxRates($now);

        // Recalculate BS summary for portal dashboard using FX aggregates (2 decimal precision)
        if (is_array($profile)) {
            $profile = $this->recalculatePortalSummary($profile, $fxRates);
        }

        // Bank accounts (receiver accounts)
        $bankAccounts = $this->loadBankAccounts();

        // Recent payments status
        $paymentsStatus = $cid ? $this->loadPaymentsStatus((int) $cid) : null;

        // Recent receipts (last 3)
        $recentReceipts = $cid ? $this->loadRecentReceipts((int) $cid, 3) : [];

        // Active contract summary
        $contractSummary = $cid ? $this->loadContractSummary((int) $cid) : null;

        return Inertia::render('portal/index-modern', [
            'user' => [
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
            ],
            'concessionaire' => $concessionaire,
            'profile' => $profile,
            'at' => $at,
            'fxRates' => $fxRates,
            'bankAccounts' => $bankAccounts,
            'paymentsStatus' => $paymentsStatus,
            'recentReceipts' => $recentReceipts,
            'contractSummary' => $contractSummary,
        ]);
    }

    /**
     * Load FX rates for EUR and USD with timestamp.
     *
     * @return array<string, mixed>
     */
    private function loadFxRates(Carbon $at): array
    {
        $eurRate = $this->fx->resolveAt('EUR', $at);
        $usdRate = $this->fx->resolveAt('USD', $at);

        return [
            'EUR' => [
                'rate_to_ves' => $eurRate ? (float) $eurRate->getAttribute('rate_to_ves') : null,
                'rate_date' => $eurRate ? (string) $eurRate->getAttribute('rate_date') : null,
                'published_at' => $eurRate ? (string) $eurRate->getAttribute('published_at') : null,
            ],
            'USD' => [
                'rate_to_ves' => $usdRate ? (float) $usdRate->getAttribute('rate_to_ves') : null,
                'rate_date' => $usdRate ? (string) $usdRate->getAttribute('rate_date') : null,
                'published_at' => $usdRate ? (string) $usdRate->getAttribute('published_at') : null,
            ],
            'fetched_at' => $at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Load active bank accounts with full details for copy.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadBankAccounts(): array
    {
        return DB::table('company_bank_accounts as a')
            ->join('banks as b', 'b.id', '=', 'a.bank_id')
            ->where('a.is_active', true)
            ->whereNull('a.deleted_at')
            ->orderBy('b.name')
            ->get([
                'a.id',
                'b.name as bank_name',
                'b.bank_code',
                'a.account_number',
                'a.phone_number',
                'a.account_holder_name',
                'a.document_type',
                'a.document_number',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'bank_name' => (string) $row->bank_name,
                'bank_code' => (string) ($row->bank_code ?? ''),
                'account_number' => (string) $row->account_number,
                'phone_number' => (string) ($row->phone_number ?? ''),
                'account_holder_name' => (string) $row->account_holder_name,
                'document_type' => (string) $row->document_type,
                'document_number' => (string) $row->document_number,
                'rif' => ((string) $row->document_type).'-'.((string) $row->document_number),
            ])
            ->all();
    }

    /**
     * Load payments status for a concessionaire.
     *
     * @return array<string, mixed>
     */
    private function loadPaymentsStatus(int $cid): array
    {
        $voidStatusId = $this->voidStatusId();

        // Get last 5 payments (status is a virtual accessor, so we load the relation)
        $payments = Payment::query()
            ->with('paymentStatus:id,code')
            ->where('debtor_type', 'CONCESSIONAIRE')
            ->where('debtor_id', $cid)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->when($voidStatusId > 0, fn ($q) => $q->where('payment_status_id', '!=', $voidStatusId))
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'amount_bs_minor', 'paid_on', 'payment_status_id', 'payment_type_id', 'reference', 'gateway_resp_code', 'gateway_message']);

        $ids = $payments->pluck('id')->all();
        $appliedByPayment = empty($ids)
            ? collect()
            : PaymentAllocation::query()->whereIn('payment_id', $ids)
                ->selectRaw('payment_id, SUM(amount_bs_minor) as s')
                ->groupBy('payment_id')->pluck('s', 'payment_id');

        $lastPayment = $payments->first();
        $items = $payments->map(function ($p) use ($appliedByPayment) {
            $amount = (int) $p->getAttribute('amount_bs_minor');
            $applied = (int) ($appliedByPayment[(int) $p->getKey()] ?? 0);
            // Get status code from loaded relation
            $statusCode = (string) optional($p->paymentStatus)->getAttribute('code');

            return [
                'id' => (int) $p->getKey(),
                'paid_on' => (string) ($p->getAttribute('paid_on') ?? ''),
                'amount_bs_minor' => $amount,
                'applied_bs_minor' => $applied,
                'available_bs_minor' => max(0, $amount - $applied),
                'status' => $statusCode,
                'reference' => (string) ($p->getAttribute('reference') ?? ''),
                'gateway_resp_code' => (string) ($p->getAttribute('gateway_resp_code') ?? ''),
                'gateway_message' => (string) ($p->getAttribute('gateway_message') ?? ''),
            ];
        })->all();

        // Count by status (using relation since status is virtual)
        $pendingReview = Payment::query()
            ->where('debtor_type', 'CONCESSIONAIRE')
            ->where('debtor_id', $cid)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->when($voidStatusId > 0, fn ($q) => $q->where('payment_status_id', '!=', $voidStatusId))
            ->whereHas('paymentStatus', fn ($q) => $q->where('code', 'REG'))
            ->count();

        $confirmed = Payment::query()
            ->where('debtor_type', 'CONCESSIONAIRE')
            ->where('debtor_id', $cid)
            ->whereNull('deleted_at')
            ->whereNull('voided_at')
            ->when($voidStatusId > 0, fn ($q) => $q->where('payment_status_id', '!=', $voidStatusId))
            ->whereHas('paymentStatus', fn ($q) => $q->where('code', 'CONF'))
            ->count();

        return [
            'last_payment' => $lastPayment ? [
                'id' => (int) $lastPayment->getKey(),
                'status' => (string) optional($lastPayment->paymentStatus)->getAttribute('code'),
                'paid_on' => (string) ($lastPayment->getAttribute('paid_on') ?? ''),
                'amount_bs_minor' => (int) $lastPayment->getAttribute('amount_bs_minor'),
                'gateway_resp_code' => (string) ($lastPayment->getAttribute('gateway_resp_code') ?? ''),
                'gateway_message' => (string) ($lastPayment->getAttribute('gateway_message') ?? ''),
            ] : null,
            'recent' => $items,
            'counts' => [
                'pending_review' => $pendingReview,
                'confirmed' => $confirmed,
            ],
        ];
    }

    /**
     * Load recent receipts for a concessionaire.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadRecentReceipts(int $cid, int $limit = 3): array
    {
        $cidList = [$cid];

        $voidStatusId = $this->voidStatusId();

        // Local IDs associated to user's concessionaires
        $today = now()->toDateString();
        $localIds = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->whereIn('cc.concessionaire_id', $cidList)
            ->whereNull('c.deleted_at')
            ->whereDate('c.start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $today);
            })
            ->pluck('cl.local_id')->unique()->values()->all();

        return DB::table('receipts as r')
            ->join('payments as p', 'p.id', '=', 'r.payment_id')
            ->where(function ($q) use ($cidList, $localIds) {
                $q->where(function ($q2) use ($cidList) {
                    $q2->where('p.debtor_type', 'CONCESSIONAIRE')->whereIn('p.debtor_id', $cidList);
                });
                if (! empty($localIds)) {
                    $q->orWhere(function ($q3) use ($localIds) {
                        $q3->where('p.debtor_type', 'LOCAL')->whereIn('p.debtor_id', $localIds);
                    });
                }
            })
            ->whereNull('r.voided_at')
            ->where('r.status', '!=', 'VOIDED')
            ->whereNull('p.deleted_at')
            ->whereNull('p.voided_at')
            ->when($voidStatusId > 0, fn ($q) => $q->where('p.payment_status_id', '!=', $voidStatusId))
            ->orderByDesc('r.issued_at')
            ->limit($limit)
            ->get(['r.id', 'r.receipt_number', 'r.issued_at', 'r.status', 'p.amount_bs_minor'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'receipt_number' => (string) $r->receipt_number,
                'issued_at' => (string) $r->issued_at,
                'status' => (string) $r->status,
                'amount_bs_minor' => (int) ($r->amount_bs_minor ?? 0),
            ])
            ->all();
    }

    /**
     * Load active contract summary for a concessionaire.
     *
     * @return array<string, mixed>|null
     */
    private function loadContractSummary(int $cid): ?array
    {
        $today = now()->toDateString();

        $row = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->leftJoin('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->leftJoin('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->where('cc.concessionaire_id', $cid)
            ->whereNull('c.deleted_at')
            ->whereIn('cs.code', ['VIG', 'EXT', 'VENC'])
            ->whereDate('c.start_date', '<=', $today)
            ->selectRaw('c.id, c.number, cs.code as status_code, cs.name as status_name, c.start_date, c.end_date, COUNT(cl.local_id) as locals_count')
            ->groupBy('c.id', 'c.number', 'cs.code', 'cs.name', 'c.start_date', 'c.end_date')
            ->orderByDesc('c.start_date')
            ->first();

        if (! $row) {
            return null;
        }

        // Get local names
        $localNames = DB::table('contract_local as cl')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cl.contract_id', (int) $row->id)
            ->orderBy('l.name')
            ->pluck('l.name')
            ->map(fn ($n) => (string) $n)
            ->all();

        return [
            'id' => (int) $row->id,
            'number' => (string) ($row->number ?? ''),
            'status_code' => (string) ($row->status_code ?? ''),
            'status_name' => (string) ($row->status_name ?? ''),
            'start_date' => (string) ($row->start_date ?? ''),
            'end_date' => (string) ($row->end_date ?? ''),
            'locals_count' => (int) ($row->locals_count ?? 0),
            'local_names' => $localNames,
        ];
    }

    public function debt(Request $request): \Inertia\Response
    {
        $user = $request->user();
        abort_if(! $user || ! (bool) ($user->is_active ?? true), 403);
        $at = $request->date('at') ?? now();
        $cid = $user->concessionaires()->value('concessionaires.id');
        abort_if(! $cid, 403);

        $filters = $request->only(['currency', 'kind', 'period_from', 'period_to', 'overdue_only']);
        $started = microtime(true);
        $data = $this->economic->forConcessionaire((int) $cid, $at, $filters);
        $latencyMs = (int) ((microtime(true) - $started) * 1000);

        // Attach canonical reconciliation data to avoid frontend recalculations
        try {
            $data['reconciliation'] = $this->economic->getReconciliation('CONCESSIONAIRE', (int) $cid, $at, $filters);
        } catch (\Throwable $e) {
        }

        try {
            \Log::info('portal.debt.snapshot', [
                'user_id' => (int) $user->id,
                'concessionaire_id' => (int) $cid,
                'at' => $at->format('Y-m-d'),
                'filters' => $filters,
                'summary_open_bs_minor' => (int) ($data['summary_bs']['open_bs_minor'] ?? 0),
                'summary_overdue_bs_minor' => (int) ($data['summary_bs']['overdue_bs_minor'] ?? 0),
                'summary_open_bs_minor_from_fx' => (int) ($data['summary_bs']['open_bs_minor_from_fx'] ?? 0),
                'summary_overdue_bs_minor_from_fx' => (int) ($data['summary_bs']['overdue_bs_minor_from_fx'] ?? 0),
                'credits_open_bs_minor' => (int) ($data['summary_bs']['credits_open_bs_minor'] ?? 0),
                'net_due_after_credit_bs_minor' => (int) ($data['summary_bs']['net_due_after_credit_bs_minor'] ?? 0),
                'net_due_after_credit_bs_minor_from_fx' => (int) ($data['summary_bs']['net_due_after_credit_bs_minor_from_fx'] ?? 0),
                'latency_ms' => $latencyMs,
            ]);
        } catch (\Throwable $e) {
        }

        return Inertia::render('portal/debt-modern', $data + [
            'at' => $at->format('Y-m-d'),
        ]);
    }

    public function receipts(Request $request): \Inertia\Response
    {
        $user = $request->user();
        abort_if(! $user || ! (bool) ($user->is_active ?? true), 403);
        $cidList = $user->concessionaires()->pluck('concessionaires.id')->all();
        abort_if(empty($cidList), 403);

        $voidStatusId = $this->voidStatusId();

        // Local IDs associated to user's concessionaires (active contracts today)
        $today = now()->toDateString();
        $localIds = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->whereIn('cc.concessionaire_id', $cidList)
            ->whereNull('c.deleted_at')
            ->whereDate('c.start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $today);
            })
            ->pluck('cl.local_id')->unique()->values()->all();

        $rows = DB::table('receipts as r')
            ->join('payments as p', 'p.id', '=', 'r.payment_id')
            ->where(function ($q) use ($cidList, $localIds) {
                $q->where(function ($q2) use ($cidList) {
                    $q2->where('p.debtor_type', 'CONCESSIONAIRE')->whereIn('p.debtor_id', $cidList);
                });
                if (! empty($localIds)) {
                    $q->orWhere(function ($q3) use ($localIds) {
                        $q3->where('p.debtor_type', 'LOCAL')->whereIn('p.debtor_id', $localIds);
                    });
                }
            })
            ->where(function ($q) {
                $q->where('r.scope', 'PAYMENT')->orWhereNull('r.scope');
            })
            ->whereNull('r.voided_at')
            ->where('r.status', '!=', 'VOIDED')
            ->whereNull('p.deleted_at')
            ->whereNull('p.voided_at')
            ->when($voidStatusId > 0, fn ($q) => $q->where('p.payment_status_id', '!=', $voidStatusId))
            ->orderByDesc('r.issued_at')
            ->limit(200)
            ->get([
                'r.id',
                'r.payment_id',
                'r.charge_id',
                'r.scope',
                'r.number_seq',
                'r.receipt_number',
                'r.issued_at',
                'r.status',
                'p.paid_on',
            ]);

        $items = $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'payment_id' => (int) $r->payment_id,
            'charge_id' => $r->charge_id ? (int) $r->charge_id : null,
            'scope' => (string) ($r->scope ?? ''),
            'number_seq' => (int) ($r->number_seq ?? 0),
            'receipt_number' => (string) $r->receipt_number,
            'issued_at' => (string) $r->issued_at,
            'paid_on' => (string) ($r->paid_on ?? ''),
            'status' => (string) $r->status,
        ])->all();

        return Inertia::render('portal/receipts-modern', [
            'items' => $items,
        ]);
    }

    public function contracts(Request $request): \Inertia\Response
    {
        $user = $request->user();
        abort_if(! $user || ! (bool) ($user->is_active ?? true), 403);
        $cidList = $user->concessionaires()->pluck('concessionaires.id')->all();
        abort_if(empty($cidList), 403);

        $rows = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->leftJoin('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->leftJoin('contract_modalities as cm', 'cm.id', '=', 'c.contract_modality_id')
            ->leftJoin('contract_types as ct', 'ct.id', '=', 'c.contract_type_id')
            ->selectRaw('c.id, c.number, cs.code as status, cs.name as status_name, c.start_date, c.end_date, cm.code as modality_code, cm.name as modality_name, ct.code as type_code, ct.name as type_name, c.monthly_price_eur')
            ->whereIn('cc.concessionaire_id', $cidList)
            ->whereNull('c.deleted_at')
            ->orderByDesc('c.start_date')
            ->limit(200)
            ->get();

        // Resolve locals with details per contract
        $ids = $rows->pluck('id')->map(fn ($v) => (int) $v)->all();
        $localsByContract = empty($ids) ? collect() : DB::table('contract_local as cl')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->leftJoin('local_types as lt', 'lt.id', '=', 'l.local_type_id')
            ->whereIn('cl.contract_id', $ids)
            ->orderBy('l.code')
            ->get([
                'cl.contract_id',
                'l.id as local_id',
                'l.code as local_code',
                'l.name as local_name',
                'lt.name as local_type',
                'l.area_m2',
                'l.market_id',
            ])
            ->groupBy('contract_id');

        $items = $rows->map(function ($r) use ($localsByContract) {
            $id = (int) $r->id;
            $locals = $localsByContract[$id] ?? collect();

            // Build locals array with details
            $localsArray = $locals->map(fn ($l) => [
                'id' => (int) $l->local_id,
                'code' => (string) ($l->local_code ?? ''),
                'name' => (string) ($l->local_name ?? ''),
                'type' => (string) ($l->local_type ?? ''),
                'area_m2' => $l->area_m2 ? (float) $l->area_m2 : null,
            ])->values()->all();

            // Determine charge type label based on modality
            $modalityCode = strtoupper((string) ($r->modality_code ?? ''));
            $chargeType = $modalityCode === 'M2' ? 'Tasa de uso' : ($modalityCode === 'TFIJA' ? 'Alquiler' : null);

            $monthlyCurrency = $modalityCode === 'TFIJA' ? 'USD' : 'EUR';

            // Monthly amount: use DB value when present, derive for M2 when empty.
            $monthlyEur = $r->monthly_price_eur ? (float) $r->monthly_price_eur : null;
            if (($monthlyEur === null || $monthlyEur <= 0.0) && $modalityCode === 'M2') {
                $monthlyEur = $this->calculateM2MonthlyPriceFromLocals($locals);
            }

            return [
                'id' => $id,
                'number' => (string) ($r->number ?? ''),
                'status' => (string) ($r->status ?? ''),
                'status_name' => (string) ($r->status_name ?? ''),
                'start_date' => (string) ($r->start_date ?? ''),
                'end_date' => (string) ($r->end_date ?? ''),
                'modality_code' => $modalityCode,
                'modality_name' => (string) ($r->modality_name ?? ''),
                'type_code' => (string) ($r->type_code ?? ''),
                'type_name' => (string) ($r->type_name ?? ''),
                'charge_type' => $chargeType,
                'monthly_eur' => $monthlyEur,
                'monthly_amount' => $monthlyEur,
                'monthly_currency' => $monthlyCurrency,
                'locals' => $localsArray,
                'locals_count' => count($localsArray),
            ];
        })->all();

        return Inertia::render('portal/contracts-modern', [
            'items' => $items,
        ]);
    }

    /**
     * Estimate monthly EUR amount for M2 contracts based on locals' area and current market tariffs.
     * Mirrors ContractService::toItem logic but limited to what the portal needs.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $locals
     */
    private function calculateM2MonthlyPriceFromLocals($locals): ?float
    {
        if ($locals->isEmpty()) {
            return null;
        }

        // Group total area by market
        $areaByMarket = [];
        foreach ($locals as $local) {
            $area = (float) ($local->area_m2 ?? 0.0);
            $marketId = (int) ($local->market_id ?? 0);
            if ($area <= 0.0 || $marketId <= 0) {
                continue;
            }

            if (! isset($areaByMarket[$marketId])) {
                $areaByMarket[$marketId] = 0.0;
            }
            $areaByMarket[$marketId] += $area;
        }

        if (empty($areaByMarket)) {
            return null;
        }

        // Fetch current tariffs per market (minor units per m2 per day)
        $tariffs = DB::table('market_tariffs')
            ->whereIn('market_id', array_keys($areaByMarket))
            ->where('is_current', true)
            ->orderByDesc('valid_from')
            ->get(['market_id', 'price_per_m2_eur_minor'])
            ->keyBy('market_id');

        $totalMinor = 0;
        $monthlyFactor = 365 / 12; // same factor as RentM2Calculator

        foreach ($areaByMarket as $marketId => $totalArea) {
            $tariff = $tariffs->get($marketId);
            if (! $tariff) {
                continue;
            }

            $priceMinorPerM2PerDay = (int) ($tariff->price_per_m2_eur_minor ?? 0);
            if ($priceMinorPerM2PerDay <= 0) {
                continue;
            }

            $totalMinor += (int) round($priceMinorPerM2PerDay * $totalArea * $monthlyFactor, 0);
        }

        if ($totalMinor <= 0) {
            return null;
        }

        return round($totalMinor / 100, 2);
    }

    public function downloadReceipt(Request $request, Receipt $receipt): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        abort_if(! $user || ! (bool) ($user->is_active ?? true), 403);
        $cidList = $user->concessionaires()->pluck('concessionaires.id')->all();
        abort_if(empty($cidList), 403);

        $refresh = $request->boolean('refresh', false);

        if ($receipt->getAttribute('voided_at') !== null) {
            abort(404);
        }

        if (strtoupper((string) ($receipt->getAttribute('status') ?? '')) === 'VOIDED') {
            abort(404);
        }

        // Scope check
        /** @var Payment|null $payment */
        $payment = Payment::query()->find((int) $receipt->getAttribute('payment_id'));
        if (! $payment) {
            abort(404);
        }

        if ($payment->getAttribute('voided_at') !== null) {
            abort(404);
        }

        $voidStatusId = $this->voidStatusId();
        if ($voidStatusId > 0 && (int) ($payment->getAttribute('payment_status_id') ?? 0) === $voidStatusId) {
            abort(404);
        }

        $paymentStatus = strtoupper((string) ($payment->getAttribute('status') ?? ''));
        if ($paymentStatus === 'VOID') {
            abort(404);
        }

        $scopeOk = false;
        $debtorType = strtoupper((string) ($payment->getAttribute('debtor_type') ?? ''));
        $debtorId = (int) ($payment->getAttribute('debtor_id') ?? 0);
        if ($debtorType === 'CONCESSIONAIRE' && in_array($debtorId, $cidList, true)) {
            $scopeOk = true;
        } elseif ($debtorType === 'LOCAL' && $debtorId > 0) {
            $today = now()->toDateString();
            $ownLocal = DB::table('concessionaire_contract as cc')
                ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
                ->whereIn('cc.concessionaire_id', $cidList)
                ->where('cl.local_id', $debtorId)
                ->whereNull('c.deleted_at')
                ->whereDate('c.start_date', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $today);
                })
                ->exists();
            $scopeOk = $ownLocal;
        }

        abort_unless($scopeOk, 403);

        // Use same logic as admin download to ensure PDF is ready and then stream
        $disk = Storage::disk('local');
        $path = (string) ($receipt->getAttribute('pdf_path') ?? '');
        if ($refresh || $path === '' || ! $disk->exists($path)) {
            try {
                $gen = app(\App\Services\ReceiptPdfGenerator::class)->render($receipt);
                $receipt->fill([
                    'pdf_path' => $gen['pdf_path'],
                    'pdf_sha256' => $gen['pdf_sha256'],
                    'rendered_at' => $gen['rendered_at'],
                ])->save();
                $path = $gen['pdf_path'];
            } catch (\Throwable $e) {
                return response('PDF no disponible aún para este recibo.', 404);
            }
        }
        if (! $disk->exists($path)) {
            return response('PDF no disponible aún para este recibo.', 404);
        }
        $full = $disk->path($path);

        return response()->file($full, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$receipt->getAttribute('receipt_number').'.pdf"',
        ]);
    }

    /**
     * Recalculate summary_bs fields for portal dashboard using FX aggregates with 2-decimal precision.
     *
     * This ensures that "Total a Pagar" in Bs matches the EUR/USD breakdown shown in the card
     * when the user multiplica montos en moneda * tasa BCV (2 decimales) y luego suma.
     *
     * @param  array<string, mixed>|null  $profile
     * @param  array<string, mixed>  $fxRates
     * @return array<string, mixed>|null
     */
    private function recalculatePortalSummary(?array $profile, array $fxRates): ?array
    {
        if ($profile === null) {
            return null;
        }

        if (! isset($profile['summary_bs'], $profile['summary_fx']) || ! is_array($profile['summary_bs']) || ! is_array($profile['summary_fx'])) {
            return $profile;
        }

        $summaryBs = $profile['summary_bs'];
        $summaryFx = $profile['summary_fx'];

        $creditsOpen = (int) ($summaryBs['credits_open_bs_minor'] ?? 0);

        $openBsFromFx = 0;
        $overdueBsFromFx = 0;

        // We currently care about EUR rent (Tasa de Uso) and USD condo (Gastos comunes)
        foreach (['rent', 'condo'] as $key) {
            if (! isset($summaryFx[$key]) || ! is_array($summaryFx[$key])) {
                continue;
            }

            $row = $summaryFx[$key];
            $openMinor = (int) ($row['open_minor'] ?? 0);
            $overdueMinor = (int) ($row['overdue_minor'] ?? 0);

            $rate = $row['rate_to_ves'] ?? null;
            if ($rate === null) {
                // Fallback to controller-level fxRates by currency code if needed
                $currency = strtoupper((string) ($row['currency'] ?? ''));
                if ($currency !== '' && isset($fxRates[$currency]['rate_to_ves'])) {
                    $rate = $fxRates[$currency]['rate_to_ves'];
                }
            }

            $rateMinor = null;
            if ($rate !== null) {
                $rateMinor = (int) round((float) $rate * 100);
            }

            $openBsFromFx += $this->convertFxAggregateToBs($openMinor, $rateMinor);
            $overdueBsFromFx += $this->convertFxAggregateToBs($overdueMinor, $rateMinor);
        }

        // If we could not compute anything, keep original values
        if ($openBsFromFx <= 0 && $overdueBsFromFx <= 0) {
            return $profile;
        }

        $summaryBs['open_bs_minor_from_fx'] = $openBsFromFx;
        $summaryBs['overdue_bs_minor_from_fx'] = $overdueBsFromFx;
        $summaryBs['net_due_after_credit_bs_minor_from_fx'] = max(0, $openBsFromFx - $creditsOpen);

        $profile['summary_bs'] = $summaryBs;

        return $profile;
    }

    /**
     * Convert aggregated amount in original currency (minor units, 2 decimals) using FX rate (2 decimals).
     *
     * Aplica la misma política de truncamiento que FxConversionHelper::toVes:
     * amount (2dp) * rate (2dp) => 4dp, truncar a 2dp.
     *
     * Esto replica la forma en que el usuario multiplica "monto * tasa BCV" y corta a 2 decimales.
     *
     * @param  int  $amountMinor  Amount in original currency minor units (e.g. 32519 => €325,19)
     * @param  int|null  $rateMinor  FX rate * 100 (e.g. 28350 => 283,50 Bs)
     */
    private function convertFxAggregateToBs(int $amountMinor, ?int $rateMinor): int
    {
        if ($amountMinor <= 0 || $rateMinor === null || $rateMinor <= 0) {
            return 0;
        }

        // Política BCV: redondear a 2 decimales basándose en el tercer decimal
        $prod = $amountMinor * $rateMinor;

        return (int) round($prod / 100);
    }

    private function voidStatusId(): int
    {
        if ($this->voidStatusIdCached === null) {
            $this->voidStatusIdCached = (int) (PaymentStatus::query()->where('code', 'VOID')->value('id') ?? 0);
        }

        return (int) $this->voidStatusIdCached;
    }
}
