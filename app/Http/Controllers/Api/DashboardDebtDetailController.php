<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardDebtDetailController extends Controller
{
    /**
     * Get detailed debt information by concessionaire with local breakdown
     */
    public function byConcessionaire(Request $request): JsonResponse
    {
        $this->authorize('viewCharts', \App\Models\User::class);

        $validated = $request->validate([
            'limit' => 'integer|min:1|max:100',
            'sort' => 'string|in:debt,days',
        ]);

        $limit = (int) ($validated['limit'] ?? 20);
        $sort = (string) ($validated['sort'] ?? 'debt');

        $cacheKey = sprintf('dash:debt:detail:conc:%s:%d', $sort, $limit);

        $data = Cache::remember($cacheKey, 120, function () use ($limit, $sort): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Get active EUR exchange rate
            $eurRate = DB::table('fx_rates')
                ->where('currency_code', 'EUR')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('rate_date', 'desc')
                ->value('rate_to_ves');

            if (! $eurRate) {
                $eurRate = 1;
            }

            $orderBy = $sort === 'days' ? 'max_days_overdue' : 'debt_eur_minor';

            $items = DB::table('concessionaires as c')
                ->join('concessionaire_contract as cc', 'cc.concessionaire_id', '=', 'c.id')
                ->join('contracts as ct', 'ct.id', '=', 'cc.contract_id')
                ->join('charges as ch', 'ch.contract_id', '=', 'ct.id')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->leftJoin('payment_allocations as pa', function ($join) {
                    $join->on('pa.charge_id', '=', 'ch.id')
                        ->whereNull('pa.deleted_at');
                })
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->where('ch.due_on', '<=', $today)
                ->whereNull('ch.deleted_at')
                ->whereNull('ct.deleted_at')
                ->whereNull('c.deleted_at')
                ->select(
                    DB::raw('MIN(c.id) as id'),
                    DB::raw('MIN(c.full_name) as name'),
                    DB::raw('SUM(ch.amount_minor) as debt_eur_minor'),
                    DB::raw("SUM(ch.amount_minor * {$eurRate}) - COALESCE(SUM(pa.amount_bs_minor), 0) as debt_bs_minor"),
                    DB::raw('MAX(CURRENT_DATE - ch.due_on) as max_days_overdue'),
                    DB::raw('COUNT(DISTINCT ch.id) as charges_count'),
                    DB::raw('COUNT(DISTINCT ch.local_id) as locals_count')
                )
                ->groupBy('c.document_type_id', 'c.document_number')
                ->havingRaw("SUM(ch.amount_minor * {$eurRate}) - COALESCE(SUM(pa.amount_bs_minor), 0) > 0")
                ->orderBy($orderBy, 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'debt_eur_minor' => (int) $row->debt_eur_minor,
                    'debt_bs_minor' => (int) $row->debt_bs_minor,
                    'max_days_overdue' => (int) $row->max_days_overdue,
                    'charges_count' => (int) $row->charges_count,
                    'locals_count' => (int) $row->locals_count,
                ])
                ->all();

            return [
                'items' => $items,
                'fx_rate' => (float) $eurRate,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Get debt information by local
     */
    public function byLocal(Request $request): JsonResponse
    {
        $this->authorize('viewCharts', \App\Models\User::class);

        $validated = $request->validate([
            'limit' => 'integer|min:1|max:100',
        ]);

        $limit = (int) ($validated['limit'] ?? 20);

        $cacheKey = sprintf('dash:debt:detail:local:%d', $limit);

        $data = Cache::remember($cacheKey, 120, function () use ($limit): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Get active EUR exchange rate
            $eurRate = DB::table('fx_rates')
                ->where('currency_code', 'EUR')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('rate_date', 'desc')
                ->value('rate_to_ves');

            if (! $eurRate) {
                $eurRate = 1;
            }

            $items = DB::table('locals as l')
                ->join('charges as ch', 'ch.local_id', '=', 'l.id')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->leftJoin('payment_allocations as pa', function ($join) {
                    $join->on('pa.charge_id', '=', 'ch.id')
                        ->whereNull('pa.deleted_at');
                })
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->where('ch.due_on', '<=', $today)
                ->whereNull('ch.deleted_at')
                ->whereNull('l.deleted_at')
                ->select(
                    'l.id',
                    'l.code as local_code',
                    DB::raw('SUM(ch.amount_minor) as debt_eur_minor'),
                    DB::raw("SUM(ch.amount_minor * {$eurRate}) - COALESCE(SUM(pa.amount_bs_minor), 0) as debt_bs_minor"),
                    DB::raw('COUNT(DISTINCT ch.contract_id) as contracts_count'),
                    DB::raw('COUNT(DISTINCT ch.id) as charges_count')
                )
                ->groupBy('l.id', 'l.code')
                ->havingRaw("SUM(ch.amount_minor * {$eurRate}) - COALESCE(SUM(pa.amount_bs_minor), 0) > 0")
                ->orderByRaw("SUM(ch.amount_minor * {$eurRate}) - COALESCE(SUM(pa.amount_bs_minor), 0) DESC")
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'local_code' => (string) $row->local_code,
                    'debt_eur_minor' => (int) $row->debt_eur_minor,
                    'debt_bs_minor' => (int) $row->debt_bs_minor,
                    'contracts_count' => (int) $row->contracts_count,
                    'charges_count' => (int) $row->charges_count,
                ])
                ->all();

            return [
                'items' => $items,
                'fx_rate' => (float) $eurRate,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Get list of solvent concessionaires
     */
    public function solvent(Request $request): JsonResponse
    {
        $this->authorize('viewCharts', \App\Models\User::class);

        $validated = $request->validate([
            'limit' => 'integer|min:1|max:100',
        ]);

        $limit = (int) ($validated['limit'] ?? 50);

        $cacheKey = sprintf('dash:debt:solvent:%d', $limit);

        $data = Cache::remember($cacheKey, 120, function () use ($limit): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Get concessionaires with active contracts
            $activeConcessionaires = DB::table('concessionaires as c')
                ->join('concessionaire_contract as cc', 'cc.concessionaire_id', '=', 'c.id')
                ->join('contracts as ct', 'ct.id', '=', 'cc.contract_id')
                ->join('contract_statuses as cs', 'cs.id', '=', 'ct.contract_status_id')
                ->whereIn('cs.code', ['VIG', 'VENC'])
                ->whereNull('ct.deleted_at')
                ->whereNull('c.deleted_at')
                ->select(
                    'c.id',
                    'c.full_name',
                    DB::raw('COUNT(DISTINCT ct.id) as contracts_count')
                )
                ->groupBy('c.id', 'c.full_name')
                ->get();

            // Get concessionaires with overdue debt
            $delinquentIds = DB::table('charges as ch')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->join('contracts as ct', 'ct.id', '=', 'ch.contract_id')
                ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'ct.id')
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->where('ch.due_on', '<=', $today)
                ->whereNull('ch.deleted_at')
                ->whereNull('ct.deleted_at')
                ->distinct()
                ->pluck('cc.concessionaire_id')
                ->all();

            // Filter solvent (active WITHOUT overdue debt)
            $items = $activeConcessionaires
                ->whereNotIn('id', $delinquentIds)
                ->take($limit)
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->full_name,
                    'contracts_count' => (int) $row->contracts_count,
                ])
                ->values()
                ->all();

            return [
                'items' => $items,
                'total' => count($items),
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });

        return response()->json($data);
    }
}
