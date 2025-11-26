<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\Services\ReceiptServiceInterface;
use App\Models\CustomerCredit;
use App\Models\Local;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Illuminate\Support\Facades\URL;

/**
 * Query builder for Payment show page data.
 *
 * Encapsulates all data retrieval logic for displaying a payment.
 */
class PaymentShowQuery
{
    private Payment $payment;

    /**
     * Set the payment to query.
     */
    public function forPayment(Payment $payment): self
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * Execute and get all show data.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return [
            'customer_credit_bs_minor' => $this->getCustomerCreditSum(),
            'allocations' => $this->getAllocations(),
            'receipt' => $this->getPaymentReceipt(),
            'receipts_by_charge' => $this->getReceiptsByCharge(),
            'can_edit' => $this->canEdit(),
        ];
    }

    /**
     * Get sum of open customer credits for the payment's debtor.
     */
    private function getCustomerCreditSum(): int
    {
        try {
            return (int) CustomerCredit::query()
                ->where('debtor_type', (string) $this->payment->getAttribute('debtor_type'))
                ->where('debtor_id', (int) $this->payment->getAttribute('debtor_id'))
                ->where('status', 'OPEN')
                ->sum('balance_minor');
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get allocations with charge and local data.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAllocations(): array
    {
        try {
            $rows = PaymentAllocation::query()
                ->where('payment_id', (int) $this->payment->getKey())
                ->leftJoin('charges as c', 'c.id', '=', 'payment_allocations.charge_id')
                ->orderBy('payment_allocations.id')
                ->get([
                    'payment_allocations.charge_id',
                    'payment_allocations.amount_bs_minor',
                    'payment_allocations.created_at',
                    'c.currency',
                    'c.amount_minor',
                    'c.period',
                    'c.due_on',
                    'c.local_id',
                    'c.kind',
                ]);

            $localsById = $this->resolveLocals($rows->pluck('local_id')->filter()->unique()->values()->all());

            return $rows->map(fn ($r) => [
                'charge_id' => (int) ($r->getAttribute('charge_id') ?? 0),
                'amount_bs_minor' => (int) ($r->getAttribute('amount_bs_minor') ?? 0),
                'created_at' => (string) ($r->getAttribute('created_at') ?? ''),
                'currency' => (string) ($r->getAttribute('currency') ?? ''),
                'amount_minor' => (int) ($r->getAttribute('amount_minor') ?? 0),
                'period' => (string) ($r->getAttribute('period') ?? ''),
                'due_on' => (string) ($r->getAttribute('due_on') ?? ''),
                'local_label' => $localsById[(int) ($r->getAttribute('local_id') ?? 0)] ?? null,
                'kind' => (string) ($r->getAttribute('kind') ?? ''),
            ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve local IDs to labels.
     *
     * @param  array<int>  $localIds
     * @return array<int, string>
     */
    private function resolveLocals(array $localIds): array
    {
        if (empty($localIds)) {
            return [];
        }

        return Local::query()
            ->whereIn('id', $localIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id')
            ->map(function ($l) {
                $code = (string) ($l->getAttribute('code') ?? '');
                $name = (string) ($l->getAttribute('name') ?? '');
                $label = trim(($code ? $code.' • ' : '').$name);

                return $label !== '' ? $label : (string) $l->getAttribute('id');
            })
            ->toArray();
    }

    /**
     * Get payment receipt, issuing lazily if needed.
     *
     * @return array<string, mixed>|null
     */
    private function getPaymentReceipt(): ?array
    {
        try {
            /** @var Receipt|null $rec */
            $rec = Receipt::query()
                ->where('payment_id', (int) $this->payment->getKey())
                ->where('status', 'ACTIVE')
                ->where(function ($q) {
                    $q->where('scope', 'PAYMENT')->orWhereNull('scope');
                })
                ->orderByDesc('id')
                ->first();

            // Lazy issue if payment is APPLIED but no receipt exists
            if (! $rec && (string) ($this->payment->getAttribute('status') ?? '') === 'APPLIED') {
                try {
                    /** @var ReceiptServiceInterface $svc */
                    $svc = app(ReceiptServiceInterface::class);
                    $rec = $svc->issue((int) $this->payment->getKey());
                } catch (\Throwable) {
                    // Ignore
                }
            }

            if (! $rec) {
                return null;
            }

            return [
                'id' => (int) $rec->getKey(),
                'receipt_number' => (string) $rec->getAttribute('receipt_number'),
                'issued_at' => (string) ($rec->getAttribute('issued_at') ?? ''),
                'download_url' => route('receipts.download', ['receipt' => (int) $rec->getKey()]),
                'verify_url' => URL::signedRoute('receipts.public.show', ['token' => (string) $rec->getAttribute('public_token')]),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get receipts grouped by charge.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getReceiptsByCharge(): array
    {
        try {
            $recs = Receipt::query()
                ->where('payment_id', (int) $this->payment->getKey())
                ->where('scope', 'CHARGE')
                ->where('status', 'ACTIVE')
                ->orderBy('id')
                ->get();

            $result = [];
            foreach ($recs as $r) {
                $meta = (array) ($r->getAttribute('meta') ?? []);
                $chargeId = (int) ($r->getAttribute('charge_id') ?? 0);

                $appliedBsMinor = (int) PaymentAllocation::query()
                    ->where('payment_id', (int) $this->payment->getKey())
                    ->where('charge_id', $chargeId)
                    ->sum('amount_bs_minor');

                $result[] = [
                    'id' => (int) $r->getKey(),
                    'receipt_number' => (string) $r->getAttribute('receipt_number'),
                    'issued_at' => (string) ($r->getAttribute('issued_at') ?? ''),
                    'concept' => strtoupper((string) ($r->getAttribute('concept') ?? '')),
                    'charge_id' => $chargeId,
                    'charge_period' => (string) ($meta['charge_period'] ?? ''),
                    'charge_kind' => (string) ($meta['charge_kind'] ?? ''),
                    'applied_bs_minor' => $appliedBsMinor,
                    'download_url' => route('receipts.download', ['receipt' => (int) $r->getKey()]),
                    'verify_url' => URL::signedRoute('receipts.public.show', ['token' => (string) $r->getAttribute('public_token')]),
                ];
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Determine if payment can be edited.
     */
    private function canEdit(): bool
    {
        $status = (string) ($this->payment->getAttribute('status') ?? '');
        if ($status === 'REGISTERED') {
            return true;
        }

        if ($status === 'CONFIRMED') {
            $appliedMinor = (int) PaymentAllocation::query()
                ->where('payment_id', (int) $this->payment->getKey())
                ->sum('amount_bs_minor');

            return $appliedMinor === 0;
        }

        return false;
    }
}
