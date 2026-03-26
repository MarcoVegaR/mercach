<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EconomicProfileStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', 'in:concessionaire,local'],
            'id' => ['required', 'integer', 'min:1'],
            'document' => ['sometimes', 'nullable', 'string', 'in:statement,payment_history'],
            'at' => ['sometimes', 'nullable', 'date'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'kind' => ['sometimes', 'nullable', 'string', 'max:20'],
            'period_from' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'period_to' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'overdue_only' => ['sometimes', 'nullable', 'boolean'],
            'local_ids' => ['sometimes', 'nullable', 'array'],
            'local_ids.*' => ['integer', 'min:1'],
        ];
    }
}
