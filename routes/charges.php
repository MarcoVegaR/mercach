<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Charges list
    Route::get('/charges', [\App\Http\Controllers\ChargeController::class, 'index'])
        ->middleware('permission:charges.view')
        ->name('charges.index');
    Route::get('/charges/export', [\App\Http\Controllers\ChargeController::class, 'export'])
        ->middleware('permission:charges.export')
        ->name('charges.export');

    Route::get('/charges/run', [\App\Http\Controllers\Charges\RunController::class, 'index'])
        ->middleware('permission:charges.run')
        ->name('charges.run.index');

    Route::post('/charges/run', [\App\Http\Controllers\Charges\RunController::class, 'run'])
        ->middleware('permission:charges.run')
        ->name('charges.run.execute');
});
