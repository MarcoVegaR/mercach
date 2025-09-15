<?php

declare(strict_types=1);

namespace App\Policies;

class ContractModalityPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.contract-modality';
    }
}
