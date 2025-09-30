<?php

use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\DashboardContractsTimelineController;
use App\Http\Controllers\Api\DashboardRankingsController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Web entry point (Inertia)
Route::middleware(['auth', 'permission:dashboard.view'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

// BFF endpoints (JSON)
Route::middleware(['auth'])->group(function () {
    Route::get('/api/dashboard/kpis', [DashboardApiController::class, 'kpis'])
        ->middleware('permission:dashboard.view.cards')
        ->name('api.dashboard.kpis');

    Route::get('/api/dashboard/locales-disponibles-distribucion', [DashboardApiController::class, 'localsAvailableDistribution'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.locals-available-distribution');

    // Alias spec-compatible
    Route::get('/api/dashboard/distributions', [DashboardApiController::class, 'distributions'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.distributions');

    // Spec endpoint
    Route::get('/api/dashboard/locals/available-by-type', [DashboardApiController::class, 'localsAvailableByType'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.locals.available-by-type');

    // ALL locals by type (total) — used by donut chart
    Route::get('/api/dashboard/locals/by-type', [DashboardApiController::class, 'localsByType'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.locals.by-type');

    // Concessionaires rankings (top/bottom by contracts or m2)
    Route::get('/api/dashboard/rankings', DashboardRankingsController::class)
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.rankings');

    // Contracts timeline (ordered by start_date or end_date)
    Route::get('/api/dashboard/contracts/timeline', DashboardContractsTimelineController::class)
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.contracts.timeline');
});
