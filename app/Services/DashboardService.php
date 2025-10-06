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

            // Concessionaires activos (>=1 contrato vigente)
            $concessionairesActivos = DB::table('concessionaire_contract as cc')
                ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                ->where('cs.code', '=', 'VIG')
                ->where('c.start_date', '<=', $today)
                ->where(function ($q) use ($today): void {
                    $q->whereNull('c.end_date')->orWhere('c.end_date', '>=', $today);
                })
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
            // Ensure all target statuses are present even with zero count
            $items = DB::table('contract_statuses as cs')
                ->leftJoin('contracts as c', function ($join): void {
                    $join->on('c.contract_status_id', '=', 'cs.id')
                        ->whereNull('c.deleted_at');
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
