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
     * Campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'number', 'start_date', 'end_date', 'created_at'];
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
        return ['start_date', 'desc'];
    }
}
