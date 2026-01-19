<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\ReceiptServiceInterface;
use App\Exceptions\DomainActionException;
use App\Models\Local;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\ReceiptSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReceiptService extends BaseService implements ReceiptServiceInterface
{
    public function issue(int $paymentId): Receipt
    {
        /** @var Payment $payment */
        $payment = Payment::query()->findOrFail($paymentId);

        $status = (string) ($payment->getAttribute('status') ?? '');
        if ($status !== 'APPLIED') {
            throw new DomainActionException('El pago debe estar en estado APPLIED para emitir recibo.');
        }
        $hash = $this->allocationsHash($paymentId);

        $existing = Receipt::query()
            ->where('payment_id', $paymentId)
            ->where('status', 'ACTIVE')
            ->where(function ($q) {
                $q->where('scope', 'PAYMENT')->orWhereNull('scope');
            })
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            if ((string) $existing->getAttribute('allocations_hash') === $hash) {
                if (empty($existing->getAttribute('scope'))) {
                    $existing->fill(['scope' => 'PAYMENT'])->save();
                }

                return $existing;
            }

            if (empty($existing->getAttribute('scope'))) {
                $existing->fill(['scope' => 'PAYMENT'])->save();
            }

            return $existing;
        }

        [$marketId, $seriesCode] = $this->resolveMarketAndSeries($paymentId);
        $startNumber = 130;

        return DB::transaction(function () use ($payment, $paymentId, $hash, $marketId, $seriesCode, $startNumber) {
            $seq = ReceiptSequence::query()
                ->where('market_id', $marketId)
                ->where('series_code', $seriesCode)
                ->lockForUpdate()
                ->first();
            if (! $seq) {
                $seq = new ReceiptSequence([
                    'market_id' => $marketId,
                    'series_code' => $seriesCode,
                    'next_number' => $startNumber,
                ]);
                $seq->save();
                $seq = ReceiptSequence::query()->where('id', (int) $seq->getKey())->lockForUpdate()->first();
            }

            $num = (int) ($seq?->getAttribute('next_number') ?? $startNumber);
            $receiptNumber = $seriesCode.'-'.str_pad((string) $num, 6, '0', STR_PAD_LEFT);

            $r = new Receipt([
                'payment_id' => (int) $paymentId,
                'scope' => 'PAYMENT',
                'market_id' => $marketId,
                'series_code' => $seriesCode,
                'number_seq' => $num,
                'receipt_number' => $receiptNumber,
                'issued_at' => now(),
                'status' => 'ACTIVE',
                'allocations_hash' => $hash,
                'public_token' => Str::random(48),
                'template_version' => 'v1',
                'meta' => [
                    'debtor_type' => (string) $payment->getAttribute('debtor_type'),
                    'debtor_id' => (int) $payment->getAttribute('debtor_id'),
                    'local_id' => $payment->getAttribute('local_id') ? (int) $payment->getAttribute('local_id') : null,
                    'company_bank_account_id' => $payment->getAttribute('company_bank_account_id') ? (int) $payment->getAttribute('company_bank_account_id') : null,
                    'origin_bank_id' => $payment->getAttribute('origin_bank_id') ? (int) $payment->getAttribute('origin_bank_id') : null,
                    'method' => (string) ($payment->getAttribute('method') ?? ''),
                    'reference' => (string) ($payment->getAttribute('reference') ?? ''),
                    'paid_on' => (string) ($payment->getAttribute('paid_on') ?? ''),
                    'fx_rate_id' => $payment->getAttribute('fx_rate_id') ? (int) $payment->getAttribute('fx_rate_id') : null,
                ],
            ]);
            $r->save();

            ReceiptSequence::query()->where('id', (int) $seq->getKey())->update([
                'next_number' => $num + 1,
            ]);

            return $r;
        });
    }

    public function findActiveByPaymentId(int $paymentId): ?Receipt
    {
        return Receipt::query()
            ->where('payment_id', $paymentId)
            ->where('status', 'ACTIVE')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return list<Receipt>
     */
    public function issueByPaymentPerCharge(int $paymentId): array
    {
        /** @var Payment $payment */
        $payment = Payment::query()->findOrFail($paymentId);

        $allocs = PaymentAllocation::query()
            ->where('payment_id', $paymentId)
            ->orderBy('charge_id')
            ->get();

        $byCharge = [];
        foreach ($allocs as $a) {
            $cid = (int) $a->getAttribute('charge_id');
            if ($cid <= 0) {
                continue;
            }
            $byCharge[$cid][] = $a;
        }

        $results = [];
        foreach ($byCharge as $chargeId => $rows) {
            $hash = $this->allocationsHashForCharge($paymentId, (int) $chargeId);

            $existing = Receipt::query()
                ->where('payment_id', $paymentId)
                ->where('charge_id', (int) $chargeId)
                ->where('scope', 'CHARGE')
                ->where('status', 'ACTIVE')
                ->orderByDesc('id')
                ->first();
            if ($existing && (string) $existing->getAttribute('allocations_hash') === $hash) {
                $results[] = $existing;

                continue;
            }

            [$marketId, $seriesCode] = $this->resolveMarketAndSeriesFromChargeId((int) $chargeId);

            $charge = \App\Models\Charge::query()->find((int) $chargeId);
            $concept = 'TU';
            if ($charge) {
                $kind = strtoupper((string) ($charge->getAttribute('kind') ?? ''));
                $period = (string) ($charge->getAttribute('period') ?? '');
                if ($charge->getAttribute('condo_period_id')) {
                    $concept = 'GC';
                } elseif (str_contains($kind, 'CONDO')) {
                    $concept = 'GC';
                }
            }

            $startNumber = 130;

            $created = DB::transaction(function () use ($payment, $paymentId, $hash, $marketId, $seriesCode, $chargeId, $concept, $charge, $startNumber) {
                $seq = ReceiptSequence::query()
                    ->where('market_id', $marketId)
                    ->where('series_code', $seriesCode)
                    ->lockForUpdate()
                    ->first();
                if (! $seq) {
                    $seq = new ReceiptSequence([
                        'market_id' => $marketId,
                        'series_code' => $seriesCode,
                        'next_number' => $startNumber,
                    ]);
                    $seq->save();
                    $seq = ReceiptSequence::query()->where('id', (int) $seq->getKey())->lockForUpdate()->first();
                }

                $num = (int) ($seq?->getAttribute('next_number') ?? $startNumber);
                $receiptNumber = $seriesCode.'-'.str_pad((string) $num, 6, '0', STR_PAD_LEFT);

                $meta = [
                    'debtor_type' => (string) $payment->getAttribute('debtor_type'),
                    'debtor_id' => (int) $payment->getAttribute('debtor_id'),
                    'local_id' => $payment->getAttribute('local_id') ? (int) $payment->getAttribute('local_id') : null,
                    'company_bank_account_id' => $payment->getAttribute('company_bank_account_id') ? (int) $payment->getAttribute('company_bank_account_id') : null,
                    'origin_bank_id' => $payment->getAttribute('origin_bank_id') ? (int) $payment->getAttribute('origin_bank_id') : null,
                    'method' => (string) ($payment->getAttribute('method') ?? ''),
                    'reference' => (string) ($payment->getAttribute('reference') ?? ''),
                    'paid_on' => (string) ($payment->getAttribute('paid_on') ?? ''),
                    'fx_rate_id' => $payment->getAttribute('fx_rate_id') ? (int) $payment->getAttribute('fx_rate_id') : null,
                ];
                if ($charge) {
                    $meta['charge_currency'] = (string) ($charge->getAttribute('currency') ?? '');
                    $meta['charge_amount_minor'] = (int) ($charge->getAttribute('amount_minor') ?? 0);
                    $meta['charge_period'] = (string) ($charge->getAttribute('period') ?? '');
                    $meta['charge_kind'] = (string) ($charge->getAttribute('kind') ?? '');
                    $meta['condo_period_id'] = $charge->getAttribute('condo_period_id') ? (int) $charge->getAttribute('condo_period_id') : null;
                }

                $r = new Receipt([
                    'payment_id' => (int) $paymentId,
                    'charge_id' => (int) $chargeId,
                    'market_id' => $marketId,
                    'scope' => 'CHARGE',
                    'concept' => $concept,
                    'template_version' => 'v1',
                    'series_code' => $seriesCode,
                    'number_seq' => $num,
                    'receipt_number' => $receiptNumber,
                    'issued_at' => now(),
                    'status' => 'ACTIVE',
                    'allocations_hash' => $hash,
                    'public_token' => Str::random(48),
                    'meta' => $meta,
                ]);
                $r->save();

                ReceiptSequence::query()->where('id', (int) $seq->getKey())->update([
                    'next_number' => $num + 1,
                ]);

                return $r;
            });

            $results[] = $created;
        }

        return $results;
    }

    private function allocationsHash(int $paymentId): string
    {
        $items = PaymentAllocation::query()
            ->where('payment_id', $paymentId)
            ->orderBy('charge_id')
            ->get(['charge_id', 'amount_bs_minor'])
            ->map(fn ($r) => [
                'charge_id' => (int) $r->getAttribute('charge_id'),
                'amount_bs_minor' => (int) $r->getAttribute('amount_bs_minor'),
            ])
            ->values()
            ->all();

        $payload = json_encode($items);

        return hash('sha256', (string) $payload);
    }

    private function allocationsHashForCharge(int $paymentId, int $chargeId): string
    {
        $items = PaymentAllocation::query()
            ->where('payment_id', $paymentId)
            ->where('charge_id', $chargeId)
            ->orderBy('id')
            ->get(['id', 'charge_id', 'amount_bs_minor'])
            ->map(fn ($r) => [
                'allocation_id' => (int) $r->getAttribute('id'),
                'charge_id' => (int) $r->getAttribute('charge_id'),
                'amount_bs_minor' => (int) $r->getAttribute('amount_bs_minor'),
            ])
            ->values()
            ->all();
        $payload = json_encode($items);

        return hash('sha256', (string) $payload);
    }

    /**
     * @return array{0: ?int, 1: string}
     */
    private function resolveMarketAndSeries(int $paymentId): array
    {
        $payment = Payment::query()->findOrFail($paymentId);

        $marketId = null;
        $seriesBase = 'GEN';

        $localId = $payment->getAttribute('local_id');
        if (is_numeric($localId) && (int) $localId > 0) {
            $local = Local::query()->find((int) $localId);
            if ($local) {
                $marketId = $local->getAttribute('market_id') ? (int) $local->getAttribute('market_id') : null;
            }
        }

        if ($marketId === null) {
            $marketIds = PaymentAllocation::query()
                ->where('payment_id', $paymentId)
                ->leftJoin('charges as c', 'c.id', '=', 'payment_allocations.charge_id')
                ->leftJoin('locals as l', 'l.id', '=', 'c.local_id')
                ->pluck('l.market_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            if (! empty($marketIds)) {
                $marketId = (int) $marketIds[0];
            }
        }

        if ($marketId !== null) {
            $mk = Market::query()->find($marketId);
            $seriesBase = strtoupper((string) ($mk?->getAttribute('code') ?? 'GEN'));
        }

        $seriesCode = $seriesBase.'-'.date('Y');

        return [$marketId, $seriesCode];
    }

    /**
     * @return array{0: ?int, 1: string}
     */
    private function resolveMarketAndSeriesFromChargeId(int $chargeId): array
    {
        $charge = \App\Models\Charge::query()->findOrFail($chargeId);
        $marketId = null;
        $seriesBase = 'GEN';
        $localId = $charge->getAttribute('local_id');
        if (is_numeric($localId) && (int) $localId > 0) {
            $local = Local::query()->find((int) $localId);
            if ($local) {
                $marketId = $local->getAttribute('market_id') ? (int) $local->getAttribute('market_id') : null;
            }
        }
        if ($marketId !== null) {
            $mk = Market::query()->find($marketId);
            $seriesBase = strtoupper((string) ($mk?->getAttribute('code') ?? 'GEN'));
        }
        $seriesCode = $seriesBase.'-'.date('Y');

        return [$marketId, $seriesCode];
    }
}
