<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/payments', [\App\Http\Controllers\PaymentController::class, 'index'])
        ->middleware('permission:catalogs.payment.view')->name('payments.index');

    Route::get('/payments/create', [\App\Http\Controllers\PaymentController::class, 'create'])
        ->middleware('permission:catalogs.payment.create')->name('payments.create');

    Route::post('/payments', [\App\Http\Controllers\PaymentController::class, 'store'])
        ->middleware('permission:catalogs.payment.create')->name('payments.store');

    Route::post('/payments/bulk', [\App\Http\Controllers\PaymentController::class, 'bulk'])
        ->middleware('permission:catalogs.payment.delete|catalogs.payment.restore|catalogs.payment.forceDelete|catalogs.payment.setActive')
        ->name('payments.bulk');

    // Payment lifecycle endpoints
    Route::post('/payments/{payment}/verify', [\App\Http\Controllers\PaymentController::class, 'verify'])
        ->middleware('permission:catalogs.payment.update')->name('payments.verify');
    Route::post('/payments/{payment}/apply', [\App\Http\Controllers\PaymentController::class, 'apply'])
        ->middleware('permission:catalogs.payment.update')->name('payments.apply');
    Route::get('/payments/resolve-fx', [\App\Http\Controllers\PaymentController::class, 'resolveFx'])
        ->middleware('permission:catalogs.payment.create|catalogs.payment.update|catalogs.payment.view')
        ->name('payments.resolve-fx');

    // Connectivity probe for bank gateway (diagnostics)
    Route::get('/payments/gateway-probe', [\App\Http\Controllers\PaymentController::class, 'gatewayProbe'])
        ->middleware('permission:catalogs.payment.view')
        ->name('payments.gateway-probe');

    // Open charges for debtor (for apply UI)
    Route::get('/payments/open-charges', [\App\Http\Controllers\PaymentController::class, 'openCharges'])
        ->middleware('permission:catalogs.payment.update|catalogs.payment.view')
        ->name('payments.open-charges');

    // Allocations preview and store
    Route::post('/payments/{payment}/allocations/preview', [\App\Http\Controllers\PaymentController::class, 'previewAllocations'])
        ->middleware('permission:catalogs.payment.update')
        ->name('payments.allocations.preview');
    Route::post('/payments/{payment}/allocations/suggest', [\App\Http\Controllers\PaymentController::class, 'suggestAllocations'])
        ->middleware('permission:catalogs.payment.update')
        ->name('payments.allocations.suggest');
    Route::post('/payments/{payment}/allocations', [\App\Http\Controllers\PaymentController::class, 'storeAllocations'])
        ->middleware('permission:catalogs.payment.update')
        ->name('payments.allocations.store');

    Route::get('/payments/{payment}', [\App\Http\Controllers\PaymentController::class, 'show'])
        ->middleware('permission:catalogs.payment.view')->name('payments.show');

    Route::get('/payments/{payment}/edit', [\App\Http\Controllers\PaymentController::class, 'edit'])
        ->middleware('permission:catalogs.payment.update')->name('payments.edit');

    Route::put('/payments/{payment}', [\App\Http\Controllers\PaymentController::class, 'update'])
        ->middleware('permission:catalogs.payment.update')->name('payments.update');

    Route::delete('/payments/{payment}', [\App\Http\Controllers\PaymentController::class, 'destroy'])
        ->middleware('permission:catalogs.payment.delete')->name('payments.destroy');
});
