<?php

declare(strict_types=1);

namespace App\Policies;

class PaymentPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.payment';
    }

    public function void(\App\Models\User $user, mixed $model): bool
    {
        return $this->can($user, 'void');
    }
}
