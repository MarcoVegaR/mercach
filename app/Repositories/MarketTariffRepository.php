<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\MarketTariffRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class MarketTariffRepository extends BaseRepository implements MarketTariffRepositoryInterface
{
    protected string $modelClass = \App\Models\MarketTariff::class;

    /**
     * Campos buscables por búsqueda global (LOWER LIKE).
     * MarketTariff no tiene 'code' ni 'name'; omitimos búsqueda global.
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        return [];
    }

    /**
     * Campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'valid_from', 'is_current', 'is_active', 'created_at'];
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
     * @return array<string, callable(Builder<\App\Models\MarketTariff>, mixed): void>
     */
    protected function filterMap(): array
    {
        return [
            'market_id' => function (Builder $b, $v): void {
                $b->where('market_id', (int) $v);
            },
            'is_current' => function (Builder $b, $v): void {
                $b->where('is_current', (bool) $v);
            },
            'is_active' => function (Builder $b, $v): void {
                $b->where('is_active', (bool) $v);
            },
            'valid_from_between' => function (Builder $b, $v): void {
                if (isset($v['from'])) {
                    $b->whereDate('valid_from', '>=', $v['from']);
                }
                if (isset($v['to'])) {
                    $b->whereDate('valid_from', '<=', $v['to']);
                }
            },
            'created_between' => function (Builder $b, $v): void {
                if (isset($v['from'])) {
                    $b->whereDate('created_at', '>=', $v['from']);
                }
                if (isset($v['to'])) {
                    $b->whereDate('created_at', '<=', $v['to']);
                }
            },
        ];
    }
}
