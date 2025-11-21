<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ContractIndexRequest extends BaseIndexRequest
{
    /**
     * Authorize the request using policies.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('viewAny', \App\Models\Contract::class));
    }

    /**
     * Allowed sortable fields for this resource.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'number', 'start_date', 'end_date', 'created_at', 'locals_count'];
    }

    /**
     * Filter validation rules for this resource.
     *
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'filters.contract_type_id' => ['sometimes', 'nullable', 'integer', 'exists:contract_types,id'],
            'filters.contract_status_id' => ['sometimes', 'nullable', 'integer', 'exists:contract_statuses,id'],
            'filters.contract_modality_id' => ['sometimes', 'nullable', 'integer', 'exists:contract_modalities,id'],
            'filters.trade_category_id' => ['sometimes', 'nullable', 'integer', 'exists:trade_categories,id'],
            'filters.signed' => ['sometimes', 'nullable', 'boolean'],
            'filters.has_active_procedure' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * Additional cross-field validation for ranges, etc.
     */
    protected function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        // no-op
    }
}
