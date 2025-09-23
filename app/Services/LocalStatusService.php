<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\LocalStatusServiceInterface;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;

class LocalStatusService extends BaseService implements LocalStatusServiceInterface
{
    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'description' => $model->getAttribute('description'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at') con el shape correcto según --fields.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        // Get locals count and data for this local status
        $localsCount = $model->getRelationValue('locals')
            ? $model->getRelationValue('locals')->count()
            : \App\Models\Local::query()->where('local_status_id', $model->getKey())->count();

        // For index rows, return only the codes for lightweight rendering
        $locals = $model->getRelationValue('locals');
        if ($locals) {
            $localsData = $locals->pluck('code')->all();
        } else {
            $localsData = \App\Models\Local::query()
                ->where('local_status_id', $model->getKey())
                ->pluck('code')
                ->all();
        }

        return [
            'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'description' => $model->getAttribute('description'),
            'locals_count' => $localsCount,
            'locals' => array_map('strval', $localsData),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
        ];
    }

    /**
     * Show representation: when 'locals' relation is loaded, return full objects {id, code}.
     *
     * @return array<string, mixed>
     */
    public function toItem(Model $model): array
    {
        $localsCount = $model->getAttribute('locals_count');
        if ($localsCount === null) {
            $localsCount = $model->relationLoaded('locals')
                ? $model->getRelationValue('locals')->count()
                : (int) \App\Models\Local::query()->where('local_status_id', $model->getKey())->count();
        }

        $item = [
            'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'description' => $model->getAttribute('description'),
            'locals_count' => (int) $localsCount,
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
        ];

        if ($model->relationLoaded('locals')) {
            $item['locals'] = $model->getRelationValue('locals')
                ->map(fn ($local) => ['id' => $local->id, 'code' => (string) $local->code])
                ->values()
                ->all();
        } else {
            $item['locals'] = null;
        }

        return $item;
    }

    /**
     * Load dynamic data for show page based on query parameters
     *
     * @param  array<string>  $with
     * @param  array<string>  $withCount
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function loadShowData(Model $model, array $with = [], array $withCount = []): array
    {
        $loadedCounts = ['locals'];
        $loadedRelations = [];

        if (! empty($with)) {
            // Only allow loading locals relation
            $allowedWith = array_intersect($with, ['locals']);
            if (! empty($allowedWith)) {
                // Load locals with only id and code fields for efficiency
                $model->load(['locals:id,local_status_id,code']);
                $loadedRelations = array_merge($loadedRelations, $allowedWith);
            }
        }

        if (! empty($withCount)) {
            // Only allow counting locals
            $allowedWithCount = array_intersect($withCount, ['locals']);
            if (! empty($allowedWithCount)) {
                $loadedCounts = array_merge($loadedCounts, $allowedWithCount);
            }
        }

        // Always load the count
        $model->loadCount(array_unique($loadedCounts));

        return [
            'item' => $this->toItem($model),
            'meta' => [
                'loaded_relations' => $loadedRelations,
                'loaded_counts' => array_values(array_unique($loadedCounts)),
                'appended' => [],
            ],
        ];
    }

    /**
     * Columnas por defecto de exportación (cabeceras).
     * El generador reemplazará 'id' => '#',
            'code' => 'Código',
            'name' => 'Nombre',
            'description' => 'Description',
            'is_active' => 'Estado',
            'created_at' => 'Creado'.
     *
     * @return array<string, string|int>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'code' => 'Código',
            'name' => 'Nombre',
            'description' => 'Description',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\LocalStatus::class;
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\LocalStatus::query();
        $total = (int) $model->count();
        $active = (int) (clone $model)->where('is_active', true)->count();

        return [
            'stats' => [
                'total' => $total,
                'active' => $active,
            ],
        ];
    }

    /**
     * Determine if the given LocalStatus has dependent Locals.
     */
    protected function hasDependencies(Model $model): bool
    {
        if (method_exists($model, 'locals')) {
            return (bool) $model->locals()->exists();
        }

        return (bool) \App\Models\Local::query()->where('local_status_id', $model->getKey())->exists();
    }

    /**
     * Prevent deleting a LocalStatus when it has dependent Locals.
     */
    public function delete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar el estado de local porque existen locales asociados.');
        }

        return $this->repo->delete($model);
    }

    /**
     * Prevent force-deleting a LocalStatus when it has dependent Locals.
     */
    public function forceDelete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar permanentemente el estado de local porque existen locales asociados.');
        }

        return $this->repo->forceDelete($model);
    }

    /** {@inheritDoc} */
    public function bulkDeleteByIds(array $ids): int
    {
        $deleted = 0;
        foreach ($ids as $id) {
            try {
                if ($this->delete($id)) {
                    $deleted++;
                }
            } catch (DomainActionException $e) {
                // skip blocked deletions
            }
        }

        return $deleted;
    }

    /** {@inheritDoc} */
    public function bulkForceDeleteByIds(array $ids): int
    {
        $deleted = 0;
        foreach ($ids as $id) {
            try {
                if ($this->forceDelete($id)) {
                    $deleted++;
                }
            } catch (DomainActionException $e) {
                // skip blocked deletions
            }
        }

        return $deleted;
    }
}
