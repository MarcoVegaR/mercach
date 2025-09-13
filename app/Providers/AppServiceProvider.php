<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));
            $ip = $request->ip();

            return [
                // Por cuenta (email normalizado) + IP
                Limit::perMinute(5)->by($email.'|'.$ip),
                // Por IP global para cubrir password spraying distribuido
                Limit::perMinute(50)->by($ip),
            ];
        });

        RateLimiter::for('password-email', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip().'|'.$request->input('email')),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip().'|'.$request->input('email')),
            ];
        });

        // Two-factor challenge limiter (avoid brute forcing TOTP)
        RateLimiter::for('two-factor', function (Request $request) {
            $user = $request->user();
            $userId = $user ? (string) $user->id : (string) ($request->session()->get('login.id') ?? 'guest');
            $ip = $request->ip();

            return Limit::perMinute(10)->by($userId.'|'.$ip);
        });

        // App-specific expensive operations
        RateLimiter::for('exports', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(10)->by(($user?->id) ?: $request->ip());
        });

        RateLimiter::for('bulk', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(15)->by(($user?->id) ?: $request->ip());
        });

        // Audit login/logout events
        Event::listen(Login::class, function (Login $event) {
            /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
            $user = $event->user;
            if ($user instanceof \App\Models\User) {
                $user->auditEvent = 'login';
                $user->auditCustomOld = [];
                $user->auditCustomNew = [
                    'ip' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                    'guard' => $event->guard,
                ];
                $user->isCustomEvent = true;
                event(new \OwenIt\Auditing\Events\AuditCustom($user));
                // Reset temporary audit state
                $user->auditCustomOld = $user->auditCustomNew = [];
                $user->isCustomEvent = false;
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
            $user = $event->user;
            if ($user instanceof \App\Models\User) {
                $user->auditEvent = 'logout';
                $user->auditCustomOld = [];
                $user->auditCustomNew = [
                    'ip' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                    'guard' => $event->guard,
                ];
                $user->isCustomEvent = true;
                event(new \OwenIt\Auditing\Events\AuditCustom($user));
                // Reset temporary audit state
                $user->auditCustomOld = $user->auditCustomNew = [];
                $user->isCustomEvent = false;
            }
        });
    }
}
