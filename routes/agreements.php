<?php

use App\Http\Controllers\GeneratedAgreementController;
use App\Models\Contract;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:create,'.Contract::class])->prefix('agreements')->name('agreements.')->group(function (): void {
    Route::get('/', [GeneratedAgreementController::class, 'index'])->name('index');
    Route::get('/preview', [GeneratedAgreementController::class, 'preview'])->name('preview');
    Route::get('/preview/pdf', [GeneratedAgreementController::class, 'previewPdf'])->name('preview-pdf');
    Route::get('/preview/word', [GeneratedAgreementController::class, 'previewWord'])->name('preview-word');
    Route::post('/', [GeneratedAgreementController::class, 'store'])->name('store');
    Route::get('/{agreement}', [GeneratedAgreementController::class, 'show'])->name('show');
    Route::get('/{agreement}/pdf', [GeneratedAgreementController::class, 'download'])->name('download');
    Route::get('/{agreement}/word', [GeneratedAgreementController::class, 'downloadWord'])->name('download-word');
});
