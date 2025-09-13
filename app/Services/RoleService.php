<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\RoleServiceInterface;
use App\DTO\ListQuery;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Service implementation for Role operations.
 *
 * @author Laravel Boilerplate
 */
class RoleService extends BaseService implements RoleServiceInterface
{
    /**
     * Transform a Role model to array representation.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        /** @var \Spatie\Permission\Models\Role $role */
        $role = $model;
        // Map permissions for UI (keep full list for accurate exports)
        $permissionsArray = $role->permissions->map(function (Model $permission): array {
            return [
                'id' => $permission->getAttribute('id'),
                'name' => (string) $permission->getAttribute('name'),
                'description' => $permission->getAttribute('description'),
            ];
        })->toArray();

        // Build a human-friendly permissions details string for export (prefer description over name)
        $permissionDescriptions = array_map(
            fn (array $p) => (string) ($p['description'] ?? $p['name'] ?? ''),
            $permissionsArray
        );
        $permissionsDetails = implode(', ', array_filter($permissionDescriptions, fn ($v) => $v !== ''));

        // IDs for form preselection (ensure integers)
        $permissionIds = $role->permissions->pluck('id')->map(fn ($v) => (int) $v)->toArray();

        // Fetch up to N user names via pivot to avoid guard-dependent relation
        $usersLimit = 10;
        $userNames = DB::table('model_has_roles as mhr')
            ->join('users', 'users.id', '=', 'mhr.model_id')
            ->where('mhr.role_id', $role->id)
            ->where('mhr.model_type', User::class)
            ->orderBy('users.name')
            ->limit($usersLimit)
            ->pluck('users.name')
            ->toArray();

        $usersCount = (int) ($role->users_count ?? 0);
        $usersDetails = implode(', ', $userNames);
        if ($usersCount > count($userNames)) {
            $usersDetails .= ($usersDetails !== '' ? ' ' : '').'(+'.($usersCount - count($userNames)).' más)';
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $permissionsArray,
            'permissions_ids' => $permissionIds,
            'permissions_count' => $role->permissions_count ?? $role->permissions->count(),
            'users_count' => $usersCount,
            // Extra fields for UI/tooltips
            'users' => $userNames,
            // Export-friendly detailed strings
            'permissions_details' => $permissionsDetails,
            'users_details' => $usersDetails,
            'is_active' => (bool) ($role->getAttribute('is_active') ?? true),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }

    /**
     * Get default columns for export.
     *
     * @return array<string, string> Key-value pairs where key is database field and value is display name
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'name' => 'Nombre',
            'guard_name' => 'Guard',
            'permissions_details' => 'Permisos',
            'users_details' => 'Usuarios',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * Get default filename for export.
     */
    protected function defaultExportFilename(string $format, ListQuery $query): string
    {
        return 'roles_export_'.date('Ymd_His').'.'.$format;
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexExtras(): array
    {
        $stats = [
            'total' => Role::count(),
            // Count roles where is_active = true
            'active' => Role::where('is_active', true)->count(),
            // Count distinct roles with permissions via pivot
            'with_permissions' => DB::table('role_has_permissions')
                ->distinct('role_id')
                ->count('role_id'),
        ];

        $availablePermissions = Permission::select('id', 'name', 'description')
            ->orderBy('name')
            ->get()
            ->map(function (Permission $permission): array {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'description' => $permission->description ?? $permission->name,
                ];
            })
            ->toArray();

        return [
            'stats' => $stats,
            'availablePermissions' => $availablePermissions,
        ];
    }

    /**
     * Hook called after creating a role.
     * Syncs permissions if provided.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function afterCreate(Model $model, array $attributes): void
    {
        if ($model instanceof \App\Models\Role) {
            $newIds = isset($attributes['permissions_ids']) ? (array) $attributes['permissions_ids'] : [];

            // Before: empty on create
            $before = [];
            // Sync and compute after
            $model->syncPermissions($newIds);
            $after = $model->permissions->pluck('id')->map(fn ($v) => (int) $v)->values()->all();

            // Emit custom audit event for permission sync
            /** @var \App\Models\Role $auditable */
            $auditable = $model;
            $auditable->auditEvent = 'permissions_sync';
            $auditable->auditCustomOld = ['permissions_ids' => $before];
            $auditable->auditCustomNew = ['permissions_ids' => $after];
            $auditable->isCustomEvent = true;
            event(new \OwenIt\Auditing\Events\AuditCustom($auditable));
            // reset temp state
            $auditable->auditCustomOld = $auditable->auditCustomNew = [];
            $auditable->isCustomEvent = false;
        }

        // Clear permission cache after changes
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Hook called after updating a role.
     * Syncs permissions if provided.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function afterUpdate(Model $model, array $attributes): void
    {
        if ($model instanceof \App\Models\Role) {
            // Only handle audit when permissions key was part of the payload
            if (array_key_exists('permissions_ids', $attributes)) {
                $before = $model->permissions->pluck('id')->map(fn ($v) => (int) $v)->values()->all();
                $newIds = (array) ($attributes['permissions_ids'] ?? []);
                $model->syncPermissions($newIds);
                $after = $model->permissions->pluck('id')->map(fn ($v) => (int) $v)->values()->all();

                // Emit custom audit event for permission sync
                /** @var \App\Models\Role $auditable */
                $auditable = $model;
                $auditable->auditEvent = 'permissions_sync';
                $auditable->auditCustomOld = ['permissions_ids' => $before];
                $auditable->auditCustomNew = ['permissions_ids' => $after];
                $auditable->isCustomEvent = true;
                event(new \OwenIt\Auditing\Events\AuditCustom($auditable));
                // reset temp state
                $auditable->auditCustomOld = $auditable->auditCustomNew = [];
                $auditable->isCustomEvent = false;
            }
        }

        // Clear permission cache after changes
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteSafely(Role $role): void
    {
        $guard = $role->guard_name;
        $guards = array_keys(config('auth.guards', []));

        // If the guard is configured, try the normal Eloquent delete first
        if (in_array($guard, $guards, true)) {
            try {
                $role->delete();
                // Clear permission cache after changes
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return;
            } catch (\Throwable $e) {
                // Fallback to direct DB deletion below
            }
        }

        // Fallback: manually remove pivot entries then delete the role by query
        DB::transaction(function () use ($role) {
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            DB::table('model_has_roles')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * {@inheritdoc}
     */
    public function bulkSetActiveByIds(array $ids, bool $active): int
    {
        return $this->bulkSetActiveForRoles('id', $ids, $active);
    }

    /**
     * {@inheritdoc}
     */
    public function bulkSetActiveByUuids(array $uuids, bool $active): int
    {
        return $this->bulkSetActiveForRoles('uuid', $uuids, $active);
    }

    /**
     * Apply bulk activation/deactivation for roles identified by a key (id|uuid),
     * enforcing business constraints and updating the correct column 'is_active'.
     *
     * @param  'id'|'uuid'  $key
     * @param  array<int|string>  $values
     */
    protected function bulkSetActiveForRoles(string $key, array $values, bool $active): int
    {
        if (empty($values)) {
            return 0;
        }

        return $this->transaction(function () use ($key, $values, $active): int {
            // Load minimal fields needed to evaluate rules and state changes
            $query = Role::query()->select('id', 'uuid', 'name', 'is_active');
            if ($key === 'id') {
                $query->whereIn('id', $values);
            } else {
                $query->whereIn('uuid', $values);
            }

            /** @var \Illuminate\Support\Collection<int, Role> $roles */
            $roles = $query->get();
            if ($roles->isEmpty()) {
                return 0;
            }

            // Start from roles whose state would actually change
            $candidates = $roles->filter(function (Role $role) use ($active) {
                $current = (bool) ($role->getAttribute('is_active') ?? true);

                return $current !== $active;
            });

            if ($candidates->isEmpty()) {
                return 0;
            }

            // If deactivating, apply constraints (protected roles, roles with users)
            if ($active === false) {
                $protected = (array) config('permissions.roles.protected', []);
                $blockProtected = (bool) data_get(config('permissions.roles'), 'activation.block_deactivate_protected', true);
                if ($blockProtected) {
                    $candidates = $candidates->reject(function (Role $role) use ($protected) {
                        return in_array($role->name, $protected, true);
                    });
                }

                $blockIfHasUsers = (bool) data_get(config('permissions.roles'), 'activation.block_deactivate_if_has_users', false);
                if ($blockIfHasUsers && $candidates->isNotEmpty()) {
                    $candidateIds = $candidates->pluck('id')->all();

                    $hasUsersIds = DB::table('model_has_roles')
                        ->select('role_id')
                        ->whereIn('role_id', $candidateIds)
                        ->where('model_type', User::class)
                        ->distinct()
                        ->pluck('role_id')
                        ->all();

                    if (! empty($hasUsersIds)) {
                        $candidates = $candidates->reject(function (Role $role) use ($hasUsersIds) {
                            return in_array($role->id, $hasUsersIds, true);
                        });
                    }
                }
            }

            $idsToUpdate = $candidates->pluck('id')->all();
            if (empty($idsToUpdate)) {
                return 0;
            }

            // Update the correct activation column for roles
            return DB::table('roles')
                ->whereIn('id', $idsToUpdate)
                ->update(['is_active' => $active]);
        });
    }
}
