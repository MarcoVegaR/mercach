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
                        $row['amount_bs_minor_issued'] = (int) round(($amountMinor / 100.0) * $rateToVes * 100);
                        $row['fx_rate_issued_id'] = $rate->getAttribute('id');
                    }
                }
                unset($row);
            } catch (\Throwable $e) {
                // if FX service fails, rows will fallback later via dynamic conversion when needed
            }

            [$uniqueBy, $updateCols] = $this->uniqueAndUpdateColumnsFor($type);

            // Upsert in batches
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
                    ['contract_id', 'local_id', 'kind', 'issued_on'],
                    [
                        'market_id', 'amount_minor', 'currency', 'period', 'due_on', 'charge_status_id', 'source', 'idempotency_key', 'debtor_type', 'debtor_id', 'origin_debtor_type', 'origin_debtor_id', 'amount_bs_minor_issued', 'fx_rate_issued_id', 'updated_at',
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
}
