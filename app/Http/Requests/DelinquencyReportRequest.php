<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DelinquencyReportRequest extends FormRequest
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

        $this->merge([
            'scope' => $this->input('scope', $filters['scope'] ?? 'concessionaire'),
            'debt_type' => $this->input('debt_type', $filters['debt_type'] ?? 'overdue'),
            'page' => $this->has('page') ? (int) $this->input('page', 1) : null,
            'per_page' => $this->has('per_page') ? (int) $this->input('per_page', 25) : null,
            'limit' => $this->has('limit')
                ? (int) $this->input('limit', 25)
                : ($this->has('per_page') ? (int) $this->input('per_page', 25) : null),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', 'nullable', 'string', Rule::in(['concessionaire', 'local'])],
            'debt_type' => ['sometimes', 'nullable', 'string', Rule::in(['overdue', 'current'])],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:200'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    /**
     * @return array{scope:string,debt_type:string}
     */
    public function reportFilters(): array
    {
        return [
            'scope' => (string) $this->validated('scope', 'concessionaire'),
            'debt_type' => (string) $this->validated('debt_type', 'overdue'),
        ];
    }

    public function page(): int
    {
        return max(1, (int) ($this->validated('page') ?? 1));
    }

    public function perPage(): int
    {
        return min(max((int) ($this->validated('per_page') ?? 25), 10), 200);
    }

    public function exportLimit(): int
    {
        return min(max((int) ($this->validated('limit') ?? $this->validated('per_page') ?? 25), 10), 100);
    }
}
