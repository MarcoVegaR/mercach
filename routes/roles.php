<?php

declare(strict_types=1);

use App\Http\Controllers\RolesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/roles', [RolesController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');

    Route::get('/roles/create', [RolesController::class, 'create'])
        ->middleware('permission:roles.create')
        ->name('roles.create');
    Route::post('/roles', [RolesController::class, 'store'])
        ->middleware('permission:roles.create')
        ->name('roles.store');

    Route::get('/roles/export', [RolesController::class, 'export'])
        ->middleware('permission:roles.export')
        ->name('roles.export');

    Route::post('/roles/bulk', [RolesController::class, 'bulk'])
        ->middleware('permission:roles.delete|roles.restore|roles.forceDelete|roles.setActive')
        ->name('roles.bulk');

    Route::get('/roles/selected', [RolesController::class, 'selected'])
        ->middleware('permission:roles.view')
        ->name('roles.selected');

    Route::get('/roles/{role}', [RolesController::class, 'show'])
        ->middleware('permission:roles.view')
        ->name('roles.show');
    Route::get('/roles/{role}/edit', [RolesController::class, 'edit'])
        ->middleware('permission:roles.update')
        ->name('roles.edit');
    Route::put('/roles/{role}', [RolesController::class, 'update'])
        ->middleware('permission:roles.update')
        ->name('roles.update');
    Route::patch('/roles/{role}/active', [RolesController::class, 'setActive'])
        ->middleware('permission:roles.setActive')
        ->name('roles.setActive');
    Route::delete('/roles/{role}', [RolesController::class, 'destroy'])
        ->middleware('permission:roles.delete')
        ->name('roles.destroy');
});
