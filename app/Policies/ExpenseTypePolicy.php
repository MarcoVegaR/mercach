<?php

declare(strict_types=1);

namespace App\Policies;

class ExpenseTypePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.expense-type';
    }
}
