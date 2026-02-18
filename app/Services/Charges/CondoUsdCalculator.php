<?php

declare(strict_types=1);

namespace App\Services\Charges;

use App\Contracts\Services\Charges\ChargeCalculatorInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CondoUsdCalculator implements ChargeCalculatorInterface
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function calculate(array $params): array
    {
        // Expected params: market_id (int), period (YYYY-MM-01), idempotency_key? (string)
        $marketId = (int) ($params['market_id'] ?? 0);
        $periodStr = (string) ($params['period'] ?? date('Y-m-01'));
        $idemp = isset($params['idempotency_key']) ? (string) $params['idempotency_key'] : null;

        if ($marketId <= 0) {
            return [];
        }

        $period = Carbon::parse($periodStr)->startOfMonth();
        $periodStart = $period->toDateString();
        $issuedOn = $periodStart;
        $dueOn = $period->copy()->day(6)->toDateString();

        // Map ChargeStatus 'ISSUED' id
        $statusId = (int) (DB::table('charge_statuses')->where('code', 'ISSUED')->value('id') ?? 0);
        if ($statusId <= 0) {
            return [];
        }

        // Resolve CondoPeriod
        $periodRow = DB::table('condo_periods')
            ->where('market_id', $marketId)
            ->where('period', $periodStart)
            ->whereNull('deleted_at')
            ->first(['id', 'status']);
        if (! $periodRow) {
            return [];
        }
        // Ensure period is FINAL (avoid running on DRAFT)
        if (strtoupper((string) ($periodRow->status ?? '')) !== 'FINAL') {
            return [];
        }
        $condoPeriodId = (int) $periodRow->id;

        // Sum expenses (USD minor)
        $totalMinor = (int) DB::table('condo_expenses')
            ->where('condo_period_id', $condoPeriodId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->sum('amount_usd_minor');
        if ($totalMinor <= 0) {
            return [];
        }

        // Participants: exclusions-only model
        // Include all active locals in market EXCEPT those with participant(included=false)
        $items = DB::table('locals as l')
            ->leftJoin('condo_participants as cp', function ($join) use ($condoPeriodId): void {
                $join->on('cp.local_id', '=', 'l.id')
                    ->where('cp.condo_period_id', '=', $condoPeriodId)
                    ->whereNull('cp.deleted_at')
                    ->where('cp.is_active', '=', true);
            })
            ->where('l.market_id', '=', $marketId)
            ->where('l.is_active', true)
            ->whereNull('l.deleted_at')
            ->whereNotExists(function ($q) use ($condoPeriodId): void {
                $q->from('condo_participants as cp2')
                    ->whereColumn('cp2.local_id', 'l.id')
                    ->where('cp2.condo_period_id', '=', $condoPeriodId)
                    ->whereNull('cp2.deleted_at')
                    ->where('cp2.is_active', '=', true)
                    ->where('cp2.included', '=', false);
            })
            ->select('l.id as local_id', 'l.market_id', 'l.area_m2', 'cp.area_m2_snapshot', 'cp.included')
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        // Compute total area using snapshot when present (included=true), else current area
        $areas = [];
        $totalArea = 0.0;
        foreach ($items as $row) {
            $area = ($row->included === true && $row->area_m2_snapshot !== null)
                ? (float) $row->area_m2_snapshot
                : (float) $row->area_m2;
            $areas[(int) $row->local_id] = $area;
            $totalArea += $area;
        }
        if ($totalArea <= 0.0) {
            return [];
        }

        // Unit cost per m² in USD minor
        $unitMinor = (float) $totalMinor / (float) $totalArea;

        $rows = [];
        $i = 0;
        foreach ($items as $row) {
            $localId = (int) $row->local_id;
            $area = (float) ($areas[$localId] ?? (float) $row->area_m2);
            $amountMinor = (int) round($unitMinor * $area, 0);

            $rows[] = [
                'market_id' => (int) $row->market_id,
                'local_id' => $localId,
                'contract_id' => null,
                'condo_period_id' => $condoPeriodId,

                'debtor_type' => 'LOCAL',
                'debtor_id' => $localId,
                'origin_debtor_type' => 'LOCAL',
                'origin_debtor_id' => $localId,

                'kind' => 'CONDO_USD',
                'currency' => 'USD',

                'amount_minor' => $amountMinor,
                'period' => $periodStart,
                'issued_on' => $issuedOn,
                'due_on' => $dueOn,

                'charge_status_id' => $statusId,
                'source' => 'CONDO_RUN',
                'idempotency_key' => $idemp,
                // meta (ignored by persistence, used only for summary)
                'meta_unit_minor' => $i === 0 ? (int) round($unitMinor, 0) : null,
            ];
            $i++;
        }

        return $rows;
    }
}
