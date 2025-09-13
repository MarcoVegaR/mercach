<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class SetRoleActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? $this->user()?->can('setActive', $role) === true
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        /** @var Role|null $role */
        $role = $this->route('role');
        if (! $role instanceof Role) {
            return;
        }

        $desiredActive = (bool) $this->boolean('active');

        $validator->after(function ($validator) use ($role, $desiredActive) {
            // If deactivating, apply business constraints
            if ($desiredActive === false) {
                // 1) Block deactivation of protected roles
                $protected = (array) config('permissions.roles.protected', []);
                $blockProtected = (bool) config('permissions.roles.activation.block_deactivate_protected', true);
                if ($blockProtected && in_array($role->name, $protected, true)) {
                    session()->flash('error', 'No se puede desactivar un rol protegido del sistema.');
                    $validator->errors()->add('role', 'No se puede desactivar un rol protegido del sistema.');

                    return;
                }

                // 2) Optionally block deactivation if role has assigned users
                $blockIfHasUsers = (bool) config('permissions.roles.activation.block_deactivate_if_has_users', false);
                if ($blockIfHasUsers) {
                    $hasUsers = DB::table('model_has_roles')
                        ->where('role_id', $role->id)
                        ->where('model_type', \App\Models\User::class)
                        ->exists();

                    if ($hasUsers) {
                        session()->flash('error', 'No se puede desactivar un rol que tiene usuarios asignados.');
                        $validator->errors()->add('role', 'No se puede desactivar un rol que tiene usuarios asignados.');

                        return;
                    }
                }
            }
        });
    }
}
