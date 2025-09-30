<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardRankingsController extends Controller
{
    /**
     * Get concessionaires ranking by contracts or m2.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewCharts', \App\Models\User::class);

        $validated = $request->validate([
            'metric' => 'string|in:contracts,m2',
            'order' => 'string|in:top,bottom',
            'limit' => 'integer|min:1|max:50',
            'market_id' => 'integer|nullable',
        ]);

        $metric = (string) ($validated['metric'] ?? 'contracts');
        $order = (string) ($validated['order'] ?? 'top');
        $limit = (int) ($validated['limit'] ?? 10);
        $marketId = isset($validated['market_id']) ? (int) $validated['market_id'] : null;

        $cacheKey = sprintf('dash:rankings:%s:%s:%d:%s', $metric, $order, $limit, $marketId ?? 'all');

        $data = Cache::remember($cacheKey, 120, function () use ($metric, $order, $limit, $marketId): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            if ($metric === 'contracts') {
                // Count contracts per concessionaire (deduplicate by document identity)
                $query = DB::table('concessionaires as c')
                    ->join('concessionaire_contract as cc', 'cc.concessionaire_id', '=', 'c.id')
                    ->join('contracts as ct', 'ct.id', '=', 'cc.contract_id')
                    ->join('contract_statuses as cs', 'cs.id', '=', 'ct.contract_status_id')
                    ->where('cs.code', '=', 'VIG')
                    ->where('ct.start_date', '<=', $today)
                    ->where(function ($q) use ($today): void {
                        $q->whereNull('ct.end_date')->orWhere('ct.end_date', '>=', $today);
                    })
                    ->whereNull('ct.deleted_at')
                    ->whereNull('c.deleted_at')
                    ->select(
                        DB::raw('MIN(c.id) as id'),
                        DB::raw('MIN(c.full_name) as name'),
                        DB::raw('COUNT(DISTINCT ct.id) as value')
                    )
                    ->groupBy('c.document_type_id', 'c.document_number')
                    ->orderBy('value', $order === 'top' ? 'desc' : 'asc')
                    ->limit($limit);

                $items = $query->get()->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'value' => (int) $row->value,
                ])->all();
            } else {
                // Sum area_m2 from locals linked to vigente contracts
                $query = DB::table('concessionaires as c')
                    ->join('concessionaire_contract as cc', 'cc.concessionaire_id', '=', 'c.id')
                    ->join('contracts as ct', 'ct.id', '=', 'cc.contract_id')
                    ->join('contract_statuses as cs', 'cs.id', '=', 'ct.contract_status_id')
                    ->join('contract_local as cl', 'cl.contract_id', '=', 'ct.id')
                    ->join('locals as l', 'l.id', '=', 'cl.local_id')
                    ->where('cs.code', '=', 'VIG')
                    ->where('ct.start_date', '<=', $today)
                    ->where(function ($q) use ($today): void {
                        $q->whereNull('ct.end_date')->orWhere('ct.end_date', '>=', $today);
                    })
                    ->whereNull('ct.deleted_at')
                    ->whereNull('c.deleted_at')
                    ->select(
                        DB::raw('MIN(c.id) as id'),
                        DB::raw('MIN(c.full_name) as name'),
                        DB::raw('COALESCE(SUM(l.area_m2), 0) as value')
                    )
                    ->groupBy('c.document_type_id', 'c.document_number');

                if ($marketId) {
                    $query->where('l.market_id', $marketId);
                }

                $query->orderBy('value', $order === 'top' ? 'desc' : 'asc')
                    ->limit($limit);

                $items = $query->get()->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'value' => (float) $row->value,
                ])->all();
            }

            return [
                'metric' => $metric,
                'order' => $order,
                'items' => $items,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });

        return response()->json($data);
    }
}
