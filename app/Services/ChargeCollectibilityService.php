<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChargeStatusCode;
use App\Exceptions\DomainActionException;
use App\Models\Charge;
use App\Models\ChargeCollectibilityEvent;
use App\Models\User;
use App\Support\FxConversionHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ChargeCollectibilityService
{
    public function __construct(
        private FxConversionHelper $fxHelper,
    ) {}

    /**
     * @param  list<int>  $chargeIds
     * @return array{count:int,charge_ids:list<int>,outstanding_bs_minor:int,outstanding_amount_minor:int}
     */
    public function markUncollectible(array $chargeIds, string $reason, User $user): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainActionException('Debe indicar el motivo para declarar cargos incobrables.');
        }

        return DB::transaction(function () use ($chargeIds, $reason, $user): array {
            $charges = $this->lockedCharges($chargeIds);
            $this->assertAllIdsFound($chargeIds, $charges);

            $statusIds = ChargeStatusCode::collectableIds();
            $now = Carbon::now();
            $balances = $this->balances($charges, $now);
            $errors = [];

            foreach ($charges as $charge) {
                $chargeId = (int) $charge->getKey();
                $statusId = (int) $charge->getAttribute('charge_status_id');

                if (! in_array($statusId, $statusIds, true)) {
                    $errors[] = "Cargo {$chargeId}: solo cargos ISSUED o PARTIAL pueden declararse incobrables.";
                }

                if ($charge->getAttribute('uncollectible_at') !== null) {
                    $errors[] = "Cargo {$chargeId}: ya está declarado incobrable.";
                }

                if (($balances['bs'][$chargeId] ?? 0) <= 0) {
                    $errors[] = "Cargo {$chargeId}: no tiene saldo pendiente.";
                }
            }

            $this->throwIfErrors($errors);

            $totalBs = 0;
            $totalAmount = 0;
            $ids = [];

            foreach ($charges as $charge) {
                $chargeId = (int) $charge->getKey();
                $outstandingBs = (int) ($balances['bs'][$chargeId] ?? 0);
                $outstandingAmount = (int) ($balances['currency'][$chargeId] ?? 0);

                $charge->forceFill([
                    'uncollectible_at' => $now,
                    'uncollectible_reason' => $reason,
                    'uncollectible_by_user_id' => (int) $user->getKey(),
                ])->save();

                ChargeCollectibilityEvent::query()->create([
                    'charge_id' => $chargeId,
                    'action' => ChargeCollectibilityEvent::ActionMarkedUncollectible,
                    'reason' => $reason,
                    'outstanding_amount_minor' => $outstandingAmount,
                    'outstanding_bs_minor' => $outstandingBs,
                    'user_id' => (int) $user->getKey(),
                    'occurred_at' => $now,
                ]);

                $ids[] = $chargeId;
                $totalBs += $outstandingBs;
                $totalAmount += $outstandingAmount;
            }

            $this->flushFinancialCaches();

            return [
                'count' => count($ids),
                'charge_ids' => $ids,
                'outstanding_bs_minor' => $totalBs,
                'outstanding_amount_minor' => $totalAmount,
            ];
        });
    }

    /**
     * @param  list<int>  $chargeIds
     * @return array{count:int,charge_ids:list<int>}
     */
    public function restoreCollectible(array $chargeIds, string $reason, User $user): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainActionException('Debe indicar el motivo para restaurar cargos cobrables.');
        }

        return DB::transaction(function () use ($chargeIds, $reason, $user): array {
            $charges = $this->lockedCharges($chargeIds);
            $this->assertAllIdsFound($chargeIds, $charges);

            $now = Carbon::now();
            $balances = $this->balances($charges, $now);
            $errors = [];

            foreach ($charges as $charge) {
                $chargeId = (int) $charge->getKey();

                if ($charge->getAttribute('uncollectible_at') === null) {
                    $errors[] = "Cargo {$chargeId}: no está declarado incobrable.";
                }
            }

            $this->throwIfErrors($errors);

            $ids = [];
            foreach ($charges as $charge) {
                $chargeId = (int) $charge->getKey();

                ChargeCollectibilityEvent::query()->create([
                    'charge_id' => $chargeId,
                    'action' => ChargeCollectibilityEvent::ActionRestored,
                    'reason' => $reason,
                    'outstanding_amount_minor' => (int) ($balances['currency'][$chargeId] ?? 0),
                    'outstanding_bs_minor' => (int) ($balances['bs'][$chargeId] ?? 0),
                    'user_id' => (int) $user->getKey(),
                    'occurred_at' => $now,
                ]);

                $charge->forceFill([
                    'uncollectible_at' => null,
                    'uncollectible_reason' => null,
                    'uncollectible_by_user_id' => null,
                ])->save();

                $ids[] = $chargeId;
            }

            $this->flushFinancialCaches();

            return [
                'count' => count($ids),
                'charge_ids' => $ids,
            ];
        });
    }

    /**
     * @param  list<int>  $chargeIds
     * @return Collection<int, Charge>
     */
    private function lockedCharges(array $chargeIds): Collection
    {
        $ids = $this->normalizeIds($chargeIds);
        if ($ids === []) {
            throw new DomainActionException('No se seleccionaron cargos.');
        }

        return Charge::query()
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'charge_status_id', 'uncollectible_at']);
    }

    /**
     * @param  list<int>  $chargeIds
     * @param  Collection<int, Charge>  $charges
     */
    private function assertAllIdsFound(array $chargeIds, Collection $charges): void
    {
        $expected = $this->normalizeIds($chargeIds);
        $found = $charges->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $missing = array_values(array_diff($expected, $found));
        if ($missing !== []) {
            throw new DomainActionException('Algunos cargos no existen o fueron eliminados: '.implode(', ', $missing).'.');
        }
    }

    /**
     * @param  Collection<int, Charge>  $charges
     * @return array{bs:array<int,int>,currency:array<int,int>}
     */
    private function balances(Collection $charges, Carbon $at): array
    {
        return [
            'bs' => $this->fxHelper->chargesOutstandingVesBatch($charges, $at),
            'currency' => $this->fxHelper->chargesOutstandingCurrencyMinorBatch($charges, $at),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (int|string $id): int => (int) $id,
            $ids
        ), fn (int $id): bool => $id > 0)));
    }

    /**
     * @param  list<string>  $errors
     */
    private function throwIfErrors(array $errors): void
    {
        if ($errors !== []) {
            throw new DomainActionException(implode(' ', $errors));
        }
    }

    private function flushFinancialCaches(): void
    {
        Cache::flush();
    }
}
