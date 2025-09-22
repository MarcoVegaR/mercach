<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\MarketServiceInterface;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;

class MarketService extends BaseService implements MarketServiceInterface
{
    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'address' => $model->getAttribute('address'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at') con el shape correcto según --fields.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        // Get locals count and data for this market
        $localsCount = $model->getRelationValue('locals')
            ? $model->getRelationValue('locals')->count()
            : \App\Models\Local::query()->where('market_id', $model->getKey())->count();

        // For index rows, return only the codes for lightweight rendering
        $locals = $model->getRelationValue('locals');
        if ($locals) {
            $localsData = $locals->pluck('code')->all();
        } else {
            $localsData = \App\Models\Local::query()
                ->where('market_id', $model->getKey())
                ->pluck('code')
                ->all();
        }

        return [
            'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'address' => $model->getAttribute('address'),
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
                : (int) \App\Models\Local::query()->where('market_id', $model->getKey())->count();
        }

        $item = [
            'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'address' => $model->getAttribute('address'),
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
     * Columnas por defecto de exportación (cabeceras).
     * El generador reemplazará 'id' => '#',
            'code' => 'Código',
            'name' => 'Nombre',
            'address' => 'Address',
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
            'address' => 'Address',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\Market::class;
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\Market::query();
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
     * Business rule: When the Market has dependencies, its 'code' becomes immutable.
     * If the incoming attributes attempt to change 'code', throw a DomainActionException.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeUpdate(Model $model, array &$attributes): void
    {
        // If this market has dependencies, prevent code changes
        if ($this->hasDependencies($model)) {
            if (array_key_exists('code', $attributes)) {
                $current = (string) $model->getAttribute('code');
                $incoming = strtoupper(trim((string) $attributes['code']));

                if ($incoming !== $current) {
                    throw new DomainActionException('No se puede modificar el código porque el mercado tiene dependencias.');
                }
            }
        }
    }

    /**
     * Determine if the given Market has dependent records.
     * Initially returns false (Markets independent); override in future when dependencies exist.
     */
    protected function hasDependencies(Model $model): bool
    {
        // A Market has dependencies if any Local references it
        if (method_exists($model, 'locals')) {
            /** @var bool $exists */
            $exists = (bool) $model->locals()->exists();

            return $exists;
        }

        return false;
    }

    /**
     * Prevent deleting a Market when it has dependent Locals.
     */
    public function delete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar el mercado porque existen locales asociados.');
        }

        return $this->repo->delete($model);
    }

    /**
     * Prevent force-deleting a Market when it has dependent Locals.
     */
    public function forceDelete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar permanentemente el mercado porque existen locales asociados.');
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
