<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordConcessionaireLifeProofRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('update', $this->route('concessionaire')));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'life_proof_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'life_proof_at.required' => 'La fecha de fe de vida es obligatoria.',
            'life_proof_at.date' => 'La fecha de fe de vida no es válida.',
            'life_proof_at.before_or_equal' => 'La fecha de fe de vida no puede ser futura.',
        ];
    }
}
