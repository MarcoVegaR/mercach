<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EconomicProfileExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user(); // permission enforced in route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', 'in:concessionaire,local'],
            'id' => ['required', 'integer', 'min:1'],
            'format' => ['sometimes', 'nullable', 'string', 'in:csv,json'],
            'at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
