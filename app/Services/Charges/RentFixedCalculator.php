<?php

declare(strict_types=1);

namespace App\Services\Charges;

use App\Contracts\Services\Charges\ChargeCalculatorInterface;
use App\Enums\ChargeStatusCode;
use App\Enums\ContractStatusCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RentFixedCalculator implements ChargeCalculatorInterface
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function calculate(array $params): array
    {
        // Expected params:
        // - period (YYYY-MM-01) monthly run: emit on each contract's billing_day within that month
        // - date (Y-m-d) daily run: emit only for that day (kept for compatibility)
        // - market_id (optional) to restrict by market
        // - idempotency_key (optional)
        $dateParam = isset($params['date']) ? (string) $params['date'] : null;
        $periodParam = isset($params['period']) ? (string) $params['period'] : null;
        $marketId = isset($params['market_id']) ? (int) $params['market_id'] : null;
        $idemp = isset($params['idempotency_key']) ? (string) $params['idempotency_key'] : null;
        $rows = [];

        // Status mapping using Enum
        $statusId = ChargeStatusCode::ISSUED->id();
        if ($statusId <= 0) {
            return [];
        }

        // Base query for TFIJA + CONTR with positive price
        $base = DB::table('contracts as c')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('contract_modalities as cm', 'cm.id', '=', 'c.contract_modality_id')
            ->join('contract_types as ct', 'ct.id', '=', 'c.contract_type_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cm.code', '=', 'TFIJA')
            ->where('ct.code', '=', 'CONTR')
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNotNull('c.monthly_price_eur')
            ->whereRaw('c.monthly_price_eur > 0');
        // Default to monthly if neither provided
        if (! $dateParam && ! $periodParam) {
            $periodParam = date('Y-m-01');
        }

        // Path A: Daily run by exact date
        if ($dateParam) {
            $date = Carbon::parse($dateParam)->startOfDay();
            $day = (int) $date->day;
            $period = $date->copy()->startOfMonth();
            $issuedOn = $date->toDateString();
            $dueOn = $issuedOn; // same-day due

            $query = clone $base;
            if ($marketId && $marketId > 0) {
                $query->where('l.market_id', '=', $marketId);
            }
            // Active contracts: VIG/EXT/VENC generate charges regardless of end_date
            $query->whereIn('cs.code', ContractStatusCode::activeForCharges())
                ->whereDate('c.start_date', '<=', $issuedOn);

            // Billing day
            if ($day === 1) {
                $query->where(function ($q) {
                    $q->whereNull('c.billing_day')->orWhere('c.billing_day', '=', 1);
                });
            } else {
                $query->where('c.billing_day', '=', $day);
            }

            $items = $query->select(
                'c.id as contract_id',
                'c.monthly_price_eur as monthly_price_eur',
                'l.id as local_id',
                'l.market_id as market_id'
            )->orderBy('c.id')->get();

            if ($items->isEmpty()) {
                return [];
            }

            $byContract = [];
            foreach ($items as $row) {
                $cid = (int) $row->contract_id;
                $byContract[$cid] = $byContract[$cid] ?? [
                    'total_minor' => (int) round(((float) $row->monthly_price_eur) * 100),
                    'locals' => [],
                ];
                $byContract[$cid]['locals'][] = [
                    'local_id' => (int) $row->local_id,
                    'market_id' => (int) $row->market_id,
                ];
            }

            foreach ($byContract as $contractId => $bundle) {
                $locals = $bundle['locals'];
                $n = max(1, count($locals));
                $totalMinor = (int) $bundle['total_minor'];
                $baseAmt = intdiv($totalMinor, $n);
                $rem = $totalMinor - ($baseAmt * $n);

                foreach ($locals as $idx => $loc) {
                    $amountMinor = $baseAmt + ($idx === 0 ? $rem : 0);
                    $rows[] = [
                        'market_id' => (int) $loc['market_id'],
                        'local_id' => (int) $loc['local_id'],
                        'contract_id' => (int) $contractId,
                        'condo_period_id' => null,
                        'debtor_type' => 'LOCAL',
                        'debtor_id' => (int) $loc['local_id'],
                        'origin_debtor_type' => 'LOCAL',
                        'origin_debtor_id' => (int) $loc['local_id'],
                        'kind' => 'RENT_EUR_FIXED',
                        'currency' => 'EUR',
                        'amount_minor' => $amountMinor,
                        'period' => $period->toDateString(),
                        'issued_on' => $issuedOn,
                        'due_on' => $dueOn,
                        'charge_status_id' => $statusId,
                        'source' => 'FIXED_RUN',
                        'idempotency_key' => $idemp,
                    ];
                }
            }

            return $rows;
        }

        // Path B: Monthly run by period (YYYY-MM-01)
        if ($periodParam) {
            $month = Carbon::parse($periodParam)->startOfMonth();
            $monthStart = $month->toDateString();
            $monthEnd = $month->copy()->endOfMonth()->toDateString();

            $query = clone $base;
            if ($marketId && $marketId > 0) {
                $query->where('l.market_id', '=', $marketId);
            }
            // Active contracts: VIG/EXT/VENC generate charges regardless of end_date
            $query->whereIn('cs.code', ContractStatusCode::activeForCharges())
                ->whereDate('c.start_date', '<=', $monthEnd);

            $items = $query->select(
                'c.id as contract_id',
                'c.monthly_price_eur as monthly_price_eur',
                'c.start_date as c_start',
                'c.end_date as c_end',
                'c.billing_day as billing_day',
                'cs.code as status_code',
                'l.id as local_id',
                'l.market_id as market_id'
            )->orderBy('c.id')->get();

            if ($items->isEmpty()) {
                return [];
            }

            $byContract = [];
            foreach ($items as $row) {
                $cid = (int) $row->contract_id;
                $byContract[$cid] = $byContract[$cid] ?? [
                    'total_minor' => (int) round(((float) $row->monthly_price_eur) * 100),
                    'billing_day' => $row->billing_day !== null ? (int) $row->billing_day : 1,
                    'c_start' => (string) $row->c_start,
                    'c_end' => $row->c_end !== null ? (string) $row->c_end : null,
                    'status' => (string) $row->status_code,
                    'locals' => [],
                ];
                $byContract[$cid]['locals'][] = [
                    'local_id' => (int) $row->local_id,
                    'market_id' => (int) $row->market_id,
                ];
            }

            foreach ($byContract as $contractId => $bundle) {
                $bd = max(1, (int) $bundle['billing_day']);
                $bd = min($bd, (int) $month->copy()->endOfMonth()->day); // clamp to EOM
                $issuedOn = $month->copy()->day($bd)->toDateString();
                $dueOn = $issuedOn;
                $period = $monthStart;

                // Contract must have started before or on issuedOn
                // Status VIG/EXT/VENC already filtered in query, so no end_date check needed
                if ($bundle['c_start'] > $issuedOn) {
                    continue;
                }

                $locals = $bundle['locals'];
                $n = max(1, count($locals));
                $totalMinor = (int) $bundle['total_minor'];
                $baseAmt = intdiv($totalMinor, $n);
                $rem = $totalMinor - ($baseAmt * $n);

                foreach ($locals as $idx => $loc) {
                    $amountMinor = $baseAmt + ($idx === 0 ? $rem : 0);
                    $rows[] = [
                        'market_id' => (int) $loc['market_id'],
                        'local_id' => (int) $loc['local_id'],
                        'contract_id' => (int) $contractId,
                        'condo_period_id' => null,
                        'debtor_type' => 'LOCAL',
                        'debtor_id' => (int) $loc['local_id'],
                        'origin_debtor_type' => 'LOCAL',
                        'origin_debtor_id' => (int) $loc['local_id'],
                        'kind' => 'RENT_EUR_FIXED',
                        'currency' => 'EUR',
                        'amount_minor' => $amountMinor,
                        'period' => $period,
                        'issued_on' => $issuedOn,
                        'due_on' => $dueOn,
                        'charge_status_id' => $statusId,
                        'source' => 'FIXED_RUN',
                        'idempotency_key' => $idemp,
                    ];
                }
            }

            return $rows;
        }

        return $rows;
    }
}
