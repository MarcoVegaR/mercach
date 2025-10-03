<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CondoParticipantBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'market_id' => ['required', 'integer', 'exists:markets,id'],
            'period_month' => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.local_id' => ['required', 'integer', 'exists:locals,id', 'distinct'],
            'items.*.included' => ['required', 'boolean'],
            'items.*.area_m2_snapshot' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        $month = $this->input('period_month');
        if (is_string($month) && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $month)) {
            $data['period'] = $month.'-01';
        }

        if (! empty($data)) {
            $merged = array_replace_recursive($this->all(), $data);
            $this->replace($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        $data['period'] = $this->input('period');

        return $data;
    }
}
