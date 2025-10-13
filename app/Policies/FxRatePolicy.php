<?php

declare(strict_types=1);

namespace App\Policies;

class FxRatePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.fx-rate';
    }
}
