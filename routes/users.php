<?php

declare(strict_types=1);

use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/users', [UsersController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');
    Route::get('/users/create', [UsersController::class, 'create'])
        ->middleware('permission:users.create')
        ->name('users.create');
    Route::post('/users', [UsersController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('users.store');
    Route::get('/users/export', [UsersController::class, 'export'])
        ->middleware('permission:users.export')
        ->name('users.export');
    Route::post('/users/bulk', [UsersController::class, 'bulk'])
        ->middleware('permission:users.delete|users.restore|users.forceDelete|users.setActive')
        ->name('users.bulk');
    Route::get('/users/selected', [UsersController::class, 'selected'])
        ->middleware('permission:users.view')
        ->name('users.selected');
    Route::get('/users/{user}', [UsersController::class, 'show'])
        ->middleware('permission:users.view')
        ->name('users.show');
    Route::get('/users/{user}/edit', [UsersController::class, 'edit'])
        ->middleware('permission:users.update')
        ->name('users.edit');
    Route::put('/users/{user}', [UsersController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('users.update');
    Route::patch('/users/{user}/active', [UsersController::class, 'setActive'])
        ->middleware('permission:users.setActive')
        ->name('users.setActive');
    Route::delete('/users/{user}', [UsersController::class, 'destroy'])
        ->middleware('permission:users.delete')
        ->name('users.destroy');
});
