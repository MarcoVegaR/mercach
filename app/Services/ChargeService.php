<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\ChargeServiceInterface;
use Illuminate\Database\Eloquent\Model;
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

    private function getChargeStatusName(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }
        if ($this->statusNames === null) {
            $this->statusNames = DB::table('charge_statuses')->pluck('name', 'id')->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all();
        }

        return $this->statusNames[$id] ?? null;
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
}
