<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Concessionaire;
use Illuminate\Foundation\Http\FormRequest;

class SetConcessionaireActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('concessionaire');

        return $model instanceof Concessionaire
            ? $this->user()?->can('setActive', $model) === true
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }
}
