<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class MarketTariffUpdateRequest extends BaseUpdateRequest
{
    /**
     * Validation rules for updating an existing record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('market_tariff');
        $currentId = is_object($current) ? ($current->id ?? null) : $current;

        return [
            // Generated from --fields
            // Example defaults — generator will replace with actual rules from --fields
            // 'code' => ['bail','required','string','max:50', Rule::unique('market_tariffs','code')->ignore($currentId)->withoutTrashed()],
            // 'name' => ['bail','required','string','max:120'],
            // 'is_active' => ['nullable','boolean'],
            // 'sort_order' => ['nullable','integer'],
            '_version' => ['nullable', 'string'],
            'market_id' => ['bail', 'required', 'integer', 'exists:markets,id'],
            'valid_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'price_per_m2_eur_minor' => ['bail', 'required', 'numeric'],
            'is_current' => ['bail', 'required', 'boolean'],
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
        if (array_key_exists('market_id', $data)) {
            $data['market_id'] = is_numeric($data['market_id']) ? (int) $data['market_id'] : $data['market_id'];
        }
        if (array_key_exists('price_per_m2_eur_minor', $data)) {
            $val = $data['price_per_m2_eur_minor'];
            if (is_string($val) && str_contains($val, '.')) {
                $data['price_per_m2_eur_minor'] = (int) round(((float) $val) * 100);
            } elseif (is_float($val)) {
                $data['price_per_m2_eur_minor'] = (int) round($val * 100);
            } else {
                $data['price_per_m2_eur_minor'] = (int) $val;
            }
        }
        if (array_key_exists('is_current', $data)) {
            $data['is_current'] = (bool) $data['is_current'];
        }
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }
    }
}
