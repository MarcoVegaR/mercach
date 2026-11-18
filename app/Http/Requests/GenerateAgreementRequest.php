<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', \App\Models\Contract::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'concessionaire_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('concessionaires', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'additional_concessionaire_ids' => ['sometimes', 'array', 'max:50'],
            'additional_concessionaire_ids.*' => ['integer', 'distinct', 'different:concessionaire_id', \Illuminate\Validation\Rule::exists('concessionaires', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'source_contract_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('contracts', 'id')->whereNull('deleted_at')],
            'local_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('locals', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'trade_category_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('trade_categories', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'signature_date' => ['required', 'date_format:Y-m-d'],
            'issuance_token' => ['required', 'uuid'],
            'preview_hash' => [\Illuminate\Validation\Rule::requiredIf($this->routeIs('agreements.store', 'agreements.preview-pdf', 'agreements.preview-word')), 'nullable', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'concessionaire_id.required' => 'Selecciona un cesionario.',
            'concessionaire_id.exists' => 'Selecciona un cesionario activo.',
            'additional_concessionaire_ids.array' => 'Selecciona los cesionarios adicionales de la lista.',
            'additional_concessionaire_ids.max' => 'Puedes seleccionar hasta 50 cesionarios adicionales.',
            'additional_concessionaire_ids.*.exists' => 'Selecciona cesionarios adicionales activos.',
            'additional_concessionaire_ids.*.distinct' => 'No repitas un cesionario adicional.',
            'additional_concessionaire_ids.*.different' => 'El cesionario principal no puede repetirse como adicional.',
            'source_contract_id.exists' => 'El contrato seleccionado ya no está disponible. Selecciona otro o prepara datos nuevos.',
            'local_id.required' => 'Selecciona un local.',
            'local_id.exists' => 'Selecciona un local activo.',
            'trade_category_id.required' => 'Selecciona un rubro.',
            'trade_category_id.exists' => 'Selecciona un rubro activo.',
            'start_date.required' => 'Selecciona la fecha de inicio de vigencia.',
            'signature_date.required' => 'Selecciona la fecha prevista de firma.',
            '*.date_format' => 'Selecciona una fecha válida.',
            'issuance_token.required' => 'Recarga el generador e intenta nuevamente.',
            'issuance_token.uuid' => 'Recarga el generador e intenta nuevamente.',
            'preview_hash.required' => 'Revisa la vista previa antes de emitir.',
            'preview_hash.size' => 'Genera nuevamente la vista previa.',
        ];
    }
}
