<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrintConcessionaireLifeProofFormsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('viewAny', \App\Models\Concessionaire::class));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:concessionaires,id,deleted_at,NULL'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ids.required' => 'Debe seleccionar al menos un cesionario.',
            'ids.max' => 'Solo puede imprimir hasta 50 planillas por operación.',
            'ids.*.distinct' => 'La selección contiene cesionarios repetidos.',
            'ids.*.exists' => 'Uno de los cesionarios seleccionados no está disponible.',
        ];
    }
}
