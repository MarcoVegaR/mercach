<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeContractTradeCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('contract')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trade_category_id' => ['required', 'integer', Rule::exists('trade_categories', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'previous_trade_category_id' => ['present', 'nullable', 'integer'],
            '_version' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_category_id.required' => 'Selecciona el nuevo rubro.',
            'trade_category_id.exists' => 'Selecciona un rubro activo.',
            'previous_trade_category_id.present' => 'Recarga el contrato antes de cambiar el rubro.',
            '_version.required' => 'Recarga el contrato antes de cambiar el rubro.',
            '_version.date' => 'La versión del contrato no es válida. Recarga la página.',
            'reason.required' => 'Indica el motivo del cambio.',
            'reason.max' => 'El motivo no puede superar los 1000 caracteres.',
        ];
    }
}
