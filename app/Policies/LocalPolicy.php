<?php

declare(strict_types=1);

namespace App\Policies;

class LocalPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.local';
    }
}
