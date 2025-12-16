<?php

declare(strict_types=1);

namespace App\Services\Charges;

use App\Contracts\Repositories\ChargeRepositoryInterface;
use App\Contracts\Services\Charges\ChargeCalculatorRegistryInterface;
use App\Contracts\Services\Charges\ChargesOrchestratorInterface;

class ChargesOrchestrator implements ChargesOrchestratorInterface
{
    public function __construct(
        private ChargeRepositoryInterface $charges,
        private ChargeCalculatorRegistryInterface $registry,
    ) {}

    /** {@inheritDoc} */
    public function run(string $type, array $params): array
    {
        $errors = [];
        $generated = 0;
        $upserted = 0;
        $skipped = 0;
        $totalMinor = 0;
        $unitMinor = null;

        try {
            $calculator = $this->registry->get($type);
            $rows = $calculator->calculate($params);
            $generated = count($rows);
            // Totals for summary
            $totalMinor = array_reduce($rows, static function (int $acc, array $r): int {
                return $acc + (int) ($r['amount_minor'] ?? 0);
            }, 0);
            if ($generated > 0 && isset($rows[0]['meta_unit_minor'])) {
                $unitMinor = (int) $rows[0]['meta_unit_minor'];
            }

            if ($generated === 0) {
                return compact('generated', 'upserted', 'skipped', 'errors');
            }

            // Ensure common columns existence with defaults/placeholders
            $rows = array_map(function (array $row) use ($type) {
                $row['kind'] = $type; // enforce
                $row['source'] = (string) ($row['source'] ?? $this->defaultSourceFor($type));
                $row['currency'] = (string) ($row['currency'] ?? $this->defaultCurrencyFor($type));
                // timestamps for upsert
                $row['created_at'] = now();
                $row['updated_at'] = now();

                return $row;
            }, $rows);

            // Compute baseline in VES at issuance (stable) for settlement calculations
            try {
                /** @var \App\Contracts\Services\FxRateServiceInterface $fx */
                $fx = app(\App\Contracts\Services\FxRateServiceInterface::class);
                foreach ($rows as &$row) {
                    $currency = strtoupper((string) $row['currency']);
                    $amountMinor = (int) ($row['amount_minor'] ?? 0);
                    $issuedOnStr = (string) ($row['issued_on'] ?? '');
                    if ($amountMinor <= 0 || $issuedOnStr === '') {
                        continue;
                    }
                    if ($currency === 'VES') {
                        $row['amount_bs_minor_issued'] = $amountMinor;
                        $row['fx_rate_issued_id'] = null;

                        continue;
                    }
                    $issuedOn = \Illuminate\Support\Carbon::parse($issuedOnStr);
                    $rate = $fx->resolveAt($currency, $issuedOn);
                    $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                    if ($rateToVes !== null) {
                        // Truncate to 2 decimals instead of rounding
                        $prod = (int) round($amountMinor * ($rateToVes * 100));
                        $row['amount_bs_minor_issued'] = (int) intdiv($prod, 100);
                        $row['fx_rate_issued_id'] = $rate->getAttribute('id');
                    }
                }
                unset($row);
            } catch (\Throwable $e) {
                // if FX service fails, rows will fallback later via dynamic conversion when needed
            }

            [$uniqueBy, $updateCols] = $this->uniqueAndUpdateColumnsFor($type);

            // Detect existing charges that must NOT be modified because they already have
            // payments or credits applied. These represent historical transactions that
            // should remain immutable; regeneration must not overwrite them.
            $protectedKeys = $this->findProtectedChargeKeys($rows, $uniqueBy);

            if (! empty($protectedKeys)) {
                // Filter out rows that would touch protected charges
                $rows = array_filter($rows, function (array $row) use ($protectedKeys, $uniqueBy): bool {
                    $key = $this->buildUniqueKey($row, $uniqueBy);

                    return ! isset($protectedKeys[$key]);
                });
                $rows = array_values($rows);

                $skipped += count($protectedKeys);
            }

            // Upsert in batches (only charges without movements may be updated/created)
            $batchSize = 1000;
            foreach (array_chunk($rows, $batchSize) as $chunk) {
                $upserted += $this->charges->upsert($chunk, $uniqueBy, $updateCols);
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return compact('generated', 'upserted', 'skipped', 'errors', 'totalMinor', 'unitMinor');
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function uniqueAndUpdateColumnsFor(string $type): array
    {
        switch ($type) {
            case 'RENT_EUR_M2':
                return [
                    ['debtor_type', 'debtor_id', 'kind', 'period'],
                    [
                        'market_id', 'local_id', 'contract_id', 'amount_minor', 'currency', 'issued_on', 'due_on', 'charge_status_id', 'source', 'idempotency_key', 'origin_debtor_type', 'origin_debtor_id', 'amount_bs_minor_issued', 'fx_rate_issued_id', 'updated_at',
                    ],
                ];
            case 'RENT_EUR_FIXED':
                return [
                    ['debtor_type', 'debtor_id', 'kind', 'period'],
                    [
                        'market_id', 'local_id', 'contract_id', 'amount_minor', 'currency', 'period', 'issued_on', 'due_on', 'charge_status_id', 'source', 'idempotency_key', 'debtor_type', 'debtor_id', 'origin_debtor_type', 'origin_debtor_id', 'amount_bs_minor_issued', 'fx_rate_issued_id', 'updated_at',
                    ],
                ];
            case 'CONDO_USD':
                return [
                    ['condo_period_id', 'local_id', 'kind'],
                    [
                        'market_id', 'amount_minor', 'currency', 'period', 'issued_on', 'due_on', 'charge_status_id', 'source', 'idempotency_key', 'debtor_type', 'debtor_id', 'origin_debtor_type', 'origin_debtor_id', 'amount_bs_minor_issued', 'fx_rate_issued_id', 'updated_at',
                    ],
                ];
            default:
                throw new \InvalidArgumentException('Unsupported charge type: '.$type);
        }
    }

    private function defaultSourceFor(string $type): string
    {
        return match ($type) {
            'RENT_EUR_M2' => 'RENT_RUN',
            'RENT_EUR_FIXED' => 'FIXED_RUN',
            'CONDO_USD' => 'CONDO_RUN',
            default => 'RUN',
        };
    }

    private function defaultCurrencyFor(string $type): string
    {
        return match ($type) {
            'CONDO_USD' => 'USD',
            default => 'EUR',
        };
    }

    /**
     * Build a unique composite key string from row data and the unique columns.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $uniqueBy
     */
    private function buildUniqueKey(array $row, array $uniqueBy): string
    {
        return implode('|', array_map(
            static fn (string $col): string => (string) ($row[$col] ?? 'null'),
            $uniqueBy,
        ));
    }

    /**
     * Find existing charges (by unique keys) that already have payments or credits applied
     * and therefore must be treated as immutable for regeneration.
     *
     * Returns a map of composite keys => true for quick lookup.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $uniqueBy
     * @return array<string, bool>
     */
    private function findProtectedChargeKeys(array $rows, array $uniqueBy): array
    {
        if ($rows === []) {
            return [];
        }

        try {
            // Locate existing charges matching the unique keys, ignoring soft-deleted ones
            $existing = \Illuminate\Support\Facades\DB::table('charges')
                ->whereNull('deleted_at')
                ->where(function ($query) use ($rows, $uniqueBy): void {
                    foreach ($rows as $row) {
                        $query->orWhere(function ($q) use ($row, $uniqueBy): void {
                            foreach ($uniqueBy as $col) {
                                $val = $row[$col] ?? null;
                                if ($val !== null) {
                                    $q->where($col, $val);
                                } else {
                                    $q->whereNull($col);
                                }
                            }
                        });
                    }
                })
                ->get(array_merge($uniqueBy, ['id']));

            if ($existing->isEmpty()) {
                return [];
            }

            $ids = $existing->pluck('id')->map(static fn ($id): int => (int) $id)->all();

            // Charges that have at least one payment allocation
            $hasAllocations = \Illuminate\Support\Facades\DB::table('payment_allocations')
                ->whereIn('charge_id', $ids)
                ->whereNull('deleted_at')
                ->groupBy('charge_id')
                ->pluck('charge_id')
                ->mapWithKeys(static fn ($cid): array => [(int) $cid => true])
                ->all();

            // Charges that have at least one credit application
            $hasCreditApps = \Illuminate\Support\Facades\DB::table('credit_applications')
                ->whereIn('charge_id', $ids)
                ->whereNull('deleted_at')
                ->groupBy('charge_id')
                ->pluck('charge_id')
                ->mapWithKeys(static fn ($cid): array => [(int) $cid => true])
                ->all();

            $protected = [];
            foreach ($existing as $row) {
                $cid = (int) $row->id;
                if (! isset($hasAllocations[$cid]) && ! isset($hasCreditApps[$cid])) {
                    continue;
                }

                $data = [];
                foreach ($uniqueBy as $col) {
                    $data[$col] = $row->{$col} ?? null;
                }

                $key = $this->buildUniqueKey($data, $uniqueBy);
                $protected[$key] = true;
            }

            return $protected;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('charges.upsert.protection_check_failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
