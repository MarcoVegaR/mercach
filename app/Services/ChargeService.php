<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\ChargeServiceInterface;
use App\Exceptions\DomainActionException;
use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\CreditApplication;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChargeService extends BaseService implements ChargeServiceInterface
{
    /** @var array<int,string>|null */
    private ?array $marketNames = null;

    /** @var array<int,string>|null */
    private ?array $localNames = null;

    /** @var array<int,string>|null */
    private ?array $contractNumbers = null;

    /** @var array<int,string>|null */
    private ?array $statusNames = null;

    /** @var array<int,string>|null */
    private ?array $statusCodes = null;

    /** @var array<int,float>|null */
    private ?array $localAreas = null;

    private function getMarketName(int $id): string
    {
        if ($this->marketNames === null) {
            $this->marketNames = DB::table('markets')->pluck('name', 'id')->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all();
        }

        return $this->marketNames[$id] ?? (string) $id;
    }

    private function getLocalName(int $id): string
    {
        if ($this->localNames === null) {
            $this->localNames = DB::table('locals')->pluck('name', 'id')->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all();
        }

        return $this->localNames[$id] ?? (string) $id;
    }

    private function getLocalArea(int $id): float
    {
        if ($this->localAreas === null) {
            $this->localAreas = DB::table('locals')->pluck('area_m2', 'id')->mapWithKeys(fn ($v, $k) => [(int) $k => (float) $v])->all();
        }

        return (float) ($this->localAreas[$id] ?? 0.0);
    }

    private function getContractNumber(int|string|null $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $id = (int) $id;
        if ($this->contractNumbers === null) {
            $this->contractNumbers = DB::table('contracts')->pluck('number', 'id')->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all();
        }

        return $this->contractNumbers[$id] ?? null;
    }

    private function ensureStatusCache(): void
    {
        if ($this->statusNames !== null && $this->statusCodes !== null) {
            return;
        }

        $rows = DB::table('charge_statuses')->get(['id', 'name', 'code']);
        $names = [];
        $codes = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $names[$id] = (string) $row->name;
            $codes[$id] = (string) $row->code;
        }

        $this->statusNames = $names;
        $this->statusCodes = $codes;
    }

    private function getChargeStatusName(int|string|null $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $this->ensureStatusCache();
        $intId = (int) $id;

        return $this->statusNames[$intId] ?? null;
    }

    private function getChargeStatusCode(int|string|null $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $this->ensureStatusCache();
        $intId = (int) $id;

        return $this->statusCodes[$intId] ?? null;
    }

    protected function repoModelClass(): string
    {
        return \App\Models\Charge::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        // Lightweight row for index/export with friendly fields
        return [
            'id' => $model->getAttribute('id'),
            'market_id' => $model->getAttribute('market_id'),
            'market_name' => $this->getMarketName((int) $model->getAttribute('market_id')),
            'local_id' => $model->getAttribute('local_id'),
            'local_name' => $this->getLocalName((int) $model->getAttribute('local_id')),
            'local_area_m2' => $this->getLocalArea((int) $model->getAttribute('local_id')),
            'contract_id' => $model->getAttribute('contract_id'),
            'contract_number' => $this->getContractNumber($model->getAttribute('contract_id')),
            'condo_period_id' => $model->getAttribute('condo_period_id'),
            'debtor_type' => $model->getAttribute('debtor_type'),
            'debtor_id' => $model->getAttribute('debtor_id'),
            'kind' => $model->getAttribute('kind'),
            'currency' => $model->getAttribute('currency'),
            'amount_minor' => (int) $model->getAttribute('amount_minor'),
            'period' => $model->getAttribute('period'),
            'issued_on' => $model->getAttribute('issued_on'),
            'due_on' => $model->getAttribute('due_on'),
            'charge_status_id' => $model->getAttribute('charge_status_id'),
            'charge_status_name' => $this->getChargeStatusName($model->getAttribute('charge_status_id')),
            'charge_status_code' => $this->getChargeStatusCode($model->getAttribute('charge_status_id')),
            'source' => $model->getAttribute('source'),
            'created_at' => $model->getAttribute('created_at'),
        ];
    }

    /**
     * @return array<string, string|int>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'market_id' => 'Market',
            'local_id' => 'Local',
            'contract_id' => 'Contract',
            'condo_period_id' => 'Condo period',
            'debtor_type' => 'Debtor type',
            'debtor_id' => 'Debtor id',
            'kind' => 'Kind',
            'currency' => 'Currency',
            'amount_minor' => 'Amount (minor)',
            'period' => 'Period',
            'issued_on' => 'Issued on',
            'due_on' => 'Due on',
            'charge_status_id' => 'Status',
            'source' => 'Source',
            'created_at' => 'Created',
        ];
    }

    /** {@inheritDoc} */
    public function cancel(int|string $chargeId, ?string $note = null): array
    {
        /** @var Charge $charge */
        $charge = $this->getOrFailById($chargeId);

        return $this->transaction(function () use ($charge, $note) {
            $charge->refresh();

            $statusId = (int) $charge->getAttribute('charge_status_id');
            $code = null;
            if ($statusId > 0) {
                $code = (string) (ChargeStatus::query()->where('id', $statusId)->value('code') ?? null);
                $code = strtoupper($code ?: '');
            }

            // Only ISSUED/PARTIAL charges can be canceled
            if (! in_array($code, ['ISSUED', 'PARTIAL'], true)) {
                throw new DomainActionException('Solo se pueden anular cargos en estado ISSUED o PARTIAL.');
            }

            $cid = (int) $charge->getKey();

            // Guard: no allocations
            $allocSum = (int) PaymentAllocation::query()
                ->where('charge_id', $cid)
                ->sum('amount_bs_minor');
            if ($allocSum > 0) {
                throw new DomainActionException('No se puede anular un cargo que ya tiene pagos aplicados.');
            }

            // Guard: no credit applications
            $creditApps = (int) CreditApplication::query()
                ->where('charge_id', $cid)
                ->count();
            if ($creditApps > 0) {
                throw new DomainActionException('No se puede anular un cargo que ya tiene créditos aplicados.');
            }

            $statusCanceledId = (int) (ChargeStatus::query()->where('code', 'CANCELED')->value('id') ?? 0);
            if ($statusCanceledId <= 0) {
                throw new DomainActionException('Estado CANCELED no está configurado en el catálogo de estados de cargo.');
            }

            $attributes = [
                'charge_status_id' => $statusCanceledId,
                'settled_on' => Carbon::now()->toDateString(),
            ];
            if ($note !== null && $note !== '') {
                $attributes['note'] = $note;
            }

            /** @var Charge $updated */
            $updated = $this->update($charge, $attributes);

            return $this->toRow($updated);
        });
    }

    /** {@inheritDoc} */
    public function createExtra(array $attributes): array
    {
        return $this->transaction(function () use ($attributes) {
            $localId = (int) ($attributes['local_id'] ?? 0);
            $marketId = (int) ($attributes['market_id'] ?? 0);
            if ($localId <= 0) {
                throw new DomainActionException('El local es requerido para crear un cargo extraordinario.');
            }

            // Resolve market from local when not provided
            if ($marketId <= 0) {
                $marketId = (int) (DB::table('locals')->where('id', $localId)->value('market_id') ?? 0);
            }
            if ($marketId <= 0) {
                throw new DomainActionException('No se pudo resolver el mercado del local para el cargo extraordinario.');
            }

            $kind = strtoupper((string) ($attributes['kind'] ?? 'FINE'));
            $currency = strtoupper((string) ($attributes['currency'] ?? 'EUR'));
            $amountMinor = (int) ($attributes['amount_minor'] ?? 0);
            if ($amountMinor <= 0) {
                throw new DomainActionException('El monto del cargo extraordinario debe ser mayor a cero.');
            }

            $periodStr = (string) ($attributes['period'] ?? date('Y-m-01'));
            $issuedOnStr = (string) ($attributes['issued_on'] ?? date('Y-m-d'));
            $dueOnStr = (string) ($attributes['due_on'] ?? $issuedOnStr);

            $period = Carbon::parse($periodStr)->startOfMonth();
            $issuedOn = Carbon::parse($issuedOnStr)->toDateString();
            $dueOn = Carbon::parse($dueOnStr)->toDateString();

            $statusIssuedId = (int) (ChargeStatus::query()->where('code', 'ISSUED')->value('id') ?? 0);
            if ($statusIssuedId <= 0) {
                throw new DomainActionException('Estado ISSUED no está configurado en el catálogo de estados de cargo.');
            }

            // Baseline in VES at issuance
            $amountBsMinorIssued = null;
            $fxRateId = null;
            try {
                /** @var \App\Contracts\Services\FxRateServiceInterface $fx */
                $fx = $this->container->get(\App\Contracts\Services\FxRateServiceInterface::class);
                if ($currency === 'VES') {
                    $amountBsMinorIssued = $amountMinor;
                } else {
                    $rate = $fx->resolveAt($currency, Carbon::parse($issuedOn));
                    $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                    if ($rateToVes !== null) {
                        $amountBsMinorIssued = (int) round(($amountMinor / 100.0) * $rateToVes * 100);
                        $fxRateId = (int) $rate->getAttribute('id');
                    }
                }
            } catch (\Throwable $e) {
                // si FX falla, amount_bs_minor_issued queda null y se resolverá dinámicamente cuando se necesite
            }

            // Idempotency key (optional): prevent duplicate extras
            $idemp = $attributes['idempotency_key'] ?? null;
            if ($idemp === null || $idemp === '') {
                $fingerprint = [
                    'local_id' => $localId,
                    'market_id' => $marketId,
                    'kind' => $kind,
                    'currency' => $currency,
                    'amount_minor' => $amountMinor,
                    'period' => $period->toDateString(),
                    'due_on' => $dueOn,
                    'note' => (string) ($attributes['note'] ?? ''),
                ];
                $idemp = hash('sha256', json_encode($fingerprint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            $row = [
                'market_id' => $marketId,
                'local_id' => $localId,
                'contract_id' => $attributes['contract_id'] ?? null,
                'condo_period_id' => null,
                'debtor_type' => 'LOCAL',
                'debtor_id' => $localId,
                'origin_debtor_type' => 'LOCAL',
                'origin_debtor_id' => $localId,
                'kind' => $kind,
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'amount_bs_minor_issued' => $amountBsMinorIssued,
                'fx_rate_issued_id' => $fxRateId,
                'period' => $period->toDateString(),
                'issued_on' => $issuedOn,
                'due_on' => $dueOn,
                'settled_on' => null,
                'charge_status_id' => $statusIssuedId,
                'source' => (string) ($attributes['source'] ?? 'EXTRA'),
                'idempotency_key' => $idemp,
                'note' => (string) ($attributes['note'] ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Try to find existing by idempotency_key to enforce idempotence at service level
            if (! empty($row['idempotency_key'])) {
                /** @var null|Charge $existing */
                $existing = Charge::query()
                    ->where('idempotency_key', $row['idempotency_key'])
                    ->whereNull('deleted_at')
                    ->first();
                if ($existing) {
                    return $this->toRow($existing);
                }
            }

            /** @var Charge $created */
            $created = $this->repo->create($row);

            return $this->toRow($created);
        });
    }
}
