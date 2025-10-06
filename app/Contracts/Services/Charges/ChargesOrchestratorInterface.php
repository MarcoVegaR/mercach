<?php

declare(strict_types=1);

namespace App\Contracts\Services\Charges;

interface ChargesOrchestratorInterface
{
    /**
     * Run charge generation for a given type.
     *
     * @param  string  $type  RENT_EUR_M2 | RENT_EUR_FIXED | CONDO_USD
     * @param  array<string, mixed>  $params
     * @return array{
     *     generated:int,
     *     upserted:int,
     *     skipped:int,
     *     errors:list<string>,
     *     totalMinor?: int,
     *     unitMinor?: int
     * }
     */
    public function run(string $type, array $params): array;
}
