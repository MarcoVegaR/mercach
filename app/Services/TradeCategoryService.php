<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\TradeCategoryServiceInterface;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TradeCategoryService extends BaseService implements TradeCategoryServiceInterface
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
        \assert($model instanceof \App\Models\TradeCategory);

        // All contracts associated with this trade category (any status, except soft-deleted)
        $contracts = DB::table('contracts as c')
            ->where('c.trade_category_id', $model->getKey())
            ->whereNull('c.deleted_at')
            ->select([
                'c.id as contract_id',
                'c.number as contract_number',
            ])
            ->distinct()
            ->get();

        $contractNumbers = $contracts
            ->pluck('contract_number')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();

        $contractsDetailed = $contracts
            ->map(static fn ($row): array => [
                'id' => (int) ($row->contract_id ?? 0),
                'number' => (string) ($row->contract_number ?? ''),
            ])
            ->filter(static fn (array $row): bool => $row['id'] > 0 && $row['number'] !== '')
            ->unique('id')
            ->values()
            ->all();

        $contractsText = '';
        if (! empty($contractNumbers)) {
            $contractsText = implode("\n", $contractNumbers);
        }

        return [
            'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'description' => $model->getAttribute('description'),
            'contracts_count' => count($contractNumbers),
            'contracts_numbers' => $contractNumbers,
            'contracts_text' => $contractsText,
            'contracts_detailed' => $contractsDetailed,
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
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
            'contracts_text' => 'Contratos',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\TradeCategory::class;
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\TradeCategory::query();
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
     * Determine if the given TradeCategory has dependent relationships (contracts).
     */
    protected function hasDependencies(Model $model): bool
    {
        // Block deletion if there are contracts associated with this trade category
        return method_exists($model, 'contracts') && $model->contracts()->exists();
    }

    /**
     * Prevent deleting a TradeCategory when it has dependent Locals.
     */
    public function delete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar el rubro porque existen contratos asociados.');
        }

        return $this->repo->delete($model);
    }

    /**
     * Prevent force-deleting a TradeCategory when it has dependent Locals.
     */
    public function forceDelete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar permanentemente el rubro porque existen contratos asociados.');
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
