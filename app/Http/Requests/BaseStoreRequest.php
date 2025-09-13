<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Base FormRequest para operaciones de creación (store).
 *
 * Proporciona normalización automática de datos comunes y hooks
 * extensibles para los FormRequests concretos.
 */
abstract class BaseStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * La autorización real se maneja en el controlador con Policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Prepare the data for validation.
     *
     * Normaliza datos comunes:
     * - Trim strings
     * - Cast booleans
     * - Normaliza arrays vacíos
     * - Convierte strings vacíos a null
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();

        // Normalizar datos
        $normalized = $this->normalizeData($data);

        // Permitir que las subclases añadan su propia preparación
        $this->additionalPreparation($normalized);

        $this->merge($normalized);
    }

    /**
     * Hook para preparación adicional en subclases.
     *
     * @param  array<string, mixed>  &$data
     */
    protected function additionalPreparation(array &$data): void
    {
        // Override en subclases si es necesario
    }

    /**
     * Normaliza recursivamente los datos del request.
     */
    protected function normalizeData(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([$this, 'normalizeData'], $data);
        }

        if (is_string($data)) {
            // Trim whitespace
            $trimmed = trim($data);

            // Convertir strings vacíos a null
            if ($trimmed === '') {
                return null;
            }

            // Normalizar booleanos como strings
            if (in_array(strtolower($trimmed), ['true', '1', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array(strtolower($trimmed), ['false', '0', 'no', 'off'], true)) {
                return false;
            }

            return $trimmed;
        }

        return $data;
    }

    /**
     * Explode una lista separada por comas en array.
     *
     * @return array<string>
     */
    protected function explodeList(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    /**
     * Convierte un valor a entero array si es string con IDs.
     * Ej: "1,2,3" => [1, 2, 3]
     *
     * @return array<int>
     */
    protected function toIntArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('intval', array_filter($value, 'is_numeric'));
        }

        if (is_string($value)) {
            $parts = $this->explodeList($value);

            return array_map('intval', array_filter($parts, 'is_numeric'));
        }

        return [];
    }

    /**
     * Sanitiza un slug.
     */
    protected function sanitizeSlug(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Str::slug($value);
    }
}
