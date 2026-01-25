<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Bank Validations
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Show the bank validations report page.
     */
    public function bankValidations(Request $request): Response
    {
        $this->authorize('viewBankValidations', 'Report');

        $filters = $this->normalizeBankValidationFilters($request);

        $result = $this->reportService->getBankValidations(
            filters: $filters,
            search: trim((string) $request->input('q', '')),
            sort: (string) $request->input('sort', 'paid_on'),
            dir: strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(max((int) $request->input('per_page', 15), 10), 100)
        );

        return Inertia::render('reports/bank-validations/index', $result);
    }

    /**
     * Export bank validations report.
     */
    public function exportBankValidations(Request $request): SymfonyResponse
    {
        $this->authorize('exportBankValidations', 'Report');

        $filters = $this->normalizeBankValidationFilters($request);

        return $this->reportService->exportBankValidations(
            filters: $filters,
            search: trim((string) $request->input('q', '')),
            format: (string) $request->input('format', 'csv')
        );
    }

    public function dailyBankReconciliation(Request $request): Response
    {
        $this->authorize('viewDailyBankReconciliation', 'Report');

        $result = $this->reportService->getDailyBankReconciliation(
            filters: $this->normalizeFilters($request),
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(max((int) $request->input('per_page', 25), 10), 200)
        );

        return Inertia::render('reports/daily-bank-reconciliation', $result);
    }

    public function exportDailyBankReconciliation(Request $request): SymfonyResponse
    {
        $this->authorize('exportDailyBankReconciliation', 'Report');

        return $this->reportService->exportDailyBankReconciliation(
            filters: $this->normalizeFilters($request),
            format: (string) $request->input('format', 'csv')
        );
    }

    /**
     * Normalize bank validation filters from request (backward compatibility).
     *
     * @return array<string, mixed>
     */
    private function normalizeBankValidationFilters(Request $request): array
    {
        $filters = (array) $request->input('filters', []);

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $filters['paid_between'] = [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
            ];
        }
        if ($request->filled('response_code')) {
            $filters['response_code'] = $request->input('response_code');
        }
        if ($request->filled('status')) {
            $filters['status'] = $request->input('status');
        }

        return $filters;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contracts Unsigned
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Show the unsigned contracts report page.
     */
    public function contractsUnsigned(Request $request): Response
    {
        $this->authorize('viewContractsUnsigned', 'Report');

        $result = $this->reportService->getContractsUnsigned(
            filters: (array) $request->input('filters', []),
            search: trim((string) $request->input('q', '')),
            sort: (string) $request->input('sort', 'start_date'),
            dir: strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(max((int) $request->input('per_page', 15), 10), 100)
        );

        return Inertia::render('reports/contracts-unsigned', $result);
    }

    /**
     * Export unsigned contracts report.
     */
    public function exportContractsUnsigned(Request $request): SymfonyResponse
    {
        $this->authorize('exportContractsUnsigned', 'Report');

        return $this->reportService->exportContractsUnsigned(
            filters: $this->normalizeFilters($request),
            search: trim((string) $request->input('q', '')),
            format: (string) $request->input('format', 'csv')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Concessionaire Changes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Show concessionaire changes per local report.
     */
    public function concessionaireChanges(Request $request): Response
    {
        $this->authorize('viewConcessionaireChanges', 'Report');

        $result = $this->reportService->getConcessionaireChanges(
            filters: (array) $request->input('filters', []),
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(max((int) $request->input('per_page', 25), 10), 200)
        );

        return Inertia::render('reports/concessionaire-changes', $result);
    }

    /**
     * Export concessionaire changes report.
     */
    public function exportConcessionaireChanges(Request $request): SymfonyResponse
    {
        $this->authorize('exportConcessionaireChanges', 'Report');

        return $this->reportService->exportConcessionaireChanges(
            filters: $this->normalizeFilters($request),
            format: (string) $request->input('format', 'csv')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Locals Recovered
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Show recovered locals report.
     */
    public function localsRecovered(Request $request): Response
    {
        $this->authorize('viewLocalsRecovered', 'Report');

        $result = $this->reportService->getLocalsRecovered(
            filters: (array) $request->input('filters', []),
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(max((int) $request->input('per_page', 25), 10), 200)
        );

        return Inertia::render('reports/locals-recovered', $result);
    }

    /**
     * Export recovered locals report.
     */
    public function exportLocalsRecovered(Request $request): SymfonyResponse
    {
        $this->authorize('exportLocalsRecovered', 'Report');

        return $this->reportService->exportLocalsRecovered(
            filters: $this->normalizeFilters($request),
            format: (string) $request->input('format', 'csv')
        );
    }

    public function localsFinancialStatus(Request $request): Response
    {
        $this->authorize('viewLocalsFinancialStatus', 'Report');

        $result = $this->reportService->getLocalsFinancialStatus(
            filters: $this->normalizeFilters($request),
            search: trim((string) $request->input('q', '')),
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(max((int) $request->input('per_page', 25), 10), 200)
        );

        return Inertia::render('reports/locals-financial-status', $result);
    }

    public function exportLocalsFinancialStatus(Request $request): SymfonyResponse
    {
        $this->authorize('exportLocalsFinancialStatus', 'Report');

        return $this->reportService->exportLocalsFinancialStatus(
            filters: $this->normalizeFilters($request),
            search: trim((string) $request->input('q', '')),
            format: (string) $request->input('format', 'csv')
        );
    }

    private function normalizeFilters(Request $request): mixed
    {
        $filters = $request->input('filters', []);

        if (is_string($filters) && $filters !== '') {
            $decoded = json_decode($filters, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return is_array($filters) ? $filters : [];
    }
}
