<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Models\Concessionaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PortalPaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->can('portal.access')) {
            return false;
        }
        // Must be linked to at least one concessionaire
        try {
            return $user->concessionaires()->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'local_id' => ['bail', 'nullable', 'integer', 'exists:locals,id'],
            'company_bank_account_id' => ['bail', 'required', 'integer', 'exists:company_bank_accounts,id'],
            'method' => ['bail', 'required', 'string', 'max:20'],
            'payment_type_id' => ['bail', 'nullable', 'integer', 'exists:payment_types,id'],
            'origin_bank_id' => ['bail', 'required', 'integer', 'exists:banks,id'],
            'payer_document_type' => ['bail', 'required', 'string', 'max:1', Rule::in(['V', 'E', 'J', 'G'])],
            'payer_document_type_id' => ['bail', 'nullable', 'integer', 'exists:document_types,id'],
            'payer_document_number' => ['bail', 'required', 'string', 'max:12'],
            'payer_account_number' => ['bail', 'nullable', 'string', 'size:20', 'regex:/^\d{20}$/', 'required_unless:method,PMOV,DEB'],
            'payer_phone_area_code' => ['bail', 'nullable', 'string', 'regex:/^0\d{3}$/', 'required_if:method,PMOV'],
            'payer_phone_number' => ['bail', 'nullable', 'string', 'regex:/^\d{7}$/', 'required_if:method,PMOV'],
            'payer_phone_e164' => ['bail', 'nullable', 'string', 'size:12', 'regex:/^58\d{10}$/'],
            'reference' => ['bail', 'required', 'string', 'regex:/^\d{6,8}$/'],
            'amount_bs_minor' => ['bail', 'required', 'integer', 'min:1'],
            'paid_on' => ['bail', 'required', 'date'],
            'fx_rate_id' => ['bail', 'nullable', 'integer', 'exists:fx_rates,id'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            try {
                $method = strtoupper((string) $this->input('method', ''));
                $companyId = (int) $this->input('company_bank_account_id');

                if ($companyId > 0) {
                    $acc = \App\Models\CompanyBankAccount::query()->find($companyId);
                    if (! $acc) {
                        return;
                    }
                    $allowTransfer = (bool) $acc->getAttribute('allow_transfer');
                    $allowPMOV = (bool) $acc->getAttribute('allow_pmov');
                    $allowDebit = (bool) $acc->getAttribute('allow_debit');

                    if ($method === 'TRANSFER' && ! $allowTransfer) {
                        $v->errors()->add('company_bank_account_id', 'La cuenta seleccionada no admite Transferencia.');
                    }
                    if ($method === 'PMOV') {
                        if (! $allowPMOV) {
                            $v->errors()->add('company_bank_account_id', 'La cuenta seleccionada no admite Pago Móvil.');
                        }
                        $phone = (string) ($acc->getAttribute('phone_number') ?? '');
                        if (preg_match('/^58\d{10}$/', $phone) !== 1) {
                            $v->errors()->add('company_bank_account_id', 'La cuenta seleccionada no tiene teléfono válido para Pago Móvil (58XXXXXXXXXX).');
                        }
                    }
                    if ($method === 'DEB' && ! $allowDebit) {
                        $v->errors()->add('company_bank_account_id', 'La cuenta seleccionada no admite Débito.');
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function prepareForValidation(): void
    {
        $data = $this->all();

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
        // Combine area code + phone number into e164 format
        if (! empty($data['payer_phone_area_code']) && ! empty($data['payer_phone_number'])) {
            $areaCode = preg_replace('/\D+/', '', $data['payer_phone_area_code']);
            $phoneNum = preg_replace('/\D+/', '', $data['payer_phone_number']);
            // Format: 58 + area_code (without leading 0) + phone_number
            // Example: 0412 + 1234567 = 584121234567
            if (str_starts_with($areaCode, '0')) {
                $areaCode = substr($areaCode, 1);
            }
            $data['payer_phone_e164'] = '58'.$areaCode.$phoneNum;
        } elseif (isset($data['payer_phone_e164']) && is_string($data['payer_phone_e164'])) {
            // Fallback for old format
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
        foreach (['local_id', 'company_bank_account_id', 'payment_type_id', 'origin_bank_id', 'amount_bs_minor', 'fx_rate_id'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = is_null($data[$k]) ? null : (int) $data[$k];
            }
        }
        $this->replace($data);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'local_id' => 'local',
            'company_bank_account_id' => 'cuenta receptora',
            'method' => 'método',
            'payment_type_id' => 'tipo de pago',
            'origin_bank_id' => 'banco origen',
            'payer_document_type' => 'tipo de documento del pagador',
            'payer_document_type_id' => 'tipo de documento del pagador',
            'payer_document_number' => 'documento del pagador',
            'payer_account_number' => 'cuenta del pagador',
            'payer_phone_area_code' => 'código de área del pagador',
            'payer_phone_number' => 'número de teléfono del pagador',
            'payer_phone_e164' => 'teléfono del pagador',
            'reference' => 'referencia',
            'amount_bs_minor' => 'monto (céntimos)',
            'paid_on' => 'pagado el',
            'fx_rate_id' => 'tasa FX',
        ];
    }
}
