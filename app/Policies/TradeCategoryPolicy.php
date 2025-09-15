<?php

declare(strict_types=1);

namespace App\Policies;

class TradeCategoryPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.trade-category';
    }
}
