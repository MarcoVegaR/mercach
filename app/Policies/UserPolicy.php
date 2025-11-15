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
        // Generic ability: user can view at least one type of dashboard chart.
        // Fine-grained access (contracts, locals, concessionaires, finance, etc.)
        // is enforced by route-level permission middleware.

        return $user->can('dashboard.view.charts')
            || $user->can('dashboard.view.charts.contracts')
            || $user->can('dashboard.view.charts.locals')
            || $user->can('dashboard.view.charts.concessionaires')
            || $user->can('dashboard.view.charts.debt')
            || $user->can('dashboard.view.charts.payments')
            || $user->can('dashboard.view.finance');
    }
}
