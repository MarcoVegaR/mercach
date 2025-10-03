<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Index + export + bulk (periods)
    Route::get('/condo/periods', [\App\Http\Controllers\CondoPeriodController::class, 'index'])->middleware('permission:condo_period.view')->name('condo.periods.index');
    Route::get('/condo/periods/export', [\App\Http\Controllers\CondoPeriodController::class, 'export'])->middleware('permission:condo_period.export')->name('condo.periods.export');
    Route::post('/condo/periods/bulk', [\App\Http\Controllers\CondoPeriodController::class, 'bulk'])->middleware('permission:condo_period.delete')->name('condo.periods.bulk');

    // Upsert and show workspace
    Route::post('/condo/periods/upsert', [\App\Http\Controllers\CondoPeriodController::class, 'upsert'])->middleware('permission:condo_period.create')->name('condo.periods.upsert');
    Route::get('/condo/periods/{condo_period}/show', [\App\Http\Controllers\CondoPeriodController::class, 'show'])->middleware('permission:condo_period.view')->name('condo.periods.show');

    // Actions
    Route::post('/condo/periods/{condo_period}/finalize', [\App\Http\Controllers\CondoPeriodController::class, 'finalize'])->middleware('permission:condo_period.finalize')->name('condo.periods.finalize');
    Route::post('/condo/periods/{condo_period}/reopen', [\App\Http\Controllers\CondoPeriodController::class, 'reopen'])->middleware('permission:condo_period.reopen')->name('condo.periods.reopen');
    Route::patch('/condo/periods/{condo_period}/active', [\App\Http\Controllers\CondoPeriodController::class, 'setActive'])->middleware('permission:condo_period.setActive')->name('condo.periods.setActive');
    Route::delete('/condo/periods/{condo_period}', [\App\Http\Controllers\CondoPeriodController::class, 'destroy'])->middleware('permission:condo_period.delete')->name('condo.periods.destroy');

    // Bulk endpoints for expenses and participants
    Route::post('/condo/expenses/bulk', [\App\Http\Controllers\CondoExpenseBulkController::class, 'store'])->middleware('permission:condo_period.update')->name('condo.expenses.bulk');

    // Expenses JSON CRUD for DataTable in workspace
    Route::get('/condo/periods/{condo_period}/expenses', [\App\Http\Controllers\CondoExpenseController::class, 'index'])->middleware('permission:condo_period.view')->name('condo.periods.expenses.index');
    Route::post('/condo/periods/{condo_period}/expenses', [\App\Http\Controllers\CondoExpenseController::class, 'store'])->middleware('permission:condo_period.update')->name('condo.periods.expenses.store');
    Route::put('/condo/expenses/{condo_expense}', [\App\Http\Controllers\CondoExpenseController::class, 'update'])->middleware('permission:condo_period.update')->name('condo.expenses.update');
    Route::delete('/condo/expenses/{condo_expense}', [\App\Http\Controllers\CondoExpenseController::class, 'destroy'])->middleware('permission:condo_period.update')->name('condo.expenses.destroy');

    // Participants JSON (excluded locals) for DataTable in workspace
    Route::get('/condo/periods/{condo_period}/participants', [\App\Http\Controllers\CondoParticipantController::class, 'index'])->middleware('permission:condo_period.view')->name('condo.periods.participants.index');
    Route::post('/condo/periods/{condo_period}/participants', [\App\Http\Controllers\CondoParticipantController::class, 'store'])->middleware('permission:condo_period.update')->name('condo.periods.participants.store');
    Route::post('/condo/periods/{condo_period}/participants/exclude-all', [\App\Http\Controllers\CondoParticipantController::class, 'excludeAll'])->middleware('permission:condo_period.update')->name('condo.periods.participants.excludeAll');
    Route::delete('/condo/participants/{condo_participant}', [\App\Http\Controllers\CondoParticipantController::class, 'destroy'])->middleware('permission:condo_period.update')->name('condo.participants.destroy');

    Route::post('/condo/participants/seed', [\App\Http\Controllers\CondoParticipantBulkController::class, 'seedDefaults'])->middleware('permission:condo_period.update')->name('condo.participants.seed');
    Route::post('/condo/participants/bulk', [\App\Http\Controllers\CondoParticipantBulkController::class, 'store'])->middleware('permission:condo_period.update')->name('condo.participants.bulk');
    Route::post('/condo/participants/bulk-exclude-filtered', [\App\Http\Controllers\CondoParticipantBulkController::class, 'bulkExcludeFiltered'])->middleware('permission:condo_period.update')->name('condo.participants.bulkExcludeFiltered');
    Route::post('/condo/participants/bulk-include-filtered', [\App\Http\Controllers\CondoParticipantBulkController::class, 'bulkIncludeFiltered'])->middleware('permission:condo_period.update')->name('condo.participants.bulkIncludeFiltered');

    // Lookups
    Route::get('/condo/lookup/locals', [\App\Http\Controllers\CondoLookupController::class, 'locals'])->middleware('permission:condo_period.view')->name('condo.lookup.locals');
});
