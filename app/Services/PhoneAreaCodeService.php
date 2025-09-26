<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\PhoneAreaCodeServiceInterface;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;

class PhoneAreaCodeService extends BaseService implements PhoneAreaCodeServiceInterface
{
    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at') con el shape correcto según --fields.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        // Concessionaires count for this area code
        $count = $model->getRelationValue('concessionaires')
            ? $model->getRelationValue('concessionaires')->count()
            : \App\Models\Concessionaire::query()->where('phone_area_code_id', $model->getKey())->count();

        // For index rows, include only concessionaire names for lightweight rendering
        $concessionaires = $model->getRelationValue('concessionaires');
        if ($concessionaires) {
            $names = $concessionaires->pluck('full_name')->all();
        } else {
            $names = \App\Models\Concessionaire::query()
                ->where('phone_area_code_id', $model->getKey())
                ->pluck('full_name')
                ->all();
        }

        return [
            'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'concessionaires_count' => (int) $count,
            'concessionaires' => array_map('strval', $names),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
        ];
    }

    /**
     * Columnas por defecto de exportación (cabeceras).
     * El generador reemplazará 'id' => '#',
            'code' => 'Código',
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
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\PhoneAreaCode::class;
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\PhoneAreaCode::query();
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
     * Determine if the given PhoneAreaCode has dependent Concessionaires.
     */
    protected function hasDependencies(Model $model): bool
    {
        if (method_exists($model, 'concessionaires')) {
            return (bool) $model->concessionaires()->exists();
        }

        return (bool) \App\Models\Concessionaire::query()->where('phone_area_code_id', $model->getKey())->exists();
    }

    /**
     * Prevent deleting a PhoneAreaCode when it has dependent Concessionaires.
     */
    public function delete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar el código de área porque existen concesionarios asociados. Desactive en su lugar.');
        }

        return $this->repo->delete($model);
    }

    /**
     * Prevent force-deleting a PhoneAreaCode when it has dependent Concessionaires.
     */
    public function forceDelete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar permanentemente el código de área porque existen concesionarios asociados.');
        }

        return $this->repo->forceDelete($model);
    }

    /** {\inheritDoc} */
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

    /** {\inheritDoc} */
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
