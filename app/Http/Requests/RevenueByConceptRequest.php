<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Reports\RevenueByConceptQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevenueByConceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('dashboard.view.finance');
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'concepts' => ['sometimes', 'array'],
            'concepts.*' => ['required', 'string', 'distinct', Rule::in(array_keys(RevenueByConceptQuery::CONCEPTS))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from.required' => 'Seleccione la fecha inicial.',
            'from.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'to.required' => 'Seleccione la fecha final.',
            'to.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'concepts.array' => 'Seleccione los conceptos de la lista.',
            'concepts.*.in' => 'Uno de los conceptos seleccionados no es válido.',
            'concepts.*.distinct' => 'No repita los conceptos seleccionados.',
        ];
    }

    /** @return array{from: string, to: string, concepts: list<string>} */
    public function reportFilters(): array
    {
        $concepts = $this->validated('concepts', []);

        return [
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
            'concepts' => count($concepts) === count(RevenueByConceptQuery::CONCEPTS) ? [] : $concepts,
        ];
    }
}
