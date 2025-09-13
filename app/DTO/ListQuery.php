<?php

declare(strict_types=1);

namespace App\DTO;

use Illuminate\Http\Request;

/**
 * DTO para consultas de listado con paginación, búsqueda, filtros y ordenamiento.
 *
 * Este DTO normaliza los parámetros de consulta de una Request para su uso
 * en repositorios. La validación de los parámetros debe realizarse en la
 * FormRequest correspondiente, no aquí.
 */
readonly class ListQuery
{
    /**
     * @param  array<string, mixed>|null  $filters
     */
    public function __construct(
        public ?string $q = null,
        public int $page = 1,
        public int $perPage = 15,
        public ?string $sort = null,
        public ?string $dir = 'desc',
        public ?array $filters = null
    ) {}

    /**
     * Crea una instancia de ListQuery desde una Request HTTP.
     *
     * Normaliza los tipos de datos y procesa filtros complejos como:
     * - Filtros simples: filters[clave]=valor
     * - Rangos: filters[created_between][from], filters[created_between][to]
     * - Arrays: filters[ids][]=1&filters[ids][]=2
     * - Booleanos: filters[active]=true/false
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            q: $request->input('q'),
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('per_page', 15),
            sort: $request->input('sort'),
            dir: self::normalizeDirection($request->input('dir', 'desc')),
            filters: self::normalizeFilters($request->input('filters', [])),
        );
    }

    /**
     * Normaliza la dirección de ordenamiento.
     */
    private static function normalizeDirection(?string $dir): string
    {
        return in_array(strtolower($dir ?? ''), ['asc', 'desc'])
            ? strtolower($dir)
            : 'desc';
    }

    /**
     * Normaliza los filtros de la request procesando diferentes tipos:
     * - Valores escalares
     * - Arrays (para filtros IN)
     * - Rangos (between con from/to)
     * - Booleanos (true/false/1/0)
     */
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $key => $value) {
            if (is_null($value) || $value === '') {
                continue;
            }

            // Procesar rangos (ej: created_between[from], created_between[to])
            if (is_array($value) && (isset($value['from']) || isset($value['to']))) {
                $range = [];
                if (! empty($value['from'])) {
                    $range['from'] = $value['from'];
                }
                if (! empty($value['to'])) {
                    $range['to'] = $value['to'];
                }
                if (! empty($range)) {
                    $normalized[$key] = $range;
                }

                continue;
            }

            // Procesar arrays (ej: ids[]=1&ids[]=2)
            if (is_array($value)) {
                $filtered = array_filter($value, fn ($v) => $v !== null && $v !== '');
                if (! empty($filtered)) {
                    $normalized[$key] = array_values($filtered);
                }

                continue;
            }

            // Procesar booleanos
            if (in_array(strtolower($value), ['true', 'false', '1', '0'])) {
                $normalized[$key] = in_array(strtolower($value), ['true', '1']);

                continue;
            }

            // Valor escalar
            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
