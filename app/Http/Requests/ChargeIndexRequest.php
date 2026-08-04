<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ChargeIndexRequest extends BaseIndexRequest
{
    protected function allowedSorts(): array
    {
        return [
            'id', 'market_id', 'local_id', 'contract_id', 'condo_period_id',
            'debtor_type', 'debtor_id', 'origin_debtor_type', 'origin_debtor_id',
            'kind', 'currency', 'amount_minor', 'period', 'issued_on', 'due_on',
            'charge_status_id', 'source', 'uncollectible_at', 'created_at',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'filters.market_id' => ['nullable', 'integer'],
            'filters.local_id' => ['nullable', 'integer'],
            'filters.concessionaire_id' => ['nullable', 'integer'],
            'filters.contract_id' => ['nullable', 'integer'],
            'filters.condo_period_id' => ['nullable', 'integer'],
            'filters.debtor_type' => ['nullable', 'string'],
            'filters.debtor_id' => ['nullable', 'integer'],
            'filters.kind' => ['nullable', 'string'],
            'filters.currency' => ['nullable', 'string', 'size:3'],
            'filters.period_between' => ['nullable', 'array'],
            'filters.period_between.from' => ['nullable', 'date'],
            'filters.period_between.to' => ['nullable', 'date', 'after_or_equal:filters.period_between.from'],
            'filters.issued_on_between' => ['nullable', 'array'],
            'filters.issued_on_between.from' => ['nullable', 'date'],
            'filters.issued_on_between.to' => ['nullable', 'date', 'after_or_equal:filters.issued_on_between.from'],
            'filters.due_on_between' => ['nullable', 'array'],
            'filters.due_on_between.from' => ['nullable', 'date'],
            'filters.due_on_between.to' => ['nullable', 'date', 'after_or_equal:filters.due_on_between.from'],
            'filters.charge_status_id' => ['nullable', 'integer'],
            'filters.collectibility' => ['nullable', 'string', 'in:collectible,uncollectible,all'],
        ];
    }
}
