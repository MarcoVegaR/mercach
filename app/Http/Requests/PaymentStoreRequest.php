<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PaymentStoreRequest extends BaseStoreRequest
{
    /**
     * Authorize the request using policies.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create', \App\Models\Payment::class));
    }

    /**
     * Add cross-field and cross-table validations.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            try {
                $method = strtoupper((string) $this->input('method', ''));
                $originBankId = (int) $this->input('origin_bank_id');
                $payerAcct = preg_replace('/\D+/', '', (string) $this->input('payer_account_number', '')) ?? '';

                // 211 Transfer: ensure origin bank_code matches first 4 digits of payer account (optional)
                if (config('payments.validation.strict_origin_bank_match', false)) {
                    if ($method !== 'PMOV' && $method !== 'DEB') {
                        if ($originBankId > 0 && strlen($payerAcct) >= 4) {
                            $bank = \App\Models\Bank::query()->find($originBankId);
                            $bankCode = '';
                            if ($bank) {
                                $raw = $bank->getAttribute('bank_code');
                                $bankCode = is_string($raw) ? trim((string) $raw) : '';
                            }
                            if ($bankCode !== '' && substr($payerAcct, 0, 4) !== $bankCode) {
                                $v->errors()->add('origin_bank_id', 'El banco origen no coincide con la cuenta del pagador.');
                            }
                        }
                    }
                }

                // 300 PMOV: require destination phone on company account
                if ($method === 'PMOV') {
                    $companyId = (int) $this->input('company_bank_account_id');
                    if ($companyId > 0) {
                        $acc = \App\Models\CompanyBankAccount::query()->find($companyId);
                        $phone = '';
                        if ($acc) {
                            $phone = (string) ($acc->getAttribute('phone_number') ?? '');
                        }
                        if (preg_match('/^58\d{10}$/', $phone) !== 1) {
                            $v->errors()->add('company_bank_account_id', 'La cuenta receptora no soporta Pago Móvil (teléfono 58XXXXXXXXXX requerido).');
                        }
                    }
                }

                // Reference: for all methods except EXO, require 6-12 digit numeric reference
                if ($method !== 'EXO') {
                    $ref = (string) $this->input('reference', '');
                    if (preg_match('/^\d{6,12}$/', $ref) !== 1) {
                        $v->errors()->add('reference', 'La referencia debe tener entre 6 y 12 dígitos numéricos.');
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    /**
     * Validation rules for creating a new record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Generated from --fields
            // Example defaults — generator will replace with actual rules from --fields
            // 'code' => ['bail','required','string','max:50', Rule::unique('payments','code')->withoutTrashed()],
            // 'name' => ['bail','required','string','max:120'],
            // 'is_active' => ['nullable','boolean'],
            // 'sort_order' => ['nullable','integer'],
            'local_id' => ['bail', 'nullable', 'integer', 'exists:locals,id', 'required_if:debtor_type,LOCAL'],
            'debtor_type' => ['bail', 'required', 'string', 'max:20', Rule::in(['CONCESSIONAIRE', 'LOCAL'])],
            'debtor_id' => ['bail', 'required', 'integer'],
            'company_bank_account_id' => ['bail', 'required_unless:method,EXO', 'nullable', 'integer', 'exists:company_bank_accounts,id'],
            'method' => ['bail', 'required', 'string', 'max:20'],
            'payment_type_id' => ['bail', 'nullable', 'integer', 'exists:payment_types,id'],
            'origin_bank_id' => ['bail', 'nullable', 'integer', 'exists:banks,id', 'required_unless:method,PMOV,DEB,EXO'],
            'payer_document_type' => ['bail', 'exclude_if:method,EXO', 'required', 'string', 'max:1', Rule::in(['V', 'E', 'J', 'G'])],
            'payer_document_type_id' => ['bail', 'exclude_if:method,EXO', 'nullable', 'integer', 'exists:document_types,id'],
            'payer_document_number' => ['bail', 'exclude_if:method,EXO', 'required', 'string', 'max:12', 'regex:/^\d{7,12}$/'],
            // Bank manual: account (20 digits) for transfer; phone 58XXXXXXXXXX for PMOV
            'payer_account_number' => [
                'bail', 'nullable', 'string', 'size:20', 'regex:/^\d{20}$/', 'required_unless:method,PMOV,DEB,EXO',
            ],
            // Support either E.164 or area code + number for PMOV (exclude for other methods)
            'payer_phone_area_code' => [
                'bail', 'exclude_unless:method,PMOV', 'nullable', 'string', 'regex:/^0\d{3}$/',
                // Requerido solo si no se envía E.164; si se usa uno, exigir el par
                'required_without:payer_phone_e164', 'required_with:payer_phone_number',
            ],
            'payer_phone_number' => [
                'bail', 'exclude_unless:method,PMOV', 'nullable', 'string', 'regex:/^\d{7}$/',
                'required_without:payer_phone_e164', 'required_with:payer_phone_area_code',
            ],
            'payer_phone_e164' => [
                'bail', 'exclude_unless:method,PMOV', 'nullable', 'string', 'size:12', 'regex:/^58\d{10}$/', 'required_without_all:payer_phone_area_code,payer_phone_number',
            ],
            'reference' => [
                'bail', 'exclude_if:method,EXO', 'nullable', 'string', 'max:40',
            ],
            'amount_bs_minor' => ['bail', 'required', 'integer', 'min:1'],
            'paid_on' => ['bail', 'required', 'date', 'before_or_equal:today'],
            'fx_rate_id' => ['bail', 'nullable', 'integer', 'exists:fx_rates,id'],
            'exoneration_reason' => ['bail', 'exclude_unless:method,EXO', 'required_if:method,EXO', 'string', 'min:3', 'max:500'],
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
            'origin_bank_id.required' => 'El banco origen es obligatorio para Transferencia.',
            // Campos específicos (mensajes claros para manual del banco)
            'reference.required_unless' => 'La referencia es obligatoria salvo para exoneraciones (EXO).',
            'payer_account_number.required_unless' => 'La cuenta del pagador es obligatoria para transferencias (cuando el método no es PMOV ni DEB).',
            'payer_account_number.regex' => 'La cuenta del pagador debe tener exactamente 20 dígitos numéricos.',
            'payer_account_number.size' => 'La cuenta del pagador debe tener exactamente 20 dígitos.',
            'payer_phone_area_code.required_if' => 'El código de área es obligatorio para Pago Móvil.',
            'payer_phone_number.required_if' => 'El número de teléfono es obligatorio para Pago Móvil.',
            'payer_phone_e164.required_if' => 'El teléfono del pagador es obligatorio para Pago Móvil (formato 58XXXXXXXXXX).',
            'payer_phone_e164.required_without_all' => 'El teléfono del pagador es obligatorio para Pago Móvil (código + número o 58XXXXXXXXXX).',
            'payer_phone_e164.regex' => 'El teléfono del pagador debe iniciar con 58 y tener 10 dígitos adicionales (formato 58XXXXXXXXXX).',
            'payer_phone_e164.size' => 'El teléfono del pagador debe tener exactamente 12 dígitos (58 + 10 dígitos).',
            'method.exists' => 'El método seleccionado no es válido.',
            'payment_type_id.exists' => 'El método de pago no existe.',
            'payer_document_type.in' => 'El tipo de documento del pagador no es válido.',
            'payer_document_type_id.exists' => 'El tipo de documento del pagador no existe.',
            // 'exoneration_reason.required_if' => 'El motivo de exoneración es obligatorio para EXO.',
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
            'payer_phone_area_code' => 'código de área del pagador',
            'payer_phone_number' => 'número de teléfono del pagador',
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
        // Compose E.164 from area code + number if provided, else normalize existing E.164
        if (! empty($data['payer_phone_area_code']) && ! empty($data['payer_phone_number'])) {
            $area = preg_replace('/\D+/', '', (string) $data['payer_phone_area_code']);
            $num = preg_replace('/\D+/', '', (string) $data['payer_phone_number']);
            if (is_string($area) && str_starts_with($area, '0')) {
                $area = substr($area, 1);
            }
            $data['payer_phone_e164'] = '58'.(string) $area.(string) $num;
        } elseif (isset($data['payer_phone_e164']) && is_string($data['payer_phone_e164'])) {
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
            $val = $data['origin_bank_id'];
            $data['origin_bank_id'] = (is_null($val) || $val === '' || $val === '0') ? null : (int) $val;
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

        // Default status
        if (empty($data['status'])) {
            $data['status'] = 'REGISTERED';
        }

    }

    /**
     * Post-validation hooks.
     * Useful to derive values without polluting rules (e.g., uuid).
     */
    protected function passedValidation(): void
    {
        // Example: ensure uuid is set when --uuid-route is enabled

    }
}
