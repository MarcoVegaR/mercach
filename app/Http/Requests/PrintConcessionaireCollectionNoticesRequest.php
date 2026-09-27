<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Concessionaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintConcessionaireCollectionNoticesRequest extends FormRequest
{
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
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:concessionaires,id,deleted_at,NULL'],
            'notice_type' => ['required', 'string', Rule::in(['ordinary', 'payment_agreement'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ids.required' => 'Debe seleccionar al menos un cesionario.',
            'ids.max' => 'Solo puede imprimir hasta 50 avisos por operación.',
            'ids.*.distinct' => 'La selección contiene cesionarios repetidos.',
            'ids.*.exists' => 'Uno de los cesionarios seleccionados no está disponible.',
            'notice_type.required' => 'Debe seleccionar el tipo de aviso de cobro.',
            'notice_type.in' => 'El tipo de aviso de cobro seleccionado no es válido.',
        ];
    }
}
