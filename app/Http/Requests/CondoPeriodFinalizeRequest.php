<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CondoPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CondoPeriodFinalizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CondoPeriod $period */
        $period = $this->route('condo_period');

        return $this->user()?->can('finalize', $period) ?? false;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        // No body parameters required; validation is contextual to the route model
        return [];
    }

    /**
     * @return array<callable(\Illuminate\Validation\Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var CondoPeriod $period */
            $period = $this->route('condo_period');
            $hasExpenses = $period->expenses()->exists();
            if (! $hasExpenses) {
                $validator->errors()->add('period', 'No se puede confirmar un período sin gastos.');
            }
        }];
    }
}
