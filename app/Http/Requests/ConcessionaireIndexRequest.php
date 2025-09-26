<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class ConcessionaireIndexRequest extends BaseIndexRequest
{
    /**
     * Authorize the request using policies.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('viewAny', \App\Models\Concessionaire::class));
    }

    /**
     * Allowed sortable fields for this resource.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'full_name', 'email', 'document_number', 'is_active', 'created_at'];
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
            'filters.full_name_like' => ['sometimes', 'nullable', 'string', 'max:160'],
            'filters.email_like' => ['sometimes', 'nullable', 'string', 'max:160'],
            'filters.document_number_like' => ['sometimes', 'nullable', 'string', 'max:30'],
            'filters.concessionaire_type_id' => ['sometimes', 'nullable', 'integer', 'exists:concessionaire_types,id'],
        ];
    }

    /**
     * Additional cross-field validation for ranges, etc.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $from = data_get($this->all(), 'filters.created_between.from');
            $to = data_get($this->all(), 'filters.created_between.to');
            if ($from && $to && strtotime((string) $to) < strtotime((string) $from)) {
                $v->errors()->add('filters.created_between.to', 'Debe ser >= desde.');
            }
        });
    }
}
