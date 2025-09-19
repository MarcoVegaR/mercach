<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\LocalServiceInterface;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;

class LocalService extends BaseService implements LocalServiceInterface
{
    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'market_id' => $model->getAttribute('market_id'),
            'local_type_id' => $model->getAttribute('local_type_id'),
            'local_status_id' => $model->getAttribute('local_status_id'),
            'trade_category_id' => $model->getAttribute('trade_category_id'),
            'local_location_id' => $model->getAttribute('local_location_id'),
            'area_m2' => $model->getAttribute('area_m2'),
            '2' => $model->getAttribute('2'),
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
            // Raw FK IDs (useful for internal logic/forms)
            'market_id' => $model->getAttribute('market_id'),
            'local_type_id' => $model->getAttribute('local_type_id'),
            'local_status_id' => $model->getAttribute('local_status_id'),
            'local_location_id' => $model->getAttribute('local_location_id'),
            // Friendly related names for UI/exports
            'market_name' => $model->getRelationValue('market')?->getAttribute('name'),
            'local_type_name' => $model->getRelationValue('localType')?->getAttribute('name'),
            'local_status_name' => $model->getRelationValue('localStatus')?->getAttribute('name'),
            'local_location_name' => $model->getRelationValue('localLocation')?->getAttribute('name'),
            'area_m2' => $model->getAttribute('area_m2'),
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
            'market_id' => 'Market id',
            'local_type_id' => 'Local type id',
            'local_status_id' => 'Local status id',
            'trade_category_id' => 'Trade category id',
            'local_location_id' => 'Local location id',
            'area_m2' => 'Area m2',
            '2' => '2',
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
            'market_name' => 'Mercado',
            'local_type_name' => 'Tipo de local',
            'local_status_name' => 'Estado de local',
            'local_location_name' => 'Ubicación',
            'area_m2' => 'Área (m²)',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\Local::class;
    }

    /**
     * On create, set default Local Status to 'DISP' (Disponible) if not provided.
     * Avoid hardcoding IDs by resolving by code, with a fallback by name.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeCreate(array &$attributes): void
    {
        if (empty($attributes['local_status_id'])) {
            $statusId = \App\Models\LocalStatus::query()
                ->where('code', 'DISP')
                ->value('id');

            if ($statusId === null) {
                $statusId = \App\Models\LocalStatus::query()
                    ->whereRaw('LOWER(name) = ?', ['disponible'])
                    ->value('id');
            }

            if ($statusId === null) {
                throw new DomainActionException('No se encontró el estado por defecto "Disponible" (code DISP). Ejecute los seeders.');
            }

            $attributes['local_status_id'] = (int) $statusId;
        }
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\Local::query();
        $total = (int) $model->count();
        $active = (int) (clone $model)->where('is_active', true)->count();

        // Filter options: only active items, ordered by name
        $markets = \App\Models\Market::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $localTypes = \App\Models\LocalType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $localStatuses = \App\Models\LocalStatus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $localLocations = \App\Models\LocalLocation::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        return [
            'stats' => [
                'total' => $total,
                'active' => $active,
            ],
            'filterOptions' => [
                'markets' => $markets,
                'local_types' => $localTypes,
                'local_statuses' => $localStatuses,
                'local_locations' => $localLocations,
            ],
        ];
    }
}
