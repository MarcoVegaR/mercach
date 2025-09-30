<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ConcessionaireRepositoryInterface;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Builder;

class ConcessionaireRepository extends BaseRepository implements ConcessionaireRepositoryInterface
{
    protected string $modelClass = \App\Models\Concessionaire::class;

    /**
     * Campos buscables por búsqueda global (LOWER LIKE).
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        return [
            'full_name',
            'email',
            'document_number',
        ];
    }

    /**
     * Campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'full_name', 'email', 'document_number', 'is_active', 'created_at'];
    }

    /**
     * Nombre de la columna de estado activo.
     */
    protected function activeColumn(): string
    {
        return 'is_active';
    }

    /**
     * Ordenamiento por defecto: nombre ascendente.
     *
     * @return array{string, string}
     */
    protected function defaultSort(): array
    {
        return ['full_name', 'asc'];
    }

    /**
     * Mapa de filtros específicos del recurso.
     *
     * @return array<string, callable(Builder<\Illuminate\Database\Eloquent\Model>, mixed): void>
     */
    protected function filterMap(): array
    {
        return [
            'concessionaire_type_id' => static function (Builder $b, $v): void {
                $b->where('concessionaire_type_id', (int) $v);
            },
            'is_active' => static function (Builder $b, $v): void {
                $b->where('is_active', (bool) $v);
            },
            'has_active_contract' => static function (Builder $b, $v): void {
                $flag = filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($flag === null) {
                    return; // ignore invalid values
                }
                $today = Carbon::now()->startOfDay()->toDateString();
                if ($flag) {
                    $b->whereExists(function ($q) use ($today): void {
                        $q->from('concessionaire_contract as cc')
                            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                            ->whereColumn('cc.concessionaire_id', 'concessionaires.id')
                            ->where('cs.code', '=', 'VIG')
                            ->where('c.start_date', '<=', $today)
                            ->where(function ($x) use ($today): void {
                                $x->whereNull('c.end_date')->orWhere('c.end_date', '>=', $today);
                            })
                            ->whereNull('c.deleted_at');
                    });
                } else {
                    $b->whereNotExists(function ($q) use ($today): void {
                        $q->from('concessionaire_contract as cc')
                            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                            ->whereColumn('cc.concessionaire_id', 'concessionaires.id')
                            ->where('cs.code', '=', 'VIG')
                            ->where('c.start_date', '<=', $today)
                            ->where(function ($x) use ($today): void {
                                $x->whereNull('c.end_date')->orWhere('c.end_date', '>=', $today);
                            })
                            ->whereNull('c.deleted_at');
                    });
                }
            },
        ];
    }

    /**
     * Eager-load relations needed for listing and export to avoid N+1 and
     * provide friendly names in the service toRow() mapping.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function withRelations(Builder $builder): Builder
    {
        return $builder->with([
            'concessionaireType:id,name',
            'documentType:id,code,name',
        ]);
    }
}
