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
}
