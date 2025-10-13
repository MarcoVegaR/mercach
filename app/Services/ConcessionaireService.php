<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\ConcessionaireServiceInterface;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ConcessionaireService extends BaseService implements ConcessionaireServiceInterface
{
    /** @var array<int, string> */
    private array $pendingFileDeletes = [];

    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'concessionaire_type_id' => $model->getAttribute('concessionaire_type_id'),
            'full_name' => $model->getAttribute('full_name'),
            'document_type_id' => $model->getAttribute('document_type_id'),
            'document_number' => $model->getAttribute('document_number'),
            'fiscal_address' => $model->getAttribute('fiscal_address'),
            'email' => $model->getAttribute('email'),
            'phone_area_code_id' => $model->getAttribute('phone_area_code_id'),
            'phone_number' => $model->getAttribute('phone_number'),
            'photo_path' => $model->getAttribute('photo_path'),
            'id_document_path' => $model->getAttribute('id_document_path'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at') con el shape correcto según --fields.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        $today = \Carbon\Carbon::today()->toDateString();
        $localsCodes = \DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cc.concessionaire_id', $model->getKey())
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereDate('c.start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $today);
            })
            ->select('l.code')
            ->distinct()
            ->pluck('l.code')
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        $localsText = '';
        if (! empty($localsCodes)) {
            $localsText = implode("\n", array_map(static fn ($v) => (string) $v, $localsCodes));
        }

        return [
            'id' => $model->getAttribute('id'),
            'concessionaire_type_id' => $model->getAttribute('concessionaire_type_id'),
            'full_name' => $model->getAttribute('full_name'),
            'document_type_id' => $model->getAttribute('document_type_id'),
            'concessionaire_type_name' => $model->getRelationValue('concessionaireType')?->getAttribute('name'),
            'document_type_code' => $model->getRelationValue('documentType')?->getAttribute('code'),
            'document_type_name' => $model->getRelationValue('documentType')?->getAttribute('name'),
            'document_number' => $model->getAttribute('document_number'),
            'fiscal_address' => $model->getAttribute('fiscal_address'),
            'email' => $model->getAttribute('email'),
            'phone_area_code_id' => $model->getAttribute('phone_area_code_id'),
            'phone_number' => $model->getAttribute('phone_number'),
            'photo_path' => $model->getAttribute('photo_path'),
            'photo_url' => ($model->getAttribute('photo_path')) ? Storage::disk('public')->url((string) $model->getAttribute('photo_path')) : null,
            'id_document_path' => $model->getAttribute('id_document_path'),
            'id_document_url' => ($model->getAttribute('id_document_path')) ? Storage::disk('public')->url((string) $model->getAttribute('id_document_path')) : null,
            'active_locals_count' => count($localsCodes),
            'active_locals' => $localsCodes,
            'active_locals_text' => $localsText,
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
        ];
    }

    /**
     * Subir archivos en create y setear rutas.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeCreate(array &$attributes): void
    {
        if (isset($attributes['photo']) && $attributes['photo'] instanceof \Illuminate\Http\UploadedFile) {
            $path = Storage::disk('public')->putFile('concessionaires/photos', $attributes['photo']);
            if ($path) {
                $attributes['photo_path'] = $path;
            }
            unset($attributes['photo']);
        }

        if (isset($attributes['id_document']) && $attributes['id_document'] instanceof \Illuminate\Http\UploadedFile) {
            $path = Storage::disk('public')->putFile('concessionaires/id_documents', $attributes['id_document']);
            if ($path) {
                $attributes['id_document_path'] = $path;
            }
            unset($attributes['id_document']);
        }
    }

    /**
     * Subir y reemplazar archivos en update, encolando antiguos para borrar.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeUpdate(Model $model, array &$attributes): void
    {
        if (isset($attributes['photo']) && $attributes['photo'] instanceof \Illuminate\Http\UploadedFile) {
            $newPath = Storage::disk('public')->putFile('concessionaires/photos', $attributes['photo']);
            if ($newPath) {
                $oldPath = (string) ($model->getAttribute('photo_path') ?? '');
                if ($oldPath !== '') {
                    $this->pendingFileDeletes[] = $oldPath;
                }
                $attributes['photo_path'] = $newPath;
            }
            unset($attributes['photo']);
        }

        if (isset($attributes['id_document']) && $attributes['id_document'] instanceof \Illuminate\Http\UploadedFile) {
            $newPath = Storage::disk('public')->putFile('concessionaires/id_documents', $attributes['id_document']);
            if ($newPath) {
                $oldPath = (string) ($model->getAttribute('id_document_path') ?? '');
                if ($oldPath !== '') {
                    $this->pendingFileDeletes[] = $oldPath;
                }
                $attributes['id_document_path'] = $newPath;
            }
            unset($attributes['id_document']);
        }
    }

    /**
     * Borrar archivos antiguos tras update exitoso.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function afterUpdate(Model $model, array $attributes): void
    {
        foreach ($this->pendingFileDeletes as $old) {
            try {
                Storage::disk('public')->delete($old);
            } catch (\Throwable) {
                // ignore
            }
        }
        $this->pendingFileDeletes = [];
    }

    /**
     * Determine if the given Concessionaire has dependent relationships (contracts).
     */
    protected function hasDependencies(Model $model): bool
    {
        // Block deletion if there are contracts associated with this concessionaire
        return method_exists($model, 'contracts') && $model->contracts()->exists();
    }

    /** {@inheritDoc} */
    public function delete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar el concesionario porque existen contratos asociados.');
        }

        return $this->repo->delete($model);
    }

    /** {@inheritDoc} */
    public function forceDelete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        if ($this->hasDependencies($model)) {
            throw new DomainActionException('No se puede eliminar permanentemente el concesionario porque existen contratos asociados.');
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
            'concessionaire_type_id' => 'Concessionaire type id',
            'full_name' => 'Full name',
            'document_type_id' => 'Document type id',
            'document_number' => 'Document number',
            'fiscal_address' => 'Fiscal address',
            'email' => 'Email',
            'phone_area_code_id' => 'Phone area code id',
            'phone_number' => 'Phone number',
            'photo_path' => 'Photo path',
            'id_document_path' => 'Id document path',
            'is_active' => 'Estado',
            'created_at' => 'Creado'.
     *
     * @return array<string, string|int>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'concessionaire_type_name' => 'Tipo de concesionario',
            'full_name' => 'Nombre completo',
            'document_type_name' => 'Tipo de documento',
            'document_number' => 'Número de documento',
            'fiscal_address' => 'Dirección fiscal',
            'email' => 'Correo electrónico',
            'phone_area_code_id' => 'Código área',
            'phone_number' => 'Teléfono',
            'photo_path' => 'Foto (ruta)',
            'id_document_path' => 'Documento ID (ruta)',
            'active_locals_text' => 'Locales activos',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\Concessionaire::class;
    }

    /**
     * Transform a single model for show/edit views with contracts history.
     *
     * @return array<string, mixed>
     */
    public function toItem(Model $model): array
    {
        \assert($model instanceof \App\Models\Concessionaire);
        $model->loadMissing(['concessionaireType:id,name', 'documentType:id,code,name', 'contracts:id,number,contract_status_id,start_date,end_date', 'contracts.status:id,code,name']);

        $item = $this->toRow($model);

        $item['contracts_history'] = $model->contracts
            ->sortBy('start_date')
            ->map(fn ($c) => [
                'id' => (int) $c->getAttribute('id'),
                'number' => (string) $c->getAttribute('number'),
                'status_code' => (string) $c->status->code,
                'status' => (string) $c->status->name,
                'start_date' => (string) $c->getAttribute('start_date'),
                'end_date' => (string) ($c->getAttribute('end_date') ?? ''),
            ])
            ->values()
            ->all();

        return $item;
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\Concessionaire::query();
        $total = (int) $model->count();
        $active = (int) (clone $model)->where('is_active', true)->count();

        return [
            'stats' => [
                'total' => $total,
                'active' => $active,
            ],
            'filterOptions' => [
                'concessionaire_types' => \App\Models\ConcessionaireType::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
                    ->toArray(),
            ],
        ];
    }
}
