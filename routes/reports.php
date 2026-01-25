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

    Route::get('/daily-bank-reconciliation', [ReportController::class, 'dailyBankReconciliation'])
        ->name('daily-bank-reconciliation');
    Route::get('/daily-bank-reconciliation/export', [ReportController::class, 'exportDailyBankReconciliation'])
        ->name('daily-bank-reconciliation.export');

    // Contracts without signature
    Route::get('/contracts-unsigned', [ReportController::class, 'contractsUnsigned'])
        ->name('contracts-unsigned');
    Route::get('/contracts-unsigned/export', [ReportController::class, 'exportContractsUnsigned'])
        ->name('contracts-unsigned.export');

    // Concessionaire changes per local
    Route::get('/concessionaire-changes', [ReportController::class, 'concessionaireChanges'])
        ->name('concessionaire-changes');
    Route::get('/concessionaire-changes/export', [ReportController::class, 'exportConcessionaireChanges'])
        ->name('concessionaire-changes.export');

    // Recovered locals (occupied to available)
    Route::get('/locals-recovered', [ReportController::class, 'localsRecovered'])
        ->name('locals-recovered');
    Route::get('/locals-recovered/export', [ReportController::class, 'exportLocalsRecovered'])
        ->name('locals-recovered.export');

    // Locals financial status
    Route::get('/locals-financial-status', [ReportController::class, 'localsFinancialStatus'])
        ->name('locals-financial-status');
    Route::get('/locals-financial-status/export', [ReportController::class, 'exportLocalsFinancialStatus'])
        ->name('locals-financial-status.export');
});
