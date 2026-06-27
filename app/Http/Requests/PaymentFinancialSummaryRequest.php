<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentFinancialSummaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $filters = $this->input('filters', []);

        if (is_string($filters) && $filters !== '') {
            $decoded = json_decode($filters, true);
            $filters = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($filters)) {
            $filters = [];
        }

        $normalized = [];

        if ($this->has('page')) {
            $normalized['page'] = (int) $this->input('page', 1);
        }

        if ($this->has('per_page')) {
            $normalized['per_page'] = (int) $this->input('per_page', 25);
        }

        $normalized['filters'] = $filters;

        $this->merge($normalized);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:200'],
            'filters' => ['sometimes', 'nullable', 'array'],
            'filters.report_type' => ['sometimes', 'nullable', 'string', Rule::in(['income', 'exonerations'])],
            'filters.group_by' => ['sometimes', 'nullable', 'string', Rule::in(['day', 'week', 'month'])],
            'filters.paid_between' => ['sometimes', 'nullable', 'array'],
            'filters.paid_between.from' => ['sometimes', 'nullable', 'date'],
            'filters.paid_between.to' => ['sometimes', 'nullable', 'date', 'after_or_equal:filters.paid_between.from'],
            'filters.method' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reportFilters(): array
    {
        $filters = (array) $this->validated('filters', []);

        return [
            'report_type' => (string) ($filters['report_type'] ?? 'income'),
            'group_by' => (string) ($filters['group_by'] ?? 'day'),
            'paid_between' => (array) ($filters['paid_between'] ?? []),
            'method' => (string) ($filters['method'] ?? ''),
        ];
    }

    public function page(): int
    {
        return max(1, (int) $this->validated('page', 1));
    }

    public function perPage(): int
    {
        return min(max((int) $this->validated('per_page', 25), 10), 200);
    }
}
