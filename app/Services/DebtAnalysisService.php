<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtAnalysisService
{
    /**
     * Obtener concesionarios morosos con paginación y filtros
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDelinquentConcessionaires(array $filters): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = min((int) ($filters['per_page'] ?? 25), 100);
        $sortBy = $filters['sort_by'] ?? 'debt_eur';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $fxRate = $this->getActiveFxRate();
        $today = Carbon::today()->toDateString();

        // Query base
        $baseQuery = DB::table('concessionaires as cn')
            ->join('concessionaire_contract as cc', 'cc.concessionaire_id', '=', 'cn.id')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->join('charges as ch', function ($j): void {
                $j->on('ch.debtor_id', '=', 'l.id')
                    ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
            })
            ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
            ->leftJoin('payment_allocations as pa', 'pa.charge_id', '=', 'ch.id')
            ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
            ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
            ->whereDate('ch.due_on', '<', $today)
            ->whereNull('cn.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereNull('ch.deleted_at');

        // Aplicar filtros WHERE (antes de GROUP BY)
        if (! empty($filters['market_id'])) {
            $baseQuery->where('l.market_id', (int) $filters['market_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $baseQuery->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(cn.full_name) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhere('cn.document_number', 'LIKE', "%{$search}%");
            });
        }

        // Selects agregados
        $query = (clone $baseQuery)
            ->selectRaw("
                cn.id,
                cn.full_name,
                cn.document_number,
                COALESCE(STRING_AGG(DISTINCT m.name, ', ' ORDER BY m.name), 'Sin asignar') as market_name,
                COUNT(DISTINCT l.id)::int as locals_count,
                COUNT(DISTINCT ch.id)::int as charges_count,
                (SELECT SUM(ch2.amount_minor)
                 FROM charges ch2
                 INNER JOIN charge_statuses chs2 ON chs2.id = ch2.charge_status_id
                 INNER JOIN contract_local cl2 ON ch2.debtor_id = cl2.local_id AND ch2.debtor_type = 'LOCAL'
                 INNER JOIN contracts c2 ON c2.id = cl2.contract_id
                 INNER JOIN concessionaire_contract cc2 ON cc2.contract_id = c2.id
                 WHERE cc2.concessionaire_id = cn.id
                   AND chs2.code IN ('ISSUED', 'PARTIAL')
                   AND ch2.due_on < CURRENT_DATE
                   AND ch2.deleted_at IS NULL
                   AND c2.deleted_at IS NULL
                )::bigint as debt_eur_minor,
                (SELECT COALESCE(SUM(pa2.amount_bs_minor), 0)
                 FROM payment_allocations pa2
                 INNER JOIN charges ch3 ON ch3.id = pa2.charge_id
                 INNER JOIN charge_statuses chs3 ON chs3.id = ch3.charge_status_id
                 INNER JOIN contract_local cl3 ON ch3.debtor_id = cl3.local_id AND ch3.debtor_type = 'LOCAL'
                 INNER JOIN contracts c3 ON c3.id = cl3.contract_id
                 INNER JOIN concessionaire_contract cc3 ON cc3.contract_id = c3.id
                 WHERE cc3.concessionaire_id = cn.id
                   AND chs3.code IN ('ISSUED', 'PARTIAL')
                   AND ch3.due_on < CURRENT_DATE
                   AND ch3.deleted_at IS NULL
                   AND c3.deleted_at IS NULL
                )::bigint as paid_bs_minor,
                (SELECT ROUND(AVG(EXTRACT(DAY FROM AGE(CURRENT_DATE, ch4.due_on::date)))::numeric)
                 FROM charges ch4
                 INNER JOIN charge_statuses chs4 ON chs4.id = ch4.charge_status_id
                 INNER JOIN contract_local cl4 ON ch4.debtor_id = cl4.local_id AND ch4.debtor_type = 'LOCAL'
                 INNER JOIN contracts c4 ON c4.id = cl4.contract_id
                 INNER JOIN concessionaire_contract cc4 ON cc4.contract_id = c4.id
                 WHERE cc4.concessionaire_id = cn.id
                   AND chs4.code IN ('ISSUED', 'PARTIAL')
                   AND ch4.due_on < CURRENT_DATE
                   AND ch4.deleted_at IS NULL
                   AND c4.deleted_at IS NULL
                )::int as days_overdue_avg,
                (SELECT MAX(EXTRACT(DAY FROM AGE(CURRENT_DATE, ch5.due_on::date)))
                 FROM charges ch5
                 INNER JOIN charge_statuses chs5 ON chs5.id = ch5.charge_status_id
                 INNER JOIN contract_local cl5 ON ch5.debtor_id = cl5.local_id AND ch5.debtor_type = 'LOCAL'
                 INNER JOIN contracts c5 ON c5.id = cl5.contract_id
                 INNER JOIN concessionaire_contract cc5 ON cc5.contract_id = c5.id
                 WHERE cc5.concessionaire_id = cn.id
                   AND chs5.code IN ('ISSUED', 'PARTIAL')
                   AND ch5.due_on < CURRENT_DATE
                   AND ch5.deleted_at IS NULL
                   AND c5.deleted_at IS NULL
                )::int as days_overdue_max
            ")
            ->groupBy('cn.id', 'cn.full_name', 'cn.document_number');

        // Contar total
        $total = DB::table(DB::raw("({$query->toSql()}) as subquery"))
            ->mergeBindings($query)
            ->count();

        // Ordenar
        $sortColumn = match ($sortBy) {
            'debt_bs' => DB::raw('(debt_eur_minor * '.$fxRate.' - paid_bs_minor)'),
            'days_overdue' => 'days_overdue_avg',
            'name' => 'cn.full_name',
            default => 'debt_eur_minor'
        };
        $query->orderBy($sortColumn, $sortDir);

        // Paginar
        $results = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Procesar resultados (página actual)
        $data = $results->map(function ($row) use ($fxRate) {
            $outstandingEur = max(0, $row->debt_eur_minor - ($row->paid_bs_minor / $fxRate));
            $outstandingBs = max(0, ($row->debt_eur_minor * $fxRate) - $row->paid_bs_minor);

            return [
                'id' => (int) $row->id,
                'full_name' => (string) $row->full_name,
                'document_number' => (string) $row->document_number,
                'market_name' => (string) $row->market_name,
                'debt_eur_minor' => (int) $outstandingEur,
                'debt_bs_minor' => (int) $outstandingBs,
                'days_overdue_avg' => (int) $row->days_overdue_avg,
                'days_overdue_max' => (int) $row->days_overdue_max,
                'locals_count' => (int) $row->locals_count,
                'charges_count' => (int) $row->charges_count,
                'severity' => $this->calculateSeverity((int) $row->days_overdue_avg),
            ];
        });

        // Calcular resumen (sobre el conjunto filtrado completo, no solo la página)
        $agg = (clone $baseQuery)
            ->selectRaw('SUM(ch.amount_minor)::bigint as sum_eur_minor')
            ->selectRaw('COALESCE(SUM(pa.amount_bs_minor), 0)::bigint as sum_paid_bs_minor')
            ->first();

        $sumEurMinor = (int) ($agg->sum_eur_minor ?? 0);
        $sumPaidBsMinor = (int) ($agg->sum_paid_bs_minor ?? 0);
        $sumOutstandingBs = max(0, (int) ($sumEurMinor * $fxRate) - $sumPaidBsMinor);
        $sumOutstandingEur = (int) ($sumOutstandingBs / $fxRate);

        $summary = [
            'total_debt_eur_minor' => $sumOutstandingEur,
            'total_debt_bs_minor' => $sumOutstandingBs,
            'total_count' => $total,
            'avg_debt_eur_minor' => $total > 0 ? (int) ($sumOutstandingEur / $total) : 0,
            'avg_days_overdue' => $total > 0 ? (int) $data->avg('days_overdue_avg') : 0,
        ];

        return [
            'data' => $data->values()->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'summary' => $summary,
            'fx_rate' => $fxRate,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Obtener locales morosos con paginación
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDelinquentLocals(array $filters): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = min((int) ($filters['per_page'] ?? 25), 100);
        $sortBy = $filters['sort_by'] ?? 'debt_eur';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $fxRate = $this->getActiveFxRate();
        $today = Carbon::today()->toDateString();

        // Query base
        $baseQuery = DB::table('locals as l')
            ->join('contract_local as cl', 'cl.local_id', '=', 'l.id')
            ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
            ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'c.id')
            ->join('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->join('charges as ch', function ($j): void {
                $j->on('ch.debtor_id', '=', 'l.id')
                    ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
            })
            ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
            ->leftJoin('payment_allocations as pa', 'pa.charge_id', '=', 'ch.id')
            ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
            ->leftJoin('local_types as lt', 'lt.id', '=', 'l.local_type_id')
            ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
            ->whereDate('ch.due_on', '<', $today)
            ->whereNull('l.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereNull('ch.deleted_at');

        // Aplicar filtros WHERE (antes de GROUP BY)
        if (! empty($filters['market_id'])) {
            $baseQuery->where('l.market_id', (int) $filters['market_id']);
        }

        if (! empty($filters['local_type_id'])) {
            $baseQuery->where('l.local_type_id', (int) $filters['local_type_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $baseQuery->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(cn.full_name) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhere('cn.document_number', 'LIKE', "%{$search}%")
                    ->orWhereRaw('LOWER(l.code) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereRaw('LOWER(l.name) LIKE ?', ['%'.strtolower($search).'%']);
            });
        }

        // Selects agregados
        $query = (clone $baseQuery)
            ->selectRaw("
                l.id,
                l.code as local_code,
                l.name as local_name,
                cn.full_name as concessionaire_name,
                COALESCE(m.name, 'Sin asignar') as market_name,
                COALESCE(lt.name, 'Sin tipo') as local_type_name,
                SUM(ch.amount_minor)::bigint as debt_eur_minor,
                COALESCE(SUM(pa.amount_bs_minor), 0)::bigint as paid_bs_minor,
                ROUND(AVG(EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on::date)))::numeric)::int as days_overdue_avg,
                COUNT(DISTINCT ch.id)::int as charges_count
            ")
            ->groupBy('l.id', 'l.code', 'l.name', 'cn.full_name', 'm.name', 'lt.name');

        // Contar total
        $total = DB::table(DB::raw("({$query->toSql()}) as subquery"))
            ->mergeBindings($query)
            ->count();

        // Ordenar
        $sortColumn = match ($sortBy) {
            'code' => 'l.code',
            'days_overdue' => 'days_overdue_avg',
            default => 'debt_eur_minor'
        };
        $query->orderBy($sortColumn, $sortDir);

        // Paginar
        $results = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Procesar resultados
        $data = $results->map(function ($row) use ($fxRate) {
            $outstandingEur = max(0, $row->debt_eur_minor - ($row->paid_bs_minor / $fxRate));
            $outstandingBs = max(0, ($row->debt_eur_minor * $fxRate) - $row->paid_bs_minor);

            return [
                'id' => (int) $row->id,
                'local_code' => (string) $row->local_code,
                'local_name' => (string) $row->local_name,
                'concessionaire_name' => (string) $row->concessionaire_name,
                'market_name' => (string) $row->market_name,
                'local_type_name' => (string) $row->local_type_name,
                'debt_eur_minor' => (int) $outstandingEur,
                'debt_bs_minor' => (int) $outstandingBs,
                'days_overdue_avg' => (int) $row->days_overdue_avg,
                'charges_count' => (int) $row->charges_count,
                'severity' => $this->calculateSeverity((int) $row->days_overdue_avg),
            ];
        });

        // Calcular resumen (sobre el conjunto filtrado completo, no solo la página)
        $aggAll = (clone $baseQuery)
            ->selectRaw('SUM(ch.amount_minor)::bigint as sum_eur_minor')
            ->selectRaw('COALESCE(SUM(pa.amount_bs_minor), 0)::bigint as sum_paid_bs_minor')
            ->first();

        $sumAllEurMinor = (int) ($aggAll->sum_eur_minor ?? 0);
        $sumAllPaidBsMinor = (int) ($aggAll->sum_paid_bs_minor ?? 0);
        $sumAllOutstandingBs = max(0, (int) ($sumAllEurMinor * $fxRate) - $sumAllPaidBsMinor);
        $sumAllOutstandingEur = (int) ($sumAllOutstandingBs / $fxRate);

        return [
            'data' => $data->values()->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'summary' => [
                'total_debt_eur_minor' => $sumAllOutstandingEur,
                'total_debt_bs_minor' => $sumAllOutstandingBs,
                'total_count' => $total,
            ],
            'fx_rate' => $fxRate,
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

        $query->select([
            'cn.id',
            'cn.full_name',
            'cn.document_number',
            DB::raw("'Sin asignar' as market_name"),
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
    public function getDistributions(): array
    {
        $cacheKey = 'debt_analysis:distributions';

        return Cache::remember($cacheKey, 300, function (): array {
            $fxRate = $this->getActiveFxRate();
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
                            WHEN EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on::date)) <= 30 THEN '0-30'
                            WHEN EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on::date)) <= 60 THEN '31-60'
                            WHEN EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on::date)) <= 90 THEN '61-90'
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
                    $outstanding = max(0, ($r->debt_eur_minor * $fxRate) - $r->paid_bs_minor);

                    return [
                        'bucket' => (string) $r->bucket,
                        'debt_eur_minor' => (int) ($outstanding / $fxRate),
                        'debt_bs_minor' => (int) $outstanding,
                        'count' => (int) $r->count,
                    ];
                });

            // Distribución por mercado
            // Aggregated allocations subquery
            $allocSub = DB::table('payment_allocations as pa')
                ->select('pa.charge_id', DB::raw('SUM(pa.amount_bs_minor)::bigint as paid_bs_minor'))
                ->whereNull('pa.deleted_at')
                ->groupBy('pa.charge_id');

            $byMarket = DB::table('charges as ch')
                ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
                ->join('locals as l', function ($j): void {
                    $j->on('l.id', '=', 'ch.debtor_id')
                        ->where('ch.debtor_type', '=', DB::raw("'LOCAL'"));
                })
                ->leftJoinSub($allocSub, 'ap', 'ap.charge_id', '=', 'ch.id')
                ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
                ->leftJoin('contracts as c', 'c.id', '=', 'ch.contract_id')
                ->leftJoin('concessionaire_contract as cc', 'cc.contract_id', '=', 'c.id')
                ->selectRaw("COALESCE(m.id, 0) as market_id, COALESCE(m.name, 'Sin asignar') as market_name, SUM(ch.amount_minor)::bigint as debt_eur_minor, COALESCE(SUM(ap.paid_bs_minor), 0)::bigint as paid_bs_minor, COUNT(DISTINCT cc.concessionaire_id)::int as count")
                ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
                ->whereDate('ch.due_on', '<', $today)
                ->whereNull('ch.deleted_at')
                ->groupBy('m.id', 'm.name')
                ->orderBy('debt_eur_minor', 'desc')
                ->get()
                ->map(function ($r) use ($fxRate) {
                    $outstanding = max(0, ($r->debt_eur_minor * $fxRate) - $r->paid_bs_minor);

                    return [
                        'market_id' => (int) $r->market_id,
                        'market_name' => (string) $r->market_name,
                        'debt_eur_minor' => (int) ($outstanding / $fxRate),
                        'debt_bs_minor' => (int) $outstanding,
                        'count' => (int) $r->count,
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
                    $outstanding = max(0, ($r->debt_eur_minor * $fxRate) - $r->paid_bs_minor);

                    return [
                        'local_type_id' => (int) $r->local_type_id,
                        'local_type_name' => (string) $r->local_type_name,
                        'debt_eur_minor' => (int) ($outstanding / $fxRate),
                        'debt_bs_minor' => (int) $outstanding,
                        'locals_count' => (int) $r->locals_count,
                    ];
                });

            return [
                'by_aging' => $byAging->all(),
                'by_market' => $byMarket->all(),
                'by_local_type' => $byLocalType->all(),
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
                    'Deuda EUR', 'Deuda Bs', 'Días Vencidos Promedio',
                    'Días Vencidos Máximo', 'Locales', 'Cargos', 'Severidad',
                ]);
            } else {
                fputcsv($handle, [
                    'ID', 'Código Local', 'Nombre Local', 'Concesionario', 'Mercado', 'Tipo Local',
                    'Deuda EUR', 'Deuda Bs', 'Días Vencidos', 'Cargos', 'Severidad',
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
     * Obtener tasa FX activa con caché
     */
    private function getActiveFxRate(): float
    {
        return Cache::remember('fx_rate_eur_active', 300, function (): float {
            $rate = DB::table('fx_rates')
                ->where('currency_code', 'EUR')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->value('rate_to_ves');

            return $rate ? (float) $rate : 1.0;
        });
    }
}
