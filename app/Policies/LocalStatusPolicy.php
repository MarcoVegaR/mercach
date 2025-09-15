<?php

declare(strict_types=1);

namespace App\Policies;

class LocalStatusPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.local-status';
    }
}
