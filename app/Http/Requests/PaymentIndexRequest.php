<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class PaymentIndexRequest extends BaseIndexRequest
{
    /**
     * Authorize the request using policies.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('viewAny', \App\Models\Payment::class));
    }

    /**
     * Allowed sortable fields for this resource.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return [
            'id', 'local_id', 'debtor_type', 'debtor_id', 'company_bank_account_id',
            'method', 'origin_bank_id', 'payer_document_type', 'payer_document_number',
            'payer_account_number', 'payer_phone_e164', 'reference', 'amount_bs_minor',
            'paid_on', 'fx_rate_id', 'status', 'created_at',
        ];
    }

    /**
     * Filter validation rules for this resource.
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'filters.status' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filters.has_available' => ['sometimes', 'nullable', 'boolean'],
            'filters.method' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filters.paid_between' => ['sometimes', 'nullable', 'array'],
            'filters.paid_between.from' => ['sometimes', 'nullable', 'date'],
            'filters.paid_between.to' => ['sometimes', 'nullable', 'date', 'after_or_equal:filters.paid_between.from'],
            'filters.reference_like' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Additional cross-field validation for ranges, etc.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $from = data_get($this->all(), 'filters.paid_between.from');
            $to = data_get($this->all(), 'filters.paid_between.to');
            if ($from && $to && strtotime((string) $to) < strtotime((string) $from)) {
                $v->errors()->add('filters.paid_between.to', 'Debe ser >= desde.');
            }
        });
    }
}
