<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

// Local-only playground to demo UI components without integrating into Index yet
if (app()->isLocal()) {
    Route::get('/playground', function () {
        return Inertia::render('playground');
    })->name('playground');
}

require __DIR__.'/settings.php';
require __DIR__.'/roles.php';
require __DIR__.'/users.php';
require __DIR__.'/auditoria.php';
require __DIR__.'/catalogs.php';
require __DIR__.'/auth.php';
