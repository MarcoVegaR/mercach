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
     * Validate and store payment allocations atomically.
     * Options: ['use_credit' => bool]
     *
     * @param  array<int, array{charge_id:int, amount_bs_minor:int}>  $items
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function storeAllocations(int|string $paymentId, array $items, array $options = []): array;

    /**
     * Resolve FX rate id for a given currency and paid_on date.
     */
    public function resolveFxId(string $currencyCode, \DateTimeInterface $paidOn): ?int;

    /**
     * Create and immediately verify a payment according to method policy.
     * - DEB: auto-confirms without external gateway.
     * - TRF/PMOV: requires successful bank verification to persist.
     * Returns the resulting payment row (same shape as toRow()).
     *
     * @param  array<string,mixed>  $attributes
     * @param  array<string,mixed>|null  $auditContext  Optional context: ['url'=>string,'ip'=>string,'ua'=>string]
     * @return array<string,mixed>
     */
    public function createAndVerify(array $attributes, ?array $auditContext = null): array;
}
