<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller policy will enforce permissions
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
