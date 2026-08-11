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
        return ['id', 'full_name', 'email', 'document_number', 'last_life_proof_at', 'is_active', 'created_at', 'active_locals_count'];
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
            'life_proof_status' => static function (Builder $builder, $value): void {
                $cutoff = Carbon::now()->startOfDay()->subYear()->toDateString();

                if ($value === 'current') {
                    $builder->whereNotNull('last_life_proof_at')->where('last_life_proof_at', '>=', $cutoff);
                } elseif ($value === 'requires_citation') {
                    $builder->where(function (Builder $query) use ($cutoff): void {
                        $query->whereNull('last_life_proof_at')->orWhere('last_life_proof_at', '<', $cutoff);
                    });
                } elseif ($value === 'missing') {
                    $builder->whereNull('last_life_proof_at');
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
            'phoneAreaCode:id,code',
        ]);
    }

    /**
     * Apply sorting, supporting computed sort 'active_locals_count'.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applySort(Builder $builder, ?string $sort, ?string $dir): Builder
    {
        $direction = in_array($dir, ['asc', 'desc']) ? $dir : 'desc';

        if ($sort === 'active_locals_count') {
            $today = Carbon::now()->startOfDay()->toDateString();

            // Ensure base columns are selected to avoid ambiguous selects
            $builder->select('concessionaires.*');

            // Compute active locals count per concessionaire
            // Incluye contratos VENCIDOS (VENC) porque continúan generando cargos hasta TERMINADO
            $builder->selectSub(function ($q) {
                $q->from('concessionaire_contract as cc')
                    ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                    ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                    ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
                    ->join('locals as l', 'l.id', '=', 'cl.local_id')
                    ->whereColumn('cc.concessionaire_id', 'concessionaires.id')
                    ->whereIn('cs.code', ['VIG', 'VENC'])
                    ->whereNull('c.deleted_at')
                    ->whereNull('l.deleted_at')
                    ->selectRaw('COUNT(DISTINCT l.id)');
            }, 'active_locals_count');

            return $builder->orderBy('active_locals_count', $direction);
        }

        $allowed = $this->allowedSorts();
        if (! $sort || ! in_array($sort, $allowed)) {
            [$defaultSort, $defaultDir] = $this->defaultSort();

            return $builder->orderBy($defaultSort, $defaultDir);
        }

        return $builder->orderBy($sort, $direction);
    }
}
