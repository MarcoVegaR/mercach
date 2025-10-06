<?php

declare(strict_types=1);

namespace App\Policies;

class ChargePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'charges';
    }
}
