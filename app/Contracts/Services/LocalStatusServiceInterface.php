<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface LocalStatusServiceInterface extends ServiceInterface
{
    /**
     * Extra data for index view (e.g., stats).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array;

    /**
     * Load dynamic data for show page based on query parameters.
     *
     * @param  array<string>  $with
     * @param  array<string>  $withCount
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function loadShowData(\Illuminate\Database\Eloquent\Model $model, array $with = [], array $withCount = []): array;
}
