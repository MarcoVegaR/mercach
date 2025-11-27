<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalPaymentController;
use Illuminate\Support\Facades\Route;

// Dashboard y páginas: todas requieren permiso y vínculo a concesionario
Route::middleware(['auth', 'no-admin-portal', 'verified', 'permission:portal.access', 'portal.linked'])->group(function () {
    Route::get('/portal', [PortalController::class, 'index'])->name('portal.index');
});

// Páginas con datos
Route::middleware(['auth', 'no-admin-portal', 'verified', 'permission:portal.access', 'portal.linked'])->group(function () {
    Route::get('/portal/deuda', [PortalController::class, 'debt'])->name('portal.debt');
    Route::get('/portal/recibos', [PortalController::class, 'receipts'])->name('portal.receipts');
    Route::get('/portal/contratos', [PortalController::class, 'contracts'])->name('portal.contracts');
    Route::get('/portal/contratos/{contract}', [PortalController::class, 'contractShow'])->name('portal.contracts.show');
    Route::get('/portal/recibos/{receipt}/download', [PortalController::class, 'downloadReceipt'])->name('portal.receipts.download');

    // Portal payments (self-service)
    Route::get('/portal/pagos/nuevo', [PortalPaymentController::class, 'create'])->name('portal.payments.create');
    Route::post('/portal/pagos', [PortalPaymentController::class, 'store'])->name('portal.payments.store');
    Route::get('/portal/pagos/resolve-fx', [PortalPaymentController::class, 'resolveFx'])->name('portal.payments.resolve-fx');
    Route::get('/portal/pagos', [PortalPaymentController::class, 'index'])->name('portal.payments.index');
    Route::get('/portal/pagos/{payment}/aplicar', [PortalPaymentController::class, 'applyPage'])->name('portal.payments.apply');
    Route::get('/portal/pagos/{payment}/open-charges', [PortalPaymentController::class, 'openCharges'])->name('portal.payments.open-charges');
    Route::post('/portal/pagos/{payment}/allocations/preview', [PortalPaymentController::class, 'previewAllocations'])->name('portal.payments.allocations.preview');
    Route::post('/portal/pagos/{payment}/allocations/suggest', [PortalPaymentController::class, 'suggestAllocations'])->name('portal.payments.allocations.suggest');
    Route::post('/portal/pagos/{payment}/allocations', [PortalPaymentController::class, 'storeAllocations'])->name('portal.payments.allocations.store');
});
