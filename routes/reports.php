<?php

declare(strict_types=1);

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reports Routes
|--------------------------------------------------------------------------
|
| Routes for administrative reports and analytics.
|
*/

Route::middleware(['auth', 'verified'])->prefix('reports')->name('reports.')->group(function () {
    // Bank Validations Report
    Route::get('/bank-validations', [ReportController::class, 'bankValidations'])
        ->name('bank-validations');
    Route::get('/bank-validations/export', [ReportController::class, 'exportBankValidations'])
        ->name('bank-validations.export');
});
