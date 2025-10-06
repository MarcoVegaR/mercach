<?php

declare(strict_types=1);

namespace App\Services\Charges;

use App\Contracts\Services\Charges\ChargeCalculatorInterface;
use App\Contracts\Services\Charges\ChargeCalculatorRegistryInterface;

class ChargeCalculatorRegistry implements ChargeCalculatorRegistryInterface
{
    /** @var array<string, ChargeCalculatorInterface> */
    private array $map;

    /**
     * @param  array<string, ChargeCalculatorInterface>  $map
     */
    public function __construct(array $map = [])
    {
        $this->map = $map;
    }

    public function register(string $type, ChargeCalculatorInterface $calculator): void
    {
        $this->map[$type] = $calculator;
    }

    public function get(string $type): ChargeCalculatorInterface
    {
        if (! isset($this->map[$type])) {
            throw new \InvalidArgumentException("Calculator for type '{$type}' not registered");
        }

        return $this->map[$type];
    }
}
