<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DeleteRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? $this->user()?->can('delete', $role) === true
            : false;
    }

    /**
     * No input rules for single delete; logical validations happen in withValidator.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $role = $this->route('role');

        if ($role instanceof Role) {
            $validator->after(function ($validator) use ($role) {
                // 1) Block deletion for roles marked as protected via config
                $protected = (array) config('permissions.roles.protected', []);
                if (in_array($role->name, $protected, true)) {
                    session()->flash('error', 'No se puede eliminar un rol protegido del sistema.');
                    $validator->errors()->add('role', 'No se puede eliminar un rol protegido del sistema.');

                    return; // stop further validations; already blocked
                }

                $hasUsers = DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('model_type', \App\Models\User::class)
                    ->exists();

                if ($hasUsers) {
                    // Keep current UX: set an error flash and a validation error
                    session()->flash('error', 'No se puede eliminar un rol que tiene usuarios asignados.');
                    $validator->errors()->add('role', 'No se puede eliminar un rol que tiene usuarios asignados.');

                    return;
                }

                // 1.b) Optional: require role to be inactive before delete
                $requireInactive = (bool) data_get(config('permissions.roles'), 'deletion.require_inactive', false);
                if ($requireInactive) {
                    $isActive = (bool) DB::table('roles')->where('id', $role->id)->value('is_active');
                    if ($isActive === true) {
                        session()->flash('error', 'Debe desactivar el rol antes de eliminarlo.');
                        $validator->errors()->add('role', 'Debe desactivar el rol antes de eliminarlo.');

                        return;
                    }
                }

                // 2) Optional safeguard: block when role has permissions unless 'force' flag
                $blockIfHasPerms = (bool) data_get(config('permissions.roles'), 'deletion.block_if_has_permissions', false);
                if ($blockIfHasPerms) {
                    $hasPermissions = DB::table('role_has_permissions')
                        ->where('role_id', $role->id)
                        ->exists();

                    $force = filter_var($this->input('force', false), FILTER_VALIDATE_BOOLEAN);
                    if ($hasPermissions && ! $force) {
                        session()->flash('warning', 'El rol tiene permisos asignados. Confirme eliminación forzada (force=true).');
                        $validator->errors()->add('role', 'El rol tiene permisos asignados. Confirme eliminación forzada (force=true).');
                    }
                }

                // 3) Prevent deleting the last admin role that grants all critical permissions
                $criticalPermissions = (array) data_get(config('permissions.roles'), 'deletion.critical_permissions', []);
                if (! empty($criticalPermissions)) {
                    $permissionIds = DB::table('permissions')
                        ->whereIn('name', $criticalPermissions)
                        ->pluck('id')
                        ->all();

                    if (! empty($permissionIds)) {
                        $rolePermCount = DB::table('role_has_permissions')
                            ->where('role_id', $role->id)
                            ->whereIn('permission_id', $permissionIds)
                            ->distinct('permission_id')
                            ->count('permission_id');

                        if ($rolePermCount === count($permissionIds)) {
                            // Count other roles (excluding this one) that also have all critical permissions
                            $otherAdmins = DB::table('role_has_permissions as rhp')
                                ->select('rhp.role_id')
                                ->whereIn('rhp.permission_id', $permissionIds)
                                ->where('rhp.role_id', '!=', $role->id)
                                ->groupBy('rhp.role_id')
                                ->havingRaw('COUNT(DISTINCT rhp.permission_id) = ?', [count($permissionIds)])
                                ->count();

                            if ($otherAdmins === 0) {
                                session()->flash('error', 'No se puede eliminar el último rol administrador del sistema.');
                                $validator->errors()->add('role', 'No se puede eliminar el último rol administrador del sistema.');

                                return;
                            }
                        }
                    }
                }
            });
        }
    }
}
