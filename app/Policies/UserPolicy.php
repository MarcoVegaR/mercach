<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

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

    /**
     * Determine if the user can view dashboard charts.
     */
    public function viewCharts(User $user): bool
    {
        return $user->can('dashboard.view.charts');
    }
}
