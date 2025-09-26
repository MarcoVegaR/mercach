<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PhoneAreaCode;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DeletePhoneAreaCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('phone_area_code');

        return $model instanceof PhoneAreaCode
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
        $model = $this->route('phone_area_code');
        if (! ($model instanceof PhoneAreaCode)) {
            return;
        }

        $hasActiveConcessionaires = DB::table('concessionaires')
            ->where('phone_area_code_id', $model->getKey())
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasActiveConcessionaires) {
            session()->flash('error', 'No se puede eliminar: hay concesionarios activos asociados a este código de área telefónica.');
            $validator->errors()->add('phone_area_code', 'No se puede eliminar: hay concesionarios activos asociados.');
        }
    }
}
