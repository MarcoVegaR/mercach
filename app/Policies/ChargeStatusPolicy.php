<?php

declare(strict_types=1);

namespace App\Policies;

class ChargeStatusPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.charge-status';
    }
}
