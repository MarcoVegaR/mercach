<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController
{
    public function __invoke(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return Inertia::render('settings/security', [
            'twoFactor' => [
                'enabled' => ! empty($user->two_factor_secret),
                'confirmed' => $user->two_factor_confirmed_at !== null,
                'confirmed_at' => $user->two_factor_confirmed_at
                    ? Carbon::parse($user->two_factor_confirmed_at)->toISOString()
                    : null,
            ],
            'can' => [
                'manage2FA' => (bool) $user->can('settings.security.2fa.manage'),
                'logoutOthers' => (bool) $user->can('settings.security.sessions.manage'),
            ],
            'appName' => config('app.name'),
        ]);
    }
}
