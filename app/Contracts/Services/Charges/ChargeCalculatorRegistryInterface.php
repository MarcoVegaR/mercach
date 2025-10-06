<?php

declare(strict_types=1);

namespace App\Contracts\Services\Charges;

interface ChargeCalculatorRegistryInterface
{
    /** Register a calculator for a type. */
    public function register(string $type, ChargeCalculatorInterface $calculator): void;

    /** Resolve a calculator by type. */
    public function get(string $type): ChargeCalculatorInterface;
}
