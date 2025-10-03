<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CondoPeriodUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create', \App\Models\CondoPeriod::class));
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'market_id' => ['required', 'integer', 'exists:markets,id'],
            'period_month' => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $month = $this->input('period_month');
        if (is_string($month) && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $month)) {
            $this->merge(['period' => $month.'-01']);
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
