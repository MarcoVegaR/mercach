<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\DebtTransferServiceInterface;
use Illuminate\Database\Eloquent\Model;

class DebtTransferService extends BaseService implements DebtTransferServiceInterface
{
    protected function repoModelClass(): string
    {
        return \App\Models\DebtTransfer::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        return [
            'id' => $model->getAttribute('id'),
            'executed_at' => $model->getAttribute('executed_at'),
            'performed_by_user_id' => $model->getAttribute('performed_by_user_id'),
            'market_id' => $model->getAttribute('market_id'),
            'local_id' => $model->getAttribute('local_id'),
            'from_debtor_type' => $model->getAttribute('from_debtor_type'),
            'from_debtor_id' => $model->getAttribute('from_debtor_id'),
            'to_debtor_type' => $model->getAttribute('to_debtor_type'),
            'to_debtor_id' => $model->getAttribute('to_debtor_id'),
            'new_contract_id' => $model->getAttribute('new_contract_id'),
            'reason_id' => $model->getAttribute('reason_id'),
            'note' => $model->getAttribute('note'),
            'total_amount_minor' => (int) $model->getAttribute('total_amount_minor'),
            'currency' => $model->getAttribute('currency'),
            'created_at' => $model->getAttribute('created_at'),
        ];
    }

    /**
     * @return array<string, string|int>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'executed_at' => 'Executed at',
            'performed_by_user_id' => 'User',
            'market_id' => 'Market',
            'local_id' => 'Local',
            'from_debtor_type' => 'From type',
            'from_debtor_id' => 'From id',
            'to_debtor_type' => 'To type',
            'to_debtor_id' => 'To id',
            'new_contract_id' => 'New contract',
            'reason_id' => 'Reason',
            'total_amount_minor' => 'Total (minor)',
            'currency' => 'Currency',
            'created_at' => 'Created',
        ];
    }
}
