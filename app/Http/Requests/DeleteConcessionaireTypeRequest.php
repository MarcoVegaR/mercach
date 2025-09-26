<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ConcessionaireType;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DeleteConcessionaireTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('concessionaire_type');

        return $model instanceof ConcessionaireType
            ? $this->user()?->can('delete', $model) === true
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $model = $this->route('concessionaire_type');
        if (! ($model instanceof ConcessionaireType)) {
            return;
        }

        $hasActiveConcessionaires = DB::table('concessionaires')
            ->where('concessionaire_type_id', $model->getKey())
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasActiveConcessionaires) {
            session()->flash('error', 'No se puede eliminar: hay concesionarios activos asociados a este tipo de concesionario.');
            $validator->errors()->add('concessionaire_type', 'No se puede eliminar: hay concesionarios activos asociados.');
        }
    }
}
