<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard BFF service for KPIs and distributions.
 *
 * Fuente de verdad para "Local disponible":
 * - Regla CANÓNICA: NOT EXISTS contrato vigente para ese local.
 *   Donde "vigente" ≡ status(code) = 'VIG' AND start_date <= today AND (end_date IS NULL OR end_date >= today).
 * - Si el catálogo LocalStatus con code='DISP' es confiable, puede usarse como atajo en filtros de UI,
 *   pero los cálculos aquí usan SIEMPRE la regla canónica para evitar inconsistencias.
 */
class DashboardService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getKpis(array $filters = []): array
    {
        $cacheKey = 'dash:kpis:'.$this->filtersHash($filters);

        return Cache::remember($cacheKey, 60, function (): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Contracts vigentes
            $vigentesBase = DB::table('contracts as c')
                ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                ->where('cs.code', '=', 'VIG')
                ->where('c.start_date', '<=', $today)
                ->where(function ($q) use ($today): void {
                    $q->whereNull('c.end_date')->orWhere('c.end_date', '>=', $today);
                })
                ->whereNull('c.deleted_at');

            $contractsVigentes = (clone $vigentesBase)->count();

            // Concessionaires activos (>=1 contrato vigente o vencido)
            // Incluye VENC porque continúan generando cargos hasta TERMINADO
            $concessionairesActivos = DB::table('concessionaire_contract as cc')
                ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                ->whereIn('cs.code', ['VIG', 'VENC'])
                ->whereNull('c.deleted_at')
                ->distinct('cc.concessionaire_id')
                ->count('cc.concessionaire_id');

            // Locales disponibles: NOT EXISTS contrato vigente
            $localsDisponibles = DB::table('locals as l')
                ->whereNull('l.deleted_at')
                ->whereNotExists(function ($sub) use ($today): void {
                    $sub->from('contract_local as cl')
                        ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
                        ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                        ->whereColumn('cl.local_id', 'l.id')
                        ->where('cs.code', '=', 'VIG')
                        ->where('c.start_date', '<=', $today)
                        ->where(function ($q) use ($today): void {
                            $q->whereNull('c.end_date')->orWhere('c.end_date', '>=', $today);
                        })
                        ->whereNull('c.deleted_at');
                })
                ->count();

            return [
                'users' => ['total' => (int) User::query()->count()],
                'locals' => ['available' => (int) $localsDisponibles],
                'concessionaires' => ['active' => (int) $concessionairesActivos],
                'contracts' => ['vigentes' => (int) $contractsVigentes],
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Count entities with overdue open charges older than $days (ISSUED/PARTIAL and due_on < today - days)
     *
     * @return array{days:int, concessionaires_count:int, locals_count:int, generated_at:string}
     */
    public function getOverdueCounts(int $days = 90, bool $force = false): array
    {
        $days = max(1, min(3650, $days));

        $cacheKey = 'dash:debt:overdue_counts:'.$days;

        if ($force) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 120, function () use ($days): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Concessionaires with any open charge overdue > $days
            $concessionairesCount = DB::table('charges as ch')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->join('contracts as ct', 'ct.id', '=', 'ch.contract_id')
                ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'ct.id')
                ->join('concessionaires as c', 'c.id', '=', 'cc.concessionaire_id')
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->whereRaw('(CURRENT_DATE - ch.due_on) > ?', [$days])
                ->whereNull('ch.deleted_at')
                ->whereNull('ct.deleted_at')
                ->whereNull('c.deleted_at')
                ->distinct()
                ->count(DB::raw("CONCAT(c.document_type_id, '-', c.document_number)"));

            // Locals with any open charge overdue > $days
            $localsCount = DB::table('charges as ch')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->whereRaw('(CURRENT_DATE - ch.due_on) > ?', [$days])
                ->whereNull('ch.deleted_at')
                ->distinct('ch.local_id')
                ->count('ch.local_id');

            return [
                'days' => (int) $days,
                'concessionaires_count' => (int) $concessionairesCount,
                'locals_count' => (int) $localsCount,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Charges distribution by kind (e.g., RENT_EUR_M2, RENT_EUR_FIXED, CONDO_USD)
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getChargesDistributionByKind(array $filters = [], bool $force = false): array
    {
        $cacheKey = 'dash:dist:charges:kind:'.$this->filtersHash($filters);

        if ($force) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 180, function (): array {
            $items = DB::table('charges as ch')
                ->select('ch.kind as code', DB::raw('COUNT(ch.id)::int as value'))
                ->whereNull('ch.deleted_at')
                ->groupBy('ch.kind')
                ->orderBy('value', 'desc')
                ->get()
                ->map(function ($row) {
                    $code = (string) $row->code;
                    $labels = [
                        'RENT_EUR_M2' => 'Tasa de uso por convenio',
                        'RENT_EUR_FIXED' => 'Alquiler fijo',
                        'CONDO_USD' => 'Gastos Comunes',
                        'FINE' => 'Multa',
                        'ADJ' => 'Ajuste',
                    ];

                    return [
                        'code' => $code,
                        'label' => $labels[$code] ?? $code,
                        'value' => (int) $row->value,
                    ];
                })
                ->all();

            $total = array_sum(array_map(static fn ($r) => (int) $r['value'], $items));

            return [
                'items' => $items,
                'total' => (int) $total,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Charges distribution by status (ISSUED, PARTIAL, SETTLED, CANCELED)
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getChargesDistributionByStatus(array $filters = [], bool $force = false): array
    {
        $cacheKey = 'dash:dist:charges:status:'.$this->filtersHash($filters);

        if ($force) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 180, function (): array {
            $items = DB::table('charge_statuses as cs')
                ->leftJoin('charges as ch', function ($join): void {
                    $join->on('ch.charge_status_id', '=', 'cs.id')
                        ->whereNull('ch.deleted_at');
                })
                ->select('cs.code as code', 'cs.name as label', DB::raw('COUNT(ch.id)::int as value'))
                ->groupBy('cs.id', 'cs.code', 'cs.name')
                ->orderBy('cs.name')
                ->get()
                ->map(fn ($row) => [
                    'code' => (string) $row->code,
                    'label' => (string) $row->label,
                    'value' => (int) $row->value,
                ])
                ->all();

            $total = array_sum(array_map(static fn ($r) => (int) $r['value'], $items));

            return [
                'items' => $items,
                'total' => (int) $total,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Open charges (ISSUED/PARTIAL) grouped by period month
     *
     * @param  int  $months  How many months back from now to include
     * @return array<string, mixed>
     */
    public function getOpenChargesByMonth(int $months = 12): array
    {
        $months = max(1, min(60, $months));
        $cacheKey = 'dash:charges:open-by-month:'.$months;

        return Cache::remember($cacheKey, 180, function () use ($months): array {
            $start = Carbon::now()->subMonths($months - 1)->startOfMonth()->toDateString();

            $rows = DB::table('charges as ch')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->whereNull('ch.deleted_at')
                ->where('ch.period', '>=', $start)
                ->selectRaw("DATE_TRUNC('month', ch.period) as month_start")
                ->selectRaw('COUNT(ch.id)::int as count')
                ->selectRaw('SUM(ch.amount_minor)::bigint as amount_minor')
                ->groupByRaw("DATE_TRUNC('month', ch.period)")
                ->orderByRaw("DATE_TRUNC('month', ch.period)")
                ->get();

            $items = [];
            foreach ($rows as $r) {
                $dt = Carbon::parse((string) $r->month_start);
                $items[] = [
                    'month' => $dt->format('Y-m'),
                    'month_label' => $dt->isoFormat('MMM YYYY'),
                    'count' => (int) ($r->count ?? 0),
                    'amount_minor' => (int) ($r->amount_minor ?? 0),
                ];
            }

            return [
                'items' => $items,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Distribution of ALL locals by location (local_locations)
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getLocalsDistributionByLocation(array $filters = []): array
    {
        $items = DB::table('local_locations as ll')
            ->leftJoin('locals as l', function ($join): void {
                $join->on('l.local_location_id', '=', 'll.id')
                    ->whereNull('l.deleted_at');
            })
            ->select('ll.id as id', 'll.name as label', DB::raw('COUNT(l.id)::int as value'))
            ->groupBy('ll.id', 'll.name')
            ->orderBy('ll.name')
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->label, 'id' => (int) $row->id, 'value' => (int) $row->value])
            ->all();

        $total = (int) DB::table('locals')->whereNull('deleted_at')->count('id');

        return [
            'items' => $items,
            'total' => $total,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Concessionaires by type (Persona Natural vs Persona Jurídica)
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getConcessionairesByType(array $filters = []): array
    {
        $items = DB::table('concessionaire_types as ct')
            ->leftJoin('concessionaires as cn', function ($join): void {
                $join->on('cn.concessionaire_type_id', '=', 'ct.id')
                    ->whereNull('cn.deleted_at');
            })
            ->select('ct.id as id', 'ct.code as code', 'ct.name as label', DB::raw('COUNT(cn.id)::int as value'))
            ->groupBy('ct.id', 'ct.code', 'ct.name')
            ->orderBy('ct.name')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'label' => (string) $row->label,
                'value' => (int) $row->value,
            ])
            ->all();

        $total = (int) DB::table('concessionaires')->whereNull('deleted_at')->count('id');

        return [
            'items' => $items,
            'total' => $total,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Natural persons by document type (V vs E)
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getNaturalConcessionairesByDocument(array $filters = []): array
    {
        $items = DB::table('document_types as dt')
            ->leftJoin('concessionaires as cn', function ($join): void {
                $join->on('cn.document_type_id', '=', 'dt.id')
                    ->whereNull('cn.deleted_at');
            })
            ->leftJoin('concessionaire_types as ct', 'ct.id', '=', 'cn.concessionaire_type_id')
            ->where('ct.code', '=', 'PNAT')
            ->select('dt.code as code', 'dt.name as label', DB::raw('COUNT(cn.id)::int as value'))
            ->groupBy('dt.code', 'dt.name')
            ->orderBy('dt.code')
            ->get()
            ->map(fn ($row) => [
                'code' => (string) $row->code,
                'label' => (string) $row->label,
                'value' => (int) $row->value,
            ])
            ->all();

        $total = (int) DB::table('concessionaires as cn')
            ->join('concessionaire_types as ct', 'ct.id', '=', 'cn.concessionaire_type_id')
            ->where('ct.code', '=', 'PNAT')
            ->whereNull('cn.deleted_at')
            ->count('cn.id');

        return [
            'items' => $items,
            'total' => $total,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Distribution of contracts by type codes (e.g., CONTR, CONV)
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getContractsDistributionByType(array $filters = []): array
    {
        $cacheKey = 'dash:dist:contracts:type:'.$this->filtersHash($filters);

        return Cache::remember($cacheKey, 180, function (): array {
            $items = DB::table('contract_types as ct')
                ->leftJoin('contracts as c', function ($join): void {
                    $join->on('c.contract_type_id', '=', 'ct.id')
                        ->whereNull('c.deleted_at');
                })
                ->select(
                    'ct.id as id',
                    'ct.code as code',
                    'ct.name as label',
                    DB::raw('COUNT(c.id)::int as value')
                )
                ->where('ct.is_active', true)
                ->groupBy('ct.id', 'ct.code', 'ct.name')
                ->orderBy('ct.name')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'code' => (string) $row->code,
                    'label' => (string) $row->label,
                    'value' => (int) $row->value,
                ])
                ->all();

            $total = array_sum(array_map(static fn ($r) => (int) $r['value'], $items));

            return [
                'items' => $items,
                'total' => (int) $total,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Distribution of contracts by status codes (VIG, EXT, TERM, VENC)
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getContractsDistributionByStatus(array $filters = []): array
    {
        $cacheKey = 'dash:dist:contracts:status:'.$this->filtersHash($filters);

        return Cache::remember($cacheKey, 180, function (): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Ensure all target statuses are present even with zero count
            $items = DB::table('contract_statuses as cs')
                ->leftJoin('contracts as c', function ($join) use ($today): void {
                    $join->on('c.contract_status_id', '=', 'cs.id')
                        ->whereNull('c.deleted_at')
                        ->where(function ($q) use ($today): void {
                            $q->where('cs.code', '!=', 'VIG')
                                ->orWhere(function ($w) use ($today): void {
                                    $w->where('cs.code', '=', 'VIG')
                                        ->where('c.start_date', '<=', $today)
                                        ->where(function ($qq) use ($today): void {
                                            $qq->whereNull('c.end_date')->orWhere('c.end_date', '>=', $today);
                                        });
                                });
                        });
                })
                ->select(
                    'cs.id as id',
                    'cs.code as code',
                    'cs.name as label',
                    DB::raw('COUNT(c.id)::int as value')
                )
                ->whereIn('cs.code', ['VIG', 'EXT', 'TERM', 'VENC'])
                ->groupBy('cs.id', 'cs.code', 'cs.name')
                ->orderByRaw("CASE cs.code WHEN 'VIG' THEN 1 WHEN 'EXT' THEN 2 WHEN 'TERM' THEN 3 WHEN 'VENC' THEN 4 ELSE 5 END")
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'code' => (string) $row->code,
                    'label' => (string) $row->label,
                    'value' => (int) $row->value,
                ])
                ->all();

            $total = array_sum(array_map(static fn ($r) => (int) $r['value'], $items));

            return [
                'items' => $items,
                'total' => (int) $total,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get breakdown of VIG contracts (signed vs unsigned)
     *
     * @return array<string, mixed>
     */
    public function getVigentesBreakdown(): array
    {
        $cacheKey = 'dash:contracts:vig:breakdown';

        return Cache::remember($cacheKey, 180, function (): array {
            $vigStatusId = DB::table('contract_statuses')->where('code', 'VIG')->value('id');

            if (! $vigStatusId) {
                return [
                    'total' => 0,
                    'signed' => 0,
                    'unsigned' => 0,
                    'generated_at' => Carbon::now()->toIso8601String(),
                ];
            }

            $today = Carbon::now()->startOfDay()->toDateString();

            $breakdown = DB::table('contracts as c')
                ->where('c.contract_status_id', $vigStatusId)
                ->where('c.start_date', '<=', $today)
                ->where(function ($q) use ($today): void {
                    $q->whereNull('c.end_date')->orWhere('c.end_date', '>=', $today);
                })
                ->whereNull('c.deleted_at')
                ->selectRaw('COUNT(*) FILTER (WHERE c.signed_at IS NOT NULL) as signed')
                ->selectRaw('COUNT(*) FILTER (WHERE c.signed_at IS NULL) as unsigned')
                ->selectRaw('COUNT(*) as total')
                ->first();

            return [
                'total' => (int) ($breakdown->total ?? 0),
                'signed' => (int) ($breakdown->signed ?? 0),
                'unsigned' => (int) ($breakdown->unsigned ?? 0),
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getLocalsAvailableDistribution(string $by = 'local_type_id', array $filters = []): array
    {
        $by = $by === 'local_type_id' ? 'local_type_id' : 'local_type_id'; // v1 soporta solo local_type_id
        $cacheKey = 'dash:dist:avail:'.$by.':'.$this->filtersHash($filters);

        return Cache::remember($cacheKey, 180, function () use ($by): array {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Aggregate available locals per type
            $availablePerType = DB::table('locals as l')
                ->select('l.local_type_id', DB::raw('COUNT(*)::int as cnt'))
                ->whereNull('l.deleted_at')
                ->whereNotExists(function ($sub) use ($today): void {
                    $sub->from('contract_local as cl')
                        ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
                        ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                        ->whereColumn('cl.local_id', 'l.id')
                        ->where('cs.code', '=', 'VIG')
                        ->where('c.start_date', '<=', $today)
                        ->where(function ($q) use ($today): void {
                            $q->whereNull('c.end_date')->orWhere('c.end_date', '>=', $today);
                        })
                        ->whereNull('c.deleted_at');
                })
                ->groupBy('l.local_type_id');

            // Ensure ALL local_types are present, even when count = 0
            $items = DB::table('local_types as lt')
                ->leftJoinSub($availablePerType, 'x', 'x.local_type_id', '=', 'lt.id')
                ->select('lt.id as id', 'lt.name as label', DB::raw('COALESCE(x.cnt, 0)::int as value'))
                ->orderBy('lt.name')
                ->get()
                ->map(fn ($row) => ['label' => (string) $row->label, 'id' => (int) $row->id, 'value' => (int) $row->value])
                ->all();

            $total = array_sum(array_map(static fn ($r) => (int) $r['value'], $items));

            // Provide helper to navigate by status code 'DISP' in UI
            $statusDispId = (int) (DB::table('local_statuses')->where('code', 'DISP')->value('id') ?? 0);

            return [
                'by' => $by,
                'items' => $items,
                'total' => (int) $total,
                'status_disp_id' => $statusDispId,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getLocalsDistributionByType(string $by = 'local_type_id', array $filters = []): array
    {
        $by = $by === 'local_type_id' ? 'local_type_id' : 'local_type_id';
        $cacheKey = 'dash:dist:all:'.$by.':'.$this->filtersHash($filters);

        return Cache::remember($cacheKey, 300, function () use ($by): array {
            // Aggregate ALL locals per type (exclude soft-deleted), but keep types with zero
            $items = DB::table('local_types as lt')
                ->leftJoin('locals as l', function ($join): void {
                    $join->on('l.local_type_id', '=', 'lt.id')
                        ->whereNull('l.deleted_at');
                })
                ->select('lt.id as id', 'lt.name as label', DB::raw('COUNT(l.id)::int as value'))
                ->groupBy('lt.id', 'lt.name')
                ->orderBy('lt.name')
                ->get()
                ->map(fn ($row) => ['label' => (string) $row->label, 'id' => (int) $row->id, 'value' => (int) $row->value])
                ->all();

            $total = (int) DB::table('locals')->whereNull('deleted_at')->count('id');

            return [
                'by' => $by,
                'items' => $items,
                'total' => $total,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get debt and risk metrics
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDebtMetrics(array $filters = [], bool $force = false): array
    {
        $cacheKey = 'dash:debt:metrics:'.$this->filtersHash($filters);

        if ($force) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 120, function (): array {
            $today = Carbon::now()->startOfDay();

            /** @var \App\Contracts\Services\FxRateServiceInterface $fx */
            $fx = app(\App\Contracts\Services\FxRateServiceInterface::class);
            $eurRateToday = $fx->resolveAt('EUR', $today)?->getAttribute('rate_to_ves');
            $usdRateToday = $fx->resolveAt('USD', $today)?->getAttribute('rate_to_ves');
            $eurRateToday = is_numeric($eurRateToday) ? (float) $eurRateToday : 1.0;
            $usdRateToday = is_numeric($usdRateToday) ? (float) $usdRateToday : 1.0;

            // Collect overdue charges by currency
            $base = DB::table('charges as ch')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->where('ch.due_on', '<', $today->toDateString())
                ->whereNull('ch.deleted_at');

            // Collect all open charges (ISSUED/PARTIAL, regardless of due_on) by currency for total debt
            $baseAll = DB::table('charges as ch')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->whereNull('ch.deleted_at');

            $eurChargeIds = (clone $base)->where('ch.currency', 'EUR')->pluck('ch.id')->all();
            $usdChargeIds = (clone $base)->where('ch.currency', 'USD')->pluck('ch.id')->all();

            $sumEurAmountMinor = $eurChargeIds ? (int) DB::table('charges')->whereIn('id', $eurChargeIds)->sum('amount_minor') : 0;
            $sumUsdAmountMinor = $usdChargeIds ? (int) DB::table('charges')->whereIn('id', $usdChargeIds)->sum('amount_minor') : 0;

            // Fetch allocations and credits with payment dates
            $eurAlloc = $eurChargeIds
                ? DB::table('payment_allocations as pa')
                    ->leftJoin('payments as p', 'p.id', '=', 'pa.payment_id')
                    ->whereIn('pa.charge_id', $eurChargeIds)
                    ->whereNull('pa.deleted_at')
                    ->get(['pa.amount_bs_minor', 'p.paid_on'])
                : collect();
            $usdAlloc = $usdChargeIds
                ? DB::table('payment_allocations as pa')
                    ->leftJoin('payments as p', 'p.id', '=', 'pa.payment_id')
                    ->whereIn('pa.charge_id', $usdChargeIds)
                    ->whereNull('pa.deleted_at')
                    ->get(['pa.amount_bs_minor', 'p.paid_on'])
                : collect();

            $eurCredits = $eurChargeIds
                ? DB::table('credit_applications as ca')
                    ->leftJoin('payments as p', 'p.id', '=', 'ca.payment_id')
                    ->leftJoin('customer_credits as cc', 'cc.id', '=', 'ca.customer_credit_id')
                    ->whereIn('ca.charge_id', $eurChargeIds)
                    ->get(['ca.amount_minor', 'p.paid_on', 'cc.currency'])
                : collect();
            $usdCredits = $usdChargeIds
                ? DB::table('credit_applications as ca')
                    ->leftJoin('payments as p', 'p.id', '=', 'ca.payment_id')
                    ->leftJoin('customer_credits as cc', 'cc.id', '=', 'ca.customer_credit_id')
                    ->whereIn('ca.charge_id', $usdChargeIds)
                    ->get(['ca.amount_minor', 'p.paid_on', 'cc.currency'])
                : collect();

            // Convert Bs allocations/credits to the charge currency using the rate at each payment date
            $sumAppliedEurMinor = 0;
            foreach ($eurAlloc as $row) {
                $amtBs = (int) ($row->amount_bs_minor ?? 0);
                $pd = (string) ($row->paid_on ?? '');
                $at = $pd !== '' ? new \DateTimeImmutable($pd) : $today;
                $rate = $fx->resolveAt('EUR', $at)?->getAttribute('rate_to_ves');
                $ves = is_numeric($rate) ? (float) $rate : 0.0;
                if ($ves > 0) {
                    $sumAppliedEurMinor += (int) round(($amtBs / 100.0) / $ves * 100);
                }
            }
            foreach ($eurCredits as $row) {
                $amt = (int) ($row->amount_minor ?? 0);
                $currency = strtoupper((string) ($row->currency ?? 'VES'));
                $pd = (string) ($row->paid_on ?? '');
                $at = $pd !== '' ? new \DateTimeImmutable($pd) : $today;
                if ($currency === 'VES') {
                    $rate = $fx->resolveAt('EUR', $at)?->getAttribute('rate_to_ves');
                    $ves = is_numeric($rate) ? (float) $rate : 0.0;
                    if ($ves > 0) {
                        $sumAppliedEurMinor += (int) round(($amt / 100.0) / $ves * 100);
                    }
                } elseif ($currency === 'EUR') {
                    $sumAppliedEurMinor += $amt;
                }
            }

            $sumAppliedUsdMinor = 0;
            foreach ($usdAlloc as $row) {
                $amtBs = (int) ($row->amount_bs_minor ?? 0);
                $pd = (string) ($row->paid_on ?? '');
                $at = $pd !== '' ? new \DateTimeImmutable($pd) : $today;
                $rate = $fx->resolveAt('USD', $at)?->getAttribute('rate_to_ves');
                $ves = is_numeric($rate) ? (float) $rate : 0.0;
                if ($ves > 0) {
                    $sumAppliedUsdMinor += (int) round(($amtBs / 100.0) / $ves * 100);
                }
            }
            foreach ($usdCredits as $row) {
                $amt = (int) ($row->amount_minor ?? 0);
                $currency = strtoupper((string) ($row->currency ?? 'VES'));
                $pd = (string) ($row->paid_on ?? '');
                $at = $pd !== '' ? new \DateTimeImmutable($pd) : $today;
                if ($currency === 'VES') {
                    $rate = $fx->resolveAt('USD', $at)?->getAttribute('rate_to_ves');
                    $ves = is_numeric($rate) ? (float) $rate : 0.0;
                    if ($ves > 0) {
                        $sumAppliedUsdMinor += (int) round(($amt / 100.0) / $ves * 100);
                    }
                } elseif ($currency === 'USD') {
                    $sumAppliedUsdMinor += $amt;
                }
            }

            $totalOverdueEurMinor = max(0, (int) $sumEurAmountMinor - (int) $sumAppliedEurMinor);
            $totalOverdueUsdMinor = max(0, (int) $sumUsdAmountMinor - (int) $sumAppliedUsdMinor);
            $totalOverdueBsMinor = (int) round(($totalOverdueEurMinor / 100.0) * $eurRateToday * 100 + ($totalOverdueUsdMinor / 100.0) * $usdRateToday * 100);

            // Total debt (all open charges, not only overdue) by currency (nominal amounts)
            $eurAllChargeIds = (clone $baseAll)->where('ch.currency', 'EUR')->pluck('ch.id')->all();
            $usdAllChargeIds = (clone $baseAll)->where('ch.currency', 'USD')->pluck('ch.id')->all();

            $sumAllEurAmountMinor = $eurAllChargeIds
                ? (int) DB::table('charges')->whereIn('id', $eurAllChargeIds)->sum('amount_minor')
                : 0;
            $sumAllUsdAmountMinor = $usdAllChargeIds
                ? (int) DB::table('charges')->whereIn('id', $usdAllChargeIds)->sum('amount_minor')
                : 0;

            $totalDebtEurMinor = (int) $sumAllEurAmountMinor;
            $totalDebtUsdMinor = (int) $sumAllUsdAmountMinor;
            $totalDebtBsMinor = (int) round(
                ($totalDebtEurMinor / 100.0) * $eurRateToday * 100
                + ($totalDebtUsdMinor / 100.0) * $usdRateToday * 100
            );

            // Count of delinquent concessionaires (unique by document)
            $delinquentCount = DB::table('charges as ch')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->join('contracts as ct', 'ct.id', '=', 'ch.contract_id')
                ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'ct.id')
                ->join('concessionaires as c', 'c.id', '=', 'cc.concessionaire_id')
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->where('ch.due_on', '<', $today)
                ->whereNull('ch.deleted_at')
                ->whereNull('ct.deleted_at')
                ->whereNull('c.deleted_at')
                ->distinct()
                ->count(DB::raw('CONCAT(c.document_type_id, \'-\', c.document_number)'));

            // Average days overdue
            $avgDaysOverdue = DB::table('charges as ch')
                ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
                ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
                ->where('ch.due_on', '<', $today)
                ->whereNull('ch.deleted_at')
                ->selectRaw('AVG(CURRENT_DATE - ch.due_on) as avg_days')
                ->value('avg_days');

            // Count of solvent concessionaires (active concessionaires WITHOUT overdue debt)
            $activeConcessionaires = DB::table('concessionaire_contract as cc')
                ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                ->whereIn('cs.code', ['VIG', 'VENC'])
                ->whereNull('c.deleted_at')
                ->distinct('cc.concessionaire_id')
                ->count('cc.concessionaire_id');

            $solventCount = $activeConcessionaires - $delinquentCount;

            return [
                // Backward compatibility
                'total_overdue_eur_minor' => (int) $totalOverdueEurMinor,
                'total_overdue_bs_minor' => (int) $totalOverdueBsMinor,
                // Total debt (all open charges, not only overdue)
                'total_debt_eur_minor' => (int) $totalDebtEurMinor,
                'total_debt_bs_minor' => (int) $totalDebtBsMinor,
                'fx_rate_ves_per_eur' => (float) $eurRateToday,
                'fx_rate_date' => DB::table('fx_rates')->where('currency_code', 'EUR')->where('is_active', true)->whereNull('deleted_at')->value('rate_date'),
                // New fields
                'total_overdue_usd_minor' => (int) $totalOverdueUsdMinor,
                'fx_rate_ves_per_usd' => (float) $usdRateToday,
                // Shared metrics
                'delinquent_count' => (int) $delinquentCount,
                'average_days_overdue' => round((float) ($avgDaysOverdue ?? 0), 1),
                'solvent_count' => max(0, (int) $solventCount),
                'morosidad_rate' => $activeConcessionaires > 0
                    ? round(($delinquentCount / $activeConcessionaires) * 100, 1)
                    : 0.0,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get payment statistics
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPaymentMetrics(array $filters = []): array
    {
        $cacheKey = 'dash:payment:metrics:'.$this->filtersHash($filters);

        return Cache::remember($cacheKey, 120, function (): array {
            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
            $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

            // Check if current month has payments, if not use last month with data
            $currentMonthCount = DB::table('payments')
                ->whereBetween('paid_on', [$startOfMonth, $endOfMonth])
                ->whereNull('deleted_at')
                ->count();

            if ($currentMonthCount === 0) {
                // Get last month with payments
                $lastPaymentDate = DB::table('payments')
                    ->whereNull('deleted_at')
                    ->max('paid_on');

                if ($lastPaymentDate) {
                    $lastDate = Carbon::parse($lastPaymentDate);
                    $startOfMonth = $lastDate->startOfMonth()->toDateString();
                    $endOfMonth = $lastDate->endOfMonth()->toDateString();
                }
            }

            // Current month payments
            $monthPayments = DB::table('payments')
                ->whereBetween('paid_on', [$startOfMonth, $endOfMonth])
                ->whereNull('deleted_at');

            $totalPaymentsMonth = (clone $monthPayments)->count();
            $totalAmountBsMinor = (clone $monthPayments)->sum('amount_bs_minor');
            $displayMonth = Carbon::parse($startOfMonth)->format('F Y');

            // Payments by method (current month)
            $byMethod = DB::table('payments as p')
                ->join('payment_types as pt', 'pt.id', '=', 'p.payment_type_id')
                ->whereBetween('p.paid_on', [$startOfMonth, $endOfMonth])
                ->whereNull('p.deleted_at')
                ->select('pt.code', 'pt.name', DB::raw('COUNT(*)::int as count'))
                ->groupBy('pt.id', 'pt.code', 'pt.name')
                ->orderBy('count', 'desc')
                ->get()
                ->map(fn ($row) => [
                    'code' => (string) $row->code,
                    'name' => (string) $row->name,
                    'count' => (int) $row->count,
                ])
                ->all();

            // Count payments with allocations applied (have at least 1 allocation)
            $paymentsWithAllocations = DB::table('payments as p')
                ->join('payment_allocations as pa', 'pa.payment_id', '=', 'p.id')
                ->whereBetween('p.paid_on', [$startOfMonth, $endOfMonth])
                ->whereNull('p.deleted_at')
                ->whereNull('pa.deleted_at')
                ->distinct('p.id')
                ->count('p.id');

            // Total payment allocations made this month
            $totalAllocations = DB::table('payment_allocations as pa')
                ->join('payments as p', 'p.id', '=', 'pa.payment_id')
                ->whereBetween('p.paid_on', [$startOfMonth, $endOfMonth])
                ->whereNull('pa.deleted_at')
                ->whereNull('p.deleted_at')
                ->count();

            $applicationRate = $totalPaymentsMonth > 0
                ? round(($paymentsWithAllocations / $totalPaymentsMonth) * 100, 1)
                : 0.0;

            return [
                'total_payments_month' => (int) $totalPaymentsMonth,
                'total_amount_bs_minor' => (int) $totalAmountBsMinor,
                'pending_allocations' => max(0, $totalPaymentsMonth - $paymentsWithAllocations),
                'application_rate' => (float) $applicationRate,
                'by_method' => $byMethod,
                'portal_count' => 0, // Not tracked in current schema
                'admin_count' => (int) $totalPaymentsMonth, // All payments for now
                'display_month' => $displayMonth,
                'is_current_month' => Carbon::parse($startOfMonth)->isSameMonth(Carbon::now()),
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Projected monthly revenue (EUR minor) for a month, considering occupied locals with VIG contracts
     * Includes: RENT_EUR_M2 (CONV/M2 using market tariff) and RENT_EUR_FIXED (CONTR/TFIJA)
     * Returns total and breakdown by local type
     *
     * @return array{
     *   period_start: string,
     *   period_label: string,
     *   total_eur_minor: int,
     *   by_local_type: array<int, array{local_type_id:int, local_type_name:string, amount_eur_minor:int, locals_count:int}>,
     *   generated_at: string
     * }
     */
    public function getRevenueProjection(?string $period = null): array
    {
        $month = $period ? Carbon::parse($period)->startOfMonth() : Carbon::now()->startOfMonth();
        $monthStart = $month->toDateString();
        $monthEnd = $month->copy()->endOfMonth()->toDateString();

        $cacheKey = 'dash:revenue:projection:v2:'.$monthStart;

        return Cache::remember($cacheKey, 180, function () use ($monthStart, $monthEnd): array {
            // M2 projection by local type (using current market tariff per market)
            $m2Rows = DB::select(<<<'SQL'
WITH mt AS (
  SELECT DISTINCT ON (market_id) market_id, price_per_m2_eur_minor
  FROM market_tariffs
  WHERE is_current = true AND deleted_at IS NULL
  ORDER BY market_id, valid_from DESC
)
SELECT
  COALESCE(lt.id, 0) AS local_type_id,
  COALESCE(lt.name, 'Sin tipo') AS local_type_name,
  SUM(ROUND(mt.price_per_m2_eur_minor * l.area_m2 * (365.0/12.0)))::bigint AS amount_eur_minor,
  COUNT(DISTINCT l.id)::int AS locals_count
FROM contracts c
JOIN contract_statuses cs ON cs.id = c.contract_status_id
JOIN contract_modalities cm ON cm.id = c.contract_modality_id
JOIN contract_types ct ON ct.id = c.contract_type_id
JOIN contract_local cl ON cl.contract_id = c.id
JOIN locals l ON l.id = cl.local_id
LEFT JOIN local_types lt ON lt.id = l.local_type_id
JOIN mt ON mt.market_id = l.market_id
WHERE cs.code IN ('VIG','EXT','VENC')
  AND cm.code = 'M2'
  AND ct.code = 'CONV'
  AND c.start_date <= :monthEnd
  AND c.deleted_at IS NULL
  AND l.deleted_at IS NULL
GROUP BY lt.id, lt.name
SQL
                , ['monthEnd' => $monthEnd]);

            // FIXED projection by local type (split monthly price equally among contract locals)
            $fixedRows = DB::select(<<<'SQL'
SELECT
  COALESCE(lt.id, 0) AS local_type_id,
  COALESCE(lt.name, 'Sin tipo') AS local_type_name,
  SUM(
    ROUND(
      (c.monthly_price_eur * 100.0)
      / NULLIF(
          (
            SELECT COUNT(*)
            FROM contract_local cl2
            JOIN locals l2 ON l2.id = cl2.local_id
            WHERE cl2.contract_id = c.id
              AND l2.deleted_at IS NULL
          ), 0
        )
    )
  )::bigint AS amount_eur_minor,
  COUNT(DISTINCT l.id)::int AS locals_count
FROM contracts c
JOIN contract_statuses cs ON cs.id = c.contract_status_id
JOIN contract_modalities cm ON cm.id = c.contract_modality_id
JOIN contract_types ct ON ct.id = c.contract_type_id
JOIN contract_local cl ON cl.contract_id = c.id
JOIN locals l ON l.id = cl.local_id
LEFT JOIN local_types lt ON lt.id = l.local_type_id
WHERE cs.code IN ('VIG','EXT','VENC')
  AND cm.code = 'TFIJA'
  AND ct.code = 'CONTR'
  AND c.monthly_price_eur IS NOT NULL
  AND c.monthly_price_eur > 0
  AND c.start_date <= :monthEnd
  AND c.deleted_at IS NULL
  AND l.deleted_at IS NULL
GROUP BY lt.id, lt.name
SQL, ['monthEnd' => $monthEnd]);

            // Merge by local_type_id
            $by = [];
            foreach ($m2Rows as $r) {
                $id = (int) $r->local_type_id;
                if (! isset($by[$id])) {
                    $by[$id] = [
                        'local_type_id' => $id,
                        'local_type_name' => (string) $r->local_type_name,
                        'amount_eur_minor' => 0,
                        'locals_count' => 0,
                    ];
                }
                $by[$id]['amount_eur_minor'] += (int) $r->amount_eur_minor;
                $by[$id]['locals_count'] += (int) $r->locals_count;
            }
            foreach ($fixedRows as $r) {
                $id = (int) $r->local_type_id;
                if (! isset($by[$id])) {
                    $by[$id] = [
                        'local_type_id' => $id,
                        'local_type_name' => (string) $r->local_type_name,
                        'amount_eur_minor' => 0,
                        'locals_count' => 0,
                    ];
                }
                $by[$id]['amount_eur_minor'] += (int) $r->amount_eur_minor;
                $by[$id]['locals_count'] += (int) $r->locals_count;
            }

            $byLocalType = array_values($by);
            usort($byLocalType, fn ($a, $b) => $b['amount_eur_minor'] <=> $a['amount_eur_minor']);

            $total = array_reduce($byLocalType, fn ($acc, $row) => $acc + (int) $row['amount_eur_minor'], 0);

            return [
                'period_start' => $monthStart,
                'period_label' => Carbon::parse($monthStart)->isoFormat('MMM YYYY'),
                'total_eur_minor' => (int) $total,
                'by_local_type' => $byLocalType,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * Top locals by projected monthly revenue (EUR minor) for a month
     * Combines: M2-based (CONV/M2 using current market tariff) + Fixed (CONTR/TFIJA shared among contract locals)
     *
     * @return array{
     *   period_start: string,
     *   period_label: string,
     *   items: array<int, array{
     *     local_id:int,
     *     code:string,
     *     name:string,
     *     total_eur_minor:int,
     *     m2_eur_minor:int,
     *     fixed_eur_minor:int
     *   }>,
     *   generated_at: string
     * }
     */
    public function getTopRevenueLocals(?string $period = null, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));

        $month = $period ? Carbon::parse($period)->startOfMonth() : Carbon::now()->startOfMonth();
        $monthStart = $month->toDateString();
        $monthEnd = $month->copy()->endOfMonth()->toDateString();

        $cacheKey = 'dash:revenue:top-locals:v2:'.$monthStart.':'.$limit;

        return Cache::remember($cacheKey, 180, function () use ($monthStart, $monthEnd, $limit): array {
            $sql = <<<'SQL'
WITH params AS (
  SELECT :monthStart::date AS month_start, :monthEnd::date AS month_end
), mt AS (
  SELECT DISTINCT ON (market_id) market_id, price_per_m2_eur_minor
  FROM market_tariffs
  WHERE is_current = true AND deleted_at IS NULL
  ORDER BY market_id, valid_from DESC
), m2 AS (
  SELECT
    l.id AS local_id,
    l.code AS code,
    l.name AS name,
    SUM(ROUND(mt.price_per_m2_eur_minor * l.area_m2 * (365.0/12.0)))::bigint AS m2_eur_minor
  FROM contracts c
  JOIN contract_statuses cs ON cs.id = c.contract_status_id
  JOIN contract_modalities cm ON cm.id = c.contract_modality_id
  JOIN contract_types ct ON ct.id = c.contract_type_id
  JOIN contract_local cl ON cl.contract_id = c.id
  JOIN locals l ON l.id = cl.local_id
  JOIN mt ON mt.market_id = l.market_id
  JOIN params p ON TRUE
  WHERE cs.code IN ('VIG','EXT','VENC')
    AND cm.code = 'M2'
    AND ct.code = 'CONV'
    AND c.start_date <= p.month_end
    AND c.deleted_at IS NULL
    AND l.deleted_at IS NULL
  GROUP BY l.id, l.code, l.name
), fx AS (
  SELECT
    l.id AS local_id,
    l.code AS code,
    l.name AS name,
    SUM(
      ROUND(
        (c.monthly_price_eur * 100.0)
        / NULLIF(
            (
              SELECT COUNT(*)
              FROM contract_local cl2
              JOIN locals l2 ON l2.id = cl2.local_id
              WHERE cl2.contract_id = c.id
                AND l2.deleted_at IS NULL
            ), 0
          )
      )
    )::bigint AS fixed_eur_minor
  FROM contracts c
  JOIN contract_statuses cs ON cs.id = c.contract_status_id
  JOIN contract_modalities cm ON cm.id = c.contract_modality_id
  JOIN contract_types ct ON ct.id = c.contract_type_id
  JOIN contract_local cl ON cl.contract_id = c.id
  JOIN locals l ON l.id = cl.local_id
  JOIN params p ON TRUE
  WHERE cs.code IN ('VIG','EXT','VENC')
    AND cm.code = 'TFIJA'
    AND ct.code = 'CONTR'
    AND c.monthly_price_eur IS NOT NULL
    AND c.monthly_price_eur > 0
    AND c.start_date <= p.month_end
    AND c.deleted_at IS NULL
    AND l.deleted_at IS NULL
  GROUP BY l.id, l.code, l.name
)
SELECT
  COALESCE(m2.local_id, fx.local_id) AS local_id,
  COALESCE(m2.code, fx.code) AS code,
  COALESCE(m2.name, fx.name) AS name,
  COALESCE(m2.m2_eur_minor, 0)::bigint AS m2_eur_minor,
  COALESCE(fx.fixed_eur_minor, 0)::bigint AS fixed_eur_minor,
  (COALESCE(m2.m2_eur_minor, 0) + COALESCE(fx.fixed_eur_minor, 0))::bigint AS total_eur_minor
FROM m2
FULL OUTER JOIN fx ON fx.local_id = m2.local_id
ORDER BY total_eur_minor DESC, COALESCE(m2.local_id, fx.local_id) ASC
LIMIT %LIMIT%
SQL;

            // Safe inject LIMIT after sanitization to avoid binding issues on LIMIT
            $sql = str_replace('%LIMIT%', (string) $limit, $sql);

            $rows = DB::select($sql, ['monthStart' => $monthStart, 'monthEnd' => $monthEnd]);

            $items = [];
            foreach ($rows as $r) {
                $items[] = [
                    'local_id' => (int) $r->local_id,
                    'code' => (string) $r->code,
                    'name' => (string) $r->name,
                    'total_eur_minor' => (int) $r->total_eur_minor,
                    'm2_eur_minor' => (int) $r->m2_eur_minor,
                    'fixed_eur_minor' => (int) $r->fixed_eur_minor,
                ];
            }

            return [
                'period_start' => $monthStart,
                'period_label' => Carbon::parse($monthStart)->isoFormat('MMM YYYY'),
                'items' => $items,
                'generated_at' => Carbon::now()->toIso8601String(),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filtersHash(array $filters): string
    {
        if ($filters === []) {
            return '0';
        }

        return md5((string) json_encode($filters));
    }
}
