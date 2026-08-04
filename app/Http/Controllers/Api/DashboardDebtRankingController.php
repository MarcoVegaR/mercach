<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardDebtRankingController extends Controller
{
    /**
     * Get ranking of concessionaires with highest overdue debt
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewCharts', \App\Models\User::class);

        $validated = $request->validate([
            'limit' => 'integer|min:1|max:50',
        ]);

        $limit = (int) ($validated['limit'] ?? 10);

        $cacheKey = sprintf('dash:debt:ranking:%d', $limit);

        $data = Cache::remember($cacheKey, 120, function () use ($limit): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Get active FX rates
            $eurRate = DB::table('fx_rates')
                ->where('currency_code', 'EUR')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('rate_date', 'desc')
                ->value('rate_to_ves');
            $usdRate = DB::table('fx_rates')
                ->where('currency_code', 'USD')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('rate_date', 'desc')
                ->value('rate_to_ves');

            $eurRate = is_numeric($eurRate) ? (float) $eurRate : 1.0;
            $usdRate = is_numeric($usdRate) ? (float) $usdRate : 1.0;
            $eurRateMinor = (int) round($eurRate * 100);
            $usdRateMinor = (int) round($usdRate * 100);

            // Latest active contract per local at today (to map CONDO_USD charges with contract_id = NULL)
            $activeContractByLocal = DB::table('contract_local as cl')
                ->join('contracts as ct', 'ct.id', '=', 'cl.contract_id')
                ->join('contract_statuses as cts', 'cts.id', '=', 'ct.contract_status_id')
                ->whereNull('ct.deleted_at')
                ->whereDate('ct.start_date', '<=', $today)
                ->whereIn('cts.code', ['VIG', 'EXT', 'VENC'])
                ->where(function ($q) use ($today): void {
                    $q->whereIn('cts.code', ['VIG', 'EXT'])
                        ->where(function ($w) use ($today): void {
                            $w->whereNull('ct.end_date')->orWhereDate('ct.end_date', '>=', $today);
                        })
                        ->orWhere('cts.code', '=', 'VENC');
                })
                ->selectRaw('DISTINCT ON (cl.local_id) cl.local_id, cl.contract_id')
                ->orderBy('cl.local_id')
                ->orderByDesc('ct.start_date')
                ->orderByDesc('ct.id');

            $allocSub = DB::table('payment_allocations as pa')
                ->select('pa.charge_id', DB::raw('SUM(pa.amount_bs_minor)::bigint as paid_bs_minor'))
                ->whereNull('pa.deleted_at')
                ->groupBy('pa.charge_id');

            $creditsSub = DB::table('credit_applications as ca')
                ->leftJoin('customer_credits as cc', 'cc.id', '=', 'ca.customer_credit_id')
                ->select('ca.charge_id')
                ->selectRaw(
                    "SUM(CASE UPPER(COALESCE(cc.currency, 'VES')) "
                    ."WHEN 'VES' THEN ca.amount_minor "
                    ."WHEN 'EUR' THEN (ca.amount_minor::bigint * {$eurRateMinor}) / 100 "
                    ."WHEN 'USD' THEN (ca.amount_minor::bigint * {$usdRateMinor}) / 100 "
                    .'ELSE 0 END)::bigint as credit_bs_minor'
                )
                ->groupBy('ca.charge_id');

            // Base: overdue open charges mapped to concessionaire through local->active contract
            $items = DB::table('charges as ch')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->join('locals as l', function ($j): void {
                    $j->on('l.id', '=', 'ch.debtor_id')
                        ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
                })
                ->joinSub($activeContractByLocal, 'acl', 'acl.local_id', '=', 'l.id')
                ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'acl.contract_id')
                ->join('concessionaires as c', 'c.id', '=', 'cc.concessionaire_id')
                ->leftJoinSub($allocSub, 'ap', 'ap.charge_id', '=', 'ch.id')
                ->leftJoinSub($creditsSub, 'cr', 'cr.charge_id', '=', 'ch.id')
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->where('ch.due_on', '<=', $today)
                ->whereNull('ch.deleted_at')
                ->whereNull('ch.uncollectible_at')
                ->whereNull('l.deleted_at')
                ->whereNull('c.deleted_at')
                ->select(
                    DB::raw('MIN(c.id) as id'),
                    DB::raw('MIN(c.full_name) as name'),
                    DB::raw("SUM(CASE WHEN ch.currency = 'EUR' THEN GREATEST(0, ((ch.amount_minor::bigint * {$eurRateMinor}) / 100) - COALESCE(ap.paid_bs_minor, 0) - COALESCE(cr.credit_bs_minor, 0)) ELSE 0 END)::bigint as debt_bs_minor_eur"),
                    DB::raw("SUM(CASE WHEN ch.currency = 'USD' THEN GREATEST(0, ((ch.amount_minor::bigint * {$usdRateMinor}) / 100) - COALESCE(ap.paid_bs_minor, 0) - COALESCE(cr.credit_bs_minor, 0)) ELSE 0 END)::bigint as debt_bs_minor_usd"),
                    DB::raw("SUM(GREATEST(0, (CASE WHEN ch.currency = 'EUR' THEN (ch.amount_minor::bigint * {$eurRateMinor}) / 100 WHEN ch.currency = 'USD' THEN (ch.amount_minor::bigint * {$usdRateMinor}) / 100 ELSE 0 END) - COALESCE(ap.paid_bs_minor, 0) - COALESCE(cr.credit_bs_minor, 0)))::bigint as debt_bs_minor"),
                    DB::raw('MAX(CURRENT_DATE - ch.due_on) as max_days_overdue'),
                    DB::raw('AVG(CURRENT_DATE - ch.due_on) as avg_days_overdue')
                )
                ->groupBy('c.document_type_id', 'c.document_number')
                ->havingRaw("SUM(GREATEST(0, (CASE WHEN ch.currency = 'EUR' THEN (ch.amount_minor::bigint * {$eurRateMinor}) / 100 WHEN ch.currency = 'USD' THEN (ch.amount_minor::bigint * {$usdRateMinor}) / 100 ELSE 0 END) - COALESCE(ap.paid_bs_minor, 0) - COALESCE(cr.credit_bs_minor, 0))) > 0")
                ->orderByRaw("SUM(GREATEST(0, (CASE WHEN ch.currency = 'EUR' THEN (ch.amount_minor::bigint * {$eurRateMinor}) / 100 WHEN ch.currency = 'USD' THEN (ch.amount_minor::bigint * {$usdRateMinor}) / 100 ELSE 0 END) - COALESCE(ap.paid_bs_minor, 0) - COALESCE(cr.credit_bs_minor, 0))) DESC")
                ->limit($limit)
                ->get()
                ->map(function ($row) use ($eurRateMinor, $usdRateMinor) {
                    $bsEur = (int) ($row->debt_bs_minor_eur ?? 0);
                    $bsUsd = (int) ($row->debt_bs_minor_usd ?? 0);
                    $debtEurMinor = $eurRateMinor > 0 ? (int) round($bsEur * 100 / $eurRateMinor) : 0;
                    $debtUsdMinor = $usdRateMinor > 0 ? (int) round($bsUsd * 100 / $usdRateMinor) : 0;

                    return [
                        'id' => (int) $row->id,
                        'name' => (string) $row->name,
                        // Backward-compatible fields
                        'debt_bs_minor' => (int) ($row->debt_bs_minor ?? 0),
                        'debt_eur_minor' => $debtEurMinor,
                        // New breakdown
                        'debt_usd_minor' => $debtUsdMinor,
                        'debt_bs_minor_eur' => $bsEur,
                        'debt_bs_minor_usd' => $bsUsd,
                        'max_days_overdue' => (int) $row->max_days_overdue,
                        'avg_days_overdue' => round((float) $row->avg_days_overdue, 1),
                        'severity' => $this->calculateSeverity((int) $row->max_days_overdue),
                    ];
                })
                ->all();

            return [
                'items' => $items,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });

        return response()->json($data);
    }

    private function calculateSeverity(int $daysOverdue): string
    {
        if ($daysOverdue > 90) {
            return 'critical'; // Red
        }
        if ($daysOverdue > 30) {
            return 'high'; // Orange
        }

        return 'medium'; // Yellow
    }
}
