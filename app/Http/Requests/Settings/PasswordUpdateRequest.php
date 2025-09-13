<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings.password.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            // Match UserStoreRequest requirements: strong password
            'password' => [
                'required',
                'string',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
                'confirmed',
                'different:current_password',
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
