<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Request validation for User update.
 */
class UserUpdateRequest extends BaseUpdateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : $user;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            // Strong password when provided: min 8, letters with mixed case, numbers and symbols
            'password' => ['nullable', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols(), 'confirmed'],
            'password_confirmation' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'roles_ids' => ['nullable', 'array'],
            'roles_ids.*' => ['integer', 'exists:roles,id'],
            '_version' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'email',
            'password' => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
            'is_active' => 'estado activo',
            'roles_ids' => 'roles',
            'roles_ids.*' => 'rol',
            '_version' => 'versión',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function additionalPreparation(array &$data): void
    {
        // Normalize roles_ids to int[] if provided
        if (isset($data['roles_ids'])) {
            $data['roles_ids'] = $this->toIntArray($data['roles_ids']);
        }

        // Ensure is_active is boolean if present
        if (isset($data['is_active'])) {
            $data['is_active'] = (bool) $data['is_active'];
        }
    }

    /**
     * Add custom validation: require users.setActive permission when changing is_active.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        /** @var \App\Models\User|int|string|null $routeUser */
        $routeUser = $this->route('user');
        $userModel = $routeUser instanceof \App\Models\User ? $routeUser : null;

        if (! $userModel) {
            return;
        }

        $payload = $this->all();
        $hasIsActive = array_key_exists('is_active', $payload);

        if ($hasIsActive) {
            $desired = (bool) $this->boolean('is_active');
            $current = (bool) ($userModel->getAttribute('is_active') ?? true);

            // Only enforce if the state will change
            if ($desired !== $current) {
                $actor = $this->user();
                if (! $actor || ! $actor->can('setActive', $userModel)) {
                    $validator->after(function ($v) {
                        $v->errors()->add('is_active', 'No tienes permiso para cambiar el estado activo del usuario.');
                    });
                }
            }
        }
    }
}
