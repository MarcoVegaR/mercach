<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Database\Eloquent\Model;

interface ChargeServiceInterface extends ServiceInterface
{
    /** @return array<string, mixed> */
    public function toItem(Model $model): array;

    /**
     * Cancel a charge by marking it as CANCELED when allowed by business rules.
     *
     * @return array<string, mixed>
     */
    public function cancel(int|string $chargeId, ?string $note = null): array;

    /**
     * Create an extra/manual charge (e.g., fine or adjustment) anchored to a LOCAL.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function createExtra(array $attributes): array;
}
