<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class MarketTariffStoreRequest extends BaseStoreRequest
{
    /**
     * Authorize the request using policies.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create', \App\Models\MarketTariff::class));
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
            // 'code' => ['bail','required','string','max:50', Rule::unique('market_tariffs','code')->withoutTrashed()],
            // 'name' => ['bail','required','string','max:120'],
            // 'is_active' => ['nullable','boolean'],
            // 'sort_order' => ['nullable','integer'],
            'market_id' => ['bail', 'required', 'integer', 'exists:markets,id'],
            'valid_from' => [
                'bail', 'required', 'date_format:Y-m-d',
                Rule::unique('market_tariffs', 'valid_from')
                    ->where(fn ($q) => $q->where('market_id', (int) $this->input('market_id')))
                    ->withoutTrashed(),
            ],
            'price_per_m2_eur_minor' => ['bail', 'required', 'numeric'],
            'is_current' => ['nullable', 'boolean'],
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

    /**
     * Post-validation hooks.
     * Useful to derive values without polluting rules (e.g., uuid).
     */
    protected function passedValidation(): void
    {
        // Example: ensure uuid is set when --uuid-route is enabled

    }
}
