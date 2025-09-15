<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\BankRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class BankRepository extends BaseRepository implements BankRepositoryInterface
{
    protected string $modelClass = \App\Models\Bank::class;

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
        ];
    }
}
