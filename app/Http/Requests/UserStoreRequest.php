<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

/**
 * Request validation for User creation.
 */
class UserStoreRequest extends BaseStoreRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // Strong password: min 8, letters with mixed case, numbers and symbols
            'password' => ['required', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'roles_ids' => ['nullable', 'array'],
            'roles_ids.*' => ['integer', 'exists:roles,id'],
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

        // Ensure is_active is boolean (default true)
        if (isset($data['is_active'])) {
            $data['is_active'] = (bool) $data['is_active'];
        } else {
            $data['is_active'] = true;
        }
    }
}
