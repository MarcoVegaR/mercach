<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $contract = $this->route('contract');
        $contractId = is_object($contract) ? (int) ($contract->id ?? 0) : 0;

        /** @var array<string, list<mixed>> $rules */
        $rules = [
            'number' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('contracts', 'number')->ignore($contractId)->whereNull('deleted_at'),
            ],
            'end_date' => ['nullable', 'date'],
            'pdf' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ];

        return $rules;
    }
}
