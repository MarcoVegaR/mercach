<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ShowQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request class for show operations.
 *
 * Provides validation and conversion for show query parameters
 * with support for whitelisting relations, counts, and appends.
 *
 * @author Laravel Boilerplate
 */
abstract class BaseShowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policies in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'with' => ['sometimes', 'array'],
            'with.*' => ['string', 'in:'.implode(',', $this->allowedRelations())],
            'withCount' => ['sometimes', 'array'],
            'withCount.*' => ['string', 'in:'.implode(',', $this->allowedCounts())],
            'append' => ['sometimes', 'array'],
            'append.*' => ['string', 'in:'.implode(',', $this->allowedAppends())],
            'withTrashed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'with.*.in' => 'La relación :input no está permitida.',
            'withCount.*.in' => 'El conteo :input no está permitido.',
            'append.*.in' => 'El atributo :input no está permitido.',
        ];
    }

    /**
     * Convert the validated request to a ShowQuery DTO.
     */
    public function toShowQuery(): ShowQuery
    {
        $validated = $this->validated();

        return ShowQuery::fromArray([
            'with' => $validated['with'] ?? [],
            'withCount' => $validated['withCount'] ?? [],
            'append' => $validated['append'] ?? [],
            'withTrashed' => $validated['withTrashed'] ?? false,
        ]);
    }

    /**
     * Get the allowed relations for eager loading.
     * Override in child classes to define whitelist.
     *
     * @return array<string>
     */
    protected function allowedRelations(): array
    {
        return [];
    }

    /**
     * Get the allowed relations for counting.
     * Override in child classes to define whitelist.
     *
     * @return array<string>
     */
    protected function allowedCounts(): array
    {
        return [];
    }

    /**
     * Get the allowed attributes to append.
     * Override in child classes to define whitelist.
     *
     * @return array<string>
     */
    protected function allowedAppends(): array
    {
        return [];
    }
}
