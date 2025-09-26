<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ConcessionaireUpdateRequest extends BaseUpdateRequest
{
    /**
     * Validation rules for updating an existing record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('concessionaire');
        $currentId = is_object($current) ? ($current->id ?? null) : $current;

        return [
            '_version' => ['nullable', 'string'],
            'concessionaire_type_id' => ['bail', 'required', 'integer', 'exists:concessionaire_types,id'],
            'full_name' => ['bail', 'required', 'string', 'min:2', 'max:160'],
            'document_type_id' => ['bail', 'required', 'integer', 'exists:document_types,id'],
            'document_number' => ['bail', 'required', 'string', 'max:30', 'unique:concessionaires,document_number,'.($currentId ?? 'NULL').',id,deleted_at,NULL'],
            'fiscal_address' => ['bail', 'required', 'string', 'min:4', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email:rfc,dns', 'max:160', 'unique:concessionaires,email,'.($currentId ?? 'NULL').',id,deleted_at,NULL'],
            'phone_area_code_id' => ['bail', 'nullable', 'integer', 'exists:phone_area_codes,id'],
            'phone_number' => ['bail', 'nullable', 'string', 'regex:/^[0-9]{7}$/'],
            // File inputs (optional on update)
            'photo' => ['bail', 'nullable', 'file', 'mimetypes:image/png,image/jpeg', 'max:5120'],
            'id_document' => ['bail', 'nullable', 'file', 'mimetypes:application/pdf,image/png,image/jpeg', 'max:5120'],
            'is_active' => ['bail', 'nullable', 'boolean'],
        ];
    }

    /**
     * Normalize input before validation using BaseStoreRequest hook.
     *
     * @param  array<string, mixed>  &$data
     */
    protected function additionalPreparation(array &$data): void
    {
        if (isset($data['full_name']) && is_string($data['full_name'])) {
            $data['full_name'] = trim($data['full_name']);
        }
        if (isset($data['document_number']) && is_string($data['document_number'])) {
            $data['document_number'] = strtoupper(trim($data['document_number']));
        }
        if (isset($data['fiscal_address']) && is_string($data['fiscal_address'])) {
            $data['fiscal_address'] = trim($data['fiscal_address']);
        }
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }
        if (isset($data['phone_number']) && is_string($data['phone_number'])) {
            $data['phone_number'] = trim($data['phone_number']);
        }
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }
    }
}
