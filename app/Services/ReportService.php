<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Reports\BankValidationsQuery;
use App\Services\Reports\ConcessionaireChangesQuery;
use App\Services\Reports\ContractsUnsignedQuery;
use App\Services\Reports\DailyBankReconciliationQuery;
use App\Services\Reports\LocalsFinancialStatusQuery;
use App\Services\Reports\LocalsRecoveredQuery;
use App\Support\CsvExportHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Service for generating reports.
 *
 * Orchestrates query builders and export helpers to provide
 * a clean API for the ReportController.
 */
class ReportService
{
    public function __construct(
        private CsvExportHelper $exportHelper,
    ) {}

    public function getDailyBankReconciliation(
        mixed $filters,
        int $page = 1,
        int $perPage = 25
    ): mixed {
        $filters = is_array($filters) ? $filters : [];
        $query = new DailyBankReconciliationQuery;
        $paginator = $query->withFilters($filters)->paginate($perPage, $page);

        $destinationBanks = DB::table('company_bank_accounts as cba')
            ->join('banks as b', 'b.id', '=', 'cba.bank_id')
            ->whereNull('cba.deleted_at')
            ->whereNull('b.deleted_at')
            ->where('cba.is_active', true)
            ->where('b.is_active', true)
            ->distinct()
            ->orderBy('b.name')
            ->get(['b.id', 'b.name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->all();

        $statuses = DB::table('payment_statuses')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn ($m) => ['code' => (string) $m->code, 'name' => (string) $m->name])
            ->all();

        $methods = DB::table('payment_types')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn ($m) => ['code' => (string) $m->code, 'name' => (string) $m->name])
            ->all();

        return [
            'rows' => $query->transform($paginator)->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filterOptions' => [
                'destination_banks' => $destinationBanks,
                'statuses' => $statuses,
                'methods' => $methods,
            ],
        ];
    }

    public function exportDailyBankReconciliation(mixed $filters, string $format = 'csv'): SymfonyResponse
    {
        $filters = is_array($filters) ? $filters : [];
        $format = strtolower($format);
        if (! in_array($format, ['csv', 'json'], true)) {
            $format = 'csv';
        }

        $query = new DailyBankReconciliationQuery;
        $results = $query->withFilters($filters)->get();
        $data = $query->transformForExport($results);

        return $this->exportHelper->export($this->exportHelper->limitForExport($data), 'conciliacion_diaria_bancos', $format);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bank Validations Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get bank validations report data.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, meta: array<string, mixed>, responseCodes: array<int, array{code: string, label: string}>}
     */
    public function getBankValidations(
        array $filters,
        string $search,
        string $sort = 'paid_on',
        string $dir = 'desc',
        int $page = 1,
        int $perPage = 15
    ): array {
        $query = new BankValidationsQuery;
        $rows = $query->withFilters($filters)->search($search)->execute();

        // Sort
        $sorted = $this->sortCollection($rows, $sort, $dir, [
            'reference' => fn ($r) => (string) ($r['reference'] ?? ''),
            'amount_bs' => fn ($r) => (float) ($r['amount_bs'] ?? 0.0),
            'gateway_resp_code' => fn ($r) => (string) ($r['gateway_resp_code'] ?? ''),
            'status' => fn ($r) => (string) ($r['status'] ?? ''),
            'paid_on' => fn ($r) => (string) ($r['paid_on'] ?? ''),
        ]);

        // Paginate
        $paginated = $this->paginateCollection($sorted, $page, $perPage);

        return [
            'rows' => $paginated['items'],
            'meta' => $paginated['meta'],
            'responseCodes' => BankValidationsQuery::getResponseCodes(),
        ];
    }

    /**
     * Export bank validations report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportBankValidations(array $filters, string $search, string $format = 'csv'): SymfonyResponse
    {
        $query = new BankValidationsQuery;
        $rows = $query->withFilters($filters)->search($search)->execute();

        // Sort and limit
        $sorted = $rows->sortBy(fn ($r) => (string) ($r['paid_on'] ?? '').'|'.(string) ($r['reference'] ?? ''), SORT_NATURAL, true);
        $limited = $this->exportHelper->limitForExport($sorted);

        // Transform for export
        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $data */
        $data = $limited->map(fn (array $row) => [
            'Fecha de pago' => $row['paid_on'] ?? null,
            'Nro. Referencia' => $row['reference'] ?? null,
            'Cuenta/Origen' => $row['origin_account'] ?? null,
            'Cuenta/Destino' => $row['destination_account'] ?? null,
            'Monto' => number_format((float) ($row['amount_bs'] ?? 0.0), 2, ',', '.'),
            'Cedula/RIF' => $row['payer_document'] ?? null,
            'Codigo/Respuesta' => $row['gateway_resp_code'] ?? null,
            'Respuesta' => $row['gateway_message'] ?? null,
            'ReqId' => $row['req_id'] ?? null,
        ]);

        return $this->exportHelper->export($data, 'validaciones_bancarias', $format);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contracts Unsigned Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get unsigned contracts report data.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: array<int, array<string, mixed>>, meta: array<string, mixed>, filterOptions: array<string, mixed>}
     */
    public function getContractsUnsigned(
        array $filters,
        string $search,
        string $sort = 'start_date',
        string $dir = 'desc',
        int $page = 1,
        int $perPage = 15
    ): array {
        $query = new ContractsUnsignedQuery;
        $paginator = $query
            ->search($search)
            ->withFilters($filters)
            ->orderBy($sort, $dir)
            ->paginate($perPage, $page);

        return [
            'rows' => $query->transform($paginator)->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filterOptions' => ContractsUnsignedQuery::getFilterOptions(),
        ];
    }

    /**
     * Export unsigned contracts report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportContractsUnsigned(array $filters, string $search, string $format = 'csv'): SymfonyResponse
    {
        $query = new ContractsUnsignedQuery;
        $contracts = $query->search($search)->withFilters($filters)->get();
        $data = $query->transformForExport($contracts);

        return $this->exportHelper->export($this->exportHelper->limitForExport($data), 'contratos_sin_firmar', $format);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Concessionaire Changes Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get concessionaire changes report data.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function getConcessionaireChanges(
        array $filters,
        int $page = 1,
        int $perPage = 15
    ): array {
        $changedBetween = (array) ($filters['changed_between'] ?? []);

        $query = new ConcessionaireChangesQuery;
        $events = $query
            ->changedBetween(
                ! empty($changedBetween['from']) ? (string) $changedBetween['from'] : null,
                ! empty($changedBetween['to']) ? (string) $changedBetween['to'] : null
            )
            ->execute();

        $paginated = $this->paginateCollection($events, $page, $perPage);

        return [
            'rows' => $paginated['items']->all(),
            'meta' => $paginated['meta'],
        ];
    }

    /**
     * Export concessionaire changes report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportConcessionaireChanges(array $filters, string $format = 'csv'): SymfonyResponse
    {
        $changedBetween = (array) ($filters['changed_between'] ?? []);

        $query = new ConcessionaireChangesQuery;
        $data = $query
            ->changedBetween(
                ! empty($changedBetween['from']) ? (string) $changedBetween['from'] : null,
                ! empty($changedBetween['to']) ? (string) $changedBetween['to'] : null
            )
            ->executeForExport();

        return $this->exportHelper->export($this->exportHelper->limitForExport($data), 'cambios_cesionarios', $format);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Locals Recovered Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get locals recovered report data.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function getLocalsRecovered(
        array $filters,
        int $page = 1,
        int $perPage = 25
    ): array {
        $query = new LocalsRecoveredQuery;
        $paginator = $query->withFilters($filters)->paginate($perPage, $page);

        return [
            'rows' => $query->transform($paginator)->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Export locals recovered report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportLocalsRecovered(array $filters, string $format = 'csv'): SymfonyResponse
    {
        $query = new LocalsRecoveredQuery;
        $results = $query->withFilters($filters)->get();
        $data = $query->transformForExport($results);

        return $this->exportHelper->export($data, 'locales_recuperados', $format);
    }

    public function getLocalsFinancialStatus(
        mixed $filters,
        string $search,
        int $page = 1,
        int $perPage = 25
    ): mixed {
        $filters = is_array($filters) ? $filters : [];
        $query = new LocalsFinancialStatusQuery;
        $paginator = $query->withFilters($filters)->search($search)->paginate($perPage, $page);

        return [
            'rows' => $query->transform($paginator)->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function exportLocalsFinancialStatus(mixed $filters, string $search, string $format = 'csv'): SymfonyResponse
    {
        $filters = is_array($filters) ? $filters : [];
        $format = strtolower($format);
        if (! in_array($format, ['csv', 'json', 'xlsx'], true)) {
            $format = 'csv';
        }

        $query = new LocalsFinancialStatusQuery;
        $results = $query->withFilters($filters)->search($search)->get();
        $rows = $query->transformForExport($results);

        $columns = [
            'market_name' => 'Mercado',
            'concessionaire_id' => 'ID Cesionario',
            'concessionaire_name' => 'Cesionario',
            'locals_count' => 'Nro. Locales',
            'concessionaire_total_area_m2' => 'Área total (m²)',
            'locals' => 'Locales',
            'locals_detail' => 'Detalle por local',
            'contracts' => 'Contratos',
            'last_paid_rent_period' => 'Último mes pagado (Uso)',
            'last_paid_condo_period' => 'Último mes pagado (Condominio)',
            'rent_debt_currency' => 'Moneda (Uso)',
            'rent_debt' => 'Deuda (Uso)',
            'condo_debt_currency' => 'Moneda (Condominio)',
            'condo_debt' => 'Deuda (Condominio)',
            'rent_debt_bs' => 'Deuda (Uso) Bs',
            'condo_debt_bs' => 'Deuda (Condominio) Bs',
            'total_debt_bs' => 'Deuda total Bs',
        ];

        $filename = 'estado_financiero_locales_'.date('Y-m-d_His').'.'.$format;

        $exporter = app('exporter.'.$format);
        $response = $exporter->stream($rows, $columns);
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sort a collection by a given column.
     *
     * @param  Collection<int, array<string, mixed>>  $collection
     * @param  array<string, callable>  $sortCallbacks
     * @return Collection<int, array<string, mixed>>
     */
    private function sortCollection(Collection $collection, string $sort, string $dir, array $sortCallbacks): Collection
    {
        $callback = $sortCallbacks[$sort] ?? ($sortCallbacks['paid_on'] ?? fn ($r) => '');

        return $collection->sortBy($callback, SORT_NATURAL, $dir === 'desc');
    }

    /**
     * Paginate a collection manually.
     *
     * @param  Collection<int, array<string, mixed>>  $collection
     * @return array{items: Collection<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private function paginateCollection(Collection $collection, int $page, int $perPage): array
    {
        $total = $collection->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;
        $items = $collection->slice($offset, $perPage)->values();

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $page,
                'from' => $total === 0 ? null : ($offset + 1),
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => $total === 0 ? null : ($offset + $items->count()),
                'total' => $total,
            ],
        ];
    }
}
