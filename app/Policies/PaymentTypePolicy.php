<?php

declare(strict_types=1);

namespace App\Policies;

class PaymentTypePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.payment-type';
    }
}
