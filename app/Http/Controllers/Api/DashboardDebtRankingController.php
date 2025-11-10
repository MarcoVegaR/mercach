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
                ->where('ch.due_on', '<', $today)
                ->whereNull('ch.deleted_at')
                ->whereNull('ct.deleted_at')
                ->whereNull('c.deleted_at')
                ->select(
                    DB::raw('MIN(c.id) as id'),
                    DB::raw('MIN(c.full_name) as name'),
                    DB::raw("SUM(ch.amount_minor * {$eurRate}) - COALESCE(SUM(pa.amount_bs_minor), 0) as debt_bs_minor"),
                    DB::raw('SUM(ch.amount_minor) as debt_eur_minor'),
                    DB::raw('MAX(CURRENT_DATE - ch.due_on) as max_days_overdue'),
                    DB::raw('AVG(CURRENT_DATE - ch.due_on) as avg_days_overdue')
                )
                ->groupBy('c.document_type_id', 'c.document_number')
                ->havingRaw("SUM(ch.amount_minor * {$eurRate}) - COALESCE(SUM(pa.amount_bs_minor), 0) > 0")
                ->orderByRaw("SUM(ch.amount_minor * {$eurRate}) - COALESCE(SUM(pa.amount_bs_minor), 0) DESC")
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'debt_bs_minor' => (int) $row->debt_bs_minor,
                    'debt_eur_minor' => (int) $row->debt_eur_minor,
                    'max_days_overdue' => (int) $row->max_days_overdue,
                    'avg_days_overdue' => round((float) $row->avg_days_overdue, 1),
                    'severity' => $this->calculateSeverity((int) $row->max_days_overdue),
                ])
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
