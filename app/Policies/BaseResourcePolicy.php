<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Abstract base policy for resource authorization using Spatie permissions.
 *
 * This class provides a standardized way to map Laravel abilities to Spatie permissions
 * with support for resource-specific prefixes and multi-guard configurations.
 *
 * @author Laravel Boilerplate
 */
abstract class BaseResourcePolicy
{
    /**
     * Get the ability prefix for this resource.
     *
     * This prefix is used to construct permission names in the format: {prefix}.{ability}
     * For example: 'roles.view', 'users.create', 'posts.delete'
     *
     * @return string The permission prefix (e.g., 'roles', 'users', 'posts')
     */
    abstract protected function abilityPrefix(): string;

    /**
     * Get the guard name for permission checking.
     *
     * If null is returned, the default guard will be used.
     * This is useful for multi-guard applications where permissions
     * need to be segmented by guard.
     *
     * @return string|null The guard name or null for default guard
     */
    protected function guardName(): ?string
    {
        return null;
    }

    /**
     * Check if user has the specified ability permission.
     *
     * Constructs the permission name as {abilityPrefix}.{ability} and checks
     * if the user has that permission, respecting guard constraints if specified.
     *
     * @param  User  $user  The user to check permissions for
     * @param  string  $ability  The ability to check (e.g., 'view', 'create')
     * @return bool True if user has the permission, false otherwise
     */
    protected function can(User $user, string $ability): bool
    {
        $permission = $this->abilityPrefix().'.'.$ability;

        // If a specific guard is defined, ensure we're checking permissions
        // within that guard context. Spatie segments permissions by guard.
        if ($this->guardName() !== null) {
            // Note: Spatie automatically uses the guard from the user model,
            // but this serves as documentation that guard segmentation is expected
            return $user->hasPermissionTo($permission);
        }

        return $user->hasPermissionTo($permission);
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  User  $user  The user to authorize
     * @return bool True if authorized, false otherwise
     */
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'view');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user  The user to authorize
     * @param  mixed  $model  The model instance being viewed
     * @return bool True if authorized, false otherwise
     */
    public function view(User $user, $model): bool
    {
        return $this->can($user, 'view');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  User  $user  The user to authorize
     * @return bool True if authorized, false otherwise
     */
    public function create(User $user): bool
    {
        return $this->can($user, 'create');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user  The user to authorize
     * @param  mixed  $model  The model instance being updated
     * @return bool True if authorized, false otherwise
     */
    public function update(User $user, $model): bool
    {
        return $this->can($user, 'update');
    }

    /**
     * Determine whether the user can set active/inactive the model.
     *
     * @param  User  $user  The user to authorize
     * @param  mixed  $model  The model instance being updated for activation state
     * @return bool True if authorized, false otherwise
     */
    public function setActive(User $user, $model): bool
    {
        return $this->can($user, 'setActive');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user  The user to authorize
     * @param  mixed  $model  The model instance being deleted
     * @return bool True if authorized, false otherwise
     */
    public function delete(User $user, $model): bool
    {
        return $this->can($user, 'delete');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user  The user to authorize
     * @param  mixed  $model  The model instance being restored
     * @return bool True if authorized, false otherwise
     */
    public function restore(User $user, $model): bool
    {
        return $this->can($user, 'restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user  The user to authorize
     * @param  mixed  $model  The model instance being force deleted
     * @return bool True if authorized, false otherwise
     */
    public function forceDelete(User $user, $model): bool
    {
        return $this->can($user, 'forceDelete');
    }

    /**
     * Determine whether the user can export models.
     *
     * @param  User  $user  The user to authorize
     * @param  mixed  $model  The model class or instance (optional for export)
     * @return bool True if authorized, false otherwise
     */
    public function export(User $user, $model = null): bool
    {
        return $this->can($user, 'export');
    }

    /**
     * Determine whether the user can perform bulk operations.
     *
     * Note: When authorizing with authorize('bulk', [Model::class, $action]),
     * Laravel uses the Model::class only to resolve the policy and passes
     * the remaining arguments to this method. Therefore, this method receives
     * only the $action argument in addition to the $user.
     *
     * @param  User  $user  The user to authorize
     * @param  string  $action  The bulk action to perform
     * @return bool True if authorized, false otherwise
     */
    public function bulk(User $user, string $action): bool
    {
        return match ($action) {
            'delete' => $this->can($user, 'delete'),
            'restore' => $this->can($user, 'restore'),
            'forceDelete' => $this->can($user, 'forceDelete'),
            'setActive' => $this->can($user, 'setActive'),
            'update' => $this->can($user, 'update'),
            default => false,
        };
    }

    /**
     * Determine whether the user can view selected models.
     *
     * @param  User  $user  The user to authorize
     * @return bool True if authorized, false otherwise
     */
    public function viewSelected(User $user): bool
    {
        return $this->can($user, 'view');
    }
}
