<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\DebtTransferItemServiceInterface;
use Illuminate\Database\Eloquent\Model;

class DebtTransferItemService extends BaseService implements DebtTransferItemServiceInterface
{
    protected function repoModelClass(): string
    {
        return \App\Models\DebtTransferItem::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        return [
            'id' => $model->getAttribute('id'),
            'debt_transfer_id' => $model->getAttribute('debt_transfer_id'),
            'charge_id' => $model->getAttribute('charge_id'),
            'amount_minor' => (int) $model->getAttribute('amount_minor'),
            'currency' => $model->getAttribute('currency'),
            'period' => $model->getAttribute('period'),
            'issued_on' => $model->getAttribute('issued_on'),
            'due_on' => $model->getAttribute('due_on'),
            'kind' => $model->getAttribute('kind'),
            'prev_debtor_type' => $model->getAttribute('prev_debtor_type'),
            'prev_debtor_id' => $model->getAttribute('prev_debtor_id'),
            'new_debtor_type' => $model->getAttribute('new_debtor_type'),
            'new_debtor_id' => $model->getAttribute('new_debtor_id'),
            'prev_contract_id' => $model->getAttribute('prev_contract_id'),
            'new_contract_id' => $model->getAttribute('new_contract_id'),
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
            'debt_transfer_id' => 'Transfer',
            'charge_id' => 'Charge',
            'amount_minor' => 'Amount (minor)',
            'currency' => 'Currency',
            'period' => 'Period',
            'issued_on' => 'Issued',
            'due_on' => 'Due',
            'kind' => 'Kind',
            'prev_debtor_type' => 'From type',
            'prev_debtor_id' => 'From id',
            'new_debtor_type' => 'To type',
            'new_debtor_id' => 'To id',
            'created_at' => 'Created',
        ];
    }
}
