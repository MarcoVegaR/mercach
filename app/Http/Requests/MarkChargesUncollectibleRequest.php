<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkChargesUncollectibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('charges.collectibility.mark');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['sometimes', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    /**
     * @return list<int>
     */
    public function chargeIds(?int $routeChargeId = null): array
    {
        if ($routeChargeId !== null && $routeChargeId > 0) {
            return [$routeChargeId];
        }

        return array_values(array_unique(array_map('intval', (array) $this->validated('ids', []))));
    }
}
