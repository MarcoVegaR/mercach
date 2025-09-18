<?php

declare(strict_types=1);

namespace App\Policies;

class MarketPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.market';
    }
}
