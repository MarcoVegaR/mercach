<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\FxRateServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $page = (int) ($filters['page'] ?? 1);
        $perPage = min((int) ($filters['per_page'] ?? 25), 100);
        $sortBy = $filters['sort_by'] ?? 'debt_bs';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $fxRate = $this->getActiveFxRate();
        $usdRateObj = $this->fxService->resolveAt('USD', Carbon::today());
        $usdRate = $usdRateObj ? (float) $usdRateObj->getAttribute('rate_to_ves') : 1.0;
        $eurRateMinor = (int) round($fxRate * 100);
        $usdRateMinor = (int) round($usdRate * 100);

        // CTE-based query:
        // - Maps overdue LOCAL charges through active contract by local (even when ch.contract_id is NULL)
        // - Includes overdue CONCESSIONAIRE charges directly
        // - Aggregates debt in original currencies and Bs for sorting/reporting
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
            overdue_local AS (
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
                  AND ch.due_on < CURRENT_DATE
                  AND ch.deleted_at IS NULL
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
                  AND ch.due_on < CURRENT_DATE
                  AND ch.deleted_at IS NULL
                  AND ch.debtor_type = 'CONCESSIONAIRE'
            ),
            mapped_local AS (
                SELECT cc.concessionaire_id,
                       ol.charge_id,
                       ol.currency,
                       ol.amount_minor,
                       ol.paid_bs,
                       ol.credit_bs,
                       ol.days_late,
                       ol.local_id,
                       l.market_id
                FROM overdue_local ol
                INNER JOIN active_contract_by_local acl ON acl.local_id = ol.local_id
                INNER JOIN concessionaire_contract cc ON cc.contract_id = acl.contract_id
                INNER JOIN concessionaires cn ON cn.id = cc.concessionaire_id AND cn.deleted_at IS NULL
                LEFT JOIN locals l ON l.id = ol.local_id
            ),
            mapped_concessionaire AS (
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
                SELECT * FROM mapped_local
                UNION ALL
                SELECT * FROM mapped_concessionaire
            ),
            per_concessionaire AS (
                SELECT
                    cn.id,
                    cn.full_name,
                    cn.document_number,
                    STRING_AGG(DISTINCT m.name, ', ' ORDER BY m.name) AS market_name,
                    ARRAY_REMOVE(ARRAY_AGG(DISTINCT ao.market_id), NULL)::int[] AS market_ids,
                    COUNT(DISTINCT ao.local_id)::int AS locals_count,
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
                    ROUND(AVG(ao.days_late)::numeric)::int AS days_overdue_avg,
                    MAX(ao.days_late)::int AS days_overdue_max
                FROM all_overdue ao
                INNER JOIN concessionaires cn ON cn.id = ao.concessionaire_id
                LEFT JOIN markets m ON m.id = ao.market_id
                WHERE cn.deleted_at IS NULL
                GROUP BY cn.id, cn.full_name, cn.document_number
            )
            SELECT * FROM per_concessionaire
        ";

        // Build conditions for the outer wrapper
        $conditions = [];
        $bindings = [];

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

        if (array_key_exists('min_days', $filters) && $filters['min_days'] !== null && $filters['min_days'] !== '') {
            $conditions[] = 'days_overdue_max >= ?';
            $bindings[] = (int) $filters['min_days'];
        }

        if (array_key_exists('market_id', $filters) && $filters['market_id'] !== null && $filters['market_id'] !== '') {
            $conditions[] = '(market_ids IS NOT NULL AND ? = ANY(market_ids))';
            $bindings[] = (int) $filters['market_id'];
        }

        $whereClause = $conditions !== [] ? ' WHERE '.implode(' AND ', $conditions) : '';

        // Count total
        $countSql = "SELECT COUNT(*) FROM ({$sql}{$whereClause}) AS cnt";
        $total = (int) DB::selectOne($countSql, $bindings)->count;

        // Sort
        $sortColumn = match ($sortBy) {
            'debt_eur' => 'outstanding_eur',
            'debt_usd' => 'outstanding_usd',
            'days_overdue' => 'days_overdue_avg',
            'name' => 'full_name',
            default => "(outstanding_eur * {$eurRateMinor} / 100 + outstanding_usd * {$usdRateMinor} / 100 + outstanding_ves)",
        };
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($page - 1) * $perPage;
        $dataSql = "{$sql}{$whereClause} ORDER BY {$sortColumn} {$direction} NULLS LAST LIMIT {$perPage} OFFSET {$offset}";
        $rows = DB::select($dataSql, $bindings);

        // Summary from full filtered set (not just page)
        $summarySql = "SELECT
            COALESCE(SUM(outstanding_eur), 0)::bigint AS total_eur,
            COALESCE(SUM(outstanding_usd), 0)::bigint AS total_usd,
            COALESCE(SUM(outstanding_ves), 0)::bigint AS total_ves,
            ROUND(AVG(days_overdue_avg)::numeric)::int AS avg_days
            FROM ({$sql}{$whereClause}) AS s";
        $summaryRow = DB::selectOne($summarySql, $bindings);

        $totalEur = (int) ($summaryRow->total_eur ?? 0);
        $totalUsd = (int) ($summaryRow->total_usd ?? 0);
        $totalVes = (int) ($summaryRow->total_ves ?? 0);

        // Map rows — debt_bs_minor = outstanding_original * current_rate (matches economic profile)
        $data = collect($rows)->map(function ($row) use ($eurRateMinor, $usdRateMinor) {
            $outEur = (int) $row->outstanding_eur;
            $outUsd = (int) $row->outstanding_usd;
            $outVes = (int) $row->outstanding_ves;
            $bsFromEur = $this->toVesMinor($outEur, (float) $eurRateMinor / 100);
            $bsFromUsd = $this->toVesMinor($outUsd, (float) $usdRateMinor / 100);

            return [
                'id' => (int) $row->id,
                'full_name' => (string) $row->full_name,
                'document_number' => (string) $row->document_number,
                'market_name' => (string) (($row->market_name !== null && $row->market_name !== '') ? $row->market_name : 'Sin asignar'),
                'debt_eur_minor' => $outEur,
                'debt_usd_minor' => $outUsd,
                'debt_bs_minor' => $bsFromEur + $bsFromUsd + $outVes,
                'days_overdue_avg' => (int) $row->days_overdue_avg,
                'days_overdue_max' => (int) $row->days_overdue_max,
                'locals_count' => (int) $row->locals_count,
                'charges_count' => (int) $row->charges_count,
                'severity' => $this->calculateSeverity((int) $row->days_overdue_avg),
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
                'total_count' => $total,
                'avg_days_overdue' => (int) ($summaryRow->avg_days ?? 0),
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
        $page = (int) ($filters['page'] ?? 1);
        $perPage = min((int) ($filters['per_page'] ?? 25), 100);
        $sortBy = $filters['sort_by'] ?? 'debt_bs';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $fxRate = $this->getActiveFxRate();
        $usdRateObj = $this->fxService->resolveAt('USD', Carbon::today());
        $usdRate = $usdRateObj ? (float) $usdRateObj->getAttribute('rate_to_ves') : 1.0;
        $eurRateMinor = (int) round($fxRate * 100);
        $usdRateMinor = (int) round($usdRate * 100);

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
                  AND ch.due_on < CURRENT_DATE
                  AND ch.deleted_at IS NULL
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
            per_local AS (
                SELECT
                    l.id,
                    l.code AS local_code,
                    l.name AS local_name,
                    COALESCE(ac.concessionaire_name, 'Sin concesionario') AS concessionaire_name,
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
                    ROUND(AVG(o.days_late)::numeric)::int AS days_overdue_avg,
                    MAX(o.days_late)::int AS days_overdue_max
                FROM locals l
                INNER JOIN overdue o ON o.local_id = l.id
                LEFT JOIN active_concessionaire_by_local ac ON ac.local_id = l.id
                LEFT JOIN markets m ON m.id = l.market_id
                LEFT JOIN local_types lt ON lt.id = l.local_type_id
                WHERE l.deleted_at IS NULL
                GROUP BY l.id, l.code, l.name, ac.concessionaire_name, m.name, lt.name, l.market_id, l.local_type_id
            )
            SELECT * FROM per_local
        ";

        $conditions = [];
        $bindings = [];

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

        if (array_key_exists('min_days', $filters) && $filters['min_days'] !== null && $filters['min_days'] !== '') {
            $conditions[] = 'days_overdue_max >= ?';
            $bindings[] = (int) $filters['min_days'];
        }

        $whereClause = $conditions !== [] ? ' WHERE '.implode(' AND ', $conditions) : '';

        $total = (int) DB::selectOne("SELECT COUNT(*) FROM ({$sql}{$whereClause}) AS cnt", $bindings)->count;

        $sortColumn = match ($sortBy) {
            'code' => 'local_code',
            'debt_eur' => 'outstanding_eur',
            'debt_usd' => 'outstanding_usd',
            'days_overdue' => 'days_overdue_avg',
            default => "(outstanding_eur * {$eurRateMinor} / 100 + outstanding_usd * {$usdRateMinor} / 100 + outstanding_ves)",
        };
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;

        $rows = DB::select("{$sql}{$whereClause} ORDER BY {$sortColumn} {$direction} NULLS LAST LIMIT {$perPage} OFFSET {$offset}", $bindings);

        $summarySql = "SELECT
            COALESCE(SUM(outstanding_eur),0)::bigint AS total_eur,
            COALESCE(SUM(outstanding_usd),0)::bigint AS total_usd,
            COALESCE(SUM(outstanding_ves),0)::bigint AS total_ves,
            ROUND(AVG(days_overdue_avg)::numeric)::int AS avg_days
            FROM ({$sql}{$whereClause}) AS s";
        $summaryRow = DB::selectOne($summarySql, $bindings);

        $totalEur = (int) ($summaryRow->total_eur ?? 0);
        $totalUsd = (int) ($summaryRow->total_usd ?? 0);
        $totalVes = (int) ($summaryRow->total_ves ?? 0);

        $data = collect($rows)->map(function ($row) use ($eurRateMinor, $usdRateMinor) {
            $outEur = (int) $row->outstanding_eur;
            $outUsd = (int) $row->outstanding_usd;
            $outVes = (int) $row->outstanding_ves;
            $bsFromEur = (int) round($outEur * $eurRateMinor / 100);
            $bsFromUsd = (int) round($outUsd * $usdRateMinor / 100);

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
                'days_overdue_avg' => (int) $row->days_overdue_avg,
                'charges_count' => (int) $row->charges_count,
                'severity' => $this->calculateSeverity((int) $row->days_overdue_avg),
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
                'total_count' => $total,
                'avg_days_overdue' => (int) ($summaryRow->avg_days ?? 0),
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
        $perPage = min((int) ($filters['per_page'] ?? 25), 100);
        $monthsSolvent = max(1, (int) ($filters['months_solvent'] ?? 1));

        $today = Carbon::today();
        $todayStr = $today->toDateString();
        $monthsAgo = $today->copy()->subMonthsNoOverflow($monthsSolvent)->toDateString();

        $activeContractByLocal = $this->buildActiveContractByLocalSubquery($todayStr);

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
            ->joinSub($activeContractByLocal, 'acl', 'acl.local_id', '=', 'l.id')
            ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'acl.contract_id')
            ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
            ->whereDate('ch.due_on', '<', $todayStr)
            ->whereDate('ch.due_on', '>=', $monthsAgo)
            ->whereNull('ch.deleted_at')
            ->whereNull('l.deleted_at')
            ->select('cc.concessionaire_id');

        $delinquentDirect = DB::table('charges as ch')
            ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
            ->where('ch.debtor_type', 'CONCESSIONAIRE')
            ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
            ->whereDate('ch.due_on', '<', $todayStr)
            ->whereDate('ch.due_on', '>=', $monthsAgo)
            ->whereNull('ch.deleted_at')
            ->selectRaw('ch.debtor_id as concessionaire_id');

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
            ->joinSub($activeContractByLocal, 'acl', 'acl.local_id', '=', 'l.id')
            ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'acl.contract_id')
            ->whereNull('pa.deleted_at')
            ->whereNull('p.deleted_at')
            ->whereNull('ch.deleted_at')
            ->whereNull('l.deleted_at')
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
                    SELECT ch.id AS charge_id,
                           ch.debtor_type,
                           ch.debtor_id,
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
                      AND ch.due_on < CURRENT_DATE
                      AND ch.deleted_at IS NULL
                ),
                outstanding AS (
                    SELECT
                        o.charge_id,
                        o.debtor_type,
                        o.debtor_id,
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

            $byAgingRows = DB::select("{$baseOutstandingSql}
                SELECT
                    bucket,
                    COALESCE(SUM(outstanding_eur_minor), 0)::bigint AS debt_eur_minor,
                    COALESCE(SUM(outstanding_usd_minor), 0)::bigint AS debt_usd_minor,
                    COALESCE(SUM(outstanding_bs_minor), 0)::bigint AS debt_bs_minor,
                    COUNT(DISTINCT charge_id)::int AS count
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
                ORDER BY CASE bucket
                    WHEN '0-30' THEN 1
                    WHEN '31-60' THEN 2
                    WHEN '61-90' THEN 3
                    ELSE 4
                END
            ");

            $byAging = collect($byAgingRows)->map(function ($row) {
                return [
                    'bucket' => (string) $row->bucket,
                    'debt_eur_minor' => (int) $row->debt_eur_minor,
                    'debt_usd_minor' => (int) $row->debt_usd_minor,
                    'debt_bs_minor' => (int) $row->debt_bs_minor,
                    'count' => (int) $row->count,
                ];
            });

            $byMarketRows = DB::select("{$baseOutstandingSql}
                SELECT
                    COALESCE(m.id, 0) AS market_id,
                    COALESCE(m.name, 'Sin asignar') AS market_name,
                    COALESCE(SUM(o.outstanding_eur_minor), 0)::bigint AS debt_eur_minor,
                    COALESCE(SUM(o.outstanding_usd_minor), 0)::bigint AS debt_usd_minor,
                    COALESCE(SUM(o.outstanding_bs_minor), 0)::bigint AS debt_bs_minor
                FROM outstanding o
                INNER JOIN locals l ON l.id = o.debtor_id AND o.debtor_type = 'LOCAL'
                LEFT JOIN markets m ON m.id = l.market_id
                WHERE l.deleted_at IS NULL
                  AND o.outstanding_bs_minor > 0
                GROUP BY COALESCE(m.id, 0), COALESCE(m.name, 'Sin asignar')
                ORDER BY debt_bs_minor DESC
            ");

            $activeContractByLocal = $this->buildActiveContractByLocalSubquery($today);

            $concessionairesByMarket = DB::table('charges as ch')
                ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
                ->join('locals as l', function ($j): void {
                    $j->on('l.id', '=', 'ch.debtor_id')
                        ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
                })
                ->joinSub($activeContractByLocal, 'acl', 'acl.local_id', '=', 'l.id')
                ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'acl.contract_id')
                ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
                ->whereDate('ch.due_on', '<', $today)
                ->whereNull('ch.deleted_at')
                ->whereNull('l.deleted_at')
                ->groupByRaw('COALESCE(l.market_id, 0)')
                ->selectRaw('COALESCE(l.market_id, 0) as market_id, COUNT(DISTINCT cc.concessionaire_id)::int as concessionaires_count')
                ->pluck('concessionaires_count', 'market_id');

            $byMarket = collect($byMarketRows)->map(function ($row) use ($concessionairesByMarket) {
                return [
                    'market_id' => (int) $row->market_id,
                    'market_name' => (string) $row->market_name,
                    'debt_eur_minor' => (int) $row->debt_eur_minor,
                    'debt_usd_minor' => (int) $row->debt_usd_minor,
                    'debt_bs_minor' => (int) $row->debt_bs_minor,
                    'count' => (int) ($concessionairesByMarket->get((int) $row->market_id, 0)),
                ];
            });

            $byLocalTypeRows = DB::select("{$baseOutstandingSql}
                SELECT
                    COALESCE(lt.id, 0) AS local_type_id,
                    COALESCE(lt.name, 'Sin tipo') AS local_type_name,
                    COUNT(DISTINCT l.id)::int AS locals_count,
                    COALESCE(SUM(o.outstanding_eur_minor), 0)::bigint AS debt_eur_minor,
                    COALESCE(SUM(o.outstanding_usd_minor), 0)::bigint AS debt_usd_minor,
                    COALESCE(SUM(o.outstanding_bs_minor), 0)::bigint AS debt_bs_minor
                FROM outstanding o
                INNER JOIN locals l ON l.id = o.debtor_id AND o.debtor_type = 'LOCAL'
                LEFT JOIN local_types lt ON lt.id = l.local_type_id
                WHERE l.deleted_at IS NULL
                  AND o.outstanding_bs_minor > 0
                GROUP BY COALESCE(lt.id, 0), COALESCE(lt.name, 'Sin tipo')
                ORDER BY debt_bs_minor DESC
            ");

            $byLocalType = collect($byLocalTypeRows)->map(function ($row) {
                return [
                    'local_type_id' => (int) $row->local_type_id,
                    'local_type_name' => (string) $row->local_type_name,
                    'locals_count' => (int) $row->locals_count,
                    'debt_bs_minor' => (int) $row->debt_bs_minor,
                    'debt_eur_minor' => (int) $row->debt_eur_minor,
                    'debt_usd_minor' => (int) $row->debt_usd_minor,
                ];
            });

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

        // Obtener datos sin paginación
        $data = $scope === 'locals'
            ? $this->getDelinquentLocals(array_merge($filters, ['per_page' => 10000]))
            : $this->getDelinquentConcessionaires(array_merge($filters, ['per_page' => 10000]));

        $filename = sprintf(
            'analisis-deuda-%s-%s.%s',
            $scope,
            Carbon::now()->format('Y-m-d-His'),
            $format
        );

        return response()->streamDownload(function () use ($data, $scope): void {
            $handle = fopen('php://output', 'w');

            // Headers CSV
            if ($scope === 'concessionaires') {
                fputcsv($handle, [
                    'ID', 'Concesionario', 'Documento', 'Mercado',
                    'Deuda EUR', 'Deuda USD', 'Deuda Bs',
                    'Días Vencidos Promedio', 'Días Vencidos Máximo',
                    'Locales', 'Cargos', 'Severidad',
                ]);
            } else {
                fputcsv($handle, [
                    'ID', 'Código Local', 'Nombre Local', 'Concesionario',
                    'Mercado', 'Tipo Local', 'Deuda EUR', 'Deuda USD',
                    'Deuda Bs', 'Días Vencidos', 'Cargos', 'Severidad',
                ]);
            }

            // Rows
            foreach ($data['data'] as $row) {
                $csvRow = $scope === 'concessionaires'
                    ? [
                        $row['id'],
                        $row['full_name'],
                        $row['document_number'],
                        $row['market_name'],
                        number_format($row['debt_eur_minor'] / 100, 2, ',', '.'),
                        number_format(($row['debt_usd_minor'] ?? 0) / 100, 2, ',', '.'),
                        number_format($row['debt_bs_minor'] / 100, 2, ',', '.'),
                        $row['days_overdue_avg'],
                        $row['days_overdue_max'],
                        $row['locals_count'],
                        $row['charges_count'],
                        $row['severity'],
                    ]
                    : [
                        $row['id'],
                        $row['local_code'],
                        $row['local_name'],
                        $row['concessionaire_name'],
                        $row['market_name'],
                        $row['local_type_name'],
                        number_format($row['debt_eur_minor'] / 100, 2, ',', '.'),
                        number_format(($row['debt_usd_minor'] ?? 0) / 100, 2, ',', '.'),
                        number_format($row['debt_bs_minor'] / 100, 2, ',', '.'),
                        $row['days_overdue_avg'],
                        $row['charges_count'],
                        $row['severity'],
                    ];

                fputcsv($handle, $csvRow);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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
