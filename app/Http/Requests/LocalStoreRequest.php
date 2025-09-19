<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class LocalStoreRequest extends BaseStoreRequest
{
    /**
     * Authorize the request using policies.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create', \App\Models\Local::class));
    }

    /**
     * Validation rules for creating a new record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Generated from --fields
            // Example defaults — generator will replace with actual rules from --fields
            // 'code' => ['bail','required','string','max:50', Rule::unique('locals','code')->withoutTrashed()],
            // 'name' => ['bail','required','string','max:120'],
            // 'is_active' => ['nullable','boolean'],
            // 'sort_order' => ['nullable','integer'],
            // Code must match A-01 style and be unique case-insensitively among non-deleted rows
            'code' => [
                'bail', 'required', 'string', 'size:4', 'regex:/^[A-Z]-[0-9]{2}$/',
                Rule::unique('locals', 'code')
                    ->where(fn ($q) => $q->whereRaw('UPPER(code) = ?', [strtoupper((string) $this->input('code'))]))
                    ->withoutTrashed(),
            ],
            'name' => ['bail', 'required', 'string', 'max:160'],
            // Foreign keys must be valid integers and exist
            'market_id' => ['bail', 'required', 'integer', 'exists:markets,id'],
            'local_type_id' => ['bail', 'required', 'integer', 'exists:local_types,id'],
            // local_status_id is set automatically in the service (default DISP), do not accept input
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
        // Cast foreign keys to int
        foreach (['market_id', 'local_type_id', 'local_location_id'] as $fk) {
            if (array_key_exists($fk, $data)) {
                $data[$fk] = is_null($data[$fk]) ? null : (int) $data[$fk];
            }
        }
        // Cast decimals and booleans
        if (array_key_exists('area_m2', $data)) {
            $data['area_m2'] = is_null($data['area_m2']) ? null : (float) $data['area_m2'];
        }
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }
    }

    /**
     * Post-validation hooks.
     * Useful to derive values without polluting rules (e.g., uuid).
     */
    protected function passedValidation(): void
    {
        // Example: ensure uuid is set when --uuid-route is enabled

    }
}
