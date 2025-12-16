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
     * Void an APPLIED payment (admin action).
     *
     * @param  array{reason?: string}  $options
     * @return array<string, mixed>
     */
    public function void(int|string $paymentId, array $options = []): array;

    /**
     * Void and rebook an APPLIED payment with a corrected paid_on date.
     *
     * @param  array{paid_on?: string, reason?: string}  $options
     * @return array<string, mixed>
     */
    public function voidRebook(int|string $paymentId, array $options = []): array;

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

    /**
     * Preview allocations: validates items against outstanding and payment available.
     *
     * @param  array<int, array{charge_id: int, amount_bs_minor: int}>  $items
     * @param  array{use_credit?: bool}  $options
     * @return array{ok: bool, errors: list<string>, available_bs_minor: int, requested_bs_minor: int, summary: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function previewAllocations(int|string $paymentId, array $items, array $options = []): array;

    /**
     * Suggest allocations for a payment using a strategy.
     *
     * @param  array{strategy?: string, currency?: string, kind?: string, period_from?: string, period_to?: string, overdue_only?: bool}  $filters
     * @return array{items: list<array{charge_id: int, amount_bs_minor: int}>, summary: array<string, mixed>}
     */
    public function suggestAllocations(int|string $paymentId, array $filters = []): array;
}
