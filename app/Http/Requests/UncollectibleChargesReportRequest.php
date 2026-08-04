<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UncollectibleChargesReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filters' => ['sometimes', 'array'],
            'filters.marked_between' => ['sometimes', 'array'],
            'filters.marked_between.from' => ['nullable', 'date'],
            'filters.marked_between.to' => ['nullable', 'date', 'after_or_equal:filters.marked_between.from'],
            'filters.status' => ['nullable', Rule::in(['current', 'restored', 'all'])],
            'filters.market_id' => ['nullable', 'integer', 'exists:markets,id'],
            'filters.currency' => ['nullable', Rule::in(['VES', 'USD', 'EUR'])],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
            'format' => ['nullable', Rule::in(['csv', 'json', 'pdf'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reportFilters(): array
    {
        /** @var array<string, mixed> $filters */
        $filters = $this->validated('filters', []);

        return $filters;
    }

    public function search(): string
    {
        return trim((string) $this->validated('q', ''));
    }

    public function page(): int
    {
        return max(1, (int) $this->validated('page', 1));
    }

    public function perPage(): int
    {
        return min(max((int) $this->validated('per_page', 25), 10), 200);
    }

    public function exportFormat(): string
    {
        return (string) $this->validated('format', 'csv');
    }
}
