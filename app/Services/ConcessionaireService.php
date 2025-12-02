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

    /** @var array{user_id:int,email:string}|null */
    private ?array $pendingEmailSync = null;

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
        \assert($model instanceof \App\Models\Concessionaire);

        $disk = config('filesystems.uploads_disk', 'public');

        // Locales y contratos "activos" (contratos VIG o VENC) para este concesionario
        $activeContracts = \DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cc.concessionaire_id', $model->getKey())
            ->whereIn('cs.code', ['VIG', 'VENC']) // Incluye VENC porque continúan generando cargos
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->select([
                'c.id as contract_id',
                'c.number as contract_number',
                'l.code as local_code',
            ])
            ->distinct()
            ->get();

        $localsCodes = $activeContracts
            ->pluck('local_code')
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

        $activeContractsDetailed = $activeContracts
            ->map(static fn ($row): array => [
                'id' => (int) ($row->contract_id ?? 0),
                'number' => (string) ($row->contract_number ?? ''),
            ])
            ->filter(static fn (array $row): bool => $row['id'] > 0 && $row['number'] !== '')
            ->unique('id')
            ->values()
            ->all();

        $localsText = '';
        if (! empty($localsCodes)) {
            $localsText = implode("\n", array_map(static fn ($v) => (string) $v, $localsCodes));
        }

        $activeContractsText = '';
        if (! empty($activeContractNumbers)) {
            $activeContractsText = implode("\n", $activeContractNumbers);
        }

        // Whether this concessionaire already has a linked portal user (1:1)
        $hasPortalUser = false;
        try {
            $hasPortalUser = $model->users()->exists();
        } catch (\Throwable $e) {
            $hasPortalUser = false;
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
            'photo_url' => ($model->getAttribute('photo_path')) ? Storage::disk($disk)->url((string) $model->getAttribute('photo_path')) : null,
            'id_document_path' => $model->getAttribute('id_document_path'),
            'id_document_url' => ($model->getAttribute('id_document_path')) ? Storage::disk($disk)->url((string) $model->getAttribute('id_document_path')) : null,
            'active_locals_count' => count($localsCodes),
            'active_locals' => $localsCodes,
            'active_locals_text' => $localsText,
            'active_contract_numbers' => $activeContractNumbers,
            'active_contracts_text' => $activeContractsText,
            'active_contracts_detailed' => $activeContractsDetailed,
            'portal_user_exists' => (bool) $hasPortalUser,
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
        $disk = config('filesystems.uploads_disk', 'public');

        if (isset($attributes['photo']) && $attributes['photo'] instanceof \Illuminate\Http\UploadedFile) {
            $path = Storage::disk($disk)->putFile('concessionaires/photos', $attributes['photo']);
            if ($path) {
                $attributes['photo_path'] = $path;
            }
            unset($attributes['photo']);
        }

        if (isset($attributes['id_document']) && $attributes['id_document'] instanceof \Illuminate\Http\UploadedFile) {
            $path = Storage::disk($disk)->putFile('concessionaires/id_documents', $attributes['id_document']);
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
        $disk = config('filesystems.uploads_disk', 'public');
        // Email sync validations (source-of-truth: Concessionaire.email)
        if (array_key_exists('email', $attributes)) {
            $newEmail = strtolower(trim((string) $attributes['email']));
            $oldEmail = strtolower(trim((string) ($model->getAttribute('email') ?? '')));
            if ($newEmail !== '' && $newEmail !== $oldEmail) {
                /** @var \App\Models\Concessionaire $model */
                // Get currently linked portal user (1:1 policy - first)
                try {
                    $linkedUser = $model->users()->first();
                } catch (\Throwable $e) {
                    $linkedUser = null;
                }

                // If there is an existing user with the new email, it must be the same linked user
                $existingByEmail = \App\Models\User::query()->where('email', $newEmail)->first();
                if ($existingByEmail && (! $linkedUser || (int) $existingByEmail->getKey() !== (int) $linkedUser->getKey())) {
                    // If that user is linked to some concessionaire, block as conflict with another concessionaire
                    $linkedOther = false;
                    try {
                        $linkedOther = $existingByEmail->concessionaires()->exists();
                    } catch (\Throwable $e) {
                        $linkedOther = false;
                    }
                    if ($linkedOther) {
                        throw new \App\Exceptions\DomainActionException('El correo ya está vinculado a otro concesionario.');
                    }
                    // Otherwise, it's used by a standalone user: simple policy = bloquear y pedir resolución manual
                    throw new \App\Exceptions\DomainActionException('El correo ya está en uso por otro usuario.');
                }

                // Schedule email sync after update if there is a linked user
                if ($linkedUser) {
                    $this->pendingEmailSync = ['user_id' => (int) $linkedUser->getKey(), 'email' => $newEmail];
                }
            }
        }

        if (isset($attributes['photo']) && $attributes['photo'] instanceof \Illuminate\Http\UploadedFile) {
            $newPath = Storage::disk($disk)->putFile('concessionaires/photos', $attributes['photo']);
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
            $newPath = Storage::disk($disk)->putFile('concessionaires/id_documents', $attributes['id_document']);
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

        // Perform pending email sync to linked portal user
        if ($this->pendingEmailSync) {
            try {
                $u = \App\Models\User::query()->find((int) $this->pendingEmailSync['user_id']);
                if ($u) {
                    $u->forceFill(['email' => (string) $this->pendingEmailSync['email']])->save();
                }
            } catch (\Throwable $e) {
                // Best-effort; surface via logs if needed (avoid breaking update flow here)
                \Log::warning('No se pudo sincronizar email de usuario de portal tras actualizar concesionario', [
                    'concessionaire_id' => (int) $model->getKey(),
                    'user_id' => (int) $this->pendingEmailSync['user_id'],
                    'error' => $e->getMessage(),
                ]);
            }
            $this->pendingEmailSync = null;
        }
    }

    /**
     * Determine if the given Concessionaire has dependent relationships (contracts).
     */
    protected function hasDependencies(Model $model): bool
    {
        // Block deletion if there are contracts associated with this concessionaire
        return $model instanceof \App\Models\Concessionaire && $model->contracts()->exists();
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
            'active_contracts_text' => 'Contratos activos',
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

        // Portal user linkage (1:1): expose existence for UI actions
        try {
            $hasPortalUser = $model->users()->exists();
        } catch (\Throwable $e) {
            $hasPortalUser = false;
        }
        $item['portal_user_exists'] = (bool) $hasPortalUser;

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
