<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ExtendContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $contract = $this->route('contract');
        $end = is_object($contract) ? ($contract->end_date ?? null) : null;
        $after = $end ? 'after:'.Carbon::parse((string) $end)->toDateString() : 'after:today';

        /** @var array<string, list<string>> $rules */
        $rules = [
            'new_end_date' => ['required', 'date', $after],
            'extension_pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ];

        return $rules;
    }
}
