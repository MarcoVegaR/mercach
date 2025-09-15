<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface TradeCategoryServiceInterface extends ServiceInterface
{
    /**
     * Extra data for index view (e.g., stats).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array;
}
