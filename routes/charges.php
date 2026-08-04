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

    // Domain operations: cancel charges and create extra/manual charges
    Route::post('/charges/{charge}/cancel', [\App\Http\Controllers\ChargeController::class, 'cancel'])
        ->middleware('permission:charges.cancel')
        ->name('charges.cancel');

    Route::post('/charges/{charge}/mark-uncollectible', [\App\Http\Controllers\ChargeCollectibilityController::class, 'mark'])
        ->middleware('permission:charges.collectibility.mark')
        ->name('charges.mark-uncollectible');

    Route::post('/charges/{charge}/restore-collectible', [\App\Http\Controllers\ChargeCollectibilityController::class, 'restore'])
        ->middleware('permission:charges.collectibility.restore')
        ->name('charges.restore-collectible');

    Route::post('/charges/bulk-cancel', [\App\Http\Controllers\ChargeController::class, 'bulkCancel'])
        ->middleware('permission:charges.cancel')
        ->name('charges.bulk-cancel');

    Route::post('/charges/bulk-mark-uncollectible', [\App\Http\Controllers\ChargeCollectibilityController::class, 'markMany'])
        ->middleware('permission:charges.collectibility.mark')
        ->name('charges.bulk-mark-uncollectible');

    Route::post('/charges/bulk-restore-collectible', [\App\Http\Controllers\ChargeCollectibilityController::class, 'restoreMany'])
        ->middleware('permission:charges.collectibility.restore')
        ->name('charges.bulk-restore-collectible');

    Route::post('/charges/extra', [\App\Http\Controllers\ChargeController::class, 'storeExtra'])
        ->middleware('permission:charges.extra.create')
        ->name('charges.extra.store');
});
