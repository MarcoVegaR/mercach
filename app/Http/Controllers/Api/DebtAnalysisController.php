<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DebtAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtAnalysisController extends Controller
{
    public function __construct(private DebtAnalysisService $service) {}

    /**
     * Lista paginada de concesionarios morosos con filtros
     */
    public function delinquentConcessionaires(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort_by' => 'string|in:debt_eur,debt_usd,debt_bs,days_overdue,name',
            'sort_dir' => 'string|in:asc,desc',
            'min_debt_eur' => 'numeric|min:0',
            'max_debt_eur' => 'numeric|min:0',
            'min_days' => 'integer|min:0',
            'market_id' => 'integer|exists:markets,id',
            'period_from' => ['string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'period_to' => ['string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'search' => 'string|max:255',
        ]);

        $data = $this->service->getDelinquentConcessionaires($filters);

        return response()->json($data);
    }

    /**
     * Lista paginada de locales morosos con filtros
     */
    public function delinquentLocals(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort_by' => 'string|in:debt_eur,debt_usd,debt_bs,days_overdue,code',
            'sort_dir' => 'string|in:asc,desc',
            'min_debt_eur' => 'numeric|min:0',
            'min_days' => 'integer|min:0',
            'local_type_id' => 'integer|exists:local_types,id',
            'market_id' => 'integer|exists:markets,id',
            'period_from' => ['string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'period_to' => ['string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'local_code_from' => 'string|max:30',
            'local_code_to' => 'string|max:30',
            'search' => 'string|max:255',
        ]);

        $data = $this->service->getDelinquentLocals($filters);

        return response()->json($data);
    }

    /**
     * Lista de concesionarios solventes
     */
    public function solventConcessionaires(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'months_solvent' => 'integer|min:1',
            'market_id' => 'integer|exists:markets,id',
            'search' => 'string|max:255',
        ]);

        $data = $this->service->getSolventConcessionaires($filters);

        return response()->json($data);
    }

    /**
     * Distribuciones agregadas para gráficas
     */
    public function distributions(Request $request): JsonResponse
    {
        $force = $request->boolean('force');
        $data = $this->service->getDistributions($force);

        return response()->json($data);
    }

    /**
     * Exportar datos a CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'scope' => 'required|string|in:concessionaires,locals',
            'format' => 'string|in:csv,xlsx,json',
            'sort_by' => 'string|in:debt_eur,debt_usd,debt_bs,days_overdue,name,code',
            'sort_dir' => 'string|in:asc,desc',
            'market_id' => 'integer|exists:markets,id',
            'min_debt_eur' => 'numeric|min:0',
            'min_days' => 'integer|min:0',
            'local_type_id' => 'integer|exists:local_types,id',
            'period_from' => ['string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'period_to' => ['string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'local_code_from' => 'string|max:30',
            'local_code_to' => 'string|max:30',
            'search' => 'string|max:255',
        ]);

        return $this->service->export($filters);
    }
}
