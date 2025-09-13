<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Policy for User model authorization.
 */
class UserPolicy extends BaseResourcePolicy
{
    /**
     * Permission prefix for users resource.
     */
    protected function abilityPrefix(): string
    {
        return 'users';
    }
}
