<?php

declare(strict_types=1);

namespace App\Contracts\Services\Charges;

/**
 * Calculator interface for charge generation.
 * Implementations should compute eligibility, amounts, and dates,
 * but not persist. The Orchestrator will upsert results.
 */
interface ChargeCalculatorInterface
{
    /**
     * @param  array<string, mixed>  $params  Context (market_id, period/date, etc.)
     * @return array<int, array<string, mixed>> List of proposed charge rows ready for upsert
     */
    public function calculate(array $params): array;
}
