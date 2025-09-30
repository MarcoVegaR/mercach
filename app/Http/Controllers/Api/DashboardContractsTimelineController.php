<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardContractsTimelineController extends Controller
{
    /**
     * Get contracts timeline ordered by start or end date.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewCharts', \App\Models\User::class);

        $validated = $request->validate([
            'sort_by' => 'string|in:start_date,end_date',
            'order' => 'string|in:asc,desc',
            'limit' => 'integer|min:1|max:100',
        ]);

        $sortBy = (string) ($validated['sort_by'] ?? 'start_date');
        $order = (string) ($validated['order'] ?? 'asc');
        $limit = (int) ($validated['limit'] ?? 20);

        $cacheKey = sprintf('dash:timeline:%s:%s:%d', $sortBy, $order, $limit);

        $data = Cache::remember($cacheKey, 120, function () use ($sortBy, $order, $limit): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Get vigente contracts with their concessionaire names
            $query = DB::table('contracts as ct')
                ->join('contract_statuses as cs', 'cs.id', '=', 'ct.contract_status_id')
                ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'ct.id')
                ->join('concessionaires as c', 'c.id', '=', 'cc.concessionaire_id')
                ->where('cs.code', '=', 'VIG')
                ->where('ct.start_date', '<=', $today)
                ->where(function ($q) use ($today): void {
                    $q->whereNull('ct.end_date')
                        ->orWhere('ct.end_date', '>=', $today);
                })
                ->whereNull('ct.deleted_at')
                ->whereNull('c.deleted_at')
                ->select(
                    'ct.id',
                    DB::raw('ct.number as code'),
                    DB::raw('ct.start_date as start_date'),
                    'ct.end_date',
                    DB::raw('(COALESCE(ct.end_date, CURRENT_DATE) - ct.start_date) as duration_total_days'),
                    DB::raw('(CURRENT_DATE - ct.start_date) as elapsed_days'),
                    DB::raw('CASE WHEN ct.end_date IS NULL THEN NULL ELSE (ct.end_date - CURRENT_DATE) END as remaining_days'),
                    DB::raw("STRING_AGG(c.full_name, ', ' ORDER BY c.full_name) as concessionaire_names")
                )
                ->groupBy('ct.id', 'ct.number', 'ct.start_date', 'ct.end_date');

            if ($sortBy === 'start_date') {
                $query->orderBy('ct.start_date', $order);
                $query->orderBy('ct.id', $order); // Secondary sort for consistency
            } else {
                // end_date can be NULL (vigente indefinido)
                // Always push NULLS to the end to emphasize defined end dates first
                if ($order === 'asc') {
                    $query->orderByRaw('ct.end_date ASC NULLS LAST');
                } else {
                    $query->orderByRaw('ct.end_date DESC NULLS LAST');
                }
                $query->orderBy('ct.id', $order); // Secondary sort for consistency
            }
            $query->limit($limit);

            $items = $query->get()->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'duration_total_days' => (int) $row->duration_total_days,
                'elapsed_days' => (int) $row->elapsed_days,
                'remaining_days' => $row->remaining_days !== null ? (int) $row->remaining_days : null,
                'concessionaire_names' => (string) $row->concessionaire_names,
            ])->all();

            return [
                'sort_by' => $sortBy,
                'order' => $order,
                'items' => $items,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });

        return response()->json($data);
    }
}
