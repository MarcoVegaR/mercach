<?php

declare(strict_types=1);

namespace App\Http\Requests;

class CondoPeriodIndexRequest extends BaseIndexRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('viewAny', \App\Models\CondoPeriod::class));
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        // Only process one period filter type at a time (priority: month > year > range)
        $periodMonth = $this->input('filters.period_month');
        $periodYear = $this->input('filters.period_year');
        $periodFrom = $this->input('filters.period_from');
        $periodTo = $this->input('filters.period_to');

        // Single month has highest priority
        if (is_string($periodMonth) && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $periodMonth)) {
            $data['filters']['period'] = $periodMonth.'-01';
            $data['filters']['period_month'] = null;
            $data['filters']['period_year'] = null;
            $data['filters']['period_from'] = null;
            $data['filters']['period_to'] = null;
        }
        // Year filter (second priority)
        elseif (is_string($periodYear) && preg_match('/^\d{4}$/', $periodYear)) {
            $data['filters']['period_between']['from'] = $periodYear.'-01-01';
            $data['filters']['period_between']['to'] = $periodYear.'-12-01';
            $data['filters']['period_year'] = null;
            $data['filters']['period_month'] = null;
            $data['filters']['period_from'] = null;
            $data['filters']['period_to'] = null;
        }
        // Month range (lowest priority)
        elseif ((is_string($periodFrom) && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $periodFrom)) ||
                (is_string($periodTo) && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $periodTo))) {
            if (is_string($periodFrom) && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $periodFrom)) {
                $data['filters']['period_between']['from'] = $periodFrom.'-01';
            }
            if (is_string($periodTo) && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $periodTo)) {
                $data['filters']['period_between']['to'] = $periodTo.'-01';
            }
            $data['filters']['period_year'] = null;
            $data['filters']['period_month'] = null;
            $data['filters']['period_from'] = null;
            $data['filters']['period_to'] = null;
        }

        if (! empty($data)) {
            $merged = array_replace_recursive($this->all(), $data);
            $this->replace($merged);
        }

        parent::prepareForValidation();
    }

    protected function allowedSorts(): array
    {
        return ['id', 'market_id', 'period', 'status', 'created_at', 'updated_at'];
    }

    protected function filterRules(): array
    {
        return [
            'filters.market_id' => ['sometimes', 'nullable', 'integer', 'exists:markets,id'],
            'filters.period_month' => ['sometimes', 'nullable', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'filters.period_year' => ['sometimes', 'nullable', 'regex:/^\d{4}$/'],
            'filters.period_from' => ['sometimes', 'nullable', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'filters.period_to' => ['sometimes', 'nullable', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'filters.period' => ['sometimes', 'nullable', 'date'],
            'filters.period_between' => ['sometimes', 'nullable', 'array'],
            'filters.period_between.from' => ['sometimes', 'nullable', 'date'],
            'filters.period_between.to' => ['sometimes', 'nullable', 'date', 'after_or_equal:filters.period_between.from'],
            'filters.has_charges' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
