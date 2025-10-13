<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PaymentUpdateRequest extends BaseUpdateRequest
{
    /**
     * Validation rules for updating an existing record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('payment');
        $currentId = is_object($current) ? ($current->id ?? null) : $current;

        return [
            // Generated from --fields
            // Example defaults — generator will replace with actual rules from --fields
            // 'code' => ['bail','required','string','max:50', Rule::unique('payments','code')->ignore($currentId)->withoutTrashed()],
            // 'name' => ['bail','required','string','max:120'],
            // 'is_active' => ['nullable','boolean'],
            // 'sort_order' => ['nullable','integer'],
            '_version' => ['nullable', 'string'],
            'local_id' => ['bail', 'nullable', 'integer', 'exists:locals,id'],
            'debtor_type' => ['bail', 'required', 'string', 'max:20'],
            'debtor_id' => ['bail', 'required', 'integer'],
            'company_bank_account_id' => ['bail', 'required', 'integer', 'exists:company_bank_accounts,id'],
            'method' => ['bail', 'required', 'string', 'max:20', Rule::exists('payment_types', 'code')->where('is_active', true)],
            'payment_type_id' => ['bail', 'nullable', 'integer', 'exists:payment_types,id'],
            'origin_bank_id' => ['bail', 'required', 'integer', 'exists:banks,id'],
            'payer_document_type' => ['bail', 'required', 'string', 'max:1', Rule::in(['V', 'E', 'J', 'G'])],
            'payer_document_type_id' => ['bail', 'nullable', 'integer', 'exists:document_types,id'],
            'payer_document_number' => ['bail', 'required', 'string', 'max:12'],
            // Bank manual: account (20 digits) for transfer; phone 58XXXXXXXXXX for PMOV
            'payer_account_number' => [
                'bail', 'nullable', 'string', 'size:20', 'regex:/^\d{20}$/', 'required_unless:method,PMOV,DEB',
            ],
            'payer_phone_e164' => [
                'bail', 'nullable', 'string', 'size:12', 'regex:/^58\d{10}$/', 'required_if:method,PMOV',
            ],
            // Reference: transfer requires 6–12 digits; PMOV allows "0" or 6–12 digits
            'reference' => [
                'bail', 'required', 'string',
                Rule::when($this->input('method') === 'PMOV', ['regex:/^(0|\d{6,12})$/'], ['regex:/^\d{6,12}$/']),
            ],
            'amount_bs_minor' => ['bail', 'required', 'integer'],
            'paid_on' => ['bail', 'required', 'date'],
            'fx_rate_id' => ['bail', 'nullable', 'integer', 'exists:fx_rates,id'],
            // status/gateway/idempotency are backend-managed
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El :attribute seleccionado no es válido.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no debe exceder :max caracteres.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'in' => 'El valor de :attribute no es válido.',
            // Campos específicos (mensajes claros para manual del banco)
            'reference.regex' => 'La referencia debe tener entre 6 y 12 dígitos numéricos.',
            'reference.in' => 'Para Pago Móvil, la referencia debe ser exactamente 0.',
            'payer_account_number.required_unless' => 'La cuenta del pagador es obligatoria para transferencias (cuando el método no es PMOV ni DEB).',
            'payer_account_number.regex' => 'La cuenta del pagador debe tener exactamente 20 dígitos numéricos.',
            'payer_account_number.size' => 'La cuenta del pagador debe tener exactamente 20 dígitos.',
            'payer_phone_e164.required_if' => 'El teléfono del pagador es obligatorio para Pago Móvil (formato 58XXXXXXXXXX).',
            'payer_phone_e164.regex' => 'El teléfono del pagador debe iniciar con 58 y tener 10 dígitos adicionales (formato 58XXXXXXXXXX).',
            'payer_phone_e164.size' => 'El teléfono del pagador debe tener exactamente 12 dígitos (58 + 10 dígitos).',
            'method.exists' => 'El método seleccionado no es válido.',
            'payment_type_id.exists' => 'El método de pago no existe.',
            'payer_document_type.in' => 'El tipo de documento del pagador no es válido.',
            'payer_document_type_id.exists' => 'El tipo de documento del pagador no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'local_id' => 'local',
            'debtor_type' => 'tipo de deudor',
            'debtor_id' => 'deudor',
            'company_bank_account_id' => 'cuenta receptora',
            'method' => 'método',
            'origin_bank_id' => 'banco origen',
            'payer_document_type' => 'tipo de documento del pagador',
            'payer_document_number' => 'documento del pagador',
            'payer_account_number' => 'cuenta del pagador',
            'payer_phone_e164' => 'teléfono del pagador',
            'reference' => 'referencia',
            'amount_bs_minor' => 'monto (céntimos)',
            'paid_on' => 'pagado el',
            'fx_rate_id' => 'tasa FX',
            'status' => 'estado',
            'gateway_request' => 'solicitud gateway',
            'gateway_response' => 'respuesta gateway',
            'gateway_resp_code' => 'código de respuesta',
            'gateway_message' => 'mensaje gateway',
            'payer_details' => 'detalles del pagador',
            'idempotency_key' => 'idempotencia',
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

        if (isset($data['debtor_type']) && is_string($data['debtor_type'])) {
            $data['debtor_type'] = strtoupper(trim($data['debtor_type']));
        }
        if (isset($data['method']) && is_string($data['method'])) {
            $data['method'] = strtoupper(trim($data['method']));
        }
        if (isset($data['payer_document_type']) && is_string($data['payer_document_type'])) {
            $data['payer_document_type'] = strtoupper(trim($data['payer_document_type']));
        }
        if (isset($data['payer_document_number']) && is_string($data['payer_document_number'])) {
            $data['payer_document_number'] = preg_replace('/\D+/', '', $data['payer_document_number']);
        }
        if (isset($data['payer_account_number']) && is_string($data['payer_account_number'])) {
            $data['payer_account_number'] = preg_replace('/\D+/', '', $data['payer_account_number']);
        }
        if (isset($data['payer_phone_e164']) && is_string($data['payer_phone_e164'])) {
            $digits = preg_replace('/\D+/', '', $data['payer_phone_e164']);
            if (is_string($digits) && $digits !== '') {
                if (str_starts_with($digits, '58')) {
                    $data['payer_phone_e164'] = substr($digits, 0, 12);
                } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
                    $data['payer_phone_e164'] = '58'.substr($digits, 1, 10);
                } else {
                    $data['payer_phone_e164'] = $digits;
                }
            }
        }
        if (isset($data['reference']) && is_string($data['reference'])) {
            $data['reference'] = trim($data['reference']);
        }
        if (isset($data['status']) && is_string($data['status'])) {
            $data['status'] = trim($data['status']);
        }
        if (isset($data['gateway_request']) && is_string($data['gateway_request'])) {
            $data['gateway_request'] = trim($data['gateway_request']);
        }
        if (isset($data['gateway_response']) && is_string($data['gateway_response'])) {
            $data['gateway_response'] = trim($data['gateway_response']);
        }
        if (isset($data['gateway_resp_code']) && is_string($data['gateway_resp_code'])) {
            $data['gateway_resp_code'] = trim($data['gateway_resp_code']);
        }
        if (isset($data['gateway_message']) && is_string($data['gateway_message'])) {
            $data['gateway_message'] = trim($data['gateway_message']);
        }
        if (isset($data['payer_details']) && is_string($data['payer_details'])) {
            $data['payer_details'] = trim($data['payer_details']);
        }
        if (isset($data['idempotency_key']) && is_string($data['idempotency_key'])) {
            $data['idempotency_key'] = trim($data['idempotency_key']);
        }
        if (array_key_exists('local_id', $data)) {
            $data['local_id'] = is_null($data['local_id']) ? null : (int) $data['local_id'];
        }
        if (array_key_exists('debtor_id', $data)) {
            $data['debtor_id'] = is_null($data['debtor_id']) ? null : (int) $data['debtor_id'];
        }
        if (array_key_exists('company_bank_account_id', $data)) {
            $data['company_bank_account_id'] = is_null($data['company_bank_account_id']) ? null : (int) $data['company_bank_account_id'];
        }
        if (array_key_exists('origin_bank_id', $data)) {
            $data['origin_bank_id'] = is_null($data['origin_bank_id']) ? null : (int) $data['origin_bank_id'];
        }
        if (array_key_exists('amount_bs_minor', $data)) {
            $data['amount_bs_minor'] = is_null($data['amount_bs_minor']) ? null : (int) $data['amount_bs_minor'];
        }
        if (array_key_exists('fx_rate_id', $data)) {
            $data['fx_rate_id'] = is_null($data['fx_rate_id']) ? null : (int) $data['fx_rate_id'];
        }

        // Map method code to FK payment_type_id (keep accepting code from FE)
        if (! empty($data['method'])) {
            try {
                /** @var null|\App\Models\PaymentType $pt */
                $pt = \App\Models\PaymentType::query()->where('code', (string) $data['method'])->first();
                if ($pt) {
                    $data['payment_type_id'] = (int) $pt->getKey();
                }
            } catch (\Throwable $e) { /* ignore */
            }
        }
        // Map payer_document_type code to FK payer_document_type_id
        if (! empty($data['payer_document_type'])) {
            try {
                /** @var null|\App\Models\DocumentType $dt */
                $dt = \App\Models\DocumentType::query()->where('code', (string) $data['payer_document_type'])->first();
                if ($dt) {
                    $data['payer_document_type_id'] = (int) $dt->getKey();
                }
            } catch (\Throwable $e) { /* ignore */
            }
        }

        // If debtor_type is not LOCAL, ensure local_id is null (scope by Concessionaire)
        if (($data['debtor_type'] ?? null) !== 'LOCAL') {
            $data['local_id'] = null;
        }

    }
}
