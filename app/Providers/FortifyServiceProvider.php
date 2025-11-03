<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
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

        // Customize password reset email (used for invitations and standard resets)
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $minutes = (int) (config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire') ?? 60);

            return (new MailMessage)
                ->subject('Accede al Portal: establece tu contraseña')
                ->greeting('Hola')
                ->line('Has recibido este mensaje para crear o restablecer tu contraseña de acceso al Portal de Servicios.')
                ->action('Establecer contraseña', $resetUrl)
                ->line('Por seguridad, este enlace expira en '.$minutes.' minutos.')
                ->line('Si el enlace expira o no funciona, puedes solicitar uno nuevo desde la pantalla de acceso usando “Olvidé mi contraseña”.')
                ->line('Si no solicitaste este correo, puedes ignorarlo.');
        });
    }
}
