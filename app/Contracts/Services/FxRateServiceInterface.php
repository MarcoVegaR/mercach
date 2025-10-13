<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface FxRateServiceInterface extends ServiceInterface
{
    /**
     * Extra data for index view (e.g., stats).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array;

    /**
     * Resolve the exchange rate for a currency at a given instant based on operational window.
     */
    public function resolveAt(string $currencyCode, \DateTimeInterface $at): ?\App\Models\FxRate;

    /**
     * Fetch and ingest official rates from BCV provider, closing previous operational window.
     *
     * @return array{inserted:int,updated:int}
     */
    public function ingestFromBcv(): array;
}
