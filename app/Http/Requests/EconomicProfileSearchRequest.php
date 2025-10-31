<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EconomicProfileSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled via route middleware (permission:admin.economic_profile.view)
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:concessionaire,local'],
            'q' => ['required', 'string', 'max:120'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
