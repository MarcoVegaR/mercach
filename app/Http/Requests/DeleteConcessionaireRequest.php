<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Concessionaire;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class DeleteConcessionaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('concessionaire');

        return $model instanceof Concessionaire
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
        // For now, no extra business constraints for deleting a concessionaire
        // (soft-deletes are allowed even if active). Add here if required.
    }
}
