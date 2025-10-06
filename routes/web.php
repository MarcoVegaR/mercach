<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Health check that returns 200 without DB access
Route::get('/healthz', function () {
    return response('ok', 200);
})->name('healthz');

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Dashboard routes (web + api) are defined in a dedicated file
require __DIR__.'/dashboard.php';

// Playground eliminado

require __DIR__.'/settings.php';
require __DIR__.'/roles.php';
require __DIR__.'/users.php';
require __DIR__.'/auditoria.php';
require __DIR__.'/catalogs.php';
require __DIR__.'/condo.php';
require __DIR__.'/charges.php';
require __DIR__.'/auth.php';
