<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\ChargeStatusCode;
use App\Enums\ContractStatusCode;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\FxConversionHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Query builder para sugerir allocations usando estrategias FIFO o proporcional.
 */
class SuggestAllocationsQuery
{
    private Payment $payment;

    private Carbon $paidOn;

    private string $strategy = 'fifo';

    private ?string $currency = null;

    private ?string $kind = null;

    private ?string $periodFrom = null;

    private ?string $periodTo = null;

    private bool $overdueOnly = false;

    public function __construct(
        private FxConversionHelper $fxHelper,
    ) {}

    public function forPayment(Payment $payment): self
    {
        $this->payment = $payment;
        $this->paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));

        return $this;
    }

    public function strategy(string $strategy): self
    {
        $this->strategy = in_array($strategy, ['fifo', 'proportional'], true) ? $strategy : 'fifo';

        return $this;
    }

    public function filterCurrency(?string $currency): self
    {
        $this->currency = $currency ? strtoupper($currency) : null;

        return $this;
    }

    public function filterKind(?string $kind): self
    {
        $this->kind = $kind ? strtoupper($kind) : null;

        return $this;
    }

    public function filterPeriod(?string $from, ?string $to): self
    {
        $this->periodFrom = $from;
        $this->periodTo = $to;

        return $this;
    }

    public function overdueOnly(bool $value = true): self
    {
        $this->overdueOnly = $value;

        return $this;
    }

    /**
     * Execute and return suggested allocations.
     *
     * @return array{items: list<array{charge_id: int, amount_bs_minor: int}>, summary: array<string, mixed>}
     */
    public function execute(): array
    {
        $amountPayment = (int) $this->payment->getAttribute('amount_bs_minor');
        $currentAssigned = (int) PaymentAllocation::query()
            ->where('payment_id', $this->payment->getKey())
            ->sum('amount_bs_minor');
        $available = max(0, $amountPayment - $currentAssigned);

        if ($available === 0) {
            return [
                'items' => [],
                'summary' => [
                    'available_bs_minor' => 0,
                    'suggested_bs_minor' => 0,
                    'after_available_bs_minor' => 0,
                ],
            ];
        }

        // Build charges query
        $charges = $this->buildChargesQuery();

        // Calculate outstanding for each charge
        $rows = $this->calculateOutstanding($charges);

        // Apply strategy
        $items = $this->applyStrategy($rows, $available);

        $suggested = array_reduce($items, fn ($a, $i) => $a + (int) $i['amount_bs_minor'], 0);

        return [
            'items' => $items,
            'summary' => [
                'available_bs_minor' => $available,
                'suggested_bs_minor' => $suggested,
                'after_available_bs_minor' => max(0, $available - $suggested),
            ],
        ];
    }

    /**
     * Build the charges query based on debtor and filters.
     *
     * @return \Illuminate\Support\Collection<int, Charge>
     */
    private function buildChargesQuery(): \Illuminate\Support\Collection
    {
        $debtorType = (string) $this->payment->getAttribute('debtor_type');
        $debtorId = (int) $this->payment->getAttribute('debtor_id');

        if ($debtorType === 'CONCESSIONAIRE') {
            $localIds = $this->resolveLocalIds($debtorId);
            // Include both concessionaire-level AND local-level charges
            $q = Charge::query()->where(function ($query) use ($debtorId, $localIds) {
                // Concessionaire-level charges
                $query->where(function ($sub) use ($debtorId) {
                    $sub->where('debtor_type', 'CONCESSIONAIRE')
                        ->where('debtor_id', $debtorId);
                });
                // Local-level charges (if any locals found)
                if (! empty($localIds)) {
                    $query->orWhere(function ($sub) use ($localIds) {
                        $sub->where('debtor_type', 'LOCAL')
                            ->whereIn('debtor_id', $localIds);
                    });
                }
            });
        } else {
            $q = Charge::query()
                ->where('debtor_type', $debtorType)
                ->where('debtor_id', $debtorId);
        }

        // Collectable statuses
        $statusIds = ChargeStatusCode::collectableIds();
        if (! empty($statusIds)) {
            $q->whereIn('charge_status_id', $statusIds);
        }

        // Filters
        if ($this->currency !== null) {
            $q->where('currency', $this->currency);
        }

        if ($this->kind !== null) {
            $q->where('kind', $this->kind);
        }

        if ($this->periodFrom !== null) {
            $from = Carbon::createFromFormat('Y-m', $this->periodFrom)->startOfMonth()->toDateString();
            $q->whereDate('period', '>=', $from);
        }

        if ($this->periodTo !== null) {
            $to = Carbon::createFromFormat('Y-m', $this->periodTo)->endOfMonth()->toDateString();
            $q->whereDate('period', '<=', $to);
        }

        if ($this->overdueOnly) {
            $q->whereDate('due_on', '<=', $this->paidOn->toDateString());
        }

        return $q->orderBy('period')
            ->limit(500)
            ->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'period', 'due_on']);
    }

    /**
     * Resolve local IDs for a concessionaire.
     *
     * @return list<int>
     */
    private function resolveLocalIds(int $concessionaireId): array
    {
        return DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cc.concessionaire_id', $concessionaireId)
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereDate('c.start_date', '<=', $this->paidOn->toDateString())
            ->whereIn('cs.code', ContractStatusCode::activeForCharges())
            ->pluck('l.id')
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Calculate outstanding for each charge.
     *
     * @param  \Illuminate\Support\Collection<int, Charge>  $charges
     * @return list<array{charge_id: int, outstanding: int, due_on: string, period: string}>
     */
    private function calculateOutstanding(\Illuminate\Support\Collection $charges): array
    {
        if ($charges->isEmpty()) {
            return [];
        }

        $outstandingMap = $this->fxHelper->chargesOutstandingVesBatch($charges, $this->paidOn);

        $rows = [];
        foreach ($charges as $charge) {
            $cid = (int) $charge->getKey();
            $rows[] = [
                'charge_id' => $cid,
                'outstanding' => $outstandingMap[$cid] ?? 0,
                'due_on' => (string) ($charge->getAttribute('due_on') ?? ''),
                'period' => (string) $charge->getAttribute('period'),
            ];
        }

        return $rows;
    }

    /**
     * Apply allocation strategy.
     *
     * @param  list<array{charge_id: int, outstanding: int, due_on: string, period: string}>  $rows
     * @return list<array{charge_id: int, amount_bs_minor: int}>
     */
    private function applyStrategy(array $rows, int $available): array
    {
        if ($this->strategy === 'fifo') {
            return $this->applyFifo($rows, $available);
        }

        return $this->applyProportional($rows, $available);
    }

    /**
     * FIFO strategy: oldest first.
     *
     * @param  list<array{charge_id: int, outstanding: int, due_on: string, period: string}>  $rows
     * @return list<array{charge_id: int, amount_bs_minor: int}>
     */
    private function applyFifo(array $rows, int $available): array
    {
        usort($rows, fn ($a, $b) => strcmp($a['due_on'] ?: $a['period'], $b['due_on'] ?: $b['period']));

        $items = [];
        $remaining = $available;

        foreach ($rows as $r) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $r['outstanding']);
            if ($take > 0) {
                $items[] = ['charge_id' => $r['charge_id'], 'amount_bs_minor' => $take];
                $remaining -= $take;
            }
        }

        return $items;
    }

    /**
     * Proportional strategy: distribute proportionally.
     *
     * @param  list<array{charge_id: int, outstanding: int, due_on: string, period: string}>  $rows
     * @return list<array{charge_id: int, amount_bs_minor: int}>
     */
    private function applyProportional(array $rows, int $available): array
    {
        $totalOut = array_reduce($rows, fn ($acc, $r) => $acc + (int) $r['outstanding'], 0);

        if ($totalOut === 0) {
            return [];
        }

        $shares = [];
        foreach ($rows as $r) {
            $out = (int) $r['outstanding'];
            if ($out <= 0) {
                continue;
            }
            $share = (int) floor(($out / $totalOut) * $available);
            $shares[$r['charge_id']] = min($share, $out);
        }

        // Distribute residual
        $assigned = array_sum($shares);
        $residual = max(0, $available - $assigned);

        if ($residual > 0) {
            foreach ($rows as $r) {
                if ($residual <= 0) {
                    break;
                }
                $cid = $r['charge_id'];
                $out = (int) $r['outstanding'];
                $curr = $shares[$cid] ?? 0;
                if ($curr < $out) {
                    $shares[$cid] = $curr + 1;
                    $residual--;
                }
            }
        }

        $items = [];
        foreach ($shares as $cid => $amt) {
            if ($amt > 0) {
                $items[] = ['charge_id' => (int) $cid, 'amount_bs_minor' => (int) $amt];
            }
        }

        return $items;
    }
}
