<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DeleteUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? $this->user()?->can('delete', $user) === true
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Single delete does not need input rules; logical validations in withValidator
        return [];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        /** @var User|null $target */
        $target = $this->route('user');
        if (! $target instanceof User) {
            return;
        }

        $authId = $this->user()?->id;

        $validator->after(function ($validator) use ($target, $authId) {
            // 1) Block self-delete
            if ($authId !== null && (int) $authId === (int) $target->id) {
                session()->flash('error', 'No puedes eliminar tu propio usuario.');
                $validator->errors()->add('user', 'No puedes eliminar tu propio usuario.');

                return;
            }

            // 2) Optionally require inactive before delete
            $requireInactive = (bool) config('permissions.users.deletion.require_inactive', false);
            if ($requireInactive && (bool) ($target->getAttribute('is_active') ?? true) === true) {
                session()->flash('error', 'Debe desactivar el usuario antes de eliminarlo.');
                $validator->errors()->add('user', 'Debe desactivar el usuario antes de eliminarlo.');

                return;
            }

            // 3) Optionally block deleting last admin
            $blockIfLastAdmin = (bool) config('permissions.users.deletion.block_if_last_admin', false);
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
                        session()->flash('error', 'No se puede eliminar al último administrador del sistema.');
                        $validator->errors()->add('user', 'No se puede eliminar al último administrador del sistema.');

                        return;
                    }
                }
            }
        });
    }
}
