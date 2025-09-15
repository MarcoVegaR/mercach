<?php

declare(strict_types=1);

namespace App\Policies;

class BankPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.bank';
    }
}
