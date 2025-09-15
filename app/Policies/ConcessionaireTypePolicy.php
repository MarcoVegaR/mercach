<?php

declare(strict_types=1);

namespace App\Policies;

class ConcessionaireTypePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.concessionaire-type';
    }
}
