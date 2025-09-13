<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class SetUserActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? $this->user()?->can('setActive', $user) === true
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
        /** @var User|null $target */
        $target = $this->route('user');
        if (! $target instanceof User) {
            return;
        }

        $desiredActive = (bool) $this->boolean('active');

        $validator->after(function ($validator) use ($target, $desiredActive) {
            // If deactivating, apply business constraints
            if ($desiredActive === false) {
                // 1) Block self-deactivation if configured
                $blockSelf = (bool) config('permissions.users.activation.block_self_deactivate', true);
                if ($blockSelf && $this->user()?->id === $target->id) {
                    session()->flash('error', 'No puedes desactivar tu propio usuario.');
                    $validator->errors()->add('user', 'No puedes desactivar tu propio usuario.');

                    return;
                }

                // 2) Optionally block deactivation of users with critical role when would leave no admins
                $blockIfLastAdmin = (bool) config('permissions.users.activation.block_deactivate_if_last_admin', false);
                $adminRole = (string) config('permissions.users.activation.admin_role_name', 'admin');
                if ($blockIfLastAdmin && $target->hasRole($adminRole)) {
                    $roleId = DB::table('roles')->where('name', $adminRole)->value('id');
                    if ($roleId) {
                        $others = DB::table('model_has_roles')
                            ->where('role_id', $roleId)
                            ->where('model_type', \App\Models\User::class)
                            ->where('model_id', '!=', $target->id)
                            ->count();
                        if ($others === 0) {
                            session()->flash('error', 'No se puede desactivar al último administrador.');
                            $validator->errors()->add('user', 'No se puede desactivar al último administrador.');

                            return;
                        }
                    }
                }
            }
        });
    }
}
