<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardApiController
{
    public function __construct(private readonly DashboardService $service) {}

    public function kpis(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getKpis($filters);

        return response()->json($data);
    }

    /**
     * Spec endpoint: /api/dashboard/locals/by-location (ALL locals by location)
     */
    public function localsByLocation(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getLocalsDistributionByLocation($filters);

        $items = array_map(
            fn (array $row) => [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (int) ($row['value'] ?? 0),
                'location_id' => (int) ($row['id'] ?? 0),
            ],
            $data['items'] ?? []
        );

        return response()->json([
            'items' => $items,
            'total' => (int) ($data['total'] ?? 0),
            'generated_at' => $data['generated_at'] ?? now()->toIso8601String(),
        ]);
    }

    /**
     * Spec endpoint: /api/dashboard/concessionaires/by-type
     */
    public function concessionairesByType(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getConcessionairesByType($filters);

        $items = array_map(
            fn (array $row) => [
                'label' => (string) ($row['label'] ?? ''),
                'code' => (string) ($row['code'] ?? ''),
                'value' => (int) ($row['value'] ?? 0),
            ],
            $data['items'] ?? []
        );

        return response()->json([
            'items' => $items,
            'total' => (int) ($data['total'] ?? 0),
            'generated_at' => $data['generated_at'] ?? now()->toIso8601String(),
        ]);
    }

    /**
     * Spec endpoint: /api/dashboard/concessionaires/natural-by-document (only PNAT; V vs E)
     */
    public function naturalConcessionairesByDocument(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getNaturalConcessionairesByDocument($filters);

        $items = array_map(
            fn (array $row) => [
                'label' => (string) ($row['label'] ?? ''),
                'code' => (string) ($row['code'] ?? ''),
                'value' => (int) ($row['value'] ?? 0),
            ],
            $data['items'] ?? []
        );

        return response()->json([
            'items' => $items,
            'total' => (int) ($data['total'] ?? 0),
            'generated_at' => $data['generated_at'] ?? now()->toIso8601String(),
        ]);
    }

    /**
     * Spec endpoint: /api/dashboard/contracts/by-status
     * Returns: { items: [{ id, code, label, value }], total }
     */
    public function contractsByStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getContractsDistributionByStatus($filters);

        return response()->json($data);
    }

    /**
     * Spec endpoint: /api/dashboard/charges/by-kind
     */
    public function chargesByKind(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $force = $request->boolean('force');
        $data = $this->service->getChargesDistributionByKind($filters, $force);

        return response()->json($data);
    }

    /**
     * Spec endpoint: /api/dashboard/charges/by-status
     */
    public function chargesByStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $force = $request->boolean('force');
        $data = $this->service->getChargesDistributionByStatus($filters, $force);

        return response()->json($data);
    }

    /**
     * Spec endpoint: /api/dashboard/charges/open-by-month?months=12
     */
    public function chargesOpenByMonth(Request $request): \Illuminate\Http\JsonResponse
    {
        $months = (int) $request->query('months', 12);
        $months = max(1, min(60, $months));
        $data = $this->service->getOpenChargesByMonth($months);

        return response()->json($data);
    }

    /**
     * Spec endpoint: /api/dashboard/contracts/by-type
     * Returns: { items: [{ id, code, label, value }], total }
     */
    public function contractsByType(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getContractsDistributionByType($filters);

        return response()->json($data);
    }

    public function localsAvailableDistribution(Request $request): \Illuminate\Http\JsonResponse
    {
        $by = (string) $request->query('by', 'local_type_id');
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getLocalsAvailableDistribution($by, $filters);

        return response()->json($data);
    }

    /**
     * Alias endpoint compatible with spec: /api/dashboard/distributions?by=local_type&scope=available
     */
    public function distributions(Request $request): \Illuminate\Http\JsonResponse
    {
        $byParam = (string) $request->query('by', 'local_type');
        $scope = (string) $request->query('scope', 'available');
        $filters = (array) $request->query('filters', []);

        // v1 only supports available locals distribution by local_type
        $by = $byParam === 'local_type' ? 'local_type_id' : 'local_type_id';
        if ($scope !== 'available') {
            // For now only available is supported; maintain consistent output
            // Could return 400, but staying permissive and ignoring unknown scopes.
        }

        $data = $this->service->getLocalsAvailableDistribution($by, $filters);

        return response()->json([
            'by' => 'local_type',
            'items' => $data['items'] ?? [],
            'total' => $data['total'] ?? 0,
            'status_disp_id' => $data['status_disp_id'] ?? null,
            'generated_at' => $data['generated_at'] ?? now()->toIso8601String(),
        ]);
    }

    /**
     * Spec endpoint: /api/dashboard/locals/available-by-type
     * Returns: { items: [{ label, value, type_id }], total }
     */
    public function localsAvailableByType(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getLocalsAvailableDistribution('local_type_id', $filters);

        $items = array_map(
            fn (array $row) => [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (int) ($row['value'] ?? 0),
                'type_id' => (int) ($row['id'] ?? 0),
            ],
            $data['items'] ?? []
        );

        return response()->json([
            'items' => $items,
            'total' => (int) ($data['total'] ?? 0),
            'status_disp_id' => $data['status_disp_id'] ?? null,
            'generated_at' => $data['generated_at'] ?? now()->toIso8601String(),
        ]);
    }

    /**
     * Spec endpoint: /api/dashboard/locals/by-type (ALL locals by type)
     */
    public function localsByType(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getLocalsDistributionByType('local_type_id', $filters);

        $items = array_map(
            fn (array $row) => [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (int) ($row['value'] ?? 0),
                'type_id' => (int) ($row['id'] ?? 0),
            ],
            $data['items'] ?? []
        );

        return response()->json([
            'items' => $items,
            'total' => (int) ($data['total'] ?? 0),
            'generated_at' => $data['generated_at'] ?? now()->toIso8601String(),
        ]);
    }

    /**
     * Get debt and risk metrics for dashboard
     */
    public function debtMetrics(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $force = $request->boolean('force');
        $data = $this->service->getDebtMetrics($filters, $force);

        return response()->json($data);
    }

    /**
     * Get payment statistics for dashboard
     */
    public function paymentMetrics(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = (array) $request->query('filters', []);
        $data = $this->service->getPaymentMetrics($filters);

        return response()->json($data);
    }

    /**
     * Get payment revenue breakdown by bank and method for a paid_on range.
     */
    public function paymentRevenueBreakdown(Request $request): \Illuminate\Http\JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $fromStr = is_string($from) && $from !== '' ? $from : null;
        $toStr = is_string($to) && $to !== '' ? $to : null;

        $data = $this->service->getPaymentRevenueBreakdown($fromStr, $toStr);

        return response()->json($data);
    }

    /**
     * Get breakdown of VIG contracts (signed vs unsigned)
     */
    public function vigentesBreakdown(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $this->service->getVigentesBreakdown();

        return response()->json($data);
    }

    /**
     * Get monthly payment revenue for chart
     */
    public function paymentTrend(Request $request): \Illuminate\Http\JsonResponse
    {
        $group = strtolower((string) $request->query('group', 'month'));
        $months = (int) $request->query('months', 12);
        $days = (int) $request->query('days', 30);
        $months = max(1, min(60, $months));
        $days = max(1, min(365, $days));

        $voidStatusId = (int) (\Illuminate\Support\Facades\DB::table('payment_statuses')->where('code', 'VOID')->value('id') ?? 0);

        $base = \Illuminate\Support\Facades\DB::table('payments')
            ->leftJoin('payment_types as pt_filter', 'pt_filter.id', '=', 'payments.payment_type_id')
            ->whereNull('payments.deleted_at')
            ->whereRaw("COALESCE(NULLIF(UPPER(TRIM(pt_filter.code)), ''), NULLIF(UPPER(TRIM(payments.method)), ''), '') <> ?", ['EXO'])
            ->when($voidStatusId > 0, fn ($q) => $q->where('payments.payment_status_id', '!=', $voidStatusId));

        if ($group === 'day') {
            $data = $base
                ->selectRaw("TO_CHAR(paid_on, 'YYYY-MM-DD') as month")
                ->selectRaw("TO_CHAR(paid_on, 'DD/MM') as month_label")
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(amount_bs_minor) as amount_bs_minor')
                ->whereRaw("paid_on >= CURRENT_DATE - INTERVAL '{$days} days'")
                ->groupByRaw("TO_CHAR(paid_on, 'YYYY-MM-DD'), TO_CHAR(paid_on, 'DD/MM')")
                ->orderBy('month', 'asc')
                ->get()
                ->map(fn ($row) => [
                    'month' => (string) $row->month,
                    'month_label' => (string) $row->month_label,
                    'count' => (int) $row->count,
                    'amount_bs_minor' => (int) $row->amount_bs_minor,
                ])
                ->all();
        } else {
            $data = $base
                ->selectRaw("TO_CHAR(paid_on, 'YYYY-MM') as month")
                ->selectRaw("TO_CHAR(paid_on, 'Mon YY') as month_label")
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(amount_bs_minor) as amount_bs_minor')
                ->whereRaw("paid_on >= CURRENT_DATE - INTERVAL '{$months} months'")
                ->groupByRaw("TO_CHAR(paid_on, 'YYYY-MM'), TO_CHAR(paid_on, 'Mon YY')")
                ->orderBy('month', 'asc')
                ->get()
                ->map(fn ($row) => [
                    'month' => (string) $row->month,
                    'month_label' => (string) $row->month_label,
                    'count' => (int) $row->count,
                    'amount_bs_minor' => (int) $row->amount_bs_minor,
                ])
                ->all();
        }

        return response()->json([
            'items' => $data,
            'generated_at' => \Carbon\Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Spec endpoint: /api/dashboard/debt/overdue-counts?days=90
     */
    public function overdueCounts(Request $request): \Illuminate\Http\JsonResponse
    {
        $days = (int) $request->query('days', 90);
        $days = max(1, min(3650, $days));
        $force = $request->boolean('force');
        $data = $this->service->getOverdueCounts($days, $force);

        return response()->json($data);
    }

    /**
     * Spec endpoint: /api/dashboard/revenue/projection?period=YYYY-MM
     * Returns total monthly projected revenue and breakdown by local type
     */
    public function revenueProjection(Request $request): \Illuminate\Http\JsonResponse
    {
        $period = $request->query('period');
        $periodStr = is_string($period) && $period !== '' ? $period : null;
        $data = $this->service->getRevenueProjection($periodStr);

        return response()->json($data);
    }

    /**
     * Spec endpoint: /api/dashboard/revenue/top-locals?period=YYYY-MM&limit=10
     * Returns top locals by projected monthly revenue
     */
    public function topRevenueLocals(Request $request): \Illuminate\Http\JsonResponse
    {
        $period = $request->query('period');
        $periodStr = is_string($period) && $period !== '' ? $period : null;
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min(100, $limit));

        $data = $this->service->getTopRevenueLocals($periodStr, $limit);

        return response()->json($data);
    }

    /**
     * Dashboard alerts based on business thresholds.
     */
    public function alerts(): \Illuminate\Http\JsonResponse
    {
        $data = $this->service->getAlerts();

        return response()->json($data);
    }

    /**
     * Revenue sparkline for KPI cards.
     */
    public function revenueSparkline(Request $request): \Illuminate\Http\JsonResponse
    {
        $months = (int) $request->query('months', 6);
        $months = max(1, min(24, $months));
        $data = $this->service->getRevenueSparkline($months);

        return response()->json($data);
    }
}
