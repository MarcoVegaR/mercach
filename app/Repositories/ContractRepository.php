<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ContractRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ContractRepository extends BaseRepository implements ContractRepositoryInterface
{
    protected string $modelClass = \App\Models\Contract::class;

    /**
     * Campos buscables por búsqueda global (LOWER LIKE).
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        return [
            'number',
        ];
    }

    /**
     * Extiende la búsqueda global para incluir código de local asociado (locals.code).
     * Agrupa las condiciones en un único where() para que los filtros posteriores apliquen correctamente.
     */
    protected function applySearch(Builder $builder, string $searchTerm): Builder
    {
        $term = trim($searchTerm);
        if ($term === '') {
            return $builder;
        }

        $lower = strtolower($term);
        $columns = $this->searchable();

        return $builder->where(function (Builder $q) use ($columns, $lower): void {
            // Search in own columns
            foreach ($columns as $column) {
                $q->orWhereRaw('LOWER('.$column.') LIKE ?', ["%{$lower}%"]);
            }
            // Also search by related locals.code
            $q->orWhereHas('locals', function (Builder $qq) use ($lower): void {
                $qq->whereRaw('LOWER(code) LIKE ?', ["%{$lower}%"]);
            });
        });
    }

    /**
     * Campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'number', 'start_date', 'end_date', 'created_at', 'locals_count'];
    }

    /**
     * Nombre de la columna de estado activo.
     */
    protected function activeColumn(): string
    {
        return 'is_active';
    }

    /**
     * Mapa de filtros específicos del recurso.
     *
     * @return array<string, callable(Builder<\App\Models\Contract>, mixed): void>
     */
    protected function filterMap(): array
    {
        return [
            'contract_status_id' => static function (Builder $b, $v): void {
                $b->where('contract_status_id', (int) $v);
            },
            'contract_modality_id' => static function (Builder $b, $v): void {
                $b->where('contract_modality_id', (int) $v);
            },
            'trade_category_id' => static function (Builder $b, $v): void {
                $b->where('trade_category_id', (int) $v);
            },
        ];
    }

    /**
     * Define default sort as start_date desc
     *
     * @return array{string, string}
     */
    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /**
     * Ensure locals_count is available for sorting and UI without N+1.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function withRelations(Builder $builder): Builder
    {
        // Add withCount so 'locals_count' exists when sorting/exporting
        return parent::withRelations($builder)->withCount('locals');
    }
}
