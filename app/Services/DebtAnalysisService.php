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

        // CTE: pre-aggregate allocations per charge
        // CTE: pre-aggregate credit applications per charge (converted to Bs)
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
                       ch.amount_bs_minor_issued,
                       COALESCE(al.paid_bs, 0) AS paid_bs,
                       COALESCE(cr.credit_bs, 0) AS credit_bs,
                       (CURRENT_DATE - ch.due_on::date) AS days_late,
                       ch.debtor_id AS local_id,
                       ch.contract_id
                FROM charges ch
                INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                LEFT JOIN allocs al ON al.charge_id = ch.id
                LEFT JOIN credits cr ON cr.charge_id = ch.id
                WHERE chs.code IN ('ISSUED', 'PARTIAL')
                  AND ch.due_on < CURRENT_DATE
                  AND ch.deleted_at IS NULL
            ),
            per_concessionaire AS (
                SELECT
                    cn.id,
                    cn.full_name,
                    cn.document_number,
                    STRING_AGG(DISTINCT m.name, ', ' ORDER BY m.name) AS market_name,
                    COUNT(DISTINCT o.local_id)::int AS locals_count,
                    COUNT(DISTINCT o.charge_id)::int AS charges_count,
                    -- Outstanding in original EUR (amount_minor minus payments/credits converted back to EUR at current rate)
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'EUR'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$eurRateMinor}) - ROUND(o.credit_bs * 100.0 / {$eurRateMinor}), 0)
                        ELSE 0 END)::bigint AS outstanding_eur,
                    -- Outstanding in original USD
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'USD'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$usdRateMinor}) - ROUND(o.credit_bs * 100.0 / {$usdRateMinor}), 0)
                        ELSE 0 END)::bigint AS outstanding_usd,
                    -- Outstanding in native VES
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) NOT IN ('EUR', 'USD')
                        THEN GREATEST(o.amount_minor - o.paid_bs - o.credit_bs, 0)
                        ELSE 0 END)::bigint AS outstanding_ves,
                    ROUND(AVG(o.days_late)::numeric)::int AS days_overdue_avg,
                    MAX(o.days_late)::int AS days_overdue_max
                FROM concessionaires cn
                INNER JOIN concessionaire_contract cc ON cc.concessionaire_id = cn.id
                INNER JOIN contracts c ON c.id = cc.contract_id AND c.deleted_at IS NULL
                INNER JOIN contract_local cl ON cl.contract_id = c.id
                INNER JOIN overdue o ON o.local_id = cl.local_id AND o.contract_id = c.id
                LEFT JOIN locals l ON l.id = cl.local_id
                LEFT JOIN markets m ON m.id = l.market_id
                WHERE cn.deleted_at IS NULL
                GROUP BY cn.id, cn.full_name, cn.document_number
            )
            SELECT * FROM per_concessionaire
        ";

        // Build conditions for the outer wrapper
        $conditions = [];
        $bindings = [];

        if (! empty($filters['search'])) {
            $search = '%'.strtolower($filters['search']).'%';
            $conditions[] = '(LOWER(full_name) LIKE ? OR document_number LIKE ?)';
            $bindings[] = $search;
            $bindings[] = '%'.$filters['search'].'%';
        }

        if (! empty($filters['min_debt_eur'])) {
            $minMinor = (int) (((float) $filters['min_debt_eur']) * 100);
            $conditions[] = 'outstanding_eur >= ?';
            $bindings[] = $minMinor;
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
            $bsFromEur = (int) round($outEur * $eurRateMinor / 100);
            $bsFromUsd = (int) round($outUsd * $usdRateMinor / 100);

            return [
                'id' => (int) $row->id,
                'full_name' => (string) $row->full_name,
                'document_number' => (string) $row->document_number,
                'market_name' => (string) ($row->market_name ?? 'Sin asignar'),
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
                SELECT ch.id AS charge_id, ch.currency, ch.amount_minor,
                       ch.amount_bs_minor_issued,
                       COALESCE(al.paid_bs, 0) AS paid_bs,
                       COALESCE(cr.credit_bs, 0) AS credit_bs,
                       (CURRENT_DATE - ch.due_on::date) AS days_late,
                       ch.debtor_id AS local_id, ch.contract_id
                FROM charges ch
                INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                LEFT JOIN allocs al ON al.charge_id = ch.id
                LEFT JOIN credits cr ON cr.charge_id = ch.id
                WHERE chs.code IN ('ISSUED', 'PARTIAL')
                  AND ch.due_on < CURRENT_DATE AND ch.deleted_at IS NULL
                  AND ch.debtor_type = 'LOCAL'
            ),
            per_local AS (
                SELECT
                    l.id, l.code AS local_code, l.name AS local_name,
                    COALESCE(cn.full_name, 'Sin concesionario') AS concessionaire_name,
                    COALESCE(m.name, 'Sin asignar') AS market_name,
                    COALESCE(lt.name, 'Sin tipo') AS local_type_name,
                    COUNT(DISTINCT o.charge_id)::int AS charges_count,
                    -- Outstanding in original EUR
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'EUR'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$eurRateMinor}) - ROUND(o.credit_bs * 100.0 / {$eurRateMinor}), 0)
                        ELSE 0 END)::bigint AS outstanding_eur,
                    -- Outstanding in original USD
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) = 'USD'
                        THEN GREATEST(o.amount_minor - ROUND(o.paid_bs * 100.0 / {$usdRateMinor}) - ROUND(o.credit_bs * 100.0 / {$usdRateMinor}), 0)
                        ELSE 0 END)::bigint AS outstanding_usd,
                    -- Outstanding in native VES
                    SUM(CASE WHEN UPPER(COALESCE(o.currency, 'VES')) NOT IN ('EUR', 'USD')
                        THEN GREATEST(o.amount_minor - o.paid_bs - o.credit_bs, 0)
                        ELSE 0 END)::bigint AS outstanding_ves,
                    ROUND(AVG(o.days_late)::numeric)::int AS days_overdue_avg,
                    MAX(o.days_late)::int AS days_overdue_max
                FROM locals l
                INNER JOIN overdue o ON o.local_id = l.id
                LEFT JOIN contract_local cl ON cl.local_id = l.id AND cl.contract_id = o.contract_id
                LEFT JOIN contracts c ON c.id = cl.contract_id AND c.deleted_at IS NULL
                LEFT JOIN concessionaire_contract ccc ON ccc.contract_id = c.id
                LEFT JOIN concessionaires cn ON cn.id = ccc.concessionaire_id AND cn.deleted_at IS NULL
                LEFT JOIN markets m ON m.id = l.market_id
                LEFT JOIN local_types lt ON lt.id = l.local_type_id
                WHERE l.deleted_at IS NULL
                GROUP BY l.id, l.code, l.name, cn.full_name, m.name, lt.name
            )
            SELECT * FROM per_local
        ";

        $conditions = [];
        $bindings = [];

        if (! empty($filters['search'])) {
            $search = '%'.strtolower($filters['search']).'%';
            $conditions[] = '(LOWER(concessionaire_name) LIKE ? OR LOWER(local_code) LIKE ? OR LOWER(local_name) LIKE ?)';
            $bindings = array_merge($bindings, [$search, $search, $search]);
        }

        if (! empty($filters['market_id'])) {
            $conditions[] = 'market_name = (SELECT name FROM markets WHERE id = ?)';
            $bindings[] = (int) $filters['market_id'];
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
        $monthsSolvent = (int) ($filters['months_solvent'] ?? 1);

        $today = Carbon::today();
        $monthsAgo = $today->copy()->subMonths($monthsSolvent)->toDateString();

        // Concesionarios con contratos activos
        $activeConcessionaires = DB::table('concessionaires as cn')
            ->join('concessionaire_contract as cc', 'cc.concessionaire_id', '=', 'cn.id')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->whereIn('cs.code', ['VIG', 'VENC'])
            ->whereNull('cn.deleted_at')
            ->whereNull('c.deleted_at')
            ->distinct('cn.id')
            ->pluck('cn.id');

        // Concesionarios sin deuda vencida
        $query = DB::table('concessionaires as cn')
            ->leftJoin(DB::raw('(
                SELECT 
                    cn2.id as concessionaire_id,
                    MAX(p.paid_on) as last_payment_date,
                    COUNT(p.id) as payment_count
                FROM concessionaires cn2
                JOIN concessionaire_contract cc2 ON cc2.concessionaire_id = cn2.id
                JOIN contracts c2 ON c2.id = cc2.contract_id
                JOIN contract_local cl2 ON cl2.contract_id = c2.id
                JOIN charges ch2 ON ch2.debtor_id = cl2.local_id AND ch2.debtor_type = \'LOCAL\'
                JOIN payment_allocations pa2 ON pa2.charge_id = ch2.id
                JOIN payments p ON p.id = pa2.payment_id
                WHERE c2.deleted_at IS NULL
                  AND ch2.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                GROUP BY cn2.id
            ) as payment_info'), 'payment_info.concessionaire_id', '=', 'cn.id')
            ->whereIn('cn.id', $activeConcessionaires)
            ->whereNotExists(function ($sub) use ($today): void {
                $sub->from('concessionaire_contract as cc')
                    ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                    ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
                    ->join('charges as ch', function ($j): void {
                        $j->on('ch.debtor_id', '=', 'cl.local_id')
                            ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
                    })
                    ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
                    ->whereColumn('cc.concessionaire_id', 'cn.id')
                    ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
                    ->whereDate('ch.due_on', '<', $today->toDateString())
                    ->whereNull('c.deleted_at')
                    ->whereNull('ch.deleted_at');
            })
            ->whereNull('cn.deleted_at');

        // Aplicar filtros

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(cn.full_name) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhere('cn.document_number', 'LIKE', "%{$search}%");
            });
        }

        // Resolve market name through contract → local → market chain
        $query->leftJoin(DB::raw('(
                SELECT DISTINCT ON (cc3.concessionaire_id)
                       cc3.concessionaire_id,
                       m3.name AS market_name
                FROM concessionaire_contract cc3
                JOIN contracts c3 ON c3.id = cc3.contract_id AND c3.deleted_at IS NULL
                JOIN contract_local cl3 ON cl3.contract_id = c3.id
                JOIN locals l3 ON l3.id = cl3.local_id
                LEFT JOIN markets m3 ON m3.id = l3.market_id
                ORDER BY cc3.concessionaire_id, c3.id DESC
            ) as market_info'), 'market_info.concessionaire_id', '=', 'cn.id')
            ->select([
                'cn.id',
                'cn.full_name',
                'cn.document_number',
                DB::raw("COALESCE(market_info.market_name, 'Sin asignar') as market_name"),
                'payment_info.last_payment_date',
                DB::raw('COALESCE(payment_info.payment_count, 0)::int as total_payments'),
            ]);

        // Contar total
        $total = (clone $query)->count();

        // Ordenar y paginar
        $query->orderBy('payment_info.last_payment_date', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage);

        $results = $query->get();

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
                'last_page' => (int) ceil($total / $perPage),
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
            $usdRate = $this->fxService->resolveAt('USD', Carbon::today());
            $usdRate = $usdRate ? (float) $usdRate->getAttribute('rate_to_ves') : 1.0;
            $eurRateMinor = (int) round($fxRate * 100);
            $usdRateMinor = (int) round($usdRate * 100);
            $today = Carbon::today()->toDateString();

            // Pre-aggregate allocations to avoid duplicating charges on joins
            // Distribución por aging (usar CTE con allocs agregadas)
            $byAging = DB::select("
                WITH allocs AS (
                    SELECT charge_id, SUM(amount_bs_minor)::bigint AS paid_bs_minor
                    FROM payment_allocations
                    WHERE deleted_at IS NULL
                    GROUP BY charge_id
                ),
                aging_buckets AS (
                    SELECT 
                        ch.id,
                        ch.amount_minor,
                        COALESCE(ap.paid_bs_minor, 0) as paid_bs_minor,
                        CASE 
                            WHEN (CURRENT_DATE - ch.due_on::date) <= 30 THEN '0-30'
                            WHEN (CURRENT_DATE - ch.due_on::date) <= 60 THEN '31-60'
                            WHEN (CURRENT_DATE - ch.due_on::date) <= 90 THEN '61-90'
                            ELSE '90+'
                        END as bucket
                    FROM charges ch
                    INNER JOIN charge_statuses chs ON chs.id = ch.charge_status_id
                    LEFT JOIN allocs ap ON ap.charge_id = ch.id
                    WHERE chs.code IN ('ISSUED', 'PARTIAL')
                      AND ch.due_on < CURRENT_DATE
                      AND ch.deleted_at IS NULL
                )
                SELECT 
                    bucket,
                    SUM(amount_minor)::bigint as debt_eur_minor,
                    SUM(paid_bs_minor)::bigint as paid_bs_minor,
                    COUNT(DISTINCT id)::int as count
                FROM aging_buckets
                GROUP BY bucket
                ORDER BY 
                    CASE bucket
                        WHEN '0-30' THEN 1
                        WHEN '31-60' THEN 2
                        WHEN '61-90' THEN 3
                        ELSE 4
                    END
            ");

            $byAging = collect($byAging)
                ->map(function ($r) use ($fxRate) {
                    $outstanding = $this->calculateOutstanding(
                        (int) $r->debt_eur_minor,
                        (int) $r->paid_bs_minor,
                        $fxRate
                    );

                    return [
                        'bucket' => (string) $r->bucket,
                        'debt_eur_minor' => $outstanding['eur'],
                        'debt_bs_minor' => $outstanding['bs'],
                        'count' => (int) $r->count,
                    ];
                });

            // Distribución por mercado
            // Aggregated allocations subquery
            $allocSub = DB::table('payment_allocations as pa')
                ->select('pa.charge_id', DB::raw('SUM(pa.amount_bs_minor)::bigint as paid_bs_minor'))
                ->whereNull('pa.deleted_at')
                ->groupBy('pa.charge_id');

            $creditsSub = DB::table('credit_applications as ca')
                ->leftJoin('customer_credits as cc', 'cc.id', '=', 'ca.customer_credit_id')
                ->select('ca.charge_id')
                ->selectRaw(
                    "SUM(CASE UPPER(COALESCE(cc.currency, 'VES')) "
                    ."WHEN 'VES' THEN ca.amount_minor "
                    ."WHEN 'EUR' THEN (ca.amount_minor::bigint * {$eurRateMinor}) / 100 "
                    ."WHEN 'USD' THEN (ca.amount_minor::bigint * {$usdRateMinor}) / 100 "
                    .'ELSE 0 END)::bigint as credit_bs_minor'
                )
                ->groupBy('ca.charge_id');

            $overdueByMarketBase = DB::table('charges as ch')
                ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
                ->join('locals as l', function ($j): void {
                    $j->on('l.id', '=', 'ch.debtor_id')
                        ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
                })
                ->leftJoinSub($allocSub, 'ap', 'ap.charge_id', '=', 'ch.id')
                ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
                ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
                ->whereDate('ch.due_on', '<', $today)
                ->whereNull('ch.deleted_at')
                ->selectRaw("
                    ch.id as charge_id,
                    COALESCE(m.id, 0) as market_id,
                    COALESCE(m.name, 'Sin asignar') as market_name,
                    ch.amount_minor::bigint as amount_minor,
                    COALESCE(ap.paid_bs_minor, 0)::bigint as paid_bs_minor
                ");

            $aggByMarket = DB::table(DB::raw('('.$overdueByMarketBase->toSql().') as x'))
                ->mergeBindings($overdueByMarketBase)
                ->selectRaw('x.market_id, x.market_name, SUM(x.amount_minor)::bigint as debt_eur_minor, SUM(x.paid_bs_minor)::bigint as paid_bs_minor')
                ->groupBy('x.market_id', 'x.market_name');

            $concessionairesByMarket = DB::table('charges as ch')
                ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
                ->join('contracts as ct', 'ct.id', '=', 'ch.contract_id')
                ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'ct.id')
                ->join('locals as l', function ($j): void {
                    $j->on('l.id', '=', 'ch.debtor_id')
                        ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
                })
                ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
                ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
                ->whereDate('ch.due_on', '<', $today)
                ->whereNull('ch.deleted_at')
                ->whereNull('ct.deleted_at')
                ->groupBy('m.id')
                ->selectRaw('COALESCE(m.id, 0) as market_id, COUNT(DISTINCT cc.concessionaire_id)::int as concessionaires_count')
                ->pluck('concessionaires_count', 'market_id');

            $byMarket = $aggByMarket
                ->orderBy('debt_eur_minor', 'desc')
                ->get()
                ->map(function ($r) use ($fxRate, $concessionairesByMarket) {
                    $outstanding = $this->calculateOutstanding(
                        (int) $r->debt_eur_minor,
                        (int) $r->paid_bs_minor,
                        $fxRate
                    );

                    return [
                        'market_id' => (int) $r->market_id,
                        'market_name' => (string) $r->market_name,
                        'debt_eur_minor' => $outstanding['eur'],
                        'debt_bs_minor' => $outstanding['bs'],
                        'count' => (int) ($concessionairesByMarket->get((int) $r->market_id, 0)),
                    ];
                });

            // Distribución por tipo de local
            $byLocalType = DB::table('charges as ch')
                ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
                ->join('locals as l', function ($j): void {
                    $j->on('l.id', '=', 'ch.debtor_id')
                        ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
                })
                ->leftJoin('local_types as lt', 'lt.id', '=', 'l.local_type_id')
                ->leftJoinSub($allocSub, 'ap', 'ap.charge_id', '=', 'ch.id')
                ->selectRaw("COALESCE(lt.id, 0) as local_type_id, COALESCE(lt.name, 'Sin tipo') as local_type_name, SUM(ch.amount_minor)::bigint as debt_eur_minor, COALESCE(SUM(ap.paid_bs_minor), 0)::bigint as paid_bs_minor, COUNT(DISTINCT l.id)::int as locals_count")
                ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
                ->whereDate('ch.due_on', '<', $today)
                ->whereNull('ch.deleted_at')
                ->groupBy('lt.id', 'lt.name')
                ->orderBy('debt_eur_minor', 'desc')
                ->get()
                ->map(function ($r) use ($fxRate) {
                    $outstanding = $this->calculateOutstanding(
                        (int) $r->debt_eur_minor,
                        (int) $r->paid_bs_minor,
                        $fxRate
                    );

                    return [
                        'local_type_id' => (int) $r->local_type_id,
                        'local_type_name' => (string) $r->local_type_name,
                        'debt_eur_minor' => $outstanding['eur'],
                        'debt_bs_minor' => $outstanding['bs'],
                        'locals_count' => (int) $r->locals_count,
                    ];
                });

            $byLocalTypeBs = DB::table('charges as ch')
                ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
                ->join('locals as l', function ($j): void {
                    $j->on('l.id', '=', 'ch.debtor_id')
                        ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
                })
                ->leftJoin('local_types as lt', 'lt.id', '=', 'l.local_type_id')
                ->leftJoinSub($allocSub, 'ap', 'ap.charge_id', '=', 'ch.id')
                ->leftJoinSub($creditsSub, 'cr', 'cr.charge_id', '=', 'ch.id')
                ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
                ->whereDate('ch.due_on', '<', $today)
                ->whereNull('ch.deleted_at')
                ->selectRaw('COALESCE(lt.id, 0) as local_type_id')
                ->selectRaw("COALESCE(lt.name, 'Sin tipo') as local_type_name")
                ->selectRaw('COUNT(DISTINCT l.id)::int as locals_count')
                ->selectRaw("SUM(CASE WHEN ch.currency = 'EUR' THEN GREATEST(0, ((ch.amount_minor::bigint * {$eurRateMinor}) / 100) - COALESCE(ap.paid_bs_minor, 0) - COALESCE(cr.credit_bs_minor, 0)) ELSE 0 END)::bigint as debt_bs_minor_eur")
                ->selectRaw("SUM(CASE WHEN ch.currency = 'USD' THEN GREATEST(0, ((ch.amount_minor::bigint * {$usdRateMinor}) / 100) - COALESCE(ap.paid_bs_minor, 0) - COALESCE(cr.credit_bs_minor, 0)) ELSE 0 END)::bigint as debt_bs_minor_usd")
                ->selectRaw("SUM(GREATEST(0, (CASE WHEN ch.currency = 'EUR' THEN (ch.amount_minor::bigint * {$eurRateMinor}) / 100 WHEN ch.currency = 'USD' THEN (ch.amount_minor::bigint * {$usdRateMinor}) / 100 ELSE 0 END) - COALESCE(ap.paid_bs_minor, 0) - COALESCE(cr.credit_bs_minor, 0)))::bigint as debt_bs_minor")
                ->groupBy('lt.id', 'lt.name')
                ->orderByRaw('debt_bs_minor DESC')
                ->get()
                ->map(function ($r) use ($eurRateMinor, $usdRateMinor) {
                    $bsEur = (int) ($r->debt_bs_minor_eur ?? 0);
                    $bsUsd = (int) ($r->debt_bs_minor_usd ?? 0);
                    $eurMinor = $eurRateMinor > 0 ? (int) round($bsEur * 100 / $eurRateMinor) : 0;
                    $usdMinor = $usdRateMinor > 0 ? (int) round($bsUsd * 100 / $usdRateMinor) : 0;

                    return [
                        'local_type_id' => (int) $r->local_type_id,
                        'local_type_name' => (string) $r->local_type_name,
                        'locals_count' => (int) $r->locals_count,
                        'debt_bs_minor' => (int) ($r->debt_bs_minor ?? 0),
                        'debt_eur_minor' => $eurMinor,
                        'debt_usd_minor' => $usdMinor,
                        'debt_bs_minor_eur' => $bsEur,
                        'debt_bs_minor_usd' => $bsUsd,
                    ];
                });

            return [
                'by_aging' => $byAging->all(),
                'by_market' => $byMarket->all(),
                'by_local_type' => $byLocalType->all(),
                'by_local_type_bs' => $byLocalTypeBs->all(),
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

    /**
     * Convertir monto en moneda original (minor units) a Bs (minor units).
     *
     * Aplica la misma política de truncamiento que FxConversionHelper::toVes:
     * amount (2dp) * rate (2dp) => 4dp, truncar a 2dp.
     *
     * @param  int  $amountMinor  Monto en moneda original (e.g., 10000 = €100.00)
     * @param  float  $rate  Tasa rate_to_ves (e.g., 50.25)
     * @return int Monto en Bs minor units
     */
    private function toVesMinor(int $amountMinor, float $rate): int
    {
        if ($amountMinor <= 0 || $rate <= 0) {
            return 0;
        }

        // Política BCV: redondear a 2 decimales basándose en el tercer decimal
        $rateMinor = (int) round($rate * 100);
        $prod = $amountMinor * $rateMinor;

        return (int) round($prod / 100);
    }

    /**
     * Convertir monto en Bs (minor units) a moneda original (minor units).
     *
     * Aplica la misma política de redondeo que FxConversionHelper::fromVes:
     * Bs (2dp) / rate (2dp) => 4dp, redondear a 2dp.
     *
     * @param  int  $bsMinor  Monto en Bs minor units
     * @param  float  $rate  Tasa rate_to_ves (e.g., 50.25)
     * @return int Monto en moneda original minor units
     */
    private function fromVesMinor(int $bsMinor, float $rate): int
    {
        if ($bsMinor <= 0 || $rate <= 0) {
            return 0;
        }

        // Política BCV: redondear a 2 decimales basándose en el tercer decimal
        return (int) round(($bsMinor * 100) / $rate / 100);
    }

    /**
     * Calcular outstanding en Bs y EUR usando política de truncamiento consistente.
     *
     * @param  int  $debtEurMinor  Monto original del cargo en EUR minor
     * @param  int  $paidBsMinor  Total pagado en Bs minor
     * @param  float  $rate  Tasa rate_to_ves actual
     * @return array{eur: int, bs: int} Outstanding en ambas monedas
     */
    private function calculateOutstanding(int $debtEurMinor, int $paidBsMinor, float $rate): array
    {
        // Convertir deuda EUR a Bs usando tasa actual
        $debtBsMinor = $this->toVesMinor($debtEurMinor, $rate);

        // Outstanding en Bs = deuda_bs - pagado_bs
        $outstandingBs = max(0, $debtBsMinor - $paidBsMinor);

        // Convertir outstanding Bs a EUR para mostrar
        $outstandingEur = $this->fromVesMinor($outstandingBs, $rate);

        return ['eur' => $outstandingEur, 'bs' => $outstandingBs];
    }
}
