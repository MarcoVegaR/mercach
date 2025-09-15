<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class DocumentTypeIndexRequest extends BaseIndexRequest
{
    /**
     * Authorize the request using policies.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('viewAny', \App\Models\DocumentType::class));
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
        ];
    }

    /**
     * Additional cross-field validation for ranges, etc.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $from = data_get($this->all(), 'filters.created_between.from');
            $to = data_get($this->all(), 'filters.created_between.to');
            if ($from && $to && strtotime((string) $to) < strtotime((string) $from)) {
                $v->errors()->add('filters.created_between.to', 'Debe ser >= desde.');
            }
        });
    }
}
