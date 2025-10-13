<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily expiration job for contracts (Laravel 12 style)
Schedule::command('contracts:expire')->daily();

// === Charges generation commands (skeleton) ===

Artisan::command('charges:rent-m2 {--market_id=} {--market_code=} {--period=} {--idempotency_key=} {--preview}', function () {
    /** @var \App\Contracts\Services\Charges\ChargesOrchestratorInterface $orchestrator */
    $orchestrator = app(\App\Contracts\Services\Charges\ChargesOrchestratorInterface::class);
    // Resolve market id by code if not provided
    $marketId = $this->option('market_id') ? (int) $this->option('market_id') : null;
    $marketCode = $this->option('market_code');
    if (! $marketId && $marketCode) {
        $marketId = (int) (DB::table('markets')->where('code', $marketCode)->value('id') ?? 0);
    }
    if (! $marketId) {
        $marketId = (int) (DB::table('markets')->orderBy('id')->value('id') ?? 0);
    }

    $params = array_filter([
        'market_id' => $marketId ?: null,
        'period' => $this->option('period'), // YYYY-MM-01
        'idempotency_key' => $this->option('idempotency_key'),
    ], fn ($v) => $v !== null && $v !== '');
    $result = $orchestrator->run('RENT_EUR_M2', $params);
    $this->info(sprintf('rent-m2 => generated:%d upserted:%d skipped:%d errors:%d', $result['generated'], $result['upserted'], $result['skipped'], count($result['errors'])));
    if (! empty($result['errors'])) {
        $this->error('Errors:');
        foreach ($result['errors'] as $err) {
            $this->error((string) $err);
        }
    }

    if ($this->option('preview')) {
        /** @var \App\Contracts\Services\Charges\ChargeCalculatorRegistryInterface $registry */
        $registry = app(\App\Contracts\Services\Charges\ChargeCalculatorRegistryInterface::class);
        $calc = $registry->get('RENT_EUR_M2');
        $rows = $calc->calculate($params);
        $this->line('Preview (first 3 rows):');
        foreach (array_slice($rows, 0, 3) as $r) {
            $this->line(json_encode($r));
        }

        // Compare a 2.27 m² local if present
        $sampleLocal = DB::table('locals')->where('market_id', $marketId)->where('area_m2', 2.27)->orderBy('id')->first(['id', 'area_m2']);
        if ($sampleLocal) {
            $localId = (int) $sampleLocal->id;
            $area = (float) $sampleLocal->area_m2;
            $priceMinor = (int) (DB::table('market_tariffs')->where('market_id', $marketId)->where('is_current', true)->orderByDesc('valid_from')->value('price_per_m2_eur_minor') ?? 0);
            $expected = (int) round($priceMinor * $area * (365 / 12), 0);
            $found = collect($rows)->firstWhere('local_id', $localId);
            $this->info(sprintf('Local id %d area %.2f => expected_minor=%d (formula: %.2f * %.2f * 365/12). Row amount_minor=%d', $localId, $area, $expected, $priceMinor / 100, $area, (int) ($found['amount_minor'] ?? -1)));
        }
    }
})->purpose('Generate monthly Rent per m2 charges');

Artisan::command('charges:count {--kind=} {--period=}', function () {
    $q = DB::table('charges');
    if ($this->option('kind')) {
        $q->where('kind', $this->option('kind'));
    }
    if ($this->option('period')) {
        $q->where('period', $this->option('period'));
    }
    $this->info('Charges count: '.$q->count());
})->purpose('Debug: count rows in charges table');

Artisan::command('charges:rent-fixed {--market_id=} {--date=} {--idempotency_key=}', function () {
    /** @var \App\Contracts\Services\Charges\ChargesOrchestratorInterface $orchestrator */
    $orchestrator = app(\App\Contracts\Services\Charges\ChargesOrchestratorInterface::class);
    $params = array_filter([
        'market_id' => $this->option('market_id') ? (int) $this->option('market_id') : null,
        'date' => $this->option('date'), // Y-m-d
        'idempotency_key' => $this->option('idempotency_key'),
    ], fn ($v) => $v !== null && $v !== '');
    $result = $orchestrator->run('RENT_EUR_FIXED', $params);
    $this->info(sprintf('rent-fixed => generated:%d upserted:%d skipped:%d errors:%d', $result['generated'], $result['upserted'], $result['skipped'], count($result['errors'])));
})->purpose('Generate daily Rent fixed charges');

Artisan::command('charges:condo {--market_id=} {--period=} {--idempotency_key=}', function () {
    /** @var \App\Contracts\Services\Charges\ChargesOrchestratorInterface $orchestrator */
    $orchestrator = app(\App\Contracts\Services\Charges\ChargesOrchestratorInterface::class);
    $params = array_filter([
        'market_id' => $this->option('market_id') ? (int) $this->option('market_id') : null,
        'period' => $this->option('period'), // YYYY-MM-01
        'idempotency_key' => $this->option('idempotency_key'),
    ], fn ($v) => $v !== null && $v !== '');
    $result = $orchestrator->run('CONDO_USD', $params);
    $this->info(sprintf('condo => generated:%d upserted:%d skipped:%d errors:%d', $result['generated'], $result['upserted'], $result['skipped'], count($result['errors'])));
})->purpose('Generate monthly Condo charges');

// Scheduler with TZ America/Caracas
Schedule::command('charges:rent-m2')->monthlyOn(1, '01:00')->timezone('America/Caracas');
Schedule::command('charges:rent-fixed')->dailyAt('02:00')->timezone('America/Caracas');
Schedule::command('charges:condo')->monthlyOn(1, '03:00')->timezone('America/Caracas');

// === FX rates ingestion (BCV) ===
Artisan::command('fx:ingest-bcv', function () {
    /** @var \App\Contracts\Services\FxRateServiceInterface $svc */
    $svc = app(\App\Contracts\Services\FxRateServiceInterface::class);
    $result = $svc->ingestFromBcv();
    $this->info(sprintf('BCV ingestion => inserted:%d updated:%d', $result['inserted'], $result['updated']));
})->purpose('Fetch official FX rates from BCV and upsert with operational windows');

// Daily at 08:15 America/Caracas
Schedule::command('fx:ingest-bcv')->dailyAt('08:15')->timezone('America/Caracas');
