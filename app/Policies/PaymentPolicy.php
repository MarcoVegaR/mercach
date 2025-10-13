<?php

declare(strict_types=1);

namespace App\Policies;

class PaymentPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.payment';
    }
}
