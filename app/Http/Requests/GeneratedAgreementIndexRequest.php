<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GeneratedAgreementIndexRequest extends FormRequest
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
            'tab' => ['nullable', 'in:new,history'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', \Illuminate\Validation\Rule::when($this->filled('from'), 'after_or_equal:from')],
        ];
    }

    public function messages(): array
    {
        return [
            'tab.in' => 'Selecciona una sección válida.',
            'per_page.in' => 'Selecciona una cantidad de filas válida.',
            'q.max' => 'La búsqueda admite hasta 100 caracteres.',
            'from.date_format' => 'Selecciona una fecha inicial válida.',
            'to.date_format' => 'Selecciona una fecha final válida.',
            'to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
