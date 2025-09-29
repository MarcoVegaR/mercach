<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\ContractServiceInterface;
use App\Exceptions\DomainActionException;
use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\Local;
use App\Models\LocalStatus;
use App\Models\TradeCategory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractService extends BaseService implements ContractServiceInterface
{
    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'number' => $model->getAttribute('number'),
            'contract_type_id' => $model->getAttribute('contract_type_id'),
            'contract_status_id' => $model->getAttribute('contract_status_id'),
            'contract_modality_id' => $model->getAttribute('contract_modality_id'),
            'trade_category_id' => $model->getAttribute('trade_category_id'),
            'start_date' => $model->getAttribute('start_date'),
            'end_date' => $model->getAttribute('end_date'),
            'billing_day' => $model->getAttribute('billing_day'),
            'monthly_price_eur' => $model->getAttribute('monthly_price_eur'),
            'pdf_path' => $model->getAttribute('pdf_path'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at') con el shape correcto según --fields.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        // Try to avoid N+1 when possible
        $model->loadMissing(['type:id,name', 'status:id,name,code', 'modality:id,name,code', 'tradeCategory:id,name']);
        // Count locals for index and fetch a small list of names for popover
        if (! $model->relationLoaded('locals')) {
            $model->loadCount('locals');
        }

        $pdfPath = (string) ($model->getAttribute('pdf_path') ?? '');
        $pdfFile = $pdfPath !== '' ? basename($pdfPath) : null;

        // Build locals list (limit to 25 names to avoid heavy payload)
        $localsList = [];
        try {
            $localsList = $model->relationLoaded('locals')
                ? $model->getRelation('locals')->take(25)->pluck('name')->all()
                : \App\Models\Local::query()
                    ->whereHas('contracts', fn ($q) => $q->where('contracts.id', $model->getKey()))
                    ->limit(25)
                    ->pluck('name')
                    ->all();
        } catch (\Throwable) {
            $localsList = [];
        }

        return [
            'id' => $model->getAttribute('id'),
            'number' => $model->getAttribute('number'),
            'contract_type_id' => $model->getAttribute('contract_type_id'),
            'contract_status_id' => $model->getAttribute('contract_status_id'),
            'contract_modality_id' => $model->getAttribute('contract_modality_id'),
            'trade_category_id' => $model->getAttribute('trade_category_id'),
            'contract_type' => optional($model->getRelation('type'))->getAttribute('name'),
            'contract_status' => optional($model->getRelation('status'))->getAttribute('name'),
            'contract_status_code' => optional($model->getRelation('status'))->getAttribute('code'),
            'contract_modality' => optional($model->getRelation('modality'))->getAttribute('name'),
            'trade_category' => optional($model->getRelation('tradeCategory'))->getAttribute('name'),
            'start_date' => $model->getAttribute('start_date'),
            'end_date' => $model->getAttribute('end_date'),
            'billing_day' => $model->getAttribute('billing_day'),
            'monthly_price_eur' => $model->getAttribute('monthly_price_eur'),
            'pdf_path' => $pdfPath,
            'pdf_file' => $pdfFile,
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
            'locals_count' => (int) ($model->getAttribute('locals_count') ?? ($model->getRelationValue('locals')?->count() ?: 0)),
            'locals' => array_values(array_map('strval', $localsList)),
        ];
    }

    /**
     * Create contract ensuring only fillable columns are inserted.
     * Keeps relation payload (e.g., local_ids) in attributes for afterCreate hooks.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model
    {
        return $this->transaction(function () use ($attributes) {
            // Allow beforeCreate to normalize and extract rel_* payload
            $this->beforeCreate($attributes);

            // Whitelist of columns present in contracts table
            $columns = [
                'number',
                'contract_type_id',
                'contract_status_id',
                'contract_modality_id',
                'trade_category_id',
                'start_date',
                'end_date',
                'billing_day',
                'monthly_price_eur',
                'pdf_path',
                'is_active',
            ];
            $insert = array_intersect_key($attributes, array_flip($columns));

            // Persist only columns
            $model = $this->repo->create($insert);

            // Run afterCreate with full attributes (including rel_* payload)
            $this->afterCreate($model, $attributes);

            return $model;
        });
    }

    private function recordStatus(Contract $contract, ?string $fromCode, string $toCode, ?string $occurredAt = null): void
    {
        \Illuminate\Support\Facades\DB::table('contract_status_history')->insert([
            'contract_id' => $contract->getKey(),
            'from_code' => $fromCode,
            'to_code' => $toCode,
            'occurred_at' => $occurredAt ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Transform a single model for show/edit views.
     * Adds relation-based fields needed for form prefill.
     *
     * @return array<string, mixed>
     */
    public function toItem(Model $model): array
    {
        \assert($model instanceof Contract);

        // Base attributes
        $item = $this->toRow($model);

        // Ensure relations are available (need local and concessionaire names for UI)
        $model->loadMissing([
            'locals:id,name',
            'concessionaires:id,full_name,document_type_id,document_number',
            'concessionaires.documentType:id,code',
            'extensions:id,contract_id,from_end_date,to_end_date,pdf_path,created_at',
        ]);

        // Locals prefill (ids + lightweight name list for UI when not in options)
        $locals = $model->locals()->get(['locals.id', 'locals.name']);
        $item['local_ids'] = $locals->pluck('id')->map(fn ($v) => (int) $v)->all();
        $item['locals_selected'] = $locals->map(fn ($l) => [
            'id' => (int) $l->getAttribute('id'),
            'name' => (string) $l->getAttribute('name'),
        ])->all();

        // Concessionaires prefill (primary + additional) and selection list for UI
        $primary = null;
        $additional = [];
        $selected = [];
        foreach ($model->concessionaires as $c) {
            $id = (int) $c->getAttribute('id');
            $pv = $c->getRelationValue('pivot');
            $isPrimary = (bool) ($pv?->is_primary);
            if ($isPrimary) {
                $primary = $id;
            } else {
                $additional[] = $id;
            }

            $selected[] = [
                'id' => $id,
                'name' => (string) ($c->getAttribute('full_name') ?? ''),
                'document_type_code' => (string) ($c->getRelationValue('documentType')?->code ?: ''),
                'document_number' => (string) ($c->getAttribute('document_number') ?? ''),
                'is_primary' => $isPrimary,
            ];
        }
        $item['concessionaire_primary_id'] = $primary;
        $item['concessionaire_additional_ids'] = $additional;
        $item['concessionaires_selected'] = $selected;

        // Extensions history (lightweight)
        $item['extensions'] = $model->extensions
            ->sortBy('created_at')
            ->map(fn ($e) => [
                'id' => (int) $e->getAttribute('id'),
                'from_end_date' => (string) $e->getAttribute('from_end_date'),
                'to_end_date' => (string) $e->getAttribute('to_end_date'),
                'pdf_path' => (string) ($e->getAttribute('pdf_path') ?? ''),
                'pdf_file' => (($p = (string) ($e->getAttribute('pdf_path') ?? '')) !== '') ? basename($p) : null,
                'created_at' => (string) $e->getAttribute('created_at'),
            ])->values()->all();

        // Status history (persisted)
        $history = \Illuminate\Support\Facades\DB::table('contract_status_history')
            ->where('contract_id', $model->getKey())
            ->orderBy('occurred_at')
            ->get(['from_code', 'to_code', 'occurred_at'])
            ->map(fn ($row) => [
                'from_code' => (string) ($row->from_code ?? ''),
                'to_code' => (string) ($row->to_code ?? ''),
                'occurred_at' => (string) $row->occurred_at,
            ])->all();

        // Fallback: if VIG not recorded but current status implies it happened, approximate with updated_at
        $codeNow = strtoupper((string) ($model->status?->code ?: ''));
        $hasVig = collect($history)->contains(fn ($h) => strtoupper((string) $h['to_code']) === 'VIG');
        if (! $hasVig && in_array($codeNow, ['VIG', 'EXT', 'TERM', 'VENC'], true)) {
            $occ = (string) ($model->getAttribute('updated_at') ?? $model->getAttribute('created_at'));
            $history[] = [
                'from_code' => 'BORR',
                'to_code' => 'VIG',
                'occurred_at' => $occ,
            ];
            usort($history, fn ($a, $b) => strcmp((string) $a['occurred_at'], (string) $b['occurred_at']));
        }

        $item['status_history'] = $history;

        return $item;
    }

    /**
     * Columnas por defecto de exportación (cabeceras).
     * El generador reemplazará 'id' => '#',
            'number' => 'Number',
            'contract_type_id' => 'Contract type id',
            'contract_status_id' => 'Contract status id',
            'contract_modality_id' => 'Contract modality id',
            'trade_category_id' => 'Trade category id',
            'start_date' => 'Start date',
            'end_date' => 'End date',
            'billing_day' => 'Billing day',
            'monthly_price_eur' => 'Monthly price eur',
            'pdf_path' => 'Pdf path',
            'is_active' => 'Estado',
            'created_at' => 'Creado'.
     *
     * @return array<string, string|int>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'number' => 'Number',
            'contract_type_id' => 'Contract type id',
            'contract_status_id' => 'Contract status id',
            'contract_modality_id' => 'Contract modality id',
            'trade_category_id' => 'Trade category id',
            'start_date' => 'Start date',
            'end_date' => 'End date',
            'billing_day' => 'Billing day',
            'monthly_price_eur' => 'Monthly price eur',
            'pdf_path' => 'Pdf path',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\Contract::class;
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = Contract::query();
        $total = (int) $model->count();
        $active = (int) (clone $model)->where('is_active', true)->count();

        // Filter options (active-only)
        $statuses = ContractStatus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();
        $modalities = ContractModality::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name, 'code' => (string) $m->code])
            ->toArray();
        $tradeCategories = TradeCategory::query()
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
                'contract_statuses' => $statuses,
                'contract_modalities' => $modalities,
                'trade_categories' => $tradeCategories,
            ],
        ];
    }

    // --- Hooks & helpers ---

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeCreate(array &$attributes): void
    {
        // Safety: normalize contract number
        if (isset($attributes['number']) && is_string($attributes['number'])) {
            $attributes['number'] = strtoupper(trim($attributes['number']));
        }

        // Default contract status to BORR if not provided
        if (! isset($attributes['contract_status_id']) || ! is_numeric($attributes['contract_status_id'])) {
            $borrId = $this->getContractStatusIdByCode('BORR');
            if ($borrId) {
                $attributes['contract_status_id'] = $borrId;
            }
        }

        // Extract relation payload to avoid passing arrays to model insert
        foreach (['local_ids', 'primary_concessionaire_id', 'additional_concessionaire_ids', 'concessionaires_pivot', 'concessionaire_ids'] as $k) {
            if (array_key_exists($k, $attributes)) {
                $attributes['rel_'.$k] = $attributes[$k];
                unset($attributes[$k]);
            }
        }

        // Anti-overlap only applies to ACTIVE contracts (VIG/EXT). New contracts default to BORR.
        $this->assertNoOverlap(
            $attributes['rel_local_ids'] ?? [],
            (string) $attributes['start_date'],
            $attributes['end_date'] ?? null,
            (int) $attributes['contract_status_id'],
            null
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function afterCreate(Model $model, array $attributes): void
    {
        \assert($model instanceof Contract);

        // Sync N:M relations
        $this->syncContractRelations($model, $attributes);

        // Store PDF if provided
        $this->maybeStorePdf($model, $attributes['pdf'] ?? null);

        // Apply local status transitions based on contract status
        $this->applyLocalStatusTransitions($model);

        // Record initial BORR event if available
        $model->loadMissing('status:id,code');
        $code = strtoupper((string) ($model->status?->code));
        if ($code !== '') {
            $this->recordStatus($model, null, $code, (string) $model->getAttribute('created_at'));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeUpdate(Model $model, array &$attributes): void
    {
        /** @var Contract $contract */
        $contract = $model;
        if (isset($attributes['number']) && is_string($attributes['number'])) {
            $attributes['number'] = strtoupper(trim($attributes['number']));
        }

        // Business rule: updates only allowed while in BORR
        $contract->loadMissing('status:id,code');
        $currentCode = strtoupper((string) ($contract->status?->code));
        if ($currentCode !== 'BORR') {
            throw new DomainActionException('Solo se puede editar un contrato en estado Borrador.');
        }

        // Determine which locals to validate
        $localIds = $attributes['local_ids'] ?? $contract->locals()->pluck('locals.id')->all();
        $this->assertNoOverlap(
            $localIds,
            (string) ($attributes['start_date'] ?? $contract->getAttribute('start_date')),
            $attributes['end_date'] ?? $contract->getAttribute('end_date'),
            (int) ($attributes['contract_status_id'] ?? $model->getAttribute('contract_status_id')),
            (int) $contract->getKey()
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function afterUpdate(Model $model, array $attributes): void
    {
        \assert($model instanceof Contract);

        $this->syncContractRelations($model, $attributes);
        $this->maybeStorePdf($model, $attributes['pdf'] ?? null, true);
        $this->applyLocalStatusTransitions($model);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function syncContractRelations(Contract $model, array $attributes): void
    {
        $localIds = $attributes['local_ids'] ?? $attributes['rel_local_ids'] ?? null;
        if (is_array($localIds)) {
            $model->locals()->sync($localIds);
        }

        // Build concessionaires sync map from various possible inputs
        $sync = [];
        // 1) Full pivot from UI
        if (isset($attributes['concessionaires_pivot']) && is_array($attributes['concessionaires_pivot'])) {
            foreach ($attributes['concessionaires_pivot'] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $sync[$id] = ['is_primary' => (bool) ($row['is_primary'] ?? false)];
                }
            }
        }
        if (isset($attributes['rel_concessionaires_pivot']) && is_array($attributes['rel_concessionaires_pivot'])) {
            foreach ($attributes['rel_concessionaires_pivot'] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $sync[$id] = ['is_primary' => (bool) ($row['is_primary'] ?? false)];
                }
            }
        }
        // 2) Primary + additional
        $primaryId = $attributes['primary_concessionaire_id'] ?? $attributes['rel_primary_concessionaire_id'] ?? null;
        if (! is_null($primaryId) && (int) $primaryId > 0) {
            $sync[(int) $primaryId] = ['is_primary' => true];
        }
        $additionalIds = $attributes['additional_concessionaire_ids'] ?? $attributes['rel_additional_concessionaire_ids'] ?? null;
        if (is_array($additionalIds)) {
            foreach ($additionalIds as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) {
                    // Don't override primary flag if already set
                    $sync[$cid] = $sync[$cid] ?? ['is_primary' => false];
                }
            }
        }
        // 3) Fallback: plain ids
        $plainIds = $attributes['concessionaire_ids'] ?? $attributes['rel_concessionaire_ids'] ?? null;
        if (empty($sync) && is_array($plainIds)) {
            foreach ($plainIds as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) {
                    $sync[$cid] = ['is_primary' => false];
                }
            }
        }
        if (! empty($sync)) {
            $model->concessionaires()->sync($sync);
        }
    }

    private function maybeStorePdf(Contract $model, mixed $pdf, bool $replace = false): void
    {
        if ($pdf instanceof UploadedFile) {
            if ($replace && $model->pdf_path) {
                $old = ltrim((string) $model->pdf_path, '/');
                $oldInternal = preg_replace('#^storage/#', 'public/', $old) ?? $old;
                Storage::delete($oldInternal);
            }

            $uuid = (string) Str::uuid();
            $dir = 'public/contracts/'.$model->getKey();
            $name = $uuid.'.pdf';
            Storage::putFileAs($dir, $pdf, $name);

            $model->setAttribute('pdf_path', 'storage/contracts/'.$model->getKey().'/'.$name);
            $model->save();
        }
    }

    private function applyLocalStatusTransitions(Contract $contract): void
    {
        $statusId = (int) $contract->getAttribute('contract_status_id');
        $status = ContractStatus::find($statusId);
        if (! $status) {
            return;
        }

        $code = strtoupper((string) $status->code);
        if ($code === 'VIG' || $code === 'EXT') {
            $ocupId = $this->getLocalStatusIdByCode('OCUP');
            if ($ocupId) {
                $contract->locals()->update(['local_status_id' => $ocupId]);
            }
        } elseif (in_array($code, ['TERM', 'VENC'], true)) {
            $dispId = $this->getLocalStatusIdByCode('DISP');
            if ($dispId) {
                $contract->locals()->update(['local_status_id' => $dispId]);
            }
        }
    }

    /**
     * @param  array<int>  $localIds
     *
     * @throws DomainActionException
     */
    private function assertNoOverlap(array $localIds, string $startDate, ?string $endDate, int $statusId, ?int $excludeId = null): void
    {
        if (empty($localIds)) {
            return;
        }

        $status = ContractStatus::find($statusId);
        if (! $status) {
            return;
        }
        $code = strtoupper((string) $status->code);
        if (! in_array($code, ['VIG', 'EXT'], true)) {
            return; // enforce only for ACTIVE-like contracts
        }

        $activeStatusIds = array_values(array_filter([
            $this->getContractStatusIdByCode('VIG'),
            $this->getContractStatusIdByCode('EXT'),
        ], fn ($v) => ! is_null($v)));
        if (empty($activeStatusIds)) {
            return;
        }

        foreach ($localIds as $lid) {
            $exists = Contract::query()
                ->whereIn('contract_status_id', $activeStatusIds)
                ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
                ->whereHas('locals', fn ($q) => $q->where('locals.id', (int) $lid))
                ->where(function ($q) use ($startDate, $endDate) {
                    if (! empty($endDate)) {
                        $q->whereDate('start_date', '<=', $endDate);
                    }
                    $q->where(function ($w) use ($startDate) {
                        $w->whereNull('end_date')->orWhereDate('end_date', '>=', $startDate);
                    });
                })
                ->exists();

            if ($exists) {
                throw new DomainActionException('Ya existe un contrato ACTIVO que se solapa para el local seleccionado.');
            }
        }
    }

    private function getContractStatusIdByCode(string $code): ?int
    {
        return ContractStatus::query()->whereRaw('UPPER(code) = ?', [strtoupper($code)])->value('id');
    }

    private function getLocalStatusIdByCode(string $code): ?int
    {
        return LocalStatus::query()->whereRaw('UPPER(code) = ?', [strtoupper($code)])->value('id');
    }

    /** {@inheritDoc} */
    public function setActive(Model|int|string $modelOrId, bool $active): Model
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        \assert($model instanceof Contract);

        if ($active === false) {
            $model->loadMissing('status:id,code');
            $code = strtoupper((string) ($model->status?->code));
            if ($code !== 'TERM') {
                throw new DomainActionException('Solo se pueden desactivar contratos con estado "Terminado".');
            }
        }

        return parent::setActive($model, $active);
    }

    /** {@inheritDoc} */
    public function delete(Model|int|string $modelOrId): bool
    {
        $model = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);
        \assert($model instanceof Contract);

        $model->loadMissing('status:id,code');
        $code = strtoupper((string) ($model->status?->code));
        if ($code !== 'BORR') {
            throw new DomainActionException('Solo se pueden eliminar contratos en estado "Borrador".');
        }

        // Ensure locals are marked as DISP (idempotent) and detach relations
        $dispId = $this->getLocalStatusIdByCode('DISP');
        if ($dispId) {
            $model->locals()->update(['local_status_id' => $dispId]);
        }
        $model->locals()->detach();
        $model->concessionaires()->detach();

        return parent::delete($model);
    }

    /** {@inheritDoc} */
    public function forceDelete(Model|int|string $modelOrId): bool
    {
        return $this->delete($modelOrId);
    }

    /** {@inheritDoc} */
    public function bulkSetActiveByIds(array $ids, bool $active): int
    {
        if ($active === false) {
            // verify all are TERM before deactivating
            $notTerm = Contract::query()
                ->whereIn('id', $ids)
                ->whereHas('status', fn ($q) => $q->whereRaw('UPPER(code) != ?', ['TERM']))
                ->pluck('number')
                ->all();
            if (! empty($notTerm)) {
                throw new DomainActionException('Solo se pueden desactivar contratos con estado "Terminado". Afectados: '.implode(', ', array_map(fn ($n) => (string) $n, $notTerm)));
            }
        }

        return parent::bulkSetActiveByIds($ids, $active);
    }

    /** {@inheritDoc} */
    public function bulkDeleteByIds(array $ids): int
    {
        // Delete only BORR contracts
        $toDelete = Contract::query()
            ->whereIn('id', $ids)
            ->whereHas('status', fn ($q) => $q->whereRaw('UPPER(code) = ?', ['BORR']))
            ->pluck('id')->all();

        return parent::bulkDeleteByIds($toDelete);
    }

    /** {@inheritDoc} */
    public function bulkForceDeleteByIds(array $ids): int
    {
        return $this->bulkDeleteByIds($ids);
    }

    // --- Domain operations ---

    public function confirm(Contract $contract): Contract
    {
        // Preconditions
        $contract->loadMissing(['status:id,code', 'locals:id,local_status_id', 'concessionaires']);
        $code = strtoupper((string) ($contract->status?->code ?: ''));
        if ($code !== 'BORR') {
            throw new DomainActionException('Solo se puede confirmar un contrato en Borrador.');
        }
        // Exactly one primary signer
        $primaryCount = $contract->concessionaires
            ->filter(static fn ($c) => $c->getRelationValue('pivot')?->is_primary === true)
            ->count();
        if ($primaryCount !== 1) {
            throw new DomainActionException('Debe existir exactamente un firmante principal antes de confirmar.');
        }

        // Lock & transact
        DB::transaction(function () use ($contract) {
            $localIds = $contract->locals()->pluck('locals.id')->map(fn ($v) => (int) $v)->all();
            if (empty($localIds)) {
                throw new DomainActionException('Debe asignar al menos un local antes de confirmar.');
            }

            // Lock locals involved
            Local::query()->whereIn('id', $localIds)->lockForUpdate()->get();

            // Re-validate availability (must be DISP)
            $dispId = $this->getLocalStatusIdByCode('DISP');
            if ($dispId) {
                $notDisp = Local::query()->whereIn('id', $localIds)->where('local_status_id', '!=', $dispId)->pluck('name')->all();
                if (! empty($notDisp)) {
                    throw new DomainActionException('Algunos locales ya no están disponibles: '.implode(', ', $notDisp));
                }
            }

            // Lock any active contracts (VIG/EXT) for these locals to prevent races
            $activeIds = array_values(array_filter([
                $this->getContractStatusIdByCode('VIG'),
                $this->getContractStatusIdByCode('EXT'),
            ]));
            if (! empty($activeIds)) {
                Contract::query()
                    ->whereIn('contract_status_id', $activeIds)
                    ->whereHas('locals', fn ($q) => $q->whereIn('locals.id', $localIds))
                    ->lockForUpdate()
                    ->get(['id']);
            }

            // Recheck expected state fresh from DB
            $fresh = Contract::query()->lockForUpdate()->find($contract->getKey());
            if (! $fresh) {
                throw new DomainActionException('Contrato no encontrado.');
            }
            $fresh->load('status:id,code');
            if (strtoupper((string) ($fresh->status?->code)) !== 'BORR') {
                throw new DomainActionException('El contrato cambió de estado mientras se confirmaba.');
            }

            // Overlap check
            $this->assertNoOverlap(
                $localIds,
                (string) $fresh->getAttribute('start_date'),
                $fresh->getAttribute('end_date') ? (string) $fresh->getAttribute('end_date') : null,
                (int) $this->getContractStatusIdByCode('VIG'),
                (int) $fresh->getKey()
            );

            // Set VIG and apply transitions
            $vigId = $this->getContractStatusIdByCode('VIG');
            if (! $vigId) {
                throw new DomainActionException('Estado VIG no disponible.');
            }
            $fresh->setAttribute('contract_status_id', $vigId);
            $fresh->save();
            $this->applyLocalStatusTransitions($fresh);

            // Record status transition
            $this->recordStatus($fresh, 'BORR', 'VIG');
        });

        return $contract->fresh(['status', 'locals']);
    }

    public function terminate(Contract $contract): Contract
    {
        $contract->loadMissing('status:id,code');
        $code = strtoupper((string) ($contract->status?->code));
        if (! in_array($code, ['VIG', 'EXT'], true)) {
            throw new DomainActionException('Solo se puede terminar un contrato Vigente o Extendido.');
        }

        \DB::transaction(function () use ($contract, $code) {
            // If end_date is null, set to today for historical coherence
            if (empty($contract->getAttribute('end_date'))) {
                $contract->setAttribute('end_date', Carbon::today()->toDateString());
            }
            $termId = $this->getContractStatusIdByCode('TERM');
            $contract->setAttribute('contract_status_id', $termId);
            $contract->save();
            $this->applyLocalStatusTransitions($contract);
            // Record status transition (from previous code to TERM)
            $this->recordStatus($contract, $code, 'TERM');
        });

        return $contract->fresh(['status', 'locals']);
    }

    public function extend(Contract $contract, string $newEndDate, ?UploadedFile $pdf = null): Contract
    {
        $contract->loadMissing('status:id,code');
        $code = strtoupper((string) ($contract->status?->code));
        if (! in_array($code, ['VIG', 'EXT'], true)) {
            throw new DomainActionException('Solo se puede extender un contrato Vigente o Extendido.');
        }

        $currentEnd = $contract->getAttribute('end_date');
        if (! $currentEnd) {
            throw new DomainActionException('No se puede prorrogar sin fecha de fin actual.');
        }
        if (Carbon::parse($newEndDate)->lte(Carbon::parse((string) $currentEnd))) {
            throw new DomainActionException('La nueva fecha de fin debe ser posterior a la actual.');
        }

        DB::transaction(function () use ($contract, $newEndDate, $pdf, $currentEnd) {
            // Lock locals and active contracts to avoid concurrent overlaps
            $localIds = $contract->locals()->pluck('locals.id')->map(fn ($v) => (int) $v)->all();
            if (! empty($localIds)) {
                Local::query()->whereIn('id', $localIds)->lockForUpdate()->get();
                Contract::query()
                    ->whereIn('contract_status_id', array_values(array_filter([
                        $this->getContractStatusIdByCode('VIG'),
                        $this->getContractStatusIdByCode('EXT'),
                    ])))
                    ->whereHas('locals', fn ($q) => $q->whereIn('locals.id', $localIds))
                    ->lockForUpdate()
                    ->get(['id']);
            }

            // Re-check state
            $fresh = Contract::query()->lockForUpdate()->find($contract->getKey());
            if (! $fresh) {
                throw new DomainActionException('Contrato no encontrado.');
            }
            $fresh->load('status:id,code');
            $code = strtoupper((string) ($fresh->status?->code));
            if (! in_array($code, ['VIG', 'EXT'], true)) {
                throw new DomainActionException('El contrato cambió de estado y ya no puede extenderse.');
            }

            // Persist extension record
            $extId = DB::table('contract_extensions')->insertGetId([
                'contract_id' => $fresh->getKey(),
                'from_end_date' => $currentEnd,
                'to_end_date' => $newEndDate,
                'pdf_path' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Store extension PDF if provided
            if ($pdf instanceof UploadedFile) {
                $uuid = (string) Str::uuid();
                $dir = 'public/contracts/'.$fresh->getKey().'/extensions';
                $name = $uuid.'.pdf';
                Storage::putFileAs($dir, $pdf, $name);
                $stored = 'storage/contracts/'.$fresh->getKey().'/extensions/'.$name;
                DB::table('contract_extensions')->where('id', $extId)->update(['pdf_path' => $stored]);
            }

            // Update contract end_date and status to EXT
            $extStatusId = $this->getContractStatusIdByCode('EXT');
            if (! $extStatusId) {
                throw new DomainActionException('Estado EXT no disponible.');
            }
            $fresh->setAttribute('end_date', $newEndDate);
            $fresh->setAttribute('contract_status_id', $extStatusId);
            $fresh->save();

            $this->applyLocalStatusTransitions($fresh); // remains OCUP

            // Record status transition
            $this->recordStatus($fresh, $code, 'EXT');
        });

        return $contract->fresh(['status']);
    }

    /**
     * Mark overdue active contracts (VIG/EXT with end_date < today) as VENC and free locals.
     */
    public function expireOverdue(): int
    {
        $activeIds = array_values(array_filter([
            $this->getContractStatusIdByCode('VIG'),
            $this->getContractStatusIdByCode('EXT'),
        ]));
        $vencId = $this->getContractStatusIdByCode('VENC');
        if (empty($activeIds) || ! $vencId) {
            return 0;
        }

        $today = Carbon::today()->toDateString();
        $affected = 0;
        Contract::query()
            ->whereIn('contract_status_id', $activeIds)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->chunkById(100, function ($chunk) use (&$affected, $vencId) {
                foreach ($chunk as $c) {
                    /** @var Contract $c */
                    DB::transaction(function () use ($c, $vencId) {
                        $c->loadMissing('status:id,code');
                        $prev = strtoupper((string) ($c->status?->code ?: ''));
                        $c->setAttribute('contract_status_id', $vencId);
                        $c->save();
                        $this->applyLocalStatusTransitions($c);
                        $this->recordStatus($c, in_array($prev, ['VIG', 'EXT'], true) ? $prev : null, 'VENC');
                    });
                    $affected++;
                }
            });

        return $affected;
    }
}
