<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\LocalRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class LocalRepository extends BaseRepository implements LocalRepositoryInterface
{
    protected string $modelClass = \App\Models\Local::class;

    /**
     * Campos buscables por búsqueda global (LOWER LIKE).
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        return [
            'code',
            'name',
        ];
    }

    /**
     * Campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'code', 'name', 'is_active', 'sort_order', 'created_at'];
    }

    /**
     * Default sort for Locals: code ascending
     *
     * @return array{string,string}
     */
    protected function defaultSort(): array
    {
        return ['code', 'asc'];
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
     * @return array<string, callable(\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>, mixed): void>
     */
    protected function filterMap(): array
    {
        return [
            'is_active' => function (Builder $b, $v): void {
                $b->where('is_active', (bool) $v);
            },
            'created_between' => function (Builder $b, $v): void {
                if (isset($v['from'])) {
                    $b->whereDate('created_at', '>=', $v['from']);
                }
                if (isset($v['to'])) {
                    $b->whereDate('created_at', '<=', $v['to']);
                }
            },
            'code_like' => function (Builder $b, $v): void {
                $b->whereRaw('LOWER(code) LIKE ?', ['%'.strtolower((string) $v).'%']);
            },
            'name_like' => function (Builder $b, $v): void {
                $b->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower((string) $v).'%']);
            },
            // FK filters
            'market_id' => function (Builder $b, $v): void {
                $b->where('market_id', (int) $v);
            },
            'local_type_id' => function (Builder $b, $v): void {
                $b->where('local_type_id', (int) $v);
            },
            'local_status_id' => function (Builder $b, $v): void {
                $b->where('local_status_id', (int) $v);
            },
            'local_location_id' => function (Builder $b, $v): void {
                $b->where('local_location_id', (int) $v);
            },
            // Numeric range
            'area_m2_between' => function (Builder $b, $v): void {
                if (isset($v['from'])) {
                    $b->where('area_m2', '>=', (float) $v['from']);
                }
                if (isset($v['to'])) {
                    $b->where('area_m2', '<=', (float) $v['to']);
                }
            },
        ];
    }

    /**
     * Cargar relaciones necesarias por defecto para evitar N+1 en list/export.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function withRelations(Builder $builder): Builder
    {
        return $builder->with([
            'market:id,name',
            'localType:id,name',
            'localStatus:id,name',
            'localLocation:id,name',
        ]);
    }
}
