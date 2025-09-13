<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])
        ->middleware('can:settings.profile.view')
        ->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->middleware('can:settings.profile.update')
        ->name('profile.update');
    // Route disabled: users cannot delete their own account
    // Route::delete('settings/profile', [ProfileController::class, 'destroy'])
    //     ->middleware('can:settings.profile.delete')
    //     ->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])
        ->middleware('can:settings.password.update')
        ->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('can:settings.password.update')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->middleware('can:settings.appearance.view')
        ->name('appearance');

    // Security settings page (MFA & Sessions)
    Route::get('settings/security', \App\Http\Controllers\Settings\SecurityController::class)
        ->middleware('can:settings.security.view')
        ->name('security');

    // Close other sessions endpoint (protected & throttled)
    Route::post('/user/logout-others', \App\Http\Controllers\Settings\UserSessionsController::class)
        ->middleware(['can:settings.security.sessions.manage', 'throttle:6,1'])
        ->name('settings.sessions.logout-others');

    // List active sessions (JSON)
    Route::get('/settings/sessions', [\App\Http\Controllers\Settings\SessionsBrowserController::class, 'index'])
        ->middleware(['can:settings.security.sessions.manage'])
        ->name('settings.sessions.index');

    // Close a specific session by ID (JSON)
    Route::delete('/settings/sessions/{id}', [\App\Http\Controllers\Settings\SessionsBrowserController::class, 'destroy'])
        ->where('id', '.*')
        ->middleware(['can:settings.security.sessions.manage', 'throttle:20,1'])
        ->name('settings.sessions.destroy');
});
