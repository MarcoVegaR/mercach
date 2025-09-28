<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ContractModality;
use Illuminate\Validation\Rule;

class ContractStoreRequest extends BaseStoreRequest
{
    /**
     * Authorize the request using policies.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create', \App\Models\Contract::class));
    }

    /**
     * Validation rules for creating a new record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number' => ['bail', 'required', 'string', 'max:40', Rule::unique('contracts', 'number')->withoutTrashed()],
            'contract_type_id' => ['bail', 'required', 'integer', 'exists:contract_types,id'],
            'contract_modality_id' => ['bail', 'required', 'integer', 'exists:contract_modalities,id'],
            'trade_category_id' => ['bail', 'required', 'integer', 'exists:trade_categories,id'],
            'start_date' => ['bail', 'required', 'date'],
            'end_date' => ['bail', 'nullable', 'date', 'after_or_equal:start_date'],
            'billing_day' => ['bail', 'nullable', 'integer', 'min:1', 'max:31'],
            'monthly_price_eur' => ['bail', 'nullable', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'pdf' => ['bail', 'nullable', 'file', 'mimetypes:application/pdf', 'max:10240'],
            'is_active' => ['bail', 'nullable', 'boolean'],
            'primary_concessionaire_id' => ['bail', 'required', 'integer', 'exists:concessionaires,id'],
            'additional_concessionaire_ids' => ['bail', 'sometimes', 'array'],
            'additional_concessionaire_ids.*' => ['bail', 'integer', 'distinct', 'different:primary_concessionaire_id', 'exists:concessionaires,id'],
            'local_ids' => ['bail', 'required', 'array', 'min:1'],
            'local_ids.*' => ['bail', 'integer', 'exists:locals,id'],
        ];
    }

    /**
     * Normalize input before validation using BaseStoreRequest hook.
     *
     * @param  array<string, mixed>  &$data
     */
    protected function additionalPreparation(array &$data): void
    {
        if (isset($data['number']) && is_string($data['number'])) {
            $data['number'] = strtoupper(trim($data['number']));
        }
        if (array_key_exists('monthly_price_eur', $data)) {
            $data['monthly_price_eur'] = is_null($data['monthly_price_eur']) ? null : (float) $data['monthly_price_eur'];
        }
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }
        // Normalize concessionaires
        if (isset($data['primary_concessionaire_id'])) {
            $data['primary_concessionaire_id'] = (int) $data['primary_concessionaire_id'];
        }
        if (isset($data['additional_concessionaire_ids']) && is_array($data['additional_concessionaire_ids'])) {
            $add = array_values(array_unique(array_map('intval', $data['additional_concessionaire_ids'])));
            // Exclude primary if present
            if (! empty($data['primary_concessionaire_id'])) {
                $add = array_values(array_filter($add, fn ($v) => (int) $v !== (int) $data['primary_concessionaire_id']));
            }
            $data['additional_concessionaire_ids'] = $add;
        }
        if (isset($data['local_ids']) && is_array($data['local_ids'])) {
            $data['local_ids'] = array_values(array_unique(array_map('intval', $data['local_ids'])));
        }
    }

    /**
     * Post-validation hooks.
     * Useful to derive values without polluting rules (e.g., uuid).
     */
    protected function passedValidation(): void
    {
        // no-op
    }

    /**
     * Additional cross-field validation for modality-specific rules.
     */
    protected function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $modalityId = (int) ($this->input('contract_modality_id') ?? 0);
            if ($modalityId > 0) {
                $mod = ContractModality::find($modalityId);
                if ($mod && strtoupper((string) $mod->code) === 'TFIJA') {
                    // billing_day and monthly_price_eur are required in FIXED (TFIJA)
                    if ($this->input('billing_day') === null || $this->input('billing_day') === '') {
                        $v->errors()->add('billing_day', 'Campo obligatorio para modalidad de Tasa Fija.');
                    }
                    if ($this->input('monthly_price_eur') === null || $this->input('monthly_price_eur') === '') {
                        $v->errors()->add('monthly_price_eur', 'Campo obligatorio para modalidad de Tasa Fija.');
                    }
                }
            }
        });
    }
}
