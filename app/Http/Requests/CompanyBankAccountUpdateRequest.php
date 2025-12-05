<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CompanyBankAccountUpdateRequest extends BaseUpdateRequest
{
    /**
     * Custom messages in Spanish.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El :attribute seleccionado no es válido.',
            'string' => 'El campo :attribute debe ser texto.',
            'size' => 'El campo :attribute debe tener exactamente :size caracteres.',
            'max' => 'El campo :attribute no debe exceder :max caracteres.',
            'regex' => 'El formato de :attribute no es válido.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'in' => 'El valor de :attribute no es válido.',
        ];
    }

    /**
     * Spanish attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bank_id' => 'banco',
            'account_number' => 'número de cuenta',
            'phone_number' => 'teléfono',
            'account_holder_name' => 'titular',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'is_active' => 'activo',
            'allow_transfer' => 'permite transferencia',
            'allow_pmov' => 'permite pago móvil',
            'allow_debit' => 'permite débito',
        ];
    }

    /**
     * Validation rules for updating an existing record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('company_bank_account');
        $currentId = is_object($current) ? ($current->id ?? null) : $current;

        return [
            // Generated from --fields
            // Example defaults — generator will replace with actual rules from --fields
            // 'code' => ['bail','required','string','max:50', Rule::unique('company_bank_accounts','code')->ignore($currentId)->withoutTrashed()],
            // 'name' => ['bail','required','string','max:120'],
            // 'is_active' => ['nullable','boolean'],
            // 'sort_order' => ['nullable','integer'],
            '_version' => ['nullable', 'string'],
            'bank_id' => ['bail', 'required', 'integer', 'exists:banks,id'],
            'account_number' => ['bail', 'required', 'string', 'size:20', 'regex:/^\d{20}$/'],
            'phone_number' => ['bail', 'nullable', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'account_holder_name' => ['bail', 'required', 'string', 'max:160'],
            'document_type' => ['bail', 'required', 'string', 'size:1', Rule::in(['J', 'G'])],
            'document_number' => ['bail', 'required', 'string', 'max:12', 'regex:/^\d{6,12}$/'],
            'is_active' => ['bail', 'required', 'boolean'],
            'allow_transfer' => ['bail', 'nullable', 'boolean'],
            'allow_pmov' => ['bail', 'nullable', 'boolean'],
            'allow_debit' => ['bail', 'nullable', 'boolean'],
        ];
    }

    /**
     * Normalize input before validation using BaseStoreRequest hook.
     *
     * @param  array<string, mixed>  &$data
     */
    protected function additionalPreparation(array &$data): void
    {
        // Common normalizations (generator expands these depending on --fields)
        // Uppercase code, trim strings, cast numbers/booleans

        if (isset($data['account_number']) && is_string($data['account_number'])) {
            $data['account_number'] = trim($data['account_number']);
        }
        if (isset($data['phone_number']) && is_string($data['phone_number'])) {
            $data['phone_number'] = trim($data['phone_number']);
        }
        if (isset($data['account_holder_name']) && is_string($data['account_holder_name'])) {
            $data['account_holder_name'] = trim($data['account_holder_name']);
        }
        if (isset($data['document_type']) && is_string($data['document_type'])) {
            $data['document_type'] = strtoupper(trim($data['document_type']));
        }
        if (isset($data['document_number']) && is_string($data['document_number'])) {
            $data['document_number'] = trim($data['document_number']);
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }
        foreach (['allow_transfer', 'allow_pmov', 'allow_debit'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = (bool) $data[$flag];
            }
        }
    }
}
