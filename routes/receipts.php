<?php

use Illuminate\Support\Facades\Route;

// Public signed route for receipt verification (no auth)
Route::get('/receipts/public/{token}', [\App\Http\Controllers\ReceiptController::class, 'publicShow'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('receipts.public.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/receipts/{receipt}/download', [\App\Http\Controllers\ReceiptController::class, 'download'])
        ->middleware('permission:catalogs.payment.view')
        ->name('receipts.download');
});
