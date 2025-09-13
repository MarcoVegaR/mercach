<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Role;

/**
 * Service interface for Role operations.
 *
 * @author Laravel Boilerplate
 */
interface RoleServiceInterface extends ServiceInterface
{
    /**
     * Get extra data for roles index view (stats and available permissions).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array;

    /**
     * Delete a role safely avoiding guard-related crashes.
     */
    public function deleteSafely(Role $role): void;
}
