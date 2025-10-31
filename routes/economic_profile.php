<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\EconomicProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/economic-profile', [EconomicProfileController::class, 'index'])
        ->middleware('permission:admin.economic_profile.view')
        ->name('economic_profile.index');

    Route::get('/admin/economic-profile/search', [EconomicProfileController::class, 'search'])
        ->middleware('permission:admin.economic_profile.view')
        ->name('economic_profile.search');

    Route::get('/admin/economic-profile/concessionaires/{id}', [EconomicProfileController::class, 'showConcessionaire'])
        ->whereNumber('id')
        ->middleware('permission:admin.economic_profile.view')
        ->name('economic_profile.concessionaire');

    Route::get('/admin/economic-profile/locals/{id}', [EconomicProfileController::class, 'showLocal'])
        ->whereNumber('id')
        ->middleware('permission:admin.economic_profile.view')
        ->name('economic_profile.local');

    Route::get('/admin/economic-profile/export', [EconomicProfileController::class, 'export'])
        ->middleware('permission:admin.economic_profile.export')
        ->name('economic_profile.export');
});
