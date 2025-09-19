<?php

declare(strict_types=1);

namespace App\Http\Requests;

class LocalIndexRequest extends BaseIndexRequest
{
    /**
     * Authorize the request using policies.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('viewAny', \App\Models\Local::class));
    }

    /**
     * Allowed sortable fields for this resource.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        // The generator will replace defaults based on --fields
        return ['id', 'code', 'name', 'is_active', 'sort_order', 'created_at'];
    }

    /**
     * Filter validation rules for this resource.
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'filters.is_active' => ['sometimes', 'nullable', 'boolean'],
            'filters.created_between' => ['sometimes', 'nullable', 'array'],
            'filters.created_between.from' => ['sometimes', 'nullable', 'date'],
            'filters.created_between.to' => ['sometimes', 'nullable', 'date', 'after_or_equal:filters.created_between.from'],
            'filters.code_like' => ['sometimes', 'nullable', 'string', 'max:50'],
            'filters.name_like' => ['sometimes', 'nullable', 'string', 'max:120'],
            // Foreign key filters
            'filters.market_id' => ['sometimes', 'nullable', 'integer', 'exists:markets,id'],
            'filters.local_type_id' => ['sometimes', 'nullable', 'integer', 'exists:local_types,id'],
            'filters.local_status_id' => ['sometimes', 'nullable', 'integer', 'exists:local_statuses,id'],
            'filters.local_location_id' => ['sometimes', 'nullable', 'integer', 'exists:local_locations,id'],
            // Optional area range (if used later)
            'filters.area_m2_between' => ['sometimes', 'nullable', 'array'],
            'filters.area_m2_between.from' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'filters.area_m2_between.to' => ['sometimes', 'nullable', 'numeric', 'gte:filters.area_m2_between.from'],
        ];
    }

    /**
     * Additional cross-field validation for ranges, etc.
     */
    protected function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $from = data_get($this->all(), 'filters.created_between.from');
            $to = data_get($this->all(), 'filters.created_between.to');
            if ($from && $to && strtotime((string) $to) < strtotime((string) $from)) {
                $v->errors()->add('filters.created_between.to', 'Debe ser >= desde.');
            }
        });
    }
}
