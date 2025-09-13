<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Services\ServiceInterface;
use App\DTO\ListQuery;
use App\DTO\ShowQuery;
use App\Exceptions\DomainActionException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BaseService - Implementación base abstracta para servicios de aplicación
 *
 * Orquesta el acceso al repositorio, maneja transacciones, exportación,
 * y proporciona hooks extensibles para servicios concretos.
 */
abstract class BaseService implements ServiceInterface
{
    public function __construct(
        protected RepositoryInterface $repo,
        protected ContainerInterface $container
    ) {}

    // --- Listado (Index) ---

    public function list(ListQuery $query, array $with = [], array $withCount = []): array
    {
        $paginator = $this->repo->paginate($query, $with, $withCount);

        return $this->makeListResult($paginator);
    }

    public function listByIdsDesc(array $ids, int $perPage, array $with = [], array $withCount = []): array
    {
        $paginator = $this->repo->paginateByIdsDesc($ids, $perPage, $with, $withCount);

        return $this->makeListResult($paginator);
    }

    // --- Export ---

    public function export(ListQuery $query, string $format, ?array $columns = null, ?string $filename = null): StreamedResponse
    {
        $cols = $columns ?? $this->defaultExportColumns();
        $file = $filename ?? $this->defaultExportFilename($format, $query);

        // Generador perezoso para filas (evita cargar todo en memoria)
        $rowsIterable = $this->exportRows($query, $cols);

        $exporter = $this->resolveExporter($format);
        $response = $exporter->stream($rowsIterable, $cols);

        // Adjuntar filename si el exporter lo soporta o setear header en BaseService
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$file.'"');

        return $response;
    }

    // --- Lecturas puntuales ---

    public function getById(int|string $id, array $with = []): ?Model
    {
        return $this->repo->findById($id, $with);
    }

    public function getOrFailById(int|string $id, array $with = []): Model
    {
        return $this->repo->findOrFailById($id, $with);
    }

    public function getByUuid(string $uuid, array $with = []): ?Model
    {
        return $this->repo->findByUuid($uuid, $with);
    }

    public function getOrFailByUuid(string $uuid, array $with = []): Model
    {
        return $this->repo->findOrFailByUuid($uuid, $with);
    }

    /**
     * Show a resource by ID with ShowQuery parameters.
     *
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function showById(int|string $id, ShowQuery $query): array
    {
        $model = $this->repo->showById($id, $query);

        // Apply appends if requested
        if ($query->hasAppends()) {
            $model->append($query->append);
        }

        return [
            'item' => $this->toItem($model),
            'meta' => $this->getShowMeta($model, $query),
        ];
    }

    /**
     * Show a resource by UUID with ShowQuery parameters.
     *
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function showByUuid(string $uuid, ShowQuery $query): array
    {
        $model = $this->repo->showByUuid($uuid, $query);

        // Apply appends if requested
        if ($query->hasAppends()) {
            $model->append($query->append);
        }

        return [
            'item' => $this->toItem($model),
            'meta' => $this->getShowMeta($model, $query),
        ];
    }

    /**
     * Transform a single model for show views.
     *
     * @return array<string, mixed>
     */
    public function toItem(Model $model): array
    {
        // Default implementation uses toRow for consistency
        // Override for different show representation if needed
        return $this->toRow($model);
    }

    /**
     * Get metadata for show operations.
     * Override in child classes to add custom metadata.
     *
     * @return array<string, mixed>
     */
    protected function getShowMeta(Model $model, ShowQuery $query): array
    {
        $modelArray = $model->toArray();
        $countFields = array_filter(
            array_keys($modelArray),
            fn ($key) => str_ends_with($key, '_count')
        );

        return [
            'loaded_relations' => array_keys($model->getRelations()),
            'loaded_counts' => array_values($countFields),
        ];
    }

    // --- Escritura ---

    /**
     * Create a new model with transaction support and hooks.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model
    {
        return $this->transaction(function () use ($attributes) {
            // Hook before save
            $this->beforeCreate($attributes);

            // Create the model
            $model = $this->repo->create($attributes);

            // Sync relations if needed
            $this->syncRelations($model, $attributes);

            // Hook after save
            $this->afterCreate($model, $attributes);

            return $model;
        });
    }

    /**
     * @param  array<array<string, mixed>>  $rows
     * @return Collection<int, Model>
     */
    public function createMany(array $rows): Collection
    {
        return $this->transaction(fn () => $this->repo->createMany($rows));
    }

    /**
     * Update a model with transaction support, optimistic locking and hooks.
     *
     * @param  array<string, mixed>  $attributes
     * @param  string|null  $expectedUpdatedAt  Expected updated_at for optimistic locking
     *
     * @throws DomainActionException if optimistic lock fails
     */
    public function update(Model|int|string $modelOrId, array $attributes, ?string $expectedUpdatedAt = null): Model
    {
        return $this->transaction(function () use ($modelOrId, $attributes, $expectedUpdatedAt) {
            // Get the model if we have an ID
            $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);

            // Check optimistic lock if provided
            if ($expectedUpdatedAt !== null) {
                // Normalize both dates to timestamps for comparison to handle different formats
                /** @var null|\Illuminate\Support\Carbon $currentUpdatedAt */
                $currentUpdatedAt = $model->getAttribute('updated_at');
                $currentTimestamp = $currentUpdatedAt?->timestamp;
                $expectedTimestamp = \Carbon\Carbon::parse($expectedUpdatedAt)->timestamp;

                if ($currentTimestamp !== $expectedTimestamp) {
                    throw new DomainActionException(
                        'El registro ha sido modificado por otro usuario. Por favor, recarga la página e intenta nuevamente.'
                    );
                }
            }

            // Hook before update
            $this->beforeUpdate($model, $attributes);

            // Remove fields that shouldn't be passed to the model's update method
            $updateAttributes = $attributes;
            unset($updateAttributes['permissions_ids']);

            // Update the model
            $model = $this->repo->update($model, $updateAttributes);

            // Hook after update (pass original attributes)
            $this->afterUpdate($model, $attributes);

            return $model;
        });
    }

    public function upsert(array $rows, array $uniqueBy, array $updateColumns): int
    {
        return $this->transaction(fn () => $this->repo->upsert($rows, $uniqueBy, $updateColumns));
    }

    // --- Borrado / restauración ---

    public function delete(Model|int|string $modelOrId): bool
    {
        return $this->repo->delete($modelOrId);
    }

    public function forceDelete(Model|int|string $modelOrId): bool
    {
        return $this->repo->forceDelete($modelOrId);
    }

    public function restore(Model|int|string $modelOrId): bool
    {
        return $this->repo->restore($modelOrId);
    }

    // --- Estado común ---

    public function setActive(Model|int|string $modelOrId, bool $active): Model
    {
        return $this->transaction(fn () => $this->repo->setActive($modelOrId, $active));
    }

    // --- Operaciones MASIVAS ---

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->repo->bulkDeleteByIds($ids);
    }

    public function bulkForceDeleteByIds(array $ids): int
    {
        return $this->repo->bulkForceDeleteByIds($ids);
    }

    public function bulkRestoreByIds(array $ids): int
    {
        return $this->repo->bulkRestoreByIds($ids);
    }

    public function bulkSetActiveByIds(array $ids, bool $active): int
    {
        return $this->repo->bulkSetActiveByIds($ids, $active);
    }

    public function bulkDeleteByUuids(array $uuids): int
    {
        return $this->repo->bulkDeleteByUuids($uuids);
    }

    public function bulkForceDeleteByUuids(array $uuids): int
    {
        return $this->repo->bulkForceDeleteByUuids($uuids);
    }

    public function bulkRestoreByUuids(array $uuids): int
    {
        return $this->repo->bulkRestoreByUuids($uuids);
    }

    public function bulkSetActiveByUuids(array $uuids, bool $active): int
    {
        return $this->repo->bulkSetActiveByUuids($uuids, $active);
    }

    // --- Concurrencia / Transacciones ---

    public function transaction(callable $callback): mixed
    {
        /** @phpstan-ignore-next-line */
        return DB::transaction($callback);
    }

    public function withPessimisticLockById(int|string $id, callable $callback): mixed
    {
        return $this->repo->withPessimisticLockById($id, $callback);
    }

    public function withPessimisticLockByUuid(string $uuid, callable $callback): mixed
    {
        return $this->repo->withPessimisticLockByUuid($uuid, $callback);
    }

    // --- Hooks/protegidos (sobrescribibles por servicios concretos) ---

    /**
     * Mapea un Model a array para 'rows'; por defecto attributesToArray()
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        return $model->attributesToArray();
    }

    /**
     * Columnas por defecto si no se pasan en export(); cada servicio concreto puede sobrescribir
     *
     * @return array<string>
     */
    protected function defaultExportColumns(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    /**
     * Nombre por defecto para archivo de exportación
     */
    protected function defaultExportFilename(string $format, ListQuery $query): string
    {
        $modelClass = strtolower(class_basename($this->repoModelClass()));
        $timestamp = date('Ymd_His');

        return "{$modelClass}_export_{$timestamp}.{$format}";
    }

    /**
     * Tamaño de página para streaming export (evita agotar memoria)
     */
    protected function exportPageSize(): int
    {
        return 1000;
    }

    /**
     * Generador que itera sobre las filas de export de forma paginada
     *
     * @param  array<string>  $columns
     * @return \Generator<array<string, mixed>>
     */
    protected function exportRows(ListQuery $query, array $columns): \Generator
    {
        $page = 1;
        $pageSize = $this->exportPageSize();

        do {
            // Crear nueva query con paginación específica para export
            $exportQuery = new ListQuery(
                q: $query->q,
                page: $page,
                perPage: $pageSize,
                sort: $query->sort,
                dir: $query->dir,
                filters: $query->filters
            );

            $paginator = $this->repo->paginate($exportQuery);

            foreach ($paginator->items() as $item) {
                $row = $this->toRow($item);

                // Filtrar solo las columnas solicitadas
                if (! empty($columns)) {
                    // If columns is associative (has string keys), use keys, otherwise use values
                    $columnKeys = array_is_list($columns) ? $columns : array_keys($columns);
                    $row = array_intersect_key($row, array_flip($columnKeys));
                }

                yield $row;
            }

            $page++;
        } while ($page <= $paginator->lastPage());
    }

    /**
     * Convierte un paginator a formato ['rows', 'meta']
     *
     * @param  LengthAwarePaginator<int, Model>  $paginator
     * @return array<string, mixed>
     */
    protected function makeListResult(LengthAwarePaginator $paginator): array
    {
        return [
            'rows' => array_map(
                fn (Model $item) => $this->toRow($item),
                $paginator->items()
            ),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Resuelve el exporter para el formato dado
     */
    protected function resolveExporter(string $format): \App\Contracts\Exports\ExporterInterface
    {
        return $this->container->get('exporter.'.$format);
    }

    /**
     * Devuelve el FQCN del modelo del repositorio (para generar filename)
     * Los servicios concretos pueden sobrescribir esto si es necesario
     */
    protected function repoModelClass(): string
    {
        // Por defecto, intentamos derivar del repositorio
        // Los servicios concretos deberían sobrescribir esto
        return 'Model';
    }

    // --- Hooks para create/update ---

    /**
     * Hook called before creating a model.
     * Override in child services for custom logic.
     *
     * @param  array<string, mixed>  &$attributes
     */
    protected function beforeCreate(array &$attributes): void
    {
        // Override in child services
    }

    /**
     * Hook called after creating a model.
     * Override in child services for custom logic.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function afterCreate(Model $model, array $attributes): void
    {
        // Override in child services
    }

    /**
     * Hook called before updating a model.
     * Override in child services for custom logic.
     *
     * @param  array<string, mixed>  &$attributes
     */
    protected function beforeUpdate(Model $model, array &$attributes): void
    {
        // Override in child services
    }

    /**
     * Hook called after updating a model.
     * Override in child services for custom logic.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function afterUpdate(Model $model, array $attributes): void
    {
        // Override in child services
    }

    /**
     * Sync many-to-many relations if present in attributes.
     * Looks for keys ending with '_ids' and syncs them.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function syncRelations(Model $model, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            // Check for relation IDs pattern (e.g., 'permissions_ids', 'roles_ids')
            if (str_ends_with($key, '_ids') && is_array($value)) {
                $relationName = str_replace('_ids', '', $key);

                // Check if the relation exists
                if (method_exists($model, $relationName)) {
                    try {
                        $relation = $model->$relationName();

                        // Only sync if it's a BelongsToMany relation
                        if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                            $relation->sync($value);
                        }
                    } catch (\Exception $e) {
                        // Silently ignore if not a valid relation
                        // Services can override syncRelations for custom handling
                    }
                }
            }
        }
    }
}
