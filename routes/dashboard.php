<?php

use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\DashboardContractsTimelineController;
use App\Http\Controllers\Api\DashboardDebtDetailController;
use App\Http\Controllers\Api\DashboardDebtRankingController;
use App\Http\Controllers\Api\DashboardRankingsController;
use App\Http\Controllers\Api\DebtAnalysisController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Web entry points (Inertia)
Route::middleware(['auth', 'permission:dashboard.view'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Debt Analysis section
    Route::get('/dashboard/debt-analysis', function () {
        return inertia('debt-analysis/index');
    })->middleware('permission:dashboard.view.finance')->name('dashboard.debt-analysis');
});

// BFF endpoints (JSON)
Route::middleware(['auth'])->group(function () {
    Route::get('/api/dashboard/kpis', [DashboardApiController::class, 'kpis'])
        ->middleware('permission:dashboard.view.cards')
        ->name('api.dashboard.kpis');

    // Contracts by status (VIG, EXT, TERM, VENC)
    Route::get('/api/dashboard/contracts/by-status', [DashboardApiController::class, 'contractsByStatus'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.contracts.by-status');

    // Contracts by type (CONTR, CONV, ...)
    Route::get('/api/dashboard/contracts/by-type', [DashboardApiController::class, 'contractsByType'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.contracts.by-type');

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

    // Locals by location (ALL)
    Route::get('/api/dashboard/locals/by-location', [DashboardApiController::class, 'localsByLocation'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.locals.by-location');

    // Concessionaires by type (PNAT vs PJUR)
    Route::get('/api/dashboard/concessionaires/by-type', [DashboardApiController::class, 'concessionairesByType'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.concessionaires.by-type');

    // Natural concessionaires by document (V vs E)
    Route::get('/api/dashboard/concessionaires/natural-by-document', [DashboardApiController::class, 'naturalConcessionairesByDocument'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.concessionaires.natural-by-document');

    // Concessionaires rankings (top/bottom by contracts or m2)
    Route::get('/api/dashboard/rankings', DashboardRankingsController::class)
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.rankings');

    // Contracts timeline (ordered by start_date or end_date)
    Route::get('/api/dashboard/contracts/timeline', DashboardContractsTimelineController::class)
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.contracts.timeline');

    // Debt and risk metrics
    Route::get('/api/dashboard/debt/metrics', [DashboardApiController::class, 'debtMetrics'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.debt.metrics');

    // Overdue counts (> N days)
    Route::get('/api/dashboard/debt/overdue-counts', [DashboardApiController::class, 'overdueCounts'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.debt.overdue-counts');

    // Charges analytics
    Route::get('/api/dashboard/charges/by-kind', [DashboardApiController::class, 'chargesByKind'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.charges.by-kind');
    Route::get('/api/dashboard/charges/by-status', [DashboardApiController::class, 'chargesByStatus'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.charges.by-status');
    Route::get('/api/dashboard/charges/open-by-month', [DashboardApiController::class, 'chargesOpenByMonth'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.charges.open-by-month');

    // Revenue projection (monthly)
    Route::get('/api/dashboard/revenue/projection', [DashboardApiController::class, 'revenueProjection'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.revenue.projection');

    // Revenue top locals (monthly)
    Route::get('/api/dashboard/revenue/top-locals', [DashboardApiController::class, 'topRevenueLocals'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.revenue.top-locals');

    // Payment statistics
    Route::get('/api/dashboard/payment/metrics', [DashboardApiController::class, 'paymentMetrics'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.payment.metrics');

    // Debt ranking (top delinquent concessionaires)
    Route::get('/api/dashboard/debt/ranking', DashboardDebtRankingController::class)
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.debt.ranking');

    // VIG contracts breakdown (signed vs unsigned)
    Route::get('/api/dashboard/contracts/vigentes-breakdown', [DashboardApiController::class, 'vigentesBreakdown'])
        ->middleware('permission:dashboard.view.charts')
        ->name('api.dashboard.contracts.vigentes-breakdown');

    // Payment trend (monthly revenue)
    Route::get('/api/dashboard/payment/trend', [DashboardApiController::class, 'paymentTrend'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.payment.trend');

    // Debt detail endpoints
    Route::get('/api/dashboard/debt/by-concessionaire', [DashboardDebtDetailController::class, 'byConcessionaire'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.debt.by-concessionaire');

    Route::get('/api/dashboard/debt/by-local', [DashboardDebtDetailController::class, 'byLocal'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.debt.by-local');

    Route::get('/api/dashboard/debt/solvent', [DashboardDebtDetailController::class, 'solvent'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.dashboard.debt.solvent');

    // Debt Analysis endpoints (new section)
    Route::get('/api/debt-analysis/delinquent-concessionaires', [DebtAnalysisController::class, 'delinquentConcessionaires'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.debt-analysis.delinquent-concessionaires');

    Route::get('/api/debt-analysis/delinquent-locals', [DebtAnalysisController::class, 'delinquentLocals'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.debt-analysis.delinquent-locals');

    Route::get('/api/debt-analysis/solvent-concessionaires', [DebtAnalysisController::class, 'solventConcessionaires'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.debt-analysis.solvent-concessionaires');

    Route::get('/api/debt-analysis/distributions', [DebtAnalysisController::class, 'distributions'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.debt-analysis.distributions');

    Route::get('/api/debt-analysis/export', [DebtAnalysisController::class, 'export'])
        ->middleware('permission:dashboard.view.finance')
        ->name('api.debt-analysis.export');
});
