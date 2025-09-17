<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class SetCatalogActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->resolveRouteModel();

        return $model instanceof Model
            ? $this->user()?->can('setActive', $model) === true
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        // Placeholder for future business constraints per catalog.
        // Example: block deactivation if the record is protected or in use.
        // Use session()->flash('error', '...') to push flash error like SetRoleActiveRequest when needed.
    }

    private function resolveRouteModel(): ?Model
    {
        $route = $this->route();
        if (! $route) {
            return null;
        }

        foreach ($route->parameters() as $param) {
            if ($param instanceof Model) {
                return $param;
            }
        }

        return null;
    }
}
