<?php

declare(strict_types=1);

namespace App\Policies;

class DebtTransferReasonPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.debt-transfer-reason';
    }
}
