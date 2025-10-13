<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\FxRateRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class FxRateRepository extends BaseRepository implements FxRateRepositoryInterface
{
    protected string $modelClass = \App\Models\FxRate::class;

    /**
     * Campos buscables por búsqueda global (LOWER LIKE).
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        return [
            'currency_code',
            'source',
        ];
    }

    /**
     * Campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'currency_code', 'rate_date', 'value_date', 'is_active', 'created_at'];
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
     * @return array<string, callable(Builder<\App\Models\FxRate>, mixed): void>
     */
    protected function filterMap(): array
    {
        return [
            'is_active' => function (Builder $b, $v): void {
                $b->where('is_active', (bool) $v);
            },
            'is_official' => function (Builder $b, $v): void {
                $b->where('is_official', (bool) $v);
            },
            'created_between' => function (Builder $b, $v): void {
                if (isset($v['from'])) {
                    $b->whereDate('created_at', '>=', $v['from']);
                }
                if (isset($v['to'])) {
                    $b->whereDate('created_at', '<=', $v['to']);
                }
            },
            'rate_date_between' => function (Builder $b, $v): void {
                if (isset($v['from'])) {
                    $b->whereDate('rate_date', '>=', $v['from']);
                }
                if (isset($v['to'])) {
                    $b->whereDate('rate_date', '<=', $v['to']);
                }
            },
            'value_date_between' => function (Builder $b, $v): void {
                if (isset($v['from'])) {
                    $b->whereDate('value_date', '>=', $v['from']);
                }
                if (isset($v['to'])) {
                    $b->whereDate('value_date', '<=', $v['to']);
                }
            },
            'currency_like' => function (Builder $b, $v): void {
                $b->whereRaw('LOWER(currency_code) LIKE ?', ['%'.strtolower((string) $v).'%']);
            },
            'source_like' => function (Builder $b, $v): void {
                $b->whereRaw('LOWER(source) LIKE ?', ['%'.strtolower((string) $v).'%']);
            },
        ];
    }
}
