<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EconomicProfileShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user(); // permission enforced via route middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'at' => ['sometimes', 'nullable', 'date'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'kind' => ['sometimes', 'nullable', 'string', 'max:20'],
            'period_from' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'period_to' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'overdue_only' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
