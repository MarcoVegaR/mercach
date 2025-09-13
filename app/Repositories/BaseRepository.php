<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\RepositoryInterface;
use App\DTO\ListQuery;
use App\DTO\ShowQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Implementación base abstracta para repositorios con soporte completo
 * para Index, operaciones masivas y utilidades de concurrencia.
 *
 * Proporciona hooks extensibles para personalizar comportamiento en
 * repositorios concretos sin duplicar lógica común.
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * Clase del modelo Eloquent que maneja este repositorio.
     */
    protected string $modelClass;

    /**
     * Crea un nuevo query builder base.
     * Hook: Override para personalizar el builder inicial.
     *
     * @return Builder<Model>
     */
    protected function builder(): Builder
    {
        return $this->modelClass::query();
    }

    /**
     * Define columnas que pueden ser buscadas con el parámetro 'q'.
     * Hook: Override para especificar columnas searchables.
     *
     * @return array<string> Nombres de columnas
     */
    protected function searchable(): array
    {
        return [];
    }

    /**
     * Define columnas permitidas para ordenamiento.
     * Hook: Override para whitelist de columnas de sort.
     *
     * @return array<string> Nombres de columnas permitidas
     */
    protected function allowedSorts(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    /**
     * Define ordenamiento por defecto cuando no se especifica sort válido.
     * Hook: Override para cambiar sort por defecto.
     *
     * @return array{string, string} [columna, dirección]
     */
    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /**
     * Define mapeo de filtros personalizados.
     * Hook: Override para agregar lógica de filtrado específica.
     *
     * @return array<string, callable> Mapa clave => función filtro
     */
    protected function filterMap(): array
    {
        return [];
    }

    /**
     * Hook para aplicar relaciones adicionales al builder.
     * Override para cargar relaciones específicas por defecto.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected function withRelations(Builder $builder): Builder
    {
        return $builder;
    }

    /**
     * Column name used to represent the "active" state.
     * Repositories can override to adapt to different schemas (e.g., 'is_active').
     */
    protected function activeColumn(): string
    {
        return 'active';
    }

    // === MÉTODOS COMUNES IMPLEMENTADOS ===

    /**
     * Aplica búsqueda global en columnas searchable.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected function applySearch(Builder $builder, string $searchTerm): Builder
    {
        if (empty($searchTerm) || empty($this->searchable())) {
            return $builder;
        }

        $searchLower = strtolower($searchTerm);

        return $builder->where(function (Builder $q) use ($searchLower) {
            foreach ($this->searchable() as $column) {
                $q->orWhereRaw('LOWER('.$column.') LIKE ?', ["%{$searchLower}%"]);
            }
        });
    }

    /**
     * Aplica filtros usando filterMap() y convenciones estándar.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected function applyFilters(Builder $builder, ListQuery $query): Builder
    {
        $filterMap = $this->filterMap();

        // Apply filters only if they exist and are not null
        if ($query->filters !== null) {
            foreach ($query->filters as $key => $value) {
                // Filtro personalizado definido en filterMap
                if (isset($filterMap[$key])) {
                    $filterMap[$key]($builder, $value);

                    continue;
                }

                // Filtros estándar
                $this->applyStandardFilter($builder, $key, $value);
            }
        }

        return $builder;
    }

    /**
     * Aplica filtros estándar según convenciones de naming.
     *
     * @param  Builder<Model>  $builder
     */
    private function applyStandardFilter(Builder $builder, string $key, mixed $value): void
    {
        // Aplicar filtros solo si hay valores no nulos
        if ($value !== null) {
            // Filtro LIKE (clave_like)
            if (str_ends_with($key, '_like')) {
                $column = str_replace('_like', '', $key);
                // For numeric columns like 'id', cast to text for search
                if ($column === 'id' || str_ends_with($column, '_id')) {
                    $builder->whereRaw("CAST({$column} AS TEXT) LIKE ?", ['%'.$value.'%']);
                } else {
                    $builder->whereRaw("LOWER({$column}::text) LIKE ?", ['%'.strtolower($value).'%']);
                }

                return;
            }

            // Filtro BETWEEN (clave_between con from/to)
            if (str_ends_with($key, '_between') && is_array($value)) {
                $column = str_replace('_between', '', $key);
                if (isset($value['from'])) {
                    $builder->where($column, '>=', $value['from']);
                }
                if (isset($value['to'])) {
                    $builder->where($column, '<=', $value['to']);
                }

                return;
            }

            // Filtro IN (clave_in con array)
            if (str_ends_with($key, '_in') && is_array($value)) {
                $column = str_replace('_in', '', $key);
                $builder->whereIn($column, $value);

                return;
            }

            // Filtro IS NULL/NOT NULL (clave_is con 'null'/'notnull')
            if (str_ends_with($key, '_is')) {
                $column = str_replace('_is', '', $key);
                if ($value === 'null') {
                    $builder->whereNull($column);
                } elseif ($value === 'notnull') {
                    $builder->whereNotNull($column);
                }

                return;
            }

            // Filtro de conteo de relaciones (relacion_count >= N)
            if (str_ends_with($key, '_count')) {
                $relation = str_replace('_count', '', $key);
                $builder->has($relation, '>=', (int) $value);

                return;
            }

            // Filtro equals por defecto
            if (is_bool($value)) {
                $builder->where($key, $value);
            } else {
                $builder->where($key, $value);
            }
        }
    }

    /**
     * Aplica ordenamiento validando contra allowedSorts.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected function applySort(Builder $builder, ?string $sort, ?string $dir): Builder
    {
        $allowedSorts = $this->allowedSorts();

        if (! $sort || ! in_array($sort, $allowedSorts)) {
            [$defaultSort, $defaultDir] = $this->defaultSort();

            return $builder->orderBy($defaultSort, $defaultDir);
        }

        $direction = in_array($dir, ['asc', 'desc']) ? $dir : 'desc';

        return $builder->orderBy($sort, $direction);
    }

    // === IMPLEMENTACIÓN DE REPOSITORYINTERFACE ===

    /**
     * @param  array<string>  $with
     * @param  array<string>  $withCount
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, \Illuminate\Database\Eloquent\Model>
     */
    public function list(ListQuery $query, array $with = [], array $withCount = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        // Add debug logging
        \Log::info('BaseRepository::list called', [
            'query_q' => $query->q,
            'query_page' => $query->page,
            'query_perPage' => $query->perPage,
            'query_sort' => $query->sort,
            'query_filters' => $query->filters,
            'searchable_fields' => $this->searchable(),
            'model_class' => $this->modelClass,
        ]);

        $builder = $this->builder();

        // Apply eager loading
        if (! empty($with)) {
            $builder->with($with);
        }

        // Apply count relationships
        if (! empty($withCount)) {
            $builder->withCount($withCount);
        }

        // Apply custom relationship loading
        $builder = $this->withRelations($builder);

        // Apply global search
        if (! empty($query->q)) {
            $this->applySearch($builder, $query->q);
        }

        // Apply filters
        $this->applyFilters($builder, $query);

        $builder = $this->applySort($builder, $query->sort, $query->dir);

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /**
     * @param  array<string>  $with
     * @param  array<string>  $withCount
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(ListQuery $query, array $with = [], array $withCount = []): LengthAwarePaginator
    {
        $builder = $this->builder();

        // Apply eager loading
        if (! empty($with)) {
            $builder->with($with);
        }

        // Apply count relationships
        if (! empty($withCount)) {
            $builder->withCount($withCount);
        }

        // Apply custom relationship loading
        $builder = $this->withRelations($builder);

        // Apply global search
        if (! empty($query->q)) {
            $this->applySearch($builder, $query->q);
        }

        // Apply filters
        $this->applyFilters($builder, $query);

        // Apply sorting
        $builder = $this->applySort($builder, $query->sort, $query->dir);

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->builder()->get($columns);
    }

    /**
     * @param  array<int|string>  $ids
     * @param  array<string>  $with
     * @param  array<string>  $withCount
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginateByIdsDesc(array $ids, int $perPage, array $with = [], array $withCount = []): LengthAwarePaginator
    {
        if (empty($ids)) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $builder = $this->builder();

        if (! empty($with)) {
            $builder->with($with);
        }

        if (! empty($withCount)) {
            $builder->withCount($withCount);
        }

        // Apply repository-specific relations and computed selections (e.g., users_count)
        $builder = $this->withRelations($builder);

        return $builder
            ->whereIn('id', $ids)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function findById(int|string $id, array $with = []): ?Model
    {
        return $this->builder()
            ->with($with)
            ->where('id', $id)
            ->first();
    }

    public function findOrFailById(int|string $id, array $with = []): Model
    {
        return $this->builder()
            ->with($with)
            ->where('id', $id)
            ->firstOrFail();
    }

    public function findByUuid(string $uuid, array $with = []): ?Model
    {
        return $this->builder()
            ->with($with)
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Find by UUID or fail.
     *
     * @param  array<string>  $with
     *
     * @throws ModelNotFoundException
     */
    public function findOrFailByUuid(string $uuid, array $with = []): Model
    {
        $builder = $this->builder();
        if (! empty($with)) {
            $builder->with($with);
        }

        return $builder->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Show a model by ID with ShowQuery parameters.
     *
     * @throws ModelNotFoundException
     */
    public function showById(int|string $id, ShowQuery $query): Model
    {
        $builder = $this->builder();
        $this->applyShowQuery($builder, $query);

        return $builder->findOrFail($id);
    }

    /**
     * Show a model by UUID with ShowQuery parameters.
     *
     * @throws ModelNotFoundException
     */
    public function showByUuid(string $uuid, ShowQuery $query): Model
    {
        $builder = $this->builder();
        $this->applyShowQuery($builder, $query);

        return $builder->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Apply ShowQuery parameters to a query builder.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    protected function applyShowQuery(Builder $builder, ShowQuery $query): Builder
    {
        // Apply eager loading
        if ($query->hasRelations()) {
            $builder->with($query->with);
        }

        // Apply counts
        if ($query->hasCounts()) {
            $builder->withCount($query->withCount);
        }

        // Apply soft deletes
        if ($query->withTrashed && in_array(SoftDeletes::class, class_uses_recursive($this->modelClass))) {
            /** @phpstan-ignore-next-line withTrashed available when model uses SoftDeletes */
            $builder->withTrashed();
        }

        return $builder;
    }

    public function existsById(int|string $id): bool
    {
        return $this->builder()->where('id', $id)->exists();
    }

    public function existsByUuid(string $uuid): bool
    {
        return $this->builder()->where('uuid', $uuid)->exists();
    }

    public function count(array $filters = []): int
    {
        $builder = $this->builder();

        if (! empty($filters)) {
            $query = new ListQuery(filters: $filters);
            $builder = $this->applyFilters($builder, $query);
        }

        return $builder->count();
    }

    public function create(array $attributes): Model
    {
        return $this->builder()->create($attributes);
    }

    public function createMany(array $rows): Collection
    {
        $models = new Collection;

        foreach ($rows as $attributes) {
            $models->push($this->create($attributes));
        }

        return $models;
    }

    public function update(Model|int|string $modelOrId, array $attributes): Model
    {
        $model = $modelOrId instanceof Model
            ? $modelOrId
            : $this->findOrFailById($modelOrId);

        $model->update($attributes);

        return $model->fresh();
    }

    public function upsert(array $rows, array $uniqueBy, array $updateColumns): int
    {
        return $this->builder()->upsert($rows, $uniqueBy, $updateColumns);
    }

    public function delete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model
            ? $modelOrId
            : $this->findOrFailById($modelOrId);

        return $model->delete();
    }

    public function forceDelete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model
            ? $modelOrId
            : $this->findOrFailById($modelOrId);

        if (in_array(SoftDeletes::class, class_uses_recursive($model))) {
            return $model->forceDelete();
        }

        return $model->delete();
    }

    public function restore(Model|int|string $modelOrId): bool
    {
        if ($modelOrId instanceof Model) {
            $model = $modelOrId;
        } else {
            // Use newQuery to get a fresh builder that supports soft deletes
            $modelInstance = new $this->modelClass;
            $model = $modelInstance->newQuery()->withTrashed()->where('id', $modelOrId)->firstOrFail();
        }

        if (in_array(SoftDeletes::class, class_uses_recursive($model))) {
            return $model->restore();
        }

        return false;
    }

    public function setActive(Model|int|string $modelOrId, bool $active): Model
    {
        return $this->update($modelOrId, [$this->activeColumn() => $active]);
    }

    // === OPERACIONES MASIVAS ===

    public function bulkDeleteByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $modelInstance = new $this->modelClass;
        if (in_array(SoftDeletes::class, class_uses_recursive($modelInstance))) {
            return $this->builder()->whereIn('id', $ids)->delete();
        }

        return $this->builder()->whereIn('id', $ids)->delete();
    }

    public function bulkForceDeleteByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $modelInstance = new $this->modelClass;
        if (in_array(SoftDeletes::class, class_uses_recursive($modelInstance))) {
            return $modelInstance->newQuery()->whereIn('id', $ids)->forceDelete();
        }

        return $this->builder()->whereIn('id', $ids)->delete();
    }

    public function bulkRestoreByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $modelInstance = new $this->modelClass;
        if (in_array(SoftDeletes::class, class_uses_recursive($modelInstance))) {
            return $modelInstance->newQuery()->onlyTrashed()->whereIn('id', $ids)->restore();
        }

        return 0;
    }

    public function bulkSetActiveByIds(array $ids, bool $active): int
    {
        if (empty($ids)) {
            return 0;
        }

        return $this->builder()->whereIn('id', $ids)->update([$this->activeColumn() => $active]);
    }

    public function bulkDeleteByUuids(array $uuids): int
    {
        if (empty($uuids)) {
            return 0;
        }

        return $this->builder()->whereIn('uuid', $uuids)->delete();
    }

    public function bulkForceDeleteByUuids(array $uuids): int
    {
        if (empty($uuids)) {
            return 0;
        }

        $modelInstance = new $this->modelClass;
        if (in_array(SoftDeletes::class, class_uses_recursive($modelInstance))) {
            return $modelInstance->newQuery()->whereIn('uuid', $uuids)->forceDelete();
        }

        return $this->builder()->whereIn('uuid', $uuids)->delete();
    }

    public function bulkRestoreByUuids(array $uuids): int
    {
        if (empty($uuids)) {
            return 0;
        }

        $modelInstance = new $this->modelClass;
        if (in_array(SoftDeletes::class, class_uses_recursive($modelInstance))) {
            return $modelInstance->newQuery()->onlyTrashed()->whereIn('uuid', $uuids)->restore();
        }

        return 0;
    }

    public function bulkSetActiveByUuids(array $uuids, bool $active): int
    {
        if (empty($uuids)) {
            return 0;
        }

        return $this->builder()->whereIn('uuid', $uuids)->update([$this->activeColumn() => $active]);
    }

    // === CONCURRENCIA (PESSIMISTIC LOCK) ===

    public function withPessimisticLockById(int|string $id, callable $callback): mixed
    {
        return DB::transaction(function () use ($id, $callback) {
            $model = $this->builder()
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            return $callback($model);
        });
    }

    public function withPessimisticLockByUuid(string $uuid, callable $callback): mixed
    {
        return DB::transaction(function () use ($uuid, $callback) {
            $model = $this->builder()
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            return $callback($model);
        });
    }
}
