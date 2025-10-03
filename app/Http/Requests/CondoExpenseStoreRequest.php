<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CondoPeriod;
use Brick\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CondoExpenseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check base permission only, period state validation is in after() hook
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // Check if user has the condo_period.update permission
        return $user->can('condo_period.update');
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'expense_type_id' => ['required', 'integer', 'exists:expense_types,id'],
            'amount_usd' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'amount_usd_minor' => ['sometimes', 'integer', 'min:1'],
            'expense_date' => ['nullable', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expense_type_id.required' => 'El tipo de gasto es obligatorio.',
            'expense_type_id.integer' => 'El tipo de gasto debe ser un número válido.',
            'expense_type_id.exists' => 'El tipo de gasto seleccionado no es válido.',

            'amount_usd.required' => 'El monto en USD es obligatorio.',
            'amount_usd.regex' => 'El monto en USD debe tener como máximo dos decimales.',
            'amount_usd_minor.integer' => 'El monto en centavos debe ser un número entero.',
            'amount_usd_minor.min' => 'El monto debe ser mayor que cero.',

            'expense_date.date' => 'La fecha del gasto no es válida.',
            'invoice_number.max' => 'La factura no puede exceder 60 caracteres.',
            'note.string' => 'La nota debe ser un texto válido.',

            'attachment.file' => 'El comprobante debe ser un archivo.',
            'attachment.mimetypes' => 'El comprobante debe ser PDF o imagen (JPG/PNG).',
            'attachment.max' => 'El comprobante no debe superar los 10 MB.',

            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        $amount = $this->input('amount_usd');
        if (is_string($amount) && $amount !== '') {
            $money = Money::of($amount, 'USD');
            $data['amount_usd_minor'] = (int) $money->getMinorAmount()->toInt();
        }
        if ($data) {
            $this->merge($data);
        }
    }

    /**
     * @return array<callable(\Illuminate\Validation\Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var CondoPeriod $period */
            $period = $this->route('condo_period');
            // Validate period state before business rules
            if ($period->isFinal()) {
                $validator->errors()->add('period', 'El período está finalizado y no puede modificarse.');

                return;
            }
            if ($period->hasCharges()) {
                $validator->errors()->add('period', 'El período tiene cargos generados y no puede modificarse.');

                return;
            }

            // Duplicate expense type validation
            $exists = $period->expenses()->where('expense_type_id', (int) $this->input('expense_type_id'))->exists();
            if ($exists) {
                $validator->errors()->add('expense_type_id', 'Este tipo de gasto ya existe en el período.');
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (! isset($data['amount_usd_minor']) && isset($data['amount_usd']) && $data['amount_usd'] !== '') {
            $money = Money::of($data['amount_usd'], 'USD');
            $data['amount_usd_minor'] = (int) $money->getMinorAmount()->toInt();
        }
        unset($data['amount_usd']);

        return $data;
    }
}
