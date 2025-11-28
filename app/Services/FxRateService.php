<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\FxRateServiceInterface;
use Illuminate\Database\Eloquent\Model;

class FxRateService extends BaseService implements FxRateServiceInterface
{
    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'currency_code' => $model->getAttribute('currency_code'),
            'rate_date' => $model->getAttribute('rate_date'),
            'value_date' => $model->getAttribute('value_date'),
            'published_at' => $model->getAttribute('published_at'),
            'rate_to_ves' => $model->getAttribute('rate_to_ves'),
            'operational_from' => $model->getAttribute('operational_from'),
            'operational_to' => $model->getAttribute('operational_to'),
            'source' => $model->getAttribute('source'),
            'is_official' => $model->getAttribute('is_official'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at') con el shape correcto según --fields.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        return [
            'id' => $model->getAttribute('id'),
            'currency_code' => $model->getAttribute('currency_code'),
            'rate_date' => $model->getAttribute('rate_date'),
            'value_date' => $model->getAttribute('value_date'),
            'published_at' => $model->getAttribute('published_at'),
            'rate_to_ves' => $model->getAttribute('rate_to_ves'),
            'operational_from' => $model->getAttribute('operational_from'),
            'operational_to' => $model->getAttribute('operational_to'),
            'source' => $model->getAttribute('source'),
            'is_official' => $model->getAttribute('is_official'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
        ];
    }

    /**
     * Columnas por defecto de exportación (cabeceras).
     * El generador reemplazará 'id' => '#',
            'currency_code' => 'Currency code',
            'rate_date' => 'Rate date',
            'value_date' => 'Value date',
            'published_at' => 'Published at',
            'rate_to_ves' => 'Rate to ves',
            'operational_from' => 'Operational from',
            'operational_to' => 'Operational to',
            'source' => 'Source',
            'is_official' => 'Is official',
            'is_active' => 'Estado',
            'created_at' => 'Creado'.
     *
     * @return array<string, string|int>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'currency_code' => 'Currency code',
            'rate_date' => 'Rate date',
            'value_date' => 'Value date',
            'published_at' => 'Published at',
            'rate_to_ves' => 'Rate to ves',
            'operational_from' => 'Operational from',
            'operational_to' => 'Operational to',
            'source' => 'Source',
            'is_official' => 'Is official',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\FxRate::class;
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\FxRate::query();
        $total = (int) $model->count();
        $active = (int) (clone $model)->where('is_active', true)->count();

        return [
            'stats' => [
                'total' => $total,
                'active' => $active,
            ],
        ];
    }

    /**
     * Resolve rate at instant within operational window.
     */
    public function resolveAt(string $currencyCode, \DateTimeInterface $at): ?\App\Models\FxRate
    {
        $ts = (new \Illuminate\Support\Carbon)->setTimestamp($at->getTimestamp());
        $key = 'fx:resolve:'.strtoupper($currencyCode).':'.(int) floor($ts->getTimestamp() / 60);
        $started = microtime(true);

        $rate = \Illuminate\Support\Facades\Cache::remember($key, 60, function () use ($currencyCode, $ts) {
            $dbStarted = microtime(true);
            $row = \App\Models\FxRate::query()
                ->where('currency_code', strtoupper($currencyCode))
                ->where('operational_from', '<=', $ts)
                ->where(function ($q) use ($ts) {
                    $q->whereNull('operational_to')->orWhere('operational_to', '>', $ts);
                })
                ->where('is_active', true)
                ->orderByDesc('operational_from')
                ->first();

            try {
                \Log::info('fx.resolve.db', [
                    'currency' => strtoupper($currencyCode),
                    'at' => $ts->toDateTimeString(),
                    'rate_id' => $row ? (int) $row->getAttribute('id') : null,
                    'rate_to_ves' => $row ? (float) $row->getAttribute('rate_to_ves') : null,
                    'operational_from' => $row ? (string) $row->getAttribute('operational_from') : null,
                    'operational_to' => $row ? (string) $row->getAttribute('operational_to') : null,
                    'latency_ms' => (int) ((microtime(true) - $dbStarted) * 1000),
                ]);
            } catch (\Throwable $e) {
            }

            return $row;
        });

        $latencyMs = (int) ((microtime(true) - $started) * 1000);

        try {
            \Log::info('fx.resolve', [
                'currency' => strtoupper($currencyCode),
                'at' => $ts->toDateTimeString(),
                'cache_key' => $key,
                'rate_id' => $rate ? (int) $rate->getAttribute('id') : null,
                'rate_to_ves' => $rate ? (float) $rate->getAttribute('rate_to_ves') : null,
                'operational_from' => $rate ? (string) $rate->getAttribute('operational_from') : null,
                'operational_to' => $rate ? (string) $rate->getAttribute('operational_to') : null,
                'latency_ms' => $latencyMs,
            ]);
        } catch (\Throwable $e) {
        }

        return $rate;
    }

    /**
     * Fetch from BCV and upsert rates, closing previous window.
     *
     * @return array{inserted:int,updated:int}
     */
    public function ingestFromBcv(): array
    {
        $provider = app(\App\Services\ExchangeRate\BcvProvider::class);
        $rates = $provider->fetchRates();

        $inserted = 0;
        $updated = 0;
        $validFrom = \Illuminate\Support\Carbon::parse($rates['valid_from'], (string) config('app.timezone', 'America/Caracas'));
        $valueDate = $validFrom->toDateString();
        $now = \Illuminate\Support\Carbon::now();
        $tz = (string) config('app.timezone', 'America/Caracas');
        $operationalFrom = \Illuminate\Support\Carbon::parse($valueDate, $tz)->startOfDay();

        foreach (['USD', 'EUR'] as $ccy) {
            if (! isset($rates[$ccy])) {
                continue;
            }
            $rate = (float) $rates[$ccy];
            if ($rate <= 0) {
                continue;
            }
            // Close previous open window
            $prev = \App\Models\FxRate::query()
                ->where('currency_code', $ccy)
                ->whereNull('operational_to')
                ->orderByDesc('operational_from')
                ->first();
            if ($prev && \Illuminate\Support\Carbon::parse((string) $prev->getAttribute('operational_from'))->lt($operationalFrom)) {
                $prev->setAttribute('operational_to', (clone $operationalFrom)->subSecond());
                $prev->save();
            }

            // Upsert by (currency_code, value_date)
            $attributes = [
                'currency_code' => $ccy,
                'value_date' => $valueDate,
            ];
            $values = [
                'rate_date' => $valueDate,
                'published_at' => $now,
                'rate_to_ves' => number_format($rate, 2, '.', ''),
                'operational_from' => $operationalFrom,
                'operational_to' => null,
                'source' => 'BCV',
                'is_official' => true,
                'is_active' => true,
                'updated_at' => $now,
            ];

            $existing = \App\Models\FxRate::query()->where($attributes)->first();
            if ($existing) {
                $existing->fill($values);
                $existing->save();
                $updated++;
            } else {
                $insert = new \App\Models\FxRate(array_merge($attributes, $values));
                $insert->save();
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
