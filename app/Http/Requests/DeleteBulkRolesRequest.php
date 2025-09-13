<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeleteBulkRolesRequest extends FormRequest
{
    /** @var array<int,string> */
    protected array $skipped = [];

    public function authorize(): bool
    {
        return $this->user()?->can('bulk', [Role::class, 'delete']) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:delete'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:roles,id'],
            'force' => ['sometimes', 'boolean'],
        ];
    }

    public function force(): bool
    {
        $value = $this->input('force', false);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Retrieve Role models for given ids.
     *
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        $ids = (array) $this->input('ids', []);
        if (empty($ids)) {
            return collect();
        }

        return Role::whereIn('id', $ids)->get();
    }

    /**
     * Partition roles into deletable and skipped (with reasons) based on validations.
     *
     * @return array{deletable: Collection<int, Role>, skipped: array<int,string>}
     */
    public function getDeletableRolesAndSkipped(): array
    {
        $this->skipped = [];

        $protected = (array) config('permissions.roles.protected', []);
        $requireInactive = (bool) data_get(config('permissions.roles'), 'deletion.require_inactive', false);
        $blockIfHasPerms = (bool) data_get(config('permissions.roles'), 'deletion.block_if_has_permissions', false);
        $criticalPermissions = (array) data_get(config('permissions.roles'), 'deletion.critical_permissions', []);

        $force = $this->force();
        $roles = $this->roles();

        $deletable = collect();

        foreach ($roles as $role) {
            // Protected by name
            if (in_array($role->name, $protected, true)) {
                $this->skipped[$role->id] = 'Rol protegido';

                continue;
            }

            // Has users via pivot
            $hasUsers = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', \App\Models\User::class)
                ->exists();

            if ($hasUsers) {
                $this->skipped[$role->id] = 'Tiene usuarios asignados';

                continue;
            }

            // Require inactive
            if ($requireInactive) {
                $isActive = (bool) DB::table('roles')->where('id', $role->id)->value('is_active');
                if ($isActive === true) {
                    $this->skipped[$role->id] = 'Debe desactivarse antes de eliminar';

                    continue;
                }
            }

            // Block if role has any permissions unless force
            if ($blockIfHasPerms && ! $force) {
                $hasPermissions = DB::table('role_has_permissions')
                    ->where('role_id', $role->id)
                    ->exists();

                if ($hasPermissions) {
                    $this->skipped[$role->id] = 'Tiene permisos; use force=true';

                    continue;
                }
            }

            $deletable->push($role);
        }

        // Last admin role protection (ensure at least one admin role remains)
        if (! empty($criticalPermissions)) {
            $permissionIds = DB::table('permissions')
                ->whereIn('name', $criticalPermissions)
                ->pluck('id')
                ->all();

            if (! empty($permissionIds)) {
                $adminRoleIds = DB::table('role_has_permissions as rhp')
                    ->select('rhp.role_id')
                    ->whereIn('rhp.permission_id', $permissionIds)
                    ->groupBy('rhp.role_id')
                    ->havingRaw('COUNT(DISTINCT rhp.permission_id) = ?', [count($permissionIds)])
                    ->pluck('rhp.role_id')
                    ->all();

                if (! empty($adminRoleIds)) {
                    $deletableAdminIds = $deletable->pluck('id')->intersect($adminRoleIds)->values();
                    $remainingAfter = count($adminRoleIds) - $deletableAdminIds->count();

                    if ($remainingAfter === 0 && $deletableAdminIds->isNotEmpty()) {
                        // Skip one admin role to ensure at least one remains
                        $skipId = $deletableAdminIds->first();
                        $deletable = $deletable->reject(fn (Role $r) => $r->id === $skipId)->values();
                        $this->skipped[$skipId] = 'Último rol administrador no puede eliminarse';
                    }
                }
            }
        }

        return [
            'deletable' => $deletable,
            'skipped' => $this->skipped,
        ];
    }

    /**
     * Get skipped reasons map id => reason.
     *
     * @return array<int,string>
     */
    public function skippedReasons(): array
    {
        return $this->skipped;
    }
}
