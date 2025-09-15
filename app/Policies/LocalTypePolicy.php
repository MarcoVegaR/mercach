<?php

declare(strict_types=1);

namespace App\Policies;

class LocalTypePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.local-type';
    }
}
