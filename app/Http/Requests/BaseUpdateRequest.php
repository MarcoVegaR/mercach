<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Base FormRequest para operaciones de actualización (update).
 *
 * Extiende BaseStoreRequest para heredar la normalización de datos
 * y añade soporte para control optimista con _version (updated_at).
 */
abstract class BaseUpdateRequest extends BaseStoreRequest
{
    /**
     * Get additional data to merge for update operations.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Si se solicita todo el array validado, preservar _version si existe
        if ($key === null && $this->has('_version')) {
            $validated['_version'] = $this->input('_version');
        }

        return $validated;
    }

    /**
     * Hook para preparación adicional en updates.
     * Preserva el campo _version para control optimista.
     *
     * @param  array<string, mixed>  &$data
     */
    protected function additionalPreparation(array &$data): void
    {
        parent::additionalPreparation($data);

        // Preservar _version sin modificar si está presente
        if ($this->has('_version') && ! isset($data['_version'])) {
            $data['_version'] = $this->input('_version');
        }
    }
}
