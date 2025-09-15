<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\LocalStatusServiceInterface;
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
        return [
            'id' => $model->getAttribute('id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name'),
            'description' => $model->getAttribute('description'),
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
}
