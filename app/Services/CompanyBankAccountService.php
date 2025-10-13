<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\CompanyBankAccountServiceInterface;
use Illuminate\Database\Eloquent\Model;

class CompanyBankAccountService extends BaseService implements CompanyBankAccountServiceInterface
{
    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'bank_id' => $model->getAttribute('bank_id'),
            'account_number' => $model->getAttribute('account_number'),
            'phone_number' => $model->getAttribute('phone_number'),
            'account_holder_name' => $model->getAttribute('account_holder_name'),
            'document_type' => $model->getAttribute('document_type'),
            'document_number' => $model->getAttribute('document_number'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at') con el shape correcto según --fields.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        // Get bank name if relation is loaded (no N+1) or lazily if needed (single item show/edit)
        /** @var null|\App\Models\Bank $bank */
        $bank = null;
        try {
            if ($model->relationLoaded('bank')) {
                /** @var null|\App\Models\Bank $rel */
                $rel = $model->getRelation('bank');
                $bank = $rel;
            } else {
                // Safe lazy access for single-item pages
                $maybe = $model->getAttribute('bank');
                if ($maybe instanceof \App\Models\Bank) {
                    $bank = $maybe;
                }
            }
        } catch (\Throwable $e) {
            // ignore and leave $bank as null
        }

        return [
            'id' => $model->getAttribute('id'),
            'bank_id' => $model->getAttribute('bank_id'),
            'bank_name' => $bank?->getAttribute('name'),
            'account_number' => $model->getAttribute('account_number'),
            'phone_number' => $model->getAttribute('phone_number'),
            'account_holder_name' => $model->getAttribute('account_holder_name'),
            'document_type' => $model->getAttribute('document_type'),
            'document_number' => $model->getAttribute('document_number'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
        ];
    }

    /**
     * Columnas por defecto de exportación (cabeceras).
     * El generador reemplazará 'id' => '#',
            'bank_id' => 'Bank id',
            'account_number' => 'Account number',
            'phone_number' => 'Phone number',
            'account_holder_name' => 'Account holder name',
            'document_type' => 'Document type',
            'document_number' => 'Document number',
            'is_active' => 'Estado',
            'created_at' => 'Creado'.
     *
     * @return array<string, string|int>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'bank_name' => 'Banco',
            'account_number' => 'Número de cuenta',
            'phone_number' => 'Teléfono',
            'account_holder_name' => 'Titular',
            'document_type' => 'Tipo doc.',
            'document_number' => 'Documento',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\CompanyBankAccount::class;
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\CompanyBankAccount::query();
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
