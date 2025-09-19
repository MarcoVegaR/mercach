<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class LocalUpdateRequest extends BaseUpdateRequest
{
    /**
     * Validation rules for updating an existing record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('local');
        $currentId = is_object($current) ? ($current->id ?? null) : $current;

        return [
            // Generated from --fields
            // Example defaults — generator will replace with actual rules from --fields
            // 'code' => ['bail','required','string','max:50', Rule::unique('locals','code')->ignore($currentId)->withoutTrashed()],
            // 'name' => ['bail','required','string','max:120'],
            // 'is_active' => ['nullable','boolean'],
            // 'sort_order' => ['nullable','integer'],
            '_version' => ['nullable', 'string'],
            'code' => [
                'bail', 'required', 'string', 'size:4', 'regex:/^[A-Z]-[0-9]{2}$/',
                Rule::unique('locals', 'code')
                    ->ignore($currentId)
                    ->where(fn ($q) => $q->whereRaw('UPPER(code) = ?', [strtoupper((string) $this->input('code'))]))
                    ->withoutTrashed(),
            ],
            'name' => ['bail', 'required', 'string', 'max:160'],
            'market_id' => ['bail', 'required', 'integer', 'exists:markets,id'],
            'local_type_id' => ['bail', 'required', 'integer', 'exists:local_types,id'],
            // local_status_id is managed server-side and not editable
            'local_location_id' => ['bail', 'required', 'integer', 'exists:local_locations,id'],
            'area_m2' => ['bail', 'required', 'numeric', 'min:0'],
            'is_active' => ['bail', 'required', 'boolean'],
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
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = trim($data['code']);
        }
        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }
        foreach (['market_id', 'local_type_id', 'local_location_id'] as $fk) {
            if (array_key_exists($fk, $data)) {
                $data[$fk] = is_null($fk_val = $data[$fk]) ? null : (int) $data[$fk];
            }
        }
        if (array_key_exists('area_m2', $data)) {
            $data['area_m2'] = is_null($data['area_m2']) ? null : (float) $data['area_m2'];
        }
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }
    }
}
