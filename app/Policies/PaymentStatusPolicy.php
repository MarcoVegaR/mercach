<?php

declare(strict_types=1);

namespace App\Policies;

class PaymentStatusPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.payment-status';
    }
}
