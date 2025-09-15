<?php

declare(strict_types=1);

namespace App\Policies;

class PhoneAreaCodePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.phone-area-code';
    }
}
