<?php

declare(strict_types=1);

use App\Http\Controllers\AuditoriaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auditoría Routes
|--------------------------------------------------------------------------
|
| Rutas para el módulo de auditoría del sistema.
| Solo incluye funcionalidad de consulta (Index) y exportación.
| No incluye create/edit ya que los registros de auditoría son de solo lectura.
|
*/

Route::middleware(['auth'])->prefix('auditoria')->name('auditoria.')->group(function () {
    // Listado principal de auditoría
    Route::get('/', [AuditoriaController::class, 'index'])
        ->middleware('permission:auditoria.view')
        ->name('index');

    // Exportación de registros de auditoría
    Route::get('/export', [AuditoriaController::class, 'export'])
        ->middleware('permission:auditoria.export')
        ->middleware('throttle:exports')
        ->name('export');
});
