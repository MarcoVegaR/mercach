<?php

declare(strict_types=1);

namespace App\Services\Charges;

use App\Contracts\Services\Charges\ChargeCalculatorInterface;
use App\Enums\ChargeStatusCode;
use App\Enums\ContractStatusCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RentM2Calculator implements ChargeCalculatorInterface
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
        $dueOn = $period->copy()->day(6)->toDateString();
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

        // Map ChargeStatus 'ISSUED' id using Enum
        $statusId = ChargeStatusCode::ISSUED->id();
        if ($statusId <= 0) {
            return [];
        }

        // Active M2 contracts overlapping the target month; fetch their locals
        $items = DB::table('contracts as c')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('contract_modalities as cm', 'cm.id', '=', 'c.contract_modality_id')
            ->join('contract_types as ct', 'ct.id', '=', 'c.contract_type_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->whereIn('cs.code', ContractStatusCode::activeForCharges())
            ->where('cm.code', '=', 'M2')
            ->where('ct.code', '=', 'CONV')
            ->where('l.market_id', '=', $marketId)
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            // overlap with month: start_date <= periodEnd AND (end_date IS NULL OR end_date >= periodStart)
            ->whereDate('c.start_date', '<=', $periodEnd)
            ->select('l.id as local_id', 'l.area_m2 as area', 'l.market_id', 'c.id as contract_id')
            ->get();

        $rows = [];
        foreach ($items as $it) {
            $localId = (int) $it->local_id;
            $contractId = (int) $it->contract_id;
            $area = (float) $it->area;
            // Monthly conversion factor: 365/12 days per month
            $monthlyFactor = 365 / 12;
            $amountMinor = (int) round($priceMinorPerM2PerDay * $area * $monthlyFactor, 0);

            $rows[] = [
                // Context
                'market_id' => $marketId,
                'local_id' => $localId,
                'contract_id' => $contractId,
                'condo_period_id' => null,

                // Debtor
                'debtor_type' => 'LOCAL',
                'debtor_id' => $localId,
                'origin_debtor_type' => 'LOCAL',
                'origin_debtor_id' => $localId,

                // Classification
                'kind' => 'RENT_EUR_M2',
                'currency' => 'EUR',

                // Amount and dates
                'amount_minor' => $amountMinor,
                'period' => $periodStart,
                'issued_on' => $issuedOn,
                'due_on' => $dueOn,

                // Status and source
                'charge_status_id' => $statusId,
                'source' => 'RENT_RUN',
                'idempotency_key' => $idemp,
            ];
        }

        return $rows;
    }
}
