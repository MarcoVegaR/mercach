<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\DocumentType;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DeleteDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('document_type');

        return $model instanceof DocumentType
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
        $model = $this->route('document_type');
        if (! ($model instanceof DocumentType)) {
            return;
        }

        $hasActiveConcessionaires = DB::table('concessionaires')
            ->where('document_type_id', $model->getKey())
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasActiveConcessionaires) {
            session()->flash('error', 'No se puede eliminar: hay concesionarios activos asociados a este tipo de documento.');
            $validator->errors()->add('document_type', 'No se puede eliminar: hay concesionarios activos asociados.');
        }
    }
}
