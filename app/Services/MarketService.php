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
        return [
            'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'address' => $model->getAttribute('address'),
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
        return false;
    }
}
