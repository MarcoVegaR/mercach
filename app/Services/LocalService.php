<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\LocalServiceInterface;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        \assert($model instanceof \App\Models\Local);

        // Concesionarios y contratos "activos" para este local (contratos VIG o VENC)
        $activeContracts = DB::table('contract_local as cl')
            ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'c.id')
            ->join('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->where('cl.local_id', $model->getKey())
            ->whereIn('cs.code', ['VIG', 'VENC'])
            ->whereNull('c.deleted_at')
            ->whereNull('cn.deleted_at')
            ->select([
                'c.id as contract_id',
                'c.number as contract_number',
                'cn.id as concessionaire_id',
                'cn.full_name as concessionaire_name',
            ])
            ->distinct()
            ->get();

        $activeConcessionaires = $activeContracts
            ->pluck('concessionaire_name')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();

        $activeContractNumbers = $activeContracts
            ->pluck('contract_number')
            ->filter()
            ->map(static fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();

        $activeConcessionairesDetailed = $activeContracts
            ->map(static fn ($row): array => [
                'id' => (int) ($row->concessionaire_id ?? 0),
                'name' => (string) ($row->concessionaire_name ?? ''),
            ])
            ->filter(static fn (array $row): bool => $row['id'] > 0 && $row['name'] !== '')
            ->unique('id')
            ->values()
            ->all();

        $activeContractsDetailed = $activeContracts
            ->map(static fn ($row): array => [
                'id' => (int) ($row->contract_id ?? 0),
                'number' => (string) ($row->contract_number ?? ''),
            ])
            ->filter(static fn (array $row): bool => $row['id'] > 0 && $row['number'] !== '')
            ->unique('id')
            ->values()
            ->all();

        $activeConcessionairesText = '';
        if (! empty($activeConcessionaires)) {
            $activeConcessionairesText = implode("\n", $activeConcessionaires);
        }

        $activeContractsText = '';
        if (! empty($activeContractNumbers)) {
            $activeContractsText = implode("\n", $activeContractNumbers);
        }

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
            'market_name' => $model->getRelationValue('market')?->name,
            'local_type_name' => $model->getRelationValue('localType')?->name,
            'local_status_name' => $model->getRelationValue('localStatus')?->name,
            'local_location_name' => $model->getRelationValue('localLocation')?->name,
            'area_m2' => $model->getAttribute('area_m2'),
            'active_concessionaires_count' => count($activeConcessionaires),
            'active_concessionaires' => $activeConcessionaires,
            'active_concessionaires_text' => $activeConcessionairesText,
            'active_concessionaires_detailed' => $activeConcessionairesDetailed,
            'active_contract_numbers' => $activeContractNumbers,
            'active_contracts_text' => $activeContractsText,
            'active_contracts_detailed' => $activeContractsDetailed,
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
        ];
    }

    /**
     * Transform a single model for show/edit views with contracts history.
     *
     * @return array<string, mixed>
     */
    public function toItem(Model $model): array
    {
        \assert($model instanceof \App\Models\Local);
        // Ensure relations needed for friendly names and history
        $model->loadMissing(['market:id,name', 'localType:id,name', 'localStatus:id,name', 'localLocation:id,name', 'contracts:id,number,contract_status_id,start_date,end_date', 'contracts.status:id,code,name']);

        $item = $this->toRow($model);

        // Contracts history for this local
        $item['contracts_history'] = $model->contracts
            ->sortBy('start_date')
            ->map(function (\App\Models\Contract $c): array {
                return [
                    'id' => (int) $c->getAttribute('id'),
                    'number' => (string) $c->getAttribute('number'),
                    'status_code' => (string) ($c->getRelationValue('status')?->getAttribute('code') ?: ''),
                    'status' => (string) ($c->getRelationValue('status')?->getAttribute('name') ?: ''),
                    'start_date' => (string) $c->getAttribute('start_date'),
                    'end_date' => (string) ($c->getAttribute('end_date') ?? ''),
                ];
            })
            ->values()
            ->all();

        return $item;
    }

    /** {@inheritDoc} */
    public function delete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        // Block when Local has active contracts
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar el local porque existen contratos asociados.');
        }
        // Block when Local participates in a FINAL condo period
        $inFinalCondo = DB::table('condo_participants as cp')
            ->join('condo_periods as p', 'p.id', '=', 'cp.condo_period_id')
            ->where('cp.local_id', $model->getKey())
            ->whereNull('cp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.status', 'FINAL')
            ->exists();
        if ($inFinalCondo) {
            throw new DomainActionException('No se puede eliminar el local porque participa en un condominio FINAL.');
        }

        return $this->repo->delete($model);
    }

    /** {@inheritDoc} */
    public function forceDelete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar permanentemente el local porque existen contratos asociados.');
        }
        $inFinalCondo = DB::table('condo_participants as cp')
            ->join('condo_periods as p', 'p.id', '=', 'cp.condo_period_id')
            ->whereNull('cp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.status', 'FINAL')
            ->where('cp.local_id', $model->getKey())
            ->exists();
        if ($inFinalCondo) {
            throw new DomainActionException('No se puede eliminar permanentemente el local porque participa en un condominio FINAL.');
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
            'active_concessionaires_text' => 'Concesionarios activos',
            'active_contracts_text' => 'Contratos activos',
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
     * Determine if the given Local has dependent Contracts.
     */
    protected function hasDependencies(Model $model): bool
    {
        return method_exists($model, 'contracts') && $model->contracts()->exists();
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
        $areaM2Total = (float) ((clone $model)->sum('area_m2'));

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
                'area_m2_total' => $areaM2Total,
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
