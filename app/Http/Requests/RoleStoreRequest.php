<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Request validation for Role creation.
 *
 * @author Laravel Boilerplate
 */
class RoleStoreRequest extends BaseStoreRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:roles,name',
            ],
            'guard_name' => [
                'required',
                'string',
                'in:web', // Solo permitir 'web' por ahora, ampliar si se necesitan más guards
            ],
            'permissions_ids' => [
                'nullable',
                'array',
            ],
            'permissions_ids.*' => [
                'integer',
                'exists:permissions,id',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
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
            'guard_name' => 'guard',
            'permissions_ids' => 'permisos',
            'permissions_ids.*' => 'permiso',
            'is_active' => 'estado activo',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.unique' => 'Ya existe un rol con este nombre.',
            'name.max' => 'El nombre del rol no puede exceder 100 caracteres.',
            'guard_name.in' => 'El guard seleccionado no es válido.',
            'permissions_ids.*.exists' => 'Uno o más permisos seleccionados no son válidos.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function additionalPreparation(array &$data): void
    {
        // Default guard_name si no se proporciona
        $guard = $data['guard_name'] ?? null;
        if ($guard === null || $guard === '') {
            $data['guard_name'] = 'web';
        }

        // Convertir permissions_ids a array de enteros si viene como string
        if (isset($data['permissions_ids'])) {
            $data['permissions_ids'] = $this->toIntArray($data['permissions_ids']);
        }

        // Asegurar que is_active sea boolean
        if (isset($data['is_active'])) {
            $data['is_active'] = (bool) $data['is_active'];
        } else {
            $data['is_active'] = true; // Por defecto activo
        }
    }
}
