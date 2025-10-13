<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface PaymentServiceInterface extends ServiceInterface
{
    /**
     * Extra data for index view (e.g., stats).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array;

    /**
     * Verify a payment with the bank gateway and persist gateway fields.
     * Returns updated payment as array for FE.
     *
     * @return array<string, mixed>
     */
    public function verify(int|string $paymentId): array;

    /**
     * Apply a payment by moving it to APPLIED state.
     * Future: generate PaymentAllocations FIFO.
     *
     * @return array<string, mixed>
     */
    public function apply(int|string $paymentId): array;

    /**
     * Resolve FX rate id for a given currency and paid_on date.
     */
    public function resolveFxId(string $currencyCode, \DateTimeInterface $paidOn): ?int;
}
