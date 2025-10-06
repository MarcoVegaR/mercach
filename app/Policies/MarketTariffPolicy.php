<?php

declare(strict_types=1);

namespace App\Policies;

class MarketTariffPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.market-tariff';
    }
}
