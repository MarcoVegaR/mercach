<?php

declare(strict_types=1);

namespace App\Services\Charges;

use App\Contracts\Services\Charges\ChargeCalculatorInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AvailableM2Calculator implements ChargeCalculatorInterface
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
        $issuedOn = $period->toDateString();
        $dueOn = $period->copy()->day(6)->toDateString(); // vence día 6
        $periodStart = $period->toDateString();
        $periodEnd = $period->copy()->endOfMonth()->toDateString();

        // Get current tariff (EUR minor per m² per day assumed)
        $tariff = DB::table('market_tariffs')
            ->where('market_id', $marketId)
            ->where('is_current', true)
            ->orderByDesc('valid_from')
            ->first(['price_per_m2_eur_minor']);
        if (! $tariff) {
            return [];
        }
        $priceMinorPerM2PerDay = (int) $tariff->price_per_m2_eur_minor;

        // Map ChargeStatus 'ISSUED' id
        $statusId = (int) (DB::table('charge_statuses')->where('code', 'ISSUED')->value('id') ?? 0);
        if ($statusId <= 0) {
            return [];
        }

        // Locals available (NO contrato VIGENTE overlapping the month)
        $items = DB::table('locals as l')
            ->where('l.market_id', '=', $marketId)
            ->whereNull('l.deleted_at')
            ->whereNotExists(function ($sub) use ($periodStart, $periodEnd): void {
                $sub->from('contract_local as cl')
                    ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
                    ->join('contract_modalities as cm', 'cm.id', '=', 'c.contract_modality_id')
                    ->join('contract_types as ct', 'ct.id', '=', 'c.contract_type_id')
                    ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                    ->whereColumn('cl.local_id', 'l.id')
                    ->where('ct.code', '=', 'CONV')
                    ->whereIn('cm.code', ['M2', 'TFIJA'])
                    ->whereIn('cs.code', ['VIG', 'EXT', 'VENC'])
                    ->whereDate('c.start_date', '<=', $periodEnd)
                    ->where(function ($q) use ($periodStart): void {
                        $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $periodStart);
                    })
                    ->whereNull('c.deleted_at');
            })
            ->select('l.id as local_id', 'l.area_m2 as area', 'l.market_id')
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $rows = [];
        foreach ($items as $it) {
            $localId = (int) $it->local_id;
            $area = (float) $it->area;
            // Monthly conversion factor: 365/12 days per month
            $monthlyFactor = 365 / 12;
            $amountMinor = (int) round($priceMinorPerM2PerDay * $area * $monthlyFactor, 0);

            $rows[] = [
                'market_id' => $marketId,
                'local_id' => $localId,
                'contract_id' => null,
                'condo_period_id' => null,

                'debtor_type' => 'LOCAL',
                'debtor_id' => $localId,
                'origin_debtor_type' => 'LOCAL',
                'origin_debtor_id' => $localId,

                'kind' => 'RENT_EUR_M2_AVAIL',
                'currency' => 'EUR',

                'amount_minor' => $amountMinor,
                'period' => $periodStart,
                'issued_on' => $issuedOn,
                'due_on' => $dueOn,

                'charge_status_id' => $statusId,
                'source' => 'AVAIL_RUN',
                'idempotency_key' => $idemp,
            ];
        }

        return $rows;
    }
}
