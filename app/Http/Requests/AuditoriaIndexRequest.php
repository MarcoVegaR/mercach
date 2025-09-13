<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * FormRequest para el endpoint de índice de auditoría.
 *
 * Proporciona validación y normalización para la consulta de auditorías
 * con soporte para búsqueda, filtros, paginación y ordenamiento.
 */
class AuditoriaIndexRequest extends BaseIndexRequest
{
    /**
     * Campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return [
            'id',
            'created_at',
            'user_id',
            'event',
            'auditable_type',
            'auditable_id',
            'ip_address',
            'url',
        ];
    }

    /**
     * Reglas de validación específicas para filtros de auditoría.
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            // Filtro por usuario
            'filters.user_id' => ['nullable', 'integer'],

            // Filtro por evento
            'filters.event' => ['nullable', 'string', 'max:255'],

            // Filtro por tipo de entidad auditada
            'filters.auditable_type' => ['nullable', 'string', 'max:255'],

            // Filtro por ID de entidad auditada
            'filters.auditable_id' => ['nullable', 'integer'],

            // Filtro por dirección IP
            'filters.ip_address' => ['nullable', 'string', 'max:45'],

            // Filtro por URL
            'filters.url' => ['nullable', 'string', 'max:2048'],

            // Filtro por rango de fechas de creación
            'filters.created_between' => ['nullable', 'array'],
            'filters.created_between.from' => ['nullable', 'date'],
            'filters.created_between.to' => ['nullable', 'date', 'after_or_equal:filters.created_between.from'],

            // Filtro por tags
            'filters.tags' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Número máximo de elementos por página para auditoría.
     */
    protected function maxPerPage(): int
    {
        return 100;
    }

    /**
     * Número de elementos por página por defecto para auditoría.
     */
    protected function defaultPerPage(): int
    {
        return 25;
    }
}
