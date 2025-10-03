<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Brick\Money\Money;
use Illuminate\Foundation\Http\FormRequest;

class CondoExpenseBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization will be done in controller via policy on CondoPeriod
        return (bool) $this->user();
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'market_id' => ['required', 'integer', 'exists:markets,id'],
            'period_month' => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            // prevent duplicates within the same payload
            'items.*.expense_type_id' => ['required', 'integer', 'exists:expense_types,id', 'distinct'],
            'items.*.amount_usd' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'items.*.amount_usd_minor' => ['sometimes', 'integer', 'min:1'],
            'items.*.expense_date' => ['nullable', 'date'],
            'items.*.attachment' => ['nullable', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
            'items.*.invoice_number' => ['nullable', 'string', 'max:60'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        // Normalize month to full date
        $month = $this->input('period_month');
        if (is_string($month) && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $month)) {
            $data['period'] = $month.'-01';
        }

        // Pre-filter items: drop completely empty rows (no type and no amount)
        $items = $this->input('items');
        if (is_array($items)) {
            $filtered = [];
            foreach ($items as $i => $row) {
                $type = $row['expense_type_id'] ?? null;
                $amount = $row['amount_usd'] ?? null;
                $hasContent = (is_numeric($type) && (int) $type > 0) || (is_string($amount) && trim($amount) !== '');
                if (! $hasContent) {
                    continue;
                }
                // Convert amount to minor units safely
                if (isset($row['amount_usd']) && $row['amount_usd'] !== '') {
                    $money = Money::of($row['amount_usd'], 'USD');
                    $row['amount_usd_minor'] = (int) $money->getMinorAmount()->toInt();
                }
                $filtered[] = $row;
            }
            $data['items'] = $filtered;
        }

        if (! empty($data)) {
            $merged = array_replace_recursive($this->all(), $data);
            $this->replace($merged);
        }
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        // Remove any null placeholders
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_map(function ($row) {
                // If minor not set (filtered out by validated), compute from amount_usd
                if (! isset($row['amount_usd_minor']) && isset($row['amount_usd']) && $row['amount_usd'] !== '') {
                    $money = Money::of($row['amount_usd'], 'USD');
                    $row['amount_usd_minor'] = (int) $money->getMinorAmount()->toInt();
                }
                if (isset($row['amount_usd'])) {
                    unset($row['amount_usd']);
                }

                return $row;
            }, $data['items']);
        }

        // Attach normalized period
        $data['period'] = $this->input('period');

        return $data;
    }

    /**
     * @return array<callable(\Illuminate\Validation\Validator): void>
     */
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator) {
            $marketId = (int) $this->input('market_id');
            $periodDate = (string) $this->input('period');
            if ($marketId > 0 && preg_match('/^\d{4}\-(0[1-9]|1[0-2])\-01$/', $periodDate)) {
                // If a period exists, prevent adding duplicate expense types
                $period = \App\Models\CondoPeriod::query()->where('market_id', $marketId)->where('period', $periodDate)->first();
                if ($period) {
                    $existing = $period->expenses()->pluck('expense_type_id')->all();
                    $existingSet = array_fill_keys(array_map('intval', $existing), true);
                    $items = (array) $this->input('items', []);
                    foreach ($items as $i => $row) {
                        $tid = (int) ($row['expense_type_id'] ?? 0);
                        if ($tid > 0 && isset($existingSet[$tid])) {
                            $validator->errors()->add("items.$i.expense_type_id", 'Este tipo de gasto ya existe en el período.');
                        }
                    }
                }
            }
        }];
    }
}
