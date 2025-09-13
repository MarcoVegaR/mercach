<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest base para endpoints de índice/listado con soporte completo
 * para paginación, búsqueda, filtros y ordenamiento.
 *
 * Este FormRequest se integra con el sistema BaseRepository/BaseService
 * a través del DTO ListQuery, proporcionando validación y normalización
 * consistente para todos los endpoints de Index.
 *
 * Campos soportados:
 * - q: Término de búsqueda global
 * - page: Página actual (≥1)
 * - perPage: Elementos por página (entre 1 y maxPerPage())
 * - sort: Campo de ordenamiento (debe estar en allowedSorts())
 * - dir: Dirección de ordenamiento ('asc' o 'desc')
 * - filters: Filtros anidados (estructura flexible por módulo)
 *
 * Integración con frontend:
 * - Compatible con Inertia.js partial reloads (only: ['rows', 'meta'])
 * - Optimizado para TanStack Table v8 server-side pagination/sorting
 *
 * @see \App\DTO\ListQuery Para la estructura del DTO resultante
 * @see \App\Repositories\BaseRepository Para el uso en repositorios
 */
abstract class BaseIndexRequest extends FormRequest
{
    /**
     * Determine si el usuario está autorizado para realizar esta request.
     *
     * La autorización real debe implementarse en Policies o en el Controller.
     * El FormRequest solo maneja validación y normalización.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para los parámetros de índice.
     *
     * Combina las reglas base con las reglas específicas del módulo
     * definidas en filterRules().
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            // Búsqueda global
            'q' => ['nullable', 'string', 'max:255'],

            // Paginación
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$this->maxPerPage()],

            // Ordenamiento
            'sort' => ['nullable', 'string', Rule::in($this->allowedSorts())],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],

            // Filtros (estructura base)
            'filters' => ['nullable', 'array'],
        ];

        // Merge con reglas específicas del módulo para filtros anidados
        return array_merge($rules, $this->filterRules());
    }

    /**
     * Prepara los datos para validación.
     *
     * Aplica normalizaciones que deben ocurrir antes de la validación
     * para casos como direcciones case-insensitive y booleanos.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // Normalizar dirección de ordenamiento antes de validación
        if ($this->has('dir')) {
            $data['dir'] = strtolower($this->input('dir'));
        }

        // Normalizar booleanos en filtros antes de validación
        $filters = $this->input('filters');
        if ($this->has('filters') && is_array($filters)) {
            $data['filters'] = $this->normalizeBooleans($filters);
        }

        if (! empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * Normaliza los datos después de la validación.
     *
     * Aplica transformaciones necesarias para garantizar tipos correctos
     * y valores por defecto consistentes.
     */
    protected function passedValidation(): void
    {
        $validated = $this->validated();

        // Aplicar per_page por defecto si no se especifica
        if (! isset($validated['per_page']) || $validated['per_page'] == null) {
            $validated['per_page'] = $this->defaultPerPage();
        }

        // Limitar per_page a maxPerPage como defensa extra
        if ($validated['per_page'] > $this->maxPerPage()) {
            $validated['per_page'] = $this->maxPerPage();
        }

        // Asegurar que filters sea array
        if (! isset($validated['filters']) || ! is_array($validated['filters'])) {
            $validated['filters'] = [];
        }

        // Validar y corregir rangos between si es necesario
        $validated['filters'] = $this->normalizeRanges($validated['filters']);

        // Hook para normalizaciones adicionales específicas del módulo
        $validated = $this->sanitize($validated);

        // Reemplazar los datos validados con los normalizados
        $this->replace($validated);
    }

    /**
     * Convierte la request validada y normalizada en un DTO ListQuery.
     *
     * Este método es el punto de integración principal con el sistema
     * BaseRepository/BaseService.
     */
    public function toListQuery(): ListQuery
    {
        return new ListQuery(
            q: $this->input('q'),
            page: (int) $this->input('page', 1),
            perPage: (int) $this->input('per_page', $this->defaultPerPage()),
            sort: $this->input('sort'),
            dir: $this->input('dir', 'desc'),
            filters: (array) $this->input('filters', [])
        );
    }

    /**
     * Define los campos permitidos para ordenamiento.
     *
     * Debe ser sobrescrito por cada FormRequest específico del módulo
     * para definir qué columnas son ordenables de forma segura.
     *
     * Ejemplo:
     * ```php
     * protected function allowedSorts(): array
     * {
     *     return ['id', 'name', 'email', 'created_at', 'updated_at'];
     * }
     * ```
     *
     * @return array<string> Lista de campos ordenables
     */
    abstract protected function allowedSorts(): array;

    /**
     * Define reglas de validación específicas para filtros del módulo.
     *
     * Las reglas deben seguir el patrón 'filters.{key}' para filtros anidados.
     *
     * Ejemplo:
     * ```php
     * protected function filterRules(): array
     * {
     *     return [
     *         'filters.status' => ['nullable', 'string', 'in:active,inactive'],
     *         'filters.created_between' => ['nullable', 'array'],
     *         'filters.created_between.from' => ['nullable', 'date'],
     *         'filters.created_between.to' => ['nullable', 'date', 'after_or_equal:filters.created_between.from'],
     *         'filters.ids' => ['nullable', 'array'],
     *         'filters.ids.*' => ['integer', 'exists:users,id'],
     *         'filters.is_verified' => ['nullable', 'boolean'],
     *     ];
     * }
     * ```
     *
     * @return array<string, array> Reglas de validación para filtros
     */
    /**
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [];
    }

    /**
     * Número máximo de elementos por página permitidos.
     *
     * Puede ser sobrescrito por módulos que necesiten límites diferentes
     * según el tipo de datos o consideraciones de performance.
     */
    protected function maxPerPage(): int
    {
        return 100;
    }

    /**
     * Número de elementos por página por defecto.
     *
     * Puede ser sobrescrito por módulos que necesiten paginación diferente
     * según el contexto o tipo de contenido.
     */
    protected function defaultPerPage(): int
    {
        return 15;
    }

    /**
     * Hook para aplicar normalizaciones adicionales específicas del módulo.
     *
     * Permite a cada FormRequest específico aplicar transformaciones
     * personalizadas después de la normalización base.
     *
     * @param  array<string, mixed>  $validated  Datos ya validados y normalizados
     * @return array<string, mixed> Datos con normalizaciones adicionales aplicadas
     */
    protected function sanitize(array $validated): array
    {
        return $validated;
    }

    /**
     * Normaliza valores booleanos en filtros.
     *
     * Convierte strings como 'true'/'false', '1'/'0' a booleanos reales.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeBooleans(array $filters): array
    {
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $filters[$key] = $this->normalizeBooleans($value);
            } elseif (is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0'])) {
                $filters[$key] = in_array(strtolower($value), ['true', '1']);
            }
        }

        return $filters;
    }

    /**
     * Normaliza rangos between para garantizar consistencia.
     *
     * Si 'from' > 'to', los intercambia para mantener la lógica correcta.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeRanges(array $filters): array
    {
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                // Procesar rangos anidados recursivamente
                if (isset($value['from']) && isset($value['to'])) {
                    // Si ambos valores son fechas/números y from > to, intercambiar
                    if ($this->shouldSwapRange($value['from'], $value['to'])) {
                        $temp = $value['from'];
                        $value['from'] = $value['to'];
                        $value['to'] = $temp;
                    }
                    $filters[$key] = $value;
                } else {
                    // Procesar filtros anidados recursivamente
                    $filters[$key] = $this->normalizeRanges($value);
                }
            }
        }

        return $filters;
    }

    /**
     * Determina si un rango between debe ser intercambiado.
     */
    private function shouldSwapRange(mixed $from, mixed $to): bool
    {
        // Solo intercambiar si ambos valores son comparables
        if (! is_numeric($from) && ! $this->isDateString($from)) {
            return false;
        }

        if (! is_numeric($to) && ! $this->isDateString($to)) {
            return false;
        }

        // Para fechas
        if ($this->isDateString($from) && $this->isDateString($to)) {
            return strtotime($from) > strtotime($to);
        }

        // Para números
        if (is_numeric($from) && is_numeric($to)) {
            return (float) $from > (float) $to;
        }

        return false;
    }

    /**
     * Verifica si un string parece ser una fecha válida.
     */
    private function isDateString(mixed $value): bool
    {
        return is_string($value) && strtotime($value) !== false;
    }
}
