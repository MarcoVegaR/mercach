<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class FxRateUpdateRequest extends BaseUpdateRequest
{
    /**
     * Custom messages (Spanish) for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'regex' => 'El campo :attribute debe tener como máximo 2 decimales.',
            'in' => 'El valor de :attribute no es válido.',
            'operational_from.unique' => 'Ya existe una tasa con la misma fecha de vigencia (desde) para la moneda seleccionada.',
        ];
    }

    /**
     * Translate attribute names to Spanish for friendly messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'currency_code' => 'moneda',
            'rate_date' => 'fecha de tasa',
            'value_date' => 'fecha valor',
            'published_at' => 'publicado el',
            'rate_to_ves' => 'tasa (VES)',
            'operational_from' => 'vigente desde',
            'operational_to' => 'vigente hasta',
            'source' => 'fuente',
        ];
    }

    /**
     * Validation rules for updating an existing record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('fx_rate');
        $currentId = is_object($current) ? ($current->id ?? null) : $current;

        return [
            // Generated from --fields
            // Example defaults — generator will replace with actual rules from --fields
            // 'code' => ['bail','required','string','max:50', Rule::unique('fx_rates','code')->ignore($currentId)->withoutTrashed()],
            // 'name' => ['bail','required','string','max:120'],
            // 'is_active' => ['nullable','boolean'],
            // 'sort_order' => ['nullable','integer'],
            '_version' => ['nullable', 'string'],
            'currency_code' => ['bail', 'required', 'string', 'size:3'],
            'rate_date' => ['bail', 'required', 'date'],
            'value_date' => ['bail', 'required', 'date'],
            'published_at' => ['bail', 'nullable', 'date'],
            'rate_to_ves' => ['bail', 'required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
            'operational_from' => [
                'bail', 'required', 'date',
                Rule::unique('fx_rates', 'operational_from')
                    ->ignore($currentId)
                    ->where(function ($q) {
                        $code = strtoupper((string) $this->input('currency_code'));

                        return $q->where('currency_code', $code)->whereNull('deleted_at');
                    }),
            ],
            'operational_to' => ['bail', 'nullable', 'date'],
            'source' => ['bail', 'required', 'string', 'max:80', Rule::in(['BCV', 'MANUAL'])],
            'is_official' => ['bail', 'sometimes', 'boolean'],
            'is_active' => ['bail', 'sometimes', 'boolean'],
        ];
    }

    /**
     * Normalize input before validation using BaseStoreRequest hook.
     *
     * @param  array<string, mixed>  &$data
     */
    protected function additionalPreparation(array &$data): void
    {
        // Common normalizations (generator expands these depending on --fields)
        // Uppercase code, trim strings, cast numbers/booleans
        if (isset($data['currency_code']) && is_string($data['currency_code'])) {
            $data['currency_code'] = strtoupper(trim($data['currency_code']));
        }
        if (isset($data['source']) && is_string($data['source'])) {
            $data['source'] = trim($data['source']);
        }
        if (array_key_exists('rate_to_ves', $data)) {
            if (is_null($data['rate_to_ves']) || $data['rate_to_ves'] === '') {
                $data['rate_to_ves'] = null;
            } else {
                $data['rate_to_ves'] = number_format((float) $data['rate_to_ves'], 2, '.', '');
            }
        }
        if (array_key_exists('is_official', $data)) {
            $data['is_official'] = (bool) $data['is_official'];
        }
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }
    }
}
