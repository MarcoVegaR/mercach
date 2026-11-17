<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Concessionaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintConcessionaireLocalFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Concessionaire::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'local_code' => ['required_without:local_codes', 'prohibits:local_codes', 'string', 'max:255', 'exists:locals,code,deleted_at,NULL'],
            'local_codes' => ['sometimes', 'required', 'array', 'min:1', Rule::prohibitedIf($this->input('form_type') !== 'solvency')],
            'local_codes.*' => ['required', 'string', 'max:255', 'distinct', 'exists:locals,code,deleted_at,NULL'],
            'form_type' => ['required', 'string', Rule::in(['solvency', 'waiver'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'local_code.required' => 'Debe seleccionar un local del cesionario.',
            'local_code.required_without' => 'Debe seleccionar al menos un local del cesionario.',
            'local_code.prohibits' => 'Usa una sola selección de locales.',
            'local_codes.required' => 'Debe seleccionar al menos un local.',
            'local_codes.min' => 'Debe seleccionar al menos un local.',
            'local_codes.prohibited' => 'La declaración de renuncia debe emitirse para un solo local.',
            'local_codes.*.distinct' => 'No se puede incluir el mismo local más de una vez.',
            'local_codes.*.exists' => 'Uno de los locales seleccionados no está disponible.',
            'local_code.exists' => 'El local seleccionado no está disponible.',
            'form_type.required' => 'Debe seleccionar el tipo de planilla.',
            'form_type.in' => 'El tipo de planilla no es válido.',
        ];
    }
}
