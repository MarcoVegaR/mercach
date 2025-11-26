<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Charge;
use App\Models\CustomerCredit;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\FxConversionHelper;
use Illuminate\Support\Carbon;

/**
 * Validador para preview de allocations antes de aplicarlas.
 */
class PreviewAllocationsValidator
{
    public function __construct(
        private FxConversionHelper $fxHelper,
    ) {}

    /**
     * Validate allocation items and return preview result.
     *
     * @param  array<int, array{charge_id: int, amount_bs_minor: int}>  $items
     * @param  array{use_credit?: bool}  $options
     * @return array{ok: bool, errors: list<string>, available_bs_minor: int, requested_bs_minor: int, summary: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function validate(Payment $payment, array $items, array $options = []): array
    {
        $paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));
        $amountPayment = (int) $payment->getAttribute('amount_bs_minor');
        $currentAssigned = (int) PaymentAllocation::query()
            ->where('payment_id', $payment->getKey())
            ->sum('amount_bs_minor');
        $available = max(0, $amountPayment - $currentAssigned);

        $useCredit = (bool) ($options['use_credit'] ?? false);
        $creditAvailable = 0;
        if ($useCredit) {
            $creditAvailable = (int) CustomerCredit::query()
                ->where('debtor_type', (string) $payment->getAttribute('debtor_type'))
                ->where('debtor_id', (int) $payment->getAttribute('debtor_id'))
                ->where('status', 'OPEN')
                ->sum('balance_minor');
        }

        // Normalize items
        $byChargeRequested = collect($items)->keyBy('charge_id');
        $chargeIds = $byChargeRequested->keys()->all();

        // Load charges
        $charges = Charge::query()
            ->whereIn('id', $chargeIds)
            ->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued']);

        // Calculate outstanding using FxHelper
        $outstandingMap = $this->fxHelper->chargesOutstandingVesBatch($charges, $paidOn);

        // Validate each item
        $errors = [];
        $totalRequested = 0;
        $itemsResp = [];

        foreach ($charges as $charge) {
            $cid = (int) $charge->getKey();
            $req = (int) ($byChargeRequested[$cid]['amount_bs_minor'] ?? 0);
            $totalRequested += $req;

            $outstanding = $outstandingMap[$cid] ?? 0;
            $valid = $req <= $outstanding;
            $msg = $valid ? null : 'Monto supera saldo (Bs).';

            if (! $valid) {
                $errors[] = "Charge {$cid}: monto supera saldo (Bs).";
            }

            $itemsResp[] = [
                'charge_id' => $cid,
                'requested' => $req,
                'outstanding' => $outstanding,
                'valid' => $valid,
                'message' => $msg,
            ];
        }

        // Validate total against available + credit
        $limit = $available + ($useCredit ? $creditAvailable : 0);
        if ($totalRequested > $limit) {
            $errors[] = 'Total a aplicar supera el disponible (pago + crédito a favor).';
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'available_bs_minor' => $available,
            'requested_bs_minor' => $totalRequested,
            'summary' => [
                'available_bs_minor' => $available,
                'credit_available_bs_minor' => $creditAvailable,
                'requested_bs_minor' => $totalRequested,
                'after_available_bs_minor' => max(0, $available - $totalRequested),
                'after_total_available_bs_minor' => max(0, ($available + $creditAvailable) - $totalRequested),
            ],
            'items' => $itemsResp,
        ];
    }
}
