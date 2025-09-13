<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $credentials = $this->only('email', 'password');

        if (Auth::attempt($credentials + ['is_active' => 1], $this->boolean('remember'))) {
            return;
        }

        $user = User::query()
            ->where('email', (string) $this->string('email'))
            ->first();

        if ($user && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('auth.inactive'),
            ]);
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    // Rate limiting is centralized via named limiter 'login' and middleware throttle:login
}
