<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Policy for Role model authorization.
 *
 * This policy demonstrates how to extend BaseResourcePolicy for specific resources.
 * It uses the 'roles' prefix for all permission checks, mapping abilities to
 * permissions like 'roles.view', 'roles.create', etc.
 *
 * @author Laravel Boilerplate
 */
class RolePolicy extends BaseResourcePolicy
{
    /**
     * Get the ability prefix for roles resource.
     *
     * @return string The permission prefix for roles
     */
    protected function abilityPrefix(): string
    {
        return 'roles';
    }

    /**
     * Example of contextual override: additional business logic for delete.
     *
     * You can override any method from BaseResourcePolicy to add
     * contextual rules beyond simple permission checking.
     *
     * @param  User  $user  The user to authorize
     * @param  mixed  $model  The role being deleted (Role instance)
     * @return bool True if authorized, false otherwise
     */
    public function delete(User $user, $model): bool
    {
        // First check base permission
        if (! parent::delete($user, $model)) {
            return false;
        }

        // Additional business rule: cannot delete role with users assigned
        // This is an example - uncomment if needed:
        // return $model->users()->count() === 0;

        return true;
    }
}
