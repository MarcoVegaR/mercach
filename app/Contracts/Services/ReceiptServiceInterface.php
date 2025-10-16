<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Receipt;

interface ReceiptServiceInterface extends ServiceInterface
{
    /**
     * Issue a receipt for the given payment. Idempotent for the same allocations snapshot.
     */
    public function issue(int $paymentId): Receipt;

    /**
     * Find active receipt for a payment (if any).
     */
    public function findActiveByPaymentId(int $paymentId): ?Receipt;

    /**
     * Issue one receipt per charge affected by the given payment. Returns a list of receipts (created or reused).
     *
     * @return list<Receipt>
     */
    public function issueByPaymentPerCharge(int $paymentId): array;
}
