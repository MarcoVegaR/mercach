<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\FxRateServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Servicio de análisis de deuda para reportes agregados.
 *
 * NOTA: Este servicio utiliza FxRateServiceInterface para obtener tasas FX
 * y aplica la misma política de truncamiento que FxConversionHelper para
 * garantizar consistencia en los cálculos financieros.
 */
class DebtAnalysisService
{
    public function __construct(
        private FxRateServiceInterface $fxService,
    ) {}

    /**
     * Obtener concesionarios morosos con paginación y filtros.
     *
     * Montos se devuelven en su moneda de origen (EUR / USD) y en Bs outstanding.
     * Outstanding = amount_bs_issued − pagos − créditos (floor 0).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDelinquentConcessionaires(array $filters): array
    {
        if ((bool) ($filters['_skip_aggregate_windows'] ?? false)) {
            return $this->runDelinquentConcessionairesQuery($filters);
        }

        return $this->rememberDebtAnalysisResult(
            'delinquent_concessionaires',
            $filters,
            fn (): array => $this->runDelinquentConcessionairesQuery($filters),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function runDelinquentConcessionairesQuery(array $filters): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $skipAggregateWindows = (bool) ($filters['_skip_aggregate_windows'] ?? false);
        $perPage = $skipAggregateWindows
            ? (int) ($filters['per_page'] ?? 500)
            : min((int) ($filters['per_page'] ?? 25), 100);
        $sortBy = $filters['sort_by'] ?? 'debt_bs';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $periodFrom = ! empty($filters['period_from'])
            ? Carbon::createFromFormat('Y-m', (string) $filters['period_from'])->startOfMonth()->toDateString()
            : null;
        $periodToExclusive = ! empty($filters['period_to'])
            ? Carbon::createFromFormat('Y-m', (string) $filters['period_to'])->addMonth()->startOfMonth()->toDateString()
            : null;

        $fxRate = $this->getActiveFxRate();
        $usdRateObj = $this->fxService->resolveAt('USD', Carbon::today());
        $usdRate = $usdRateObj ? (float) $usdRateObj->getAttribute('rate_to_ves') : 1.0;
        $eurRateMinor = (int) round($fxRate * 100);
        $usdRateMinor = (int) round($usdRate * 100);
        $periodSql = '';
        $periodBindings = [];
        $overdueLocalDateSql = '';
        $overdueLocalBindings = [];
        $overdueConcessionaireDateSql = '';
        $overdueConcessionaireBindings = [];

        // period_from/period_to now filter by charges.period, not due_on
        if ($periodFrom !== null) {
            $periodSql .= ' AND ch.period >= ?';
            $periodBindings[] = $periodFrom;
        }
        if ($periodToExclusive !== null) {
            $periodSql .= ' AND ch.period < ?';
            $periodBindings[] = $periodToExclusive;
        }

        // CTE-based query:
        // - Maps ALL open LOCAL charges (ISSUED/PARTIAL) through active contract by local
        // - Maps overdue LOCAL charges (ISSUED/PARTIAL, due_on <= today) separately
        // - Includes ALL open and overdue CONCESSIONAIRE charges directly
        // - Aggregates both open debt and overdue debt in original currencies and Bs
        $sql = "
            WITH allocs AS (
                SELECT charge_id, SUM(amount_bs_minor)::bigint AS paid_bs
                FROM payment_allocations WHERE deleted_at IS NULL
                GROUP BY charge_id
            ),
            credits AS (
                SELECT ca.charge_id,
                       SUM(CASE UPPER(COALESCE(cc.currency, 'VES'))
                           WHEN 'EUR' THEN (ca.amount_minor::bigint * {$eurRateMinor}) / 100
                           WHEN 'USD' THEN (ca.amount_minor::bigint * {$usdRateMinor}) / 100
                           ELSE ca.amount_minor
                       END)::bigint AS credit_bs
                FROM credit_applications ca
                LEFT JOIN customer_credits cc ON cc.id = ca.customer_credit_id
                WHERE ca.deleted_at IS NULL
                GROUP BY ca.charge_id
            ),
            active_contract_by_local AS (
                SELECT DISTINCT ON (cl.local_id)
                       cl.local_id,
                       cl.contract_id
                FROM contract_local cl
                INNER JOIN contracts ct ON ct.id = cl.contract_id
                INNER JOIN contract_statuses cts ON cts.id = ct.contract_status_id
                WHERE ct.deleted_at IS NULL
                  AND ct.start_date <= CURRENT_DATE
                  AND cts.code IN ('VIG', 'EXT', 'VENC')
                  AND (
                    (cts.code IN ('VIG', 'EXT') AND (ct.end_date IS NULL OR ct.end_date >= CURRENT_DATE))
                    OR cts.code = 'VENC'
                  )
                ORDER BY cl.local_id, ct.start_date DESC, ct.id DESC
            ),
            latest_contract_by_local AS (
                SELECT DISTINCT ON (cl.local_id)
                       cl.local_id,
                       cl.contract_id
                FROM contract_local cl
                INNER JOIN contracts ct ON ct.id = cl.contract_id
                WHERE ct.deleted_at IS NULL
                ORDER BY cl.local_id, ct.start_date DESC NULLS LAST, ct.id DESC
            ),
            primary_concessionaire_by_contract AS (
                SELECT DISTINCT ON (contract_id)
                       contract_id,
                       concessionaire_id
                FROM concessionaire_contract
                ORDER BY contract_id, is_primary DESC, id ASC
            ),
            open_local AS (
                SELECT ch.id AS charge_id,
                       ch.currency,
                       ch.amount_minor,
                       ch.contract_id,
                       COALESCE(al.paid_bs, 0) AS paid_bs,
                       COALESCE(cr.credit_bs, 0) AS credit_bs,
                       (CURRENT_DATE - ch.due_on::date) AS days_late,
                       ch.debtor_id AS local_id
                FROM charges ch
                INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                LEFT JOIN allocs al ON al.charge_id = ch.id
                LEFT JOIN credits cr ON cr.charge_id = ch.id
                WHERE chs.code IN ('ISSUED', 'PARTIAL')
                  AND ch.deleted_at IS NULL
                  {$periodSql}
                  AND ch.debtor_type = 'LOCAL'
            ),
            open_concessionaire AS (
                SELECT ch.id AS charge_id,
                       ch.currency,
                       ch.amount_minor,
                       COALESCE(al.paid_bs, 0) AS paid_bs,
                       COALESCE(cr.credit_bs, 0) AS credit_bs,
                       (CURRENT_DATE - ch.due_on::date) AS days_late,
                       ch.debtor_id AS concessionaire_id
                FROM charges ch
                INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                LEFT JOIN allocs al ON al.charge_id = ch.id
                LEFT JOIN credits cr ON cr.charge_id = ch.id
                WHERE chs.code IN ('ISSUED', 'PARTIAL')
                  AND ch.deleted_at IS NULL
                  {$periodSql}
                  AND ch.debtor_type = 'CONCESSIONAIRE'
            ),
            overdue_local AS (
                SELECT ch.id AS charge_id,
                       ch.currency,
                       ch.amount_minor,
                       ch.contract_id,
                       COALESCE(al.paid_bs, 0) AS paid_bs,
                       COALESCE(cr.credit_bs, 0) AS credit_bs,
                       (CURRENT_DATE - ch.due_on::date) AS days_late,
                       ch.debtor_id AS local_id
                FROM charges ch
                INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                LEFT JOIN allocs al ON al.charge_id = ch.id
                LEFT JOIN credits cr ON cr.charge_id = ch.id
                WHERE chs.code IN ('ISSUED', 'PARTIAL')
                  AND ch.due_on <= CURRENT_DATE
                  AND ch.deleted_at IS NULL
                  {$periodSql}
                  {$overdueLocalDateSql}
                  AND ch.debtor_type = 'LOCAL'
            ),
            overdue_concessionaire AS (
                SELECT ch.id AS charge_id,
                       ch.currency,
                       ch.amount_minor,
                       COALESCE(al.paid_bs, 0) AS paid_bs,
                       COALESCE(cr.credit_bs, 0) AS credit_bs,
                       (CURRENT_DATE - ch.due_on::date) AS days_late,
                       ch.debtor_id AS concessionaire_id
                FROM charges ch
                INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                LEFT JOIN allocs al ON al.charge_id = ch.id
                LEFT JOIN credits cr ON cr.charge_id = ch.id
                WHERE chs.code IN ('ISSUED', 'PARTIAL')
                  AND ch.due_on <= CURRENT_DATE
                  AND ch.deleted_at IS NULL
                  {$periodSql}
                  {$overdueConcessionaireDateSql}
                  AND ch.debtor_type = 'CONCESSIONAIRE'
            ),
            mapped_open_local AS (
                SELECT COALESCE(cc.concessionaire_id, 0) AS concessionaire_id,
                       ol.charge_id,
                       ol.currency,
                       ol.amount_minor,
                       ol.paid_bs,
                       ol.credit_bs,
                       ol.days_late,
                       ol.local_id,
                       l.market_id
                FROM open_local ol
                LEFT JOIN active_contract_by_local acl ON acl.local_id = ol.local_id
                LEFT JOIN latest_contract_by_local lcl ON lcl.local_id = ol.local_id
                LEFT JOIN primary_concessionaire_by_contract cc ON cc.contract_id = COALESCE(ol.contract_id, acl.contract_id, lcl.contract_id)
                LEFT JOIN locals l ON l.id = ol.local_id
            ),
            mapped_open_concessionaire AS (
                SELECT oc.concessionaire_id,
                       oc.charge_id,
                       oc.currency,
                       oc.amount_minor,
                       oc.paid_bs,
                       oc.credit_bs,
                       oc.days_late,
                       NULL::bigint AS local_id,
                       NULL::bigint AS market_id
                FROM open_concessionaire oc
                INNER JOIN concessionaires cn ON cn.id = oc.concessionaire_id AND cn.deleted_at IS NULL
            ),
            all_open AS (
                SELECT * FROM mapped_open_local
                UNION ALL
                SELECT * FROM mapped_open_concessionaire
            ),
            mapped_overdue_local AS (
                SELECT COALESCE(cc.concessionaire_id, 0) AS concessionaire_id,
                       ol.charge_id,
                       ol.currency,
                       ol.amount_minor,
                       ol.paid_bs,
                       ol.credit_bs,
                       ol.days_late,
                       ol.local_id,
                       l.market_id
                FROM overdue_local ol
                LEFT JOIN active_contract_by_local acl ON acl.local_id = ol.local_id
                LEFT JOIN latest_contract_by_local lcl ON lcl.local_id = ol.local_id
                LEFT JOIN primary_concessionaire_by_contract cc ON cc.contract_id = COALESCE(ol.contract_id, acl.contract_id, lcl.contract_id)
                LEFT JOIN locals l ON l.id = ol.local_id
            ),
            mapped_overdue_concessionaire AS (
                SELECT oc.concessionaire_id,
                       oc.charge_id,
                       oc.currency,
                       oc.amount_minor,
                       oc.paid_bs,
                       oc.credit_bs,
                       oc.days_late,
                       NULL::bigint AS local_id,
                       NULL::bigint AS market_id
                FROM overdue_concessionaire oc
                INNER JOIN concessionaires cn ON cn.id = oc.concessionaire_id AND cn.deleted_at IS NULL
            ),
            all_overdue AS (
                SELECT * FROM mapped_overdue_local
                UNION ALL
                SELECT * FROM mapped_overdue_concessionaire
            ),
            per_concessionaire AS (
                SELECT
                    COALESCE(cn.id, 0) AS id,
                    COALESCE(cn.full_name, 'Sin concesionario atribuible') AS full_name,
                    COALESCE(cn.document_number, '') AS document_number,
                    STRING_AGG(DISTINCT m.name, ', ' ORDER BY m.name) AS market_name,
                    ARRAY_REMOVE(ARRAY_AGG(DISTINCT ao.market_id), NULL)::int[] AS market_ids,
                    COUNT(DISTINCT ao.local_id)::int AS locals_count,
                    STRING_AGG(DISTINCT l.code, ', ' ORDER BY l.code) AS local_codes,
                    COUNT(DISTINCT ao.charge_id)::int AS charges_count,
                    SUM(CASE WHEN UPPER(COALESCE(ao.currency, 'VES')) = 'EUR'
                        THEN GREATEST(ao.amount_minor - ROUND(ao.paid_bs * 100.0 / {$eurRateMinor}) - ROUND(ao.credit_bs * 100.0 / {$eurRateMinor}), 0)
                        ELSE 0 END)::bigint AS outstanding_eur,
                    SUM(CASE WHEN UPPER(COALESCE(ao.currency, 'VES')) = 'USD'
                        THEN GREATEST(ao.amount_minor - ROUND(ao.paid_bs * 100.0 / {$usdRateMinor}) - ROUND(ao.credit_bs * 100.0 / {$usdRateMinor}), 0)
                        ELSE 0 END)::bigint AS outstanding_usd,
                    SUM(CASE WHEN UPPER(COALESCE(ao.currency, 'VES')) NOT IN ('EUR', 'USD')
                        THEN GREATEST(ao.amount_minor - ao.paid_bs - ao.credit_bs, 0)
                        ELSE 0 END)::bigint AS outstanding_ves,
                    SUM(CASE WHEN UPPER(COALESCE(ao.currency, 'VES')) = 'EUR'
                        THEN GREATEST(ao.amount_minor - ROUND(ao.paid_bs * 100.0 / {$eurRateMinor}) - ROUND(ao.credit_bs * 100.0 / {$eurRateMinor}), 0)
                        ELSE 0 END)::bigint AS overdue_eur,
                    SUM(CASE WHEN UPPER(COALESCE(ao.currency, 'VES')) = 'USD'
                        THEN GREATEST(ao.amount_minor - ROUND(ao.paid_bs * 100.0 / {$usdRateMinor}) - ROUND(ao.credit_bs * 100.0 / {$usdRateMinor}), 0)
                        ELSE 0 END)::bigint AS overdue_usd,
                    SUM(CASE WHEN UPPER(COALESCE(ao.currency, 'VES')) NOT IN ('EUR', 'USD')
                        THEN GREATEST(ao.amount_minor - ao.paid_bs - ao.credit_bs, 0)
                        ELSE 0 END)::bigint AS overdue_ves,
                    ROUND(AVG(ao.days_late)::numeric)::int AS days_overdue_avg,
                    MAX(ao.days_late)::int AS days_overdue_max
                FROM all_open ao
                LEFT JOIN concessionaires cn ON cn.id = ao.concessionaire_id
                LEFT JOIN markets m ON m.id = ao.market_id
                LEFT JOIN locals l ON l.id = ao.local_id
                WHERE ao.concessionaire_id = 0 OR cn.deleted_at IS NULL
                GROUP BY COALESCE(cn.id, 0), COALESCE(cn.full_name, 'Sin concesionario atribuible'), COALESCE(cn.document_number, '')
            ),
            per_concessionaire_overdue AS (
                SELECT
                    COALESCE(cn.id, 0) AS id,
                    SUM(CASE WHEN UPPER(COALESCE(ao.currency, 'VES')) = 'EUR'
                        THEN GREATEST(ao.amount_minor - ROUND(ao.paid_bs * 100.0 / {$eurRateMinor}) - ROUND(ao.credit_bs * 100.0 / {$eurRateMinor}), 0)
                        ELSE 0 END)::bigint AS overdue_eur,
                    SUM(CASE WHEN UPPER(COALESCE(ao.currency, 'VES')) = 'USD'
                        THEN GREATEST(ao.amount_minor - ROUND(ao.paid_bs * 100.0 / {$usdRateMinor}) - ROUND(ao.credit_bs * 100.0 / {$usdRateMinor}), 0)
                        ELSE 0 END)::bigint AS overdue_usd,
                    SUM(CASE WHEN UPPER(COALESCE(ao.currency, 'VES')) NOT IN ('EUR', 'USD')
                        THEN GREATEST(ao.amount_minor - ao.paid_bs - ao.credit_bs, 0)
                        ELSE 0 END)::bigint AS overdue_ves,
                    ROUND(AVG(ao.days_late)::numeric)::int AS days_overdue_avg,
                    MAX(ao.days_late)::int AS days_overdue_max
                FROM all_overdue ao
                LEFT JOIN concessionaires cn ON cn.id = ao.concessionaire_id
                WHERE ao.concessionaire_id = 0 OR cn.deleted_at IS NULL
                GROUP BY COALESCE(cn.id, 0)
            )
            SELECT
                pc.*,
                COALESCE(pco.overdue_eur, 0) AS overdue_eur_corrected,
                COALESCE(pco.overdue_usd, 0) AS overdue_usd_corrected,
                COALESCE(pco.overdue_ves, 0) AS overdue_ves_corrected,
                COALESCE(pco.days_overdue_avg, 0) AS days_overdue_avg_corrected,
                COALESCE(pco.days_overdue_max, 0) AS days_overdue_max_corrected
            FROM per_concessionaire pc
            LEFT JOIN per_concessionaire_overdue pco ON pco.id = pc.id
        ";

        // Build conditions for the outer wrapper
        $conditions = [];
        $bindings = array_merge(
            $periodBindings,
            $periodBindings,
            $periodBindings,
            $overdueLocalBindings,
            $periodBindings,
            $overdueConcessionaireBindings
        );

        if (! empty($filters['search'])) {
            $search = '%'.strtolower((string) $filters['search']).'%';
            $conditions[] = '(LOWER(full_name) LIKE ? OR LOWER(document_number) LIKE ?)';
            $bindings[] = $search;
            $bindings[] = $search;
        }

        if (array_key_exists('min_debt_eur', $filters) && $filters['min_debt_eur'] !== null && $filters['min_debt_eur'] !== '') {
            $minMinor = (int) (((float) $filters['min_debt_eur']) * 100);
            $conditions[] = 'outstanding_eur >= ?';
            $bindings[] = $minMinor;
        }

        if (array_key_exists('max_debt_eur', $filters) && $filters['max_debt_eur'] !== null && $filters['max_debt_eur'] !== '') {
            $maxMinor = (int) (((float) $filters['max_debt_eur']) * 100);
            $conditions[] = 'outstanding_eur <= ?';
            $bindings[] = $maxMinor;
        }

        // Filter by total debt in Bs (EUR + USD + VES converted to Bs)
        if (array_key_exists('min_debt_bs', $filters) && $filters['min_debt_bs'] !== null && $filters['min_debt_bs'] !== '') {
            $minBs = (int) (((float) $filters['min_debt_bs']) * 100);
            $conditions[] = "(outstanding_eur * {$eurRateMinor} / 100 + outstanding_usd * {$usdRateMinor} / 100 + outstanding_ves) >= ?";
            $bindings[] = $minBs;
        }

        if (array_key_exists('max_debt_bs', $filters) && $filters['max_debt_bs'] !== null && $filters['max_debt_bs'] !== '') {
            $maxBs = (int) (((float) $filters['max_debt_bs']) * 100);
            $conditions[] = "(outstanding_eur * {$eurRateMinor} / 100 + outstanding_usd * {$usdRateMinor} / 100 + outstanding_ves) <= ?";
            $bindings[] = $maxBs;
        }

        if (array_key_exists('min_days', $filters) && $filters['min_days'] !== null && $filters['min_days'] !== '') {
            $conditions[] = 'COALESCE(pco.days_overdue_max, 0) >= ?';
            $bindings[] = (int) $filters['min_days'];
        }

        if (array_key_exists('market_id', $filters) && $filters['market_id'] !== null && $filters['market_id'] !== '') {
            $conditions[] = '(market_ids IS NOT NULL AND ? = ANY(market_ids))';
            $bindings[] = (int) $filters['market_id'];
        }

        $whereClause = $conditions !== [] ? ' WHERE '.implode(' AND ', $conditions) : '';

        $sortColumn = match ($sortBy) {
            'debt_eur' => 'outstanding_eur',
            'debt_usd' => 'outstanding_usd',
            'days_overdue' => 'days_overdue_avg_corrected',
            'name' => 'full_name',
            default => "(outstanding_eur * {$eurRateMinor} / 100 + outstanding_usd * {$usdRateMinor} / 100 + outstanding_ves)",
        };
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($page - 1) * $perPage;
        $skipAggregateWindows = (bool) ($filters['_skip_aggregate_windows'] ?? false);
        if ($skipAggregateWindows) {
            $dataSql = "SELECT
                s.*
                FROM ({$sql}{$whereClause}) AS s
                ORDER BY {$sortColumn} {$direction} NULLS LAST
                LIMIT {$perPage} OFFSET {$offset}";
            $rows = DB::select($dataSql, $bindings);
            $total = count($rows);
            $totalEur = array_sum(array_map(fn ($row): int => (int) ($row->outstanding_eur ?? 0), $rows));
            $totalUsd = array_sum(array_map(fn ($row): int => (int) ($row->outstanding_usd ?? 0), $rows));
            $totalVes = array_sum(array_map(fn ($row): int => (int) ($row->outstanding_ves ?? 0), $rows));
            $totalOverdueEur = array_sum(array_map(fn ($row): int => (int) ($row->overdue_eur_corrected ?? 0), $rows));
            $totalOverdueUsd = array_sum(array_map(fn ($row): int => (int) ($row->overdue_usd_corrected ?? 0), $rows));
            $totalOverdueVes = array_sum(array_map(fn ($row): int => (int) ($row->overdue_ves_corrected ?? 0), $rows));
            $avgDaysValues = array_values(array_filter(array_map(fn ($row): int => (int) ($row->days_overdue_avg_corrected ?? 0), $rows), fn (int $value): bool => $value > 0));
            $avgDays = $avgDaysValues === [] ? 0 : (int) round(array_sum($avgDaysValues) / count($avgDaysValues));
        } else {
            $dataSql = "SELECT
                s.*,
                COUNT(*) OVER()::int AS total_count,
                COALESCE(SUM(s.outstanding_eur) OVER(), 0)::bigint AS total_eur,
                COALESCE(SUM(s.outstanding_usd) OVER(), 0)::bigint AS total_usd,
                COALESCE(SUM(s.outstanding_ves) OVER(), 0)::bigint AS total_ves,
                COALESCE(SUM(s.overdue_eur_corrected) OVER(), 0)::bigint AS total_overdue_eur,
                COALESCE(SUM(s.overdue_usd_corrected) OVER(), 0)::bigint AS total_overdue_usd,
                COALESCE(SUM(s.overdue_ves_corrected) OVER(), 0)::bigint AS total_overdue_ves,
                COALESCE(ROUND(AVG(s.days_overdue_avg_corrected) OVER()::numeric), 0)::int AS avg_days
                FROM ({$sql}{$whereClause}) AS s
                ORDER BY {$sortColumn} {$direction} NULLS LAST
                LIMIT {$perPage} OFFSET {$offset}";
            $rows = DB::select($dataSql, $bindings);

            $firstRow = $rows[0] ?? null;
            $total = (int) ($firstRow->total_count ?? 0);
            $totalEur = (int) ($firstRow->total_eur ?? 0);
            $totalUsd = (int) ($firstRow->total_usd ?? 0);
            $totalVes = (int) ($firstRow->total_ves ?? 0);
            $totalOverdueEur = (int) ($firstRow->total_overdue_eur ?? 0);
            $totalOverdueUsd = (int) ($firstRow->total_overdue_usd ?? 0);
            $totalOverdueVes = (int) ($firstRow->total_overdue_ves ?? 0);
            $avgDays = (int) ($firstRow->avg_days ?? 0);
        }

        // Map rows — debt_bs_minor = outstanding_original * current_rate (matches economic profile)
        $data = collect($rows)->map(function ($row) use ($eurRateMinor, $usdRateMinor) {
            $outEur = (int) $row->outstanding_eur;
            $outUsd = (int) $row->outstanding_usd;
            $outVes = (int) $row->outstanding_ves;
            $bsFromEur = $this->toVesMinor($outEur, (float) $eurRateMinor / 100);
            $bsFromUsd = $this->toVesMinor($outUsd, (float) $usdRateMinor / 100);

            // Use corrected overdue metrics (from overdue CTE)
            $daysOverdueAvg = (int) ($row->days_overdue_avg_corrected ?? $row->days_overdue_avg);
            $daysOverdueMax = (int) ($row->days_overdue_max_corrected ?? $row->days_overdue_max);

            return [
                'id' => (int) $row->id,
                'full_name' => (string) $row->full_name,
                'document_number' => (string) $row->document_number,
                'market_name' => (string) (($row->market_name !== null && $row->market_name !== '') ? $row->market_name : 'Sin asignar'),
                'local_codes' => (string) ($row->local_codes ?? ''),
                'debt_eur_minor' => $outEur,
                'debt_usd_minor' => $outUsd,
                'debt_bs_minor' => $bsFromEur + $bsFromUsd + $outVes,
                'days_overdue_avg' => $daysOverdueAvg,
                'days_overdue_max' => $daysOverdueMax,
                'locals_count' => (int) $row->locals_count,
                'charges_count' => (int) $row->charges_count,
                'severity' => $this->calculateSeverity($daysOverdueAvg),
            ];
        });

        return [
            'data' => $data->values()->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'summary' => [
                'total_debt_eur_minor' => $totalEur,
                'total_debt_usd_minor' => $totalUsd,
                'total_debt_bs_minor' => $this->toVesMinor($totalEur, (float) $eurRateMinor / 100)
                    + $this->toVesMinor($totalUsd, (float) $usdRateMinor / 100)
                    + $totalVes,
                'total_overdue_eur_minor' => $totalOverdueEur,
                'total_overdue_usd_minor' => $totalOverdueUsd,
                'total_overdue_bs_minor' => $this->toVesMinor($totalOverdueEur, (float) $eurRateMinor / 100)
                    + $this->toVesMinor($totalOverdueUsd, (float) $usdRateMinor / 100)
                    + $totalOverdueVes,
                'total_count' => $total,
                'avg_days_overdue' => $avgDays,
            ],
            'fx_rate_eur' => $fxRate,
            'fx_rate_usd' => $usdRate,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Obtener locales morosos con paginación.
     *
     * Uses CTE-based approach for consistent outstanding calculation.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDelinquentLocals(array $filters): array
    {
        if ((bool) ($filters['_skip_aggregate_windows'] ?? false)) {
            return $this->runDelinquentLocalsQuery($filters);
        }

        return $this->rememberDebtAnalysisResult(
            'delinquent_locals',
            $filters,
            fn (): array => $this->runDelinquentLocalsQuery($filters),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function runDelinquentLocalsQuery(array $filters): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $skipAggregateWindows = (bool) ($filters['_skip_aggregate_windows'] ?? false);
        $perPage = $skipAggregateWindows
            ? (int) ($filters['per_page'] ?? 500)
            : min((int) ($filters['per_page'] ?? 25), 100);
        $sortBy = $filters['sort_by'] ?? 'debt_bs';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $fxRate = $this->getActiveFxRate();
        $usdRateObj = $this->fxService->resolveAt('USD', Carbon::today());
        $usdRate = $usdRateObj ? (float) $usdRateObj->getAttribute('rate_to_ves') : 1.0;
        $eurRateMinor = (int) round($fxRate * 100);
        $usdRateMinor = (int) round($usdRate * 100);

        $periodFrom = ! empty($filters['period_from'])
            ? Carbon::createFromFormat('Y-m', (string) $filters['period_from'])->startOfMonth()->toDateString()
            : null;
        $periodToExclusive = ! empty($filters['period_to'])
            ? Carbon::createFromFormat('Y-m', (string) $filters['period_to'])->addMonth()->startOfMonth()->toDateString()
            : null;

        if ($periodFrom !== null && $periodToExclusive !== null && $periodFrom >= $periodToExclusive) {
            [$periodFrom, $periodToExclusive] = [
                Carbon::parse($periodToExclusive)->subMonth()->startOfMonth()->toDateString(),
                Carbon::parse($periodFrom)->addMonth()->startOfMonth()->toDateString(),
            ];
        }

        $baseBindings = [];
        $periodSql = '';
        // period_from/period_to now filter by charges.period, not due_on
        if ($periodFrom !== null) {
            $periodSql .= ' AND ch.period >= ?';
            $baseBindings[] = $periodFrom;
        }
        if ($periodToExclusive !== null) {
            $periodSql .= ' AND ch.period < ?';
            $baseBindings[] = $periodToExclusive;
        }

        $sql = "
            WITH allocs AS (
                SELECT charge_id, SUM(amount_bs_minor)::bigint AS paid_bs
                FROM payment_allocations WHERE deleted_at IS NULL
                GROUP BY charge_id
            ),
            credits AS (
                SELECT ca.charge_id,
                       SUM(CASE UPPER(COALESCE(cc.currency, 'VES'))
                           WHEN 'EUR' THEN (ca.amount_minor::bigint * {$eurRateMinor}) / 100
                           WHEN 'USD' THEN (ca.amount_minor::bigint * {$usdRateMinor}) / 100
                           ELSE ca.amount_minor
                       END)::bigint AS credit_bs
                FROM credit_applications ca
                LEFT JOIN customer_credits cc ON cc.id = ca.customer_credit_id
                WHERE ca.deleted_at IS NULL
                GROUP BY ca.charge_id
            ),
            open AS (
                SELECT ch.id AS charge_id,
                       ch.currency,
                       ch.amount_minor,
                       COALESCE(al.paid_bs, 0) AS paid_bs,
                       COALESCE(cr.credit_bs, 0) AS credit_bs,
                       (CURRENT_DATE - ch.due_on::date) AS days_late,
                       ch.debtor_id AS local_id
                FROM charges ch
                INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                LEFT JOIN allocs al ON al.charge_id = ch.id
                LEFT JOIN credits cr ON cr.charge_id = ch.id
                WHERE chs.code IN ('ISSUED', 'PARTIAL')
                  AND ch.deleted_at IS NULL
                  {$periodSql}
                  AND ch.debtor_type = 'LOCAL'
            ),
            overdue AS (
                SELECT ch.id AS charge_id,
                       ch.currency,
                       ch.amount_minor,
                       COALESCE(al.paid_bs, 0) AS paid_bs,
                       COALESCE(cr.credit_bs, 0) AS credit_bs,
                       (CURRENT_DATE - ch.due_on::date) AS days_late,
                       ch.debtor_id AS local_id
                FROM charges ch
                INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                LEFT JOIN allocs al ON al.charge_id = ch.id
                LEFT JOIN credits cr ON cr.charge_id = ch.id
                WHERE chs.code IN ('ISSUED', 'PARTIAL')
                  AND ch.due_on <= CURRENT_DATE
                  AND ch.deleted_at IS NULL
                  {$periodSql}
                  AND ch.debtor_type = 'LOCAL'
            ),
            active_concessionaire_by_local AS (
                SELECT
                    cl.local_id,
                    STRING_AGG(DISTINCT cn.full_name, ', ' ORDER BY cn.full_name) AS concessionaire_name
                FROM contract_local cl
                INNER JOIN contracts c ON c.id = cl.contract_id
                INNER JOIN contract_statuses cs ON cs.id = c.contract_status_id
                LEFT JOIN concessionaire_contract ccc ON ccc.contract_id = c.id
                LEFT JOIN concessionaires cn ON cn.id = ccc.concessionaire_id AND cn.deleted_at IS NULL
                WHERE c.deleted_at IS NULL
                  AND c.start_date <= CURRENT_DATE
                  AND cs.code IN ('VIG', 'EXT', 'VENC')
                  AND (
                    (cs.code IN ('VIG', 'EXT') AND (c.end_date IS NULL OR c.end_date >= CURRENT_DATE))
                    OR cs.code = 'VENC'
                  )
                GROUP BY cl.local_id
            ),
            latest_concessionaire_by_local AS (
                SELECT DISTINCT ON (cl.local_id)
                    cl.local_id,
                    cn.full_name AS concessionaire_name
                FROM contract_local cl
                INNER JOIN contracts c ON c.id = cl.contract_id
                LEFT JOIN concessionaire_contract ccc ON ccc.contract_id = c.id
                LEFT JOIN concessionaires cn ON cn.id = ccc.concessionaire_id AND cn.deleted_at IS NULL
                WHERE c.deleted_at IS NULL
                ORDER BY cl.local_id, c.start_date DESC NULLS LAST, c.id DESC, ccc.is_primary DESC, ccc.id ASC
            ),
            per_local AS (
                SELECT
                    l.id,
                    l.code AS local_code,
                    UPPER(REGEXP_REPLACE(COALESCE(l.code, ''), '[^A-Z0-9]+', '', 'g')) AS local_code_normalized,
                    l.name AS local_name,
                    COALESCE(ac.concessionaire_name, lc.concessionaire_name, 'Sin concesionario atribuible') AS concessionaire_name,
                    COALESCE(m.name, 'Sin asignar') AS market_name,
                    COALESCE(lt.name, 'Sin tipo') AS local_type_name,
                    COALESCE(l.market_id, 0)::int AS market_id,
                    COALESCE(l.local_type_id, 0)::int AS local_type_id,
                    COUNT(DISTINCT o.charge_id)::int AS charges_count,
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'EUR'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$eurRateMinor}) - ROUND(o.credit_bs * 100.0 / {$eurRateMinor}), 0)
                        ELSE 0 END)::bigint AS outstanding_eur,
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'USD'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$usdRateMinor}) - ROUND(o.credit_bs * 100.0 / {$usdRateMinor}), 0)
                        ELSE 0 END)::bigint AS outstanding_usd,
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) NOT IN ('EUR', 'USD')
                        THEN GREATEST(o.amount_minor - o.paid_bs - o.credit_bs, 0)
                        ELSE 0 END)::bigint AS outstanding_ves,
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'EUR'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$eurRateMinor}) - ROUND(o.credit_bs * 100.0 / {$eurRateMinor}), 0)
                        ELSE 0 END)::bigint AS overdue_eur,
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'USD'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$usdRateMinor}) - ROUND(o.credit_bs * 100.0 / {$usdRateMinor}), 0)
                        ELSE 0 END)::bigint AS overdue_usd,
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) NOT IN ('EUR', 'USD')
                        THEN GREATEST(o.amount_minor - o.paid_bs - o.credit_bs, 0)
                        ELSE 0 END)::bigint AS overdue_ves,
                    ROUND(AVG(o.days_late)::numeric)::int AS days_overdue_avg,
                    MAX(o.days_late)::int AS days_overdue_max
                FROM locals l
                INNER JOIN open o ON o.local_id = l.id
                LEFT JOIN active_concessionaire_by_local ac ON ac.local_id = l.id
                LEFT JOIN latest_concessionaire_by_local lc ON lc.local_id = l.id
                LEFT JOIN markets m ON m.id = l.market_id
                LEFT JOIN local_types lt ON lt.id = l.local_type_id
                WHERE l.deleted_at IS NULL
                GROUP BY l.id, l.code, l.name, ac.concessionaire_name, lc.concessionaire_name, m.name, lt.name, l.market_id, l.local_type_id
            ),
            per_local_overdue AS (
                SELECT
                    l.id,
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'EUR'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$eurRateMinor}) - ROUND(o.credit_bs * 100.0 / {$eurRateMinor}), 0)
                        ELSE 0 END)::bigint AS overdue_eur,
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'USD'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$usdRateMinor}) - ROUND(o.credit_bs * 100.0 / {$usdRateMinor}), 0)
                        ELSE 0 END)::bigint AS overdue_usd,
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) NOT IN ('EUR', 'USD')
                        THEN GREATEST(o.amount_minor - o.paid_bs - o.credit_bs, 0)
                        ELSE 0 END)::bigint AS overdue_ves,
                    ROUND(AVG(o.days_late)::numeric)::int AS days_overdue_avg,
                    MAX(o.days_late)::int AS days_overdue_max
                FROM locals l
                INNER JOIN overdue o ON o.local_id = l.id
                WHERE l.deleted_at IS NULL
                GROUP BY l.id
            )
            SELECT
                pl.*,
                COALESCE(plo.overdue_eur, 0) AS overdue_eur_corrected,
                COALESCE(plo.overdue_usd, 0) AS overdue_usd_corrected,
                COALESCE(plo.overdue_ves, 0) AS overdue_ves_corrected,
                COALESCE(plo.days_overdue_avg, 0) AS days_overdue_avg_corrected,
                COALESCE(plo.days_overdue_max, 0) AS days_overdue_max_corrected
            FROM per_local pl
            LEFT JOIN per_local_overdue plo ON plo.id = pl.id
        ";

        $conditions = [];
        $bindings = array_merge($baseBindings, $baseBindings);

        if (! empty($filters['search'])) {
            $search = '%'.strtolower((string) $filters['search']).'%';
            $conditions[] = '(LOWER(concessionaire_name) LIKE ? OR LOWER(local_code) LIKE ? OR LOWER(local_name) LIKE ?)';
            $bindings = array_merge($bindings, [$search, $search, $search]);
        }

        if (array_key_exists('market_id', $filters) && $filters['market_id'] !== null && $filters['market_id'] !== '') {
            $conditions[] = 'market_id = ?';
            $bindings[] = (int) $filters['market_id'];
        }

        if (array_key_exists('local_type_id', $filters) && $filters['local_type_id'] !== null && $filters['local_type_id'] !== '') {
            $conditions[] = 'local_type_id = ?';
            $bindings[] = (int) $filters['local_type_id'];
        }

        if (array_key_exists('min_debt_eur', $filters) && $filters['min_debt_eur'] !== null && $filters['min_debt_eur'] !== '') {
            $minMinor = (int) (((float) $filters['min_debt_eur']) * 100);
            $conditions[] = 'outstanding_eur >= ?';
            $bindings[] = $minMinor;
        }

        if (array_key_exists('max_debt_eur', $filters) && $filters['max_debt_eur'] !== null && $filters['max_debt_eur'] !== '') {
            $maxMinor = (int) (((float) $filters['max_debt_eur']) * 100);
            $conditions[] = 'outstanding_eur <= ?';
            $bindings[] = $maxMinor;
        }

        // Filter by total debt in Bs (EUR + USD + VES converted to Bs)
        if (array_key_exists('min_debt_bs', $filters) && $filters['min_debt_bs'] !== null && $filters['min_debt_bs'] !== '') {
            $minBs = (int) (((float) $filters['min_debt_bs']) * 100);
            $conditions[] = "(outstanding_eur * {$eurRateMinor} / 100 + outstanding_usd * {$usdRateMinor} / 100 + outstanding_ves) >= ?";
            $bindings[] = $minBs;
        }

        if (array_key_exists('max_debt_bs', $filters) && $filters['max_debt_bs'] !== null && $filters['max_debt_bs'] !== '') {
            $maxBs = (int) (((float) $filters['max_debt_bs']) * 100);
            $conditions[] = "(outstanding_eur * {$eurRateMinor} / 100 + outstanding_usd * {$usdRateMinor} / 100 + outstanding_ves) <= ?";
            $bindings[] = $maxBs;
        }

        if (array_key_exists('min_days', $filters) && $filters['min_days'] !== null && $filters['min_days'] !== '') {
            $conditions[] = 'COALESCE(plo.days_overdue_max, 0) >= ?';
            $bindings[] = (int) $filters['min_days'];
        }

        $localCodeFrom = $this->normalizeLocalCodeRangeValue($filters['local_code_from'] ?? null);
        $localCodeTo = $this->normalizeLocalCodeRangeValue($filters['local_code_to'] ?? null);
        if ($localCodeFrom !== null && $localCodeTo !== null && strcmp($localCodeFrom, $localCodeTo) > 0) {
            [$localCodeFrom, $localCodeTo] = [$localCodeTo, $localCodeFrom];
        }
        if ($localCodeFrom !== null) {
            $conditions[] = 'local_code_normalized >= ?';
            $bindings[] = $localCodeFrom;
        }
        if ($localCodeTo !== null) {
            $conditions[] = 'local_code_normalized <= ?';
            $bindings[] = $localCodeTo;
        }

        $whereClause = $conditions !== [] ? ' WHERE '.implode(' AND ', $conditions) : '';

        $sortColumn = match ($sortBy) {
            'code' => 'local_code',
            'debt_eur' => 'outstanding_eur',
            'debt_usd' => 'outstanding_usd',
            'days_overdue' => 'days_overdue_avg_corrected',
            default => "(outstanding_eur * {$eurRateMinor} / 100 + outstanding_usd * {$usdRateMinor} / 100 + outstanding_ves)",
        };
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;
        $skipAggregateWindows = (bool) ($filters['_skip_aggregate_windows'] ?? false);
        if ($skipAggregateWindows) {
            $rows = DB::select("SELECT
                s.*
                FROM ({$sql}{$whereClause}) AS s
                ORDER BY {$sortColumn} {$direction} NULLS LAST
                LIMIT {$perPage} OFFSET {$offset}", $bindings);
            $total = count($rows);
            $totalEur = array_sum(array_map(fn ($row): int => (int) ($row->outstanding_eur ?? 0), $rows));
            $totalUsd = array_sum(array_map(fn ($row): int => (int) ($row->outstanding_usd ?? 0), $rows));
            $totalVes = array_sum(array_map(fn ($row): int => (int) ($row->outstanding_ves ?? 0), $rows));
            $totalOverdueEur = array_sum(array_map(fn ($row): int => (int) ($row->overdue_eur_corrected ?? 0), $rows));
            $totalOverdueUsd = array_sum(array_map(fn ($row): int => (int) ($row->overdue_usd_corrected ?? 0), $rows));
            $totalOverdueVes = array_sum(array_map(fn ($row): int => (int) ($row->overdue_ves_corrected ?? 0), $rows));
            $avgDaysValues = array_values(array_filter(array_map(fn ($row): int => (int) ($row->days_overdue_avg_corrected ?? 0), $rows), fn (int $value): bool => $value > 0));
            $avgDays = $avgDaysValues === [] ? 0 : (int) round(array_sum($avgDaysValues) / count($avgDaysValues));
        } else {
            $rows = DB::select("SELECT
                s.*,
                COUNT(*) OVER()::int AS total_count,
                COALESCE(SUM(s.outstanding_eur) OVER(), 0)::bigint AS total_eur,
                COALESCE(SUM(s.outstanding_usd) OVER(), 0)::bigint AS total_usd,
                COALESCE(SUM(s.outstanding_ves) OVER(), 0)::bigint AS total_ves,
                COALESCE(SUM(s.overdue_eur_corrected) OVER(), 0)::bigint AS total_overdue_eur,
                COALESCE(SUM(s.overdue_usd_corrected) OVER(), 0)::bigint AS total_overdue_usd,
                COALESCE(SUM(s.overdue_ves_corrected) OVER(), 0)::bigint AS total_overdue_ves,
                COALESCE(ROUND(AVG(s.days_overdue_avg_corrected) OVER()::numeric), 0)::int AS avg_days
                FROM ({$sql}{$whereClause}) AS s
                ORDER BY {$sortColumn} {$direction} NULLS LAST
                LIMIT {$perPage} OFFSET {$offset}", $bindings);

            $firstRow = $rows[0] ?? null;
            $total = (int) ($firstRow->total_count ?? 0);
            $totalEur = (int) ($firstRow->total_eur ?? 0);
            $totalUsd = (int) ($firstRow->total_usd ?? 0);
            $totalVes = (int) ($firstRow->total_ves ?? 0);
            $totalOverdueEur = (int) ($firstRow->total_overdue_eur ?? 0);
            $totalOverdueUsd = (int) ($firstRow->total_overdue_usd ?? 0);
            $totalOverdueVes = (int) ($firstRow->total_overdue_ves ?? 0);
            $avgDays = (int) ($firstRow->avg_days ?? 0);
        }

        $data = collect($rows)->map(function ($row) use ($eurRateMinor, $usdRateMinor) {
            $outEur = (int) $row->outstanding_eur;
            $outUsd = (int) $row->outstanding_usd;
            $outVes = (int) $row->outstanding_ves;
            $bsFromEur = (int) round($outEur * $eurRateMinor / 100);
            $bsFromUsd = (int) round($outUsd * $usdRateMinor / 100);

            // Use corrected overdue metrics (from overdue CTE)
            $daysOverdueAvg = (int) ($row->days_overdue_avg_corrected ?? $row->days_overdue_avg);
            $daysOverdueMax = (int) ($row->days_overdue_max_corrected ?? $row->days_overdue_max);

            return [
                'id' => (int) $row->id,
                'local_code' => (string) $row->local_code,
                'local_name' => (string) $row->local_name,
                'concessionaire_name' => (string) $row->concessionaire_name,
                'market_name' => (string) $row->market_name,
                'local_type_name' => (string) $row->local_type_name,
                'debt_eur_minor' => $outEur,
                'debt_usd_minor' => $outUsd,
                'debt_bs_minor' => $bsFromEur + $bsFromUsd + $outVes,
                'days_overdue_avg' => $daysOverdueAvg,
                'days_overdue_max' => $daysOverdueMax,
                'charges_count' => (int) $row->charges_count,
                'severity' => $this->calculateSeverity($daysOverdueAvg),
            ];
        });

        return [
            'data' => $data->values()->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'summary' => [
                'total_debt_eur_minor' => $totalEur,
                'total_debt_usd_minor' => $totalUsd,
                'total_debt_bs_minor' => (int) round($totalEur * $eurRateMinor / 100) + (int) round($totalUsd * $usdRateMinor / 100) + $totalVes,
                'total_overdue_eur_minor' => $totalOverdueEur,
                'total_overdue_usd_minor' => $totalOverdueUsd,
                'total_overdue_bs_minor' => (int) round($totalOverdueEur * $eurRateMinor / 100) + (int) round($totalOverdueUsd * $usdRateMinor / 100) + $totalOverdueVes,
                'total_count' => $total,
                'avg_days_overdue' => $avgDays,
            ],
            'fx_rate_eur' => $fxRate,
            'fx_rate_usd' => $usdRate,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Obtener concesionarios solventes
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSolventConcessionaires(array $filters): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $skipAggregateWindows = (bool) ($filters['_skip_aggregate_windows'] ?? false);
        $perPage = $skipAggregateWindows
            ? (int) ($filters['per_page'] ?? 500)
            : min((int) ($filters['per_page'] ?? 25), 100);
        $monthsSolvent = array_key_exists('months_solvent', $filters) && $filters['months_solvent'] !== null && $filters['months_solvent'] !== ''
            ? max(1, (int) $filters['months_solvent'])
            : null;

        $today = Carbon::today();
        $todayStr = $today->toDateString();
        $monthsAgo = $monthsSolvent !== null
            ? $today->copy()->subMonthsNoOverflow($monthsSolvent)->toDateString()
            : null;

        $activeContractByLocal = $this->buildActiveContractByLocalSubquery($todayStr);
        $latestContractByLocal = $this->buildLatestContractByLocalSubquery();
        $primaryConcessionaireByContract = $this->buildPrimaryConcessionaireByContractSubquery();

        $activeConcessionaires = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->whereNull('c.deleted_at')
            ->whereDate('c.start_date', '<=', $todayStr)
            ->whereIn('cs.code', ['VIG', 'EXT', 'VENC'])
            ->where(function ($q) use ($todayStr): void {
                $q->whereIn('cs.code', ['VIG', 'EXT'])
                    ->where(function ($w) use ($todayStr): void {
                        $w->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $todayStr);
                    })
                    ->orWhere('cs.code', '=', 'VENC');
            })
            ->selectRaw('DISTINCT cc.concessionaire_id as id');

        $delinquentFromLocals = DB::table('charges as ch')
            ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
            ->join('locals as l', function ($j): void {
                $j->on('l.id', '=', 'ch.debtor_id')
                    ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
            })
            ->leftJoinSub($activeContractByLocal, 'acl', 'acl.local_id', '=', 'l.id')
            ->leftJoinSub($latestContractByLocal, 'lcl', 'lcl.local_id', '=', 'l.id')
            ->leftJoinSub($primaryConcessionaireByContract, 'cc', function ($join): void {
                $join->on('cc.contract_id', '=', DB::raw('COALESCE(ch.contract_id, acl.contract_id, lcl.contract_id)'));
            })
            ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
            ->whereDate('ch.due_on', '<=', $todayStr)
            ->whereNull('ch.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNotNull('cc.concessionaire_id')
            ->select('cc.concessionaire_id');

        if ($monthsAgo !== null) {
            $delinquentFromLocals->whereDate('ch.due_on', '>=', $monthsAgo);
        }

        $delinquentDirect = DB::table('charges as ch')
            ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
            ->where('ch.debtor_type', 'CONCESSIONAIRE')
            ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
            ->whereDate('ch.due_on', '<=', $todayStr)
            ->whereNull('ch.deleted_at')
            ->selectRaw('ch.debtor_id as concessionaire_id');

        if ($monthsAgo !== null) {
            $delinquentDirect->whereDate('ch.due_on', '>=', $monthsAgo);
        }

        $delinquentIds = DB::query()
            ->fromSub($delinquentFromLocals->union($delinquentDirect), 'd')
            ->selectRaw('DISTINCT d.concessionaire_id');

        $localPayments = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->join('charges as ch', 'ch.id', '=', 'pa.charge_id')
            ->join('locals as l', function ($j): void {
                $j->on('l.id', '=', 'ch.debtor_id')
                    ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
            })
            ->leftJoinSub($activeContractByLocal, 'acl', 'acl.local_id', '=', 'l.id')
            ->leftJoinSub($latestContractByLocal, 'lcl', 'lcl.local_id', '=', 'l.id')
            ->leftJoinSub($primaryConcessionaireByContract, 'cc', function ($join): void {
                $join->on('cc.contract_id', '=', DB::raw('COALESCE(ch.contract_id, acl.contract_id, lcl.contract_id)'));
            })
            ->whereNull('pa.deleted_at')
            ->whereNull('p.deleted_at')
            ->whereNull('ch.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNotNull('cc.concessionaire_id')
            ->selectRaw('cc.concessionaire_id, p.id AS payment_id, p.paid_on');

        $directPayments = DB::table('payments as p')
            ->where('p.debtor_type', 'CONCESSIONAIRE')
            ->whereNull('p.deleted_at')
            ->selectRaw('p.debtor_id AS concessionaire_id, p.id AS payment_id, p.paid_on');

        $paymentInfo = DB::query()
            ->fromSub($localPayments->unionAll($directPayments), 'pp')
            ->selectRaw('pp.concessionaire_id, MAX(pp.paid_on) as last_payment_date, COUNT(DISTINCT pp.payment_id)::int as payment_count')
            ->groupBy('pp.concessionaire_id');

        $marketInfo = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereDate('c.start_date', '<=', $todayStr)
            ->whereIn('cs.code', ['VIG', 'EXT', 'VENC'])
            ->where(function ($q) use ($todayStr): void {
                $q->whereIn('cs.code', ['VIG', 'EXT'])
                    ->where(function ($w) use ($todayStr): void {
                        $w->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $todayStr);
                    })
                    ->orWhere('cs.code', '=', 'VENC');
            })
            ->selectRaw("cc.concessionaire_id,
                STRING_AGG(DISTINCT COALESCE(m.name, 'Sin asignar'), ', ' ORDER BY COALESCE(m.name, 'Sin asignar')) as market_name,
                ARRAY_REMOVE(ARRAY_AGG(DISTINCT m.id), NULL)::int[] as market_ids")
            ->groupBy('cc.concessionaire_id');

        $query = DB::table('concessionaires as cn')
            ->joinSub($activeConcessionaires, 'active_cn', 'active_cn.id', '=', 'cn.id')
            ->leftJoinSub($delinquentIds, 'd', 'd.concessionaire_id', '=', 'cn.id')
            ->leftJoinSub($paymentInfo, 'payment_info', 'payment_info.concessionaire_id', '=', 'cn.id')
            ->leftJoinSub($marketInfo, 'market_info', 'market_info.concessionaire_id', '=', 'cn.id')
            ->whereNull('cn.deleted_at')
            ->whereNull('d.concessionaire_id');

        if (! empty($filters['search'])) {
            $search = strtolower((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->whereRaw('LOWER(cn.full_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(cn.document_number) LIKE ?', [$like]);
            });
        }

        if (array_key_exists('market_id', $filters) && $filters['market_id'] !== null && $filters['market_id'] !== '') {
            $query->whereRaw('(market_info.market_ids IS NOT NULL AND ? = ANY(market_info.market_ids))', [(int) $filters['market_id']]);
        }

        $query->select([
            'cn.id',
            'cn.full_name',
            'cn.document_number',
            DB::raw("COALESCE(market_info.market_name, 'Sin asignar') as market_name"),
            'payment_info.last_payment_date',
            DB::raw('COALESCE(payment_info.payment_count, 0)::int as total_payments'),
        ]);

        $total = (clone $query)->count('cn.id');

        $results = $query
            ->orderByRaw('payment_info.last_payment_date DESC NULLS LAST')
            ->orderBy('cn.full_name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = $results->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'full_name' => (string) $row->full_name,
                'document_number' => (string) $row->document_number,
                'market_name' => (string) $row->market_name,
                'last_payment_date' => $row->last_payment_date,
                'total_payments' => (int) $row->total_payments,
            ];
        });

        return [
            'data' => $data->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Obtener distribuciones para gráficas
     *
     * @return array<string, mixed>
     */
    public function getDistributions(bool $force = false): array
    {
        $cacheKey = 'debt_analysis:distributions';

        if ($force) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 300, function (): array {
            $fxRate = $this->getActiveFxRate();
            $usdRateObj = $this->fxService->resolveAt('USD', Carbon::today());
            $usdRate = $usdRateObj ? (float) $usdRateObj->getAttribute('rate_to_ves') : 1.0;
            $eurRateMinor = (int) round($fxRate * 100);
            $usdRateMinor = (int) round($usdRate * 100);
            $today = Carbon::today()->toDateString();

            $baseOutstandingSql = <<<SQL
                WITH allocs AS (
                    SELECT charge_id, SUM(amount_bs_minor)::bigint AS paid_bs_minor
                    FROM payment_allocations
                    WHERE deleted_at IS NULL
                    GROUP BY charge_id
                ),
                credits AS (
                    SELECT ca.charge_id,
                           SUM(CASE UPPER(COALESCE(cc.currency, 'VES'))
                               WHEN 'EUR' THEN (ca.amount_minor::bigint * {$eurRateMinor}) / 100
                               WHEN 'USD' THEN (ca.amount_minor::bigint * {$usdRateMinor}) / 100
                               ELSE ca.amount_minor
                           END)::bigint AS credit_bs_minor
                    FROM credit_applications ca
                    LEFT JOIN customer_credits cc ON cc.id = ca.customer_credit_id
                    WHERE ca.deleted_at IS NULL
                    GROUP BY ca.charge_id
                ),
                overdue AS (
                    SELECT 
                           ch.id AS charge_id,
                           ch.debtor_type,
                           ch.debtor_id,
                           ch.local_id,
                           ch.currency,
                           ch.amount_minor,
                           COALESCE(ap.paid_bs_minor, 0)::bigint AS paid_bs_minor,
                           COALESCE(cr.credit_bs_minor, 0)::bigint AS credit_bs_minor,
                           (CURRENT_DATE - ch.due_on::date)::int AS days_late
                    FROM charges ch
                    INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                    LEFT JOIN allocs ap ON ap.charge_id = ch.id
                    LEFT JOIN credits cr ON cr.charge_id = ch.id
                    WHERE chs.code IN ('ISSUED', 'PARTIAL')
                      AND ch.due_on <= CURRENT_DATE
                      AND ch.deleted_at IS NULL
                ),
                outstanding AS (
                    SELECT
                        o.charge_id,
                        o.debtor_type,
                        o.debtor_id,
                        o.local_id,
                        o.currency,
                        o.days_late,
                        CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'EUR'
                            THEN GREATEST(o.amount_minor - ROUND((o.paid_bs_minor + o.credit_bs_minor) * 100.0 / {$eurRateMinor}), 0)
                            ELSE 0 END::bigint AS outstanding_eur_minor,
                        CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'USD'
                            THEN GREATEST(o.amount_minor - ROUND((o.paid_bs_minor + o.credit_bs_minor) * 100.0 / {$usdRateMinor}), 0)
                            ELSE 0 END::bigint AS outstanding_usd_minor,
                        CASE WHEN UPPER(COALESCE(o.currency, 'VES')) NOT IN ('EUR', 'USD')
                            THEN GREATEST(o.amount_minor - o.paid_bs_minor - o.credit_bs_minor, 0)
                            ELSE 0 END::bigint AS outstanding_ves_minor,
                        GREATEST(
                            0,
                            CASE
                                WHEN UPPER(COALESCE(o.currency, 'VES')) = 'EUR' THEN (o.amount_minor::bigint * {$eurRateMinor}) / 100
                                WHEN UPPER(COALESCE(o.currency, 'VES')) = 'USD' THEN (o.amount_minor::bigint * {$usdRateMinor}) / 100
                                ELSE o.amount_minor::bigint
                            END - o.paid_bs_minor - o.credit_bs_minor
                        )::bigint AS outstanding_bs_minor
                    FROM overdue o
                )
            SQL;

            $distributionRows = DB::select("{$baseOutstandingSql}
                SELECT
                    'aging'::text AS section,
                    bucket::text AS bucket,
                    NULL::int AS market_id,
                    NULL::text AS market_name,
                    NULL::int AS local_type_id,
                    NULL::text AS local_type_name,
                    NULL::int AS locals_count,
                    COALESCE(SUM(outstanding_eur_minor), 0)::bigint AS debt_eur_minor,
                    COALESCE(SUM(outstanding_usd_minor), 0)::bigint AS debt_usd_minor,
                    COALESCE(SUM(outstanding_bs_minor), 0)::bigint AS debt_bs_minor,
                    COUNT(DISTINCT charge_id)::int AS row_count
                FROM (
                    SELECT
                        charge_id,
                        outstanding_eur_minor,
                        outstanding_usd_minor,
                        outstanding_bs_minor,
                        CASE
                            WHEN days_late <= 30 THEN '0-30'
                            WHEN days_late <= 60 THEN '31-60'
                            WHEN days_late <= 90 THEN '61-90'
                            ELSE '90+'
                        END AS bucket
                    FROM outstanding
                    WHERE outstanding_bs_minor > 0
                ) x
                GROUP BY bucket

                UNION ALL

                SELECT
                    'market'::text AS section,
                    NULL::text AS bucket,
                    COALESCE(m.id, 0)::int AS market_id,
                    COALESCE(m.name, 'Sin asignar')::text AS market_name,
                    NULL::int AS local_type_id,
                    NULL::text AS local_type_name,
                    NULL::int AS locals_count,
                    COALESCE(SUM(o.outstanding_eur_minor), 0)::bigint AS debt_eur_minor,
                    COALESCE(SUM(o.outstanding_usd_minor), 0)::bigint AS debt_usd_minor,
                    COALESCE(SUM(o.outstanding_bs_minor), 0)::bigint AS debt_bs_minor,
                    0::int AS row_count
                FROM outstanding o
                LEFT JOIN locals l ON l.id = COALESCE(o.local_id, CASE WHEN o.debtor_type = 'LOCAL' THEN o.debtor_id ELSE NULL END)
                LEFT JOIN markets m ON m.id = l.market_id
                WHERE (l.id IS NULL OR l.deleted_at IS NULL)
                  AND o.outstanding_bs_minor > 0
                GROUP BY COALESCE(m.id, 0), COALESCE(m.name, 'Sin asignar')

                UNION ALL

                SELECT
                    'local_type'::text AS section,
                    NULL::text AS bucket,
                    NULL::int AS market_id,
                    NULL::text AS market_name,
                    CASE WHEN l.id IS NULL THEN -1 ELSE COALESCE(lt.id, 0) END::int AS local_type_id,
                    COALESCE(lt.name, CASE WHEN l.id IS NULL THEN 'Sin local asociado' ELSE 'Sin tipo' END)::text AS local_type_name,
                    COUNT(DISTINCT l.id)::int AS locals_count,
                    COALESCE(SUM(o.outstanding_eur_minor), 0)::bigint AS debt_eur_minor,
                    COALESCE(SUM(o.outstanding_usd_minor), 0)::bigint AS debt_usd_minor,
                    COALESCE(SUM(o.outstanding_bs_minor), 0)::bigint AS debt_bs_minor,
                    0::int AS row_count
                FROM outstanding o
                LEFT JOIN locals l ON l.id = COALESCE(o.local_id, CASE WHEN o.debtor_type = 'LOCAL' THEN o.debtor_id ELSE NULL END)
                LEFT JOIN local_types lt ON lt.id = l.local_type_id
                WHERE (l.id IS NULL OR l.deleted_at IS NULL)
                  AND o.outstanding_bs_minor > 0
                GROUP BY CASE WHEN l.id IS NULL THEN -1 ELSE COALESCE(lt.id, 0) END,
                         COALESCE(lt.name, CASE WHEN l.id IS NULL THEN 'Sin local asociado' ELSE 'Sin tipo' END)
            ");

            $activeContractByLocal = $this->buildActiveContractByLocalSubquery($today);
            $latestContractByLocal = $this->buildLatestContractByLocalSubquery();
            $primaryConcessionaireByContract = $this->buildPrimaryConcessionaireByContractSubquery();

            $concessionairesByMarket = DB::table('charges as ch')
                ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
                ->join('locals as l', function ($j): void {
                    $j->on('l.id', '=', 'ch.debtor_id')
                        ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
                })
                ->leftJoinSub($activeContractByLocal, 'acl', 'acl.local_id', '=', 'l.id')
                ->leftJoinSub($latestContractByLocal, 'lcl', 'lcl.local_id', '=', 'l.id')
                ->leftJoinSub($primaryConcessionaireByContract, 'cc', function ($join): void {
                    $join->on('cc.contract_id', '=', DB::raw('COALESCE(ch.contract_id, acl.contract_id, lcl.contract_id)'));
                })
                ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
                ->whereDate('ch.due_on', '<=', $today)
                ->whereNull('ch.deleted_at')
                ->whereNull('l.deleted_at')
                ->whereNotNull('cc.concessionaire_id')
                ->groupByRaw('COALESCE(l.market_id, 0)')
                ->selectRaw('COALESCE(l.market_id, 0) as market_id, COUNT(DISTINCT cc.concessionaire_id)::int as concessionaires_count')
                ->pluck('concessionaires_count', 'market_id');

            $byAgingOrder = [
                '0-30' => 1,
                '31-60' => 2,
                '61-90' => 3,
                '90+' => 4,
            ];

            $byAging = collect($distributionRows)
                ->filter(fn ($row) => $row->section === 'aging')
                ->map(function ($row) {
                    return [
                        'bucket' => (string) $row->bucket,
                        'debt_eur_minor' => (int) $row->debt_eur_minor,
                        'debt_usd_minor' => (int) $row->debt_usd_minor,
                        'debt_bs_minor' => (int) $row->debt_bs_minor,
                        'count' => (int) $row->row_count,
                    ];
                })
                ->sortBy(fn (array $row) => $byAgingOrder[$row['bucket']] ?? 999)
                ->values();

            $byMarket = collect($distributionRows)
                ->filter(fn ($row) => $row->section === 'market')
                ->map(function ($row) use ($concessionairesByMarket) {
                    return [
                        'market_id' => (int) $row->market_id,
                        'market_name' => (string) $row->market_name,
                        'debt_eur_minor' => (int) $row->debt_eur_minor,
                        'debt_usd_minor' => (int) $row->debt_usd_minor,
                        'debt_bs_minor' => (int) $row->debt_bs_minor,
                        'count' => (int) ($concessionairesByMarket->get((int) $row->market_id, 0)),
                    ];
                })
                ->sortByDesc('debt_bs_minor')
                ->values();

            $byLocalType = collect($distributionRows)
                ->filter(fn ($row) => $row->section === 'local_type')
                ->map(function ($row) {
                    return [
                        'local_type_id' => (int) $row->local_type_id,
                        'local_type_name' => (string) $row->local_type_name,
                        'locals_count' => (int) $row->locals_count,
                        'debt_bs_minor' => (int) $row->debt_bs_minor,
                        'debt_eur_minor' => (int) $row->debt_eur_minor,
                        'debt_usd_minor' => (int) $row->debt_usd_minor,
                    ];
                })
                ->sortByDesc('debt_bs_minor')
                ->values();

            $byLocalTypeBs = $byLocalType->map(function (array $row) use ($eurRateMinor, $usdRateMinor) {
                $eurMinor = (int) $row['debt_eur_minor'];
                $usdMinor = (int) $row['debt_usd_minor'];
                $bsEur = $this->toVesMinor($eurMinor, (float) $eurRateMinor / 100);
                $bsUsd = $this->toVesMinor($usdMinor, (float) $usdRateMinor / 100);

                return [
                    'local_type_id' => (int) $row['local_type_id'],
                    'local_type_name' => (string) $row['local_type_name'],
                    'locals_count' => (int) $row['locals_count'],
                    'debt_bs_minor' => (int) $row['debt_bs_minor'],
                    'debt_eur_minor' => $eurMinor,
                    'debt_usd_minor' => $usdMinor,
                    'debt_bs_minor_eur' => $bsEur,
                    'debt_bs_minor_usd' => $bsUsd,
                ];
            });

            return [
                'by_aging' => $byAging->values()->all(),
                'by_market' => $byMarket->values()->all(),
                'by_local_type' => $byLocalType->values()->all(),
                'by_local_type_bs' => $byLocalTypeBs->values()->all(),
                'fx_rate' => $fxRate,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Exportar datos a CSV
     *
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters): StreamedResponse
    {
        $scope = $filters['scope'] ?? 'concessionaires';
        $format = $filters['format'] ?? 'csv';

        $data = $scope === 'locals'
            ? $this->getDelinquentLocals(array_merge($filters, ['per_page' => 500, '_skip_aggregate_windows' => true]))
            : $this->getDelinquentConcessionaires(array_merge($filters, ['per_page' => 500, '_skip_aggregate_windows' => true]));

        $filename = sprintf(
            'analisis-deuda-%s-%s.%s',
            $scope,
            Carbon::now()->format('Y-m-d-His'),
            $format
        );

        $columns = $this->exportColumnsForScope($scope);
        $rows = $this->exportRowsForScope($scope, $data['data']);
        $exporter = $this->resolveExporter($format);
        $response = $exporter->stream($rows, $columns);
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function exportColumnsForScope(string $scope): array
    {
        return $scope === 'locals'
            ? [
                'id' => 'ID',
                'local_code' => 'Código Local',
                'local_name' => 'Nombre Local',
                'concessionaire_name' => 'Concesionario',
                'market_name' => 'Mercado',
                'local_type_name' => 'Tipo Local',
                'debt_eur' => 'Deuda EUR',
                'debt_usd' => 'Deuda USD',
                'debt_bs' => 'Deuda Bs',
                'days_overdue_avg' => 'Días Vencidos',
                'charges_count' => 'Cargos',
                'severity' => 'Severidad',
            ]
            : [
                'id' => 'ID',
                'full_name' => 'Concesionario',
                'document_number' => 'Documento',
                'market_name' => 'Mercado',
                'local_codes' => 'Locales',
                'debt_eur' => 'Deuda EUR',
                'debt_usd' => 'Deuda USD',
                'debt_bs' => 'Deuda Bs',
                'days_overdue_avg' => 'Días Vencidos Promedio',
                'days_overdue_max' => 'Días Vencidos Máximo',
                'locals_count' => 'Cantidad Locales',
                'charges_count' => 'Cargos',
                'severity' => 'Severidad',
            ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function exportRowsForScope(string $scope, array $rows): array
    {
        if ($scope === 'locals') {
            return array_map(fn (array $row): array => [
                'id' => $row['id'],
                'local_code' => $row['local_code'],
                'local_name' => $row['local_name'],
                'concessionaire_name' => $row['concessionaire_name'],
                'market_name' => $row['market_name'],
                'local_type_name' => $row['local_type_name'],
                'debt_eur' => $this->formatMoneyMinor($row['debt_eur_minor'] ?? 0),
                'debt_usd' => $this->formatMoneyMinor($row['debt_usd_minor'] ?? 0),
                'debt_bs' => $this->formatMoneyMinor($row['debt_bs_minor'] ?? 0),
                'days_overdue_avg' => $row['days_overdue_avg'],
                'charges_count' => $row['charges_count'],
                'severity' => $row['severity'],
            ], $rows);
        }

        return array_map(fn (array $row): array => [
            'id' => $row['id'],
            'full_name' => $row['full_name'],
            'document_number' => $row['document_number'],
            'market_name' => $row['market_name'],
            'local_codes' => $row['local_codes'] ?? '',
            'debt_eur' => $this->formatMoneyMinor($row['debt_eur_minor'] ?? 0),
            'debt_usd' => $this->formatMoneyMinor($row['debt_usd_minor'] ?? 0),
            'debt_bs' => $this->formatMoneyMinor($row['debt_bs_minor'] ?? 0),
            'days_overdue_avg' => $row['days_overdue_avg'],
            'days_overdue_max' => $row['days_overdue_max'],
            'locals_count' => $row['locals_count'],
            'charges_count' => $row['charges_count'],
            'severity' => $row['severity'],
        ], $rows);
    }

    private function resolveExporter(string $format): \App\Contracts\Exports\ExporterInterface
    {
        $exporter = app('exporter.'.$format);

        if (! $exporter instanceof \App\Contracts\Exports\ExporterInterface) {
            throw new InvalidArgumentException("Unsupported export format [{$format}]");
        }

        return $exporter;
    }

    private function formatMoneyMinor(mixed $amountMinor): string
    {
        return number_format(((int) $amountMinor) / 100, 2, ',', '.');
    }

    private function normalizeLocalCodeRangeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Z0-9]+/', '', trim((string) $value)) ?? '');

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Calcular severidad basada en días vencidos
     */
    private function calculateSeverity(int $days): string
    {
        return match (true) {
            $days > 90 => 'critical',
            $days > 60 => 'high',
            $days > 30 => 'medium',
            default => 'low'
        };
    }

    /**
     * Latest active contract by local at a given date.
     */
    private function buildActiveContractByLocalSubquery(string $today): \Illuminate\Database\Query\Builder
    {
        return DB::table('contract_local as cl')
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
    }

    private function buildLatestContractByLocalSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('contract_local as cl')
            ->join('contracts as ct', 'ct.id', '=', 'cl.contract_id')
            ->whereNull('ct.deleted_at')
            ->selectRaw('DISTINCT ON (cl.local_id) cl.local_id, cl.contract_id')
            ->orderBy('cl.local_id')
            ->orderByRaw('ct.start_date DESC NULLS LAST')
            ->orderByDesc('ct.id');
    }

    private function buildPrimaryConcessionaireByContractSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('concessionaire_contract')
            ->selectRaw('DISTINCT ON (contract_id) contract_id, concessionaire_id')
            ->orderBy('contract_id')
            ->orderByDesc('is_primary')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function rememberDebtAnalysisResult(string $scope, array $filters, callable $resolver): array
    {
        $normalizedFilters = $filters;
        unset($normalizedFilters['_skip_aggregate_windows']);
        ksort($normalizedFilters);

        $cacheKey = sprintf(
            'debt_analysis:%s:%s:%s',
            $scope,
            Carbon::now()->format('YmdHi'),
            sha1(json_encode($normalizedFilters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]')
        );

        return Cache::remember($cacheKey, 90, fn (): array => $resolver());
    }

    /**
     * Obtener tasa FX de EUR al día de hoy usando el servicio centralizado.
     *
     * @return float Tasa rate_to_ves (e.g., 50.25 significa 1 EUR = 50.25 Bs)
     */
    private function getActiveFxRate(): float
    {
        $today = Carbon::today();
        $rate = $this->fxService->resolveAt('EUR', $today);

        return $rate ? (float) $rate->getAttribute('rate_to_ves') : 1.0;
    }

    private function toVesMinor(int $amountMinor, float $rate): int
    {
        if ($amountMinor <= 0 || $rate <= 0) {
            return 0;
        }

        $rateMinor = (int) round($rate * 100);
        $prod = $amountMinor * $rateMinor;

        return (int) round($prod / 100);
    }

    // @phpstan-ignore-next-line
    private function fromVesMinor(int $bsMinor, float $rate): int
    {
        if ($bsMinor <= 0 || $rate <= 0) {
            return 0;
        }

        return (int) round(($bsMinor * 100) / $rate / 100);
    }
}
