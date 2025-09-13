<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No bindings
    }

    public function boot(): void
    {
        // Guard against missing Fortify in environments where it's not installed yet
        if (! class_exists('Laravel\\Fortify\\Fortify')) {
            return;
        }

        // Defer import to avoid class resolution before the guard
        \Laravel\Fortify\Fortify::twoFactorChallengeView(function () {
            return \Inertia\Inertia::render('auth/two-factor-challenge');
        });

        // Enforce our custom login policy: only active users can authenticate
        \Laravel\Fortify\Fortify::authenticateUsing(function (Request $request) {
            /** @var \App\Models\User|null $user */
            $user = \App\Models\User::query()
                ->where('email', (string) $request->input('email'))
                ->first();

            if ($user && ! $user->is_active) {
                throw ValidationException::withMessages([
                    'email' => __('auth.inactive'),
                ]);
            }

            if ($user && $user->is_active && \Illuminate\Support\Facades\Hash::check((string) $request->input('password'), (string) $user->password)) {
                return $user;
            }

            return null;
        });
    }
}
