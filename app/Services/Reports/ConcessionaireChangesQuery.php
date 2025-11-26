<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query builder for Concessionaire Changes report.
 *
 * Identifies when a local changed from one concessionaire to another.
 */
class ConcessionaireChangesQuery
{
    private ?string $changedFrom = null;

    private ?string $changedTo = null;

    /**
     * Filter by date range.
     */
    public function changedBetween(?string $from, ?string $to): self
    {
        $this->changedFrom = $from;
        $this->changedTo = $to;

        return $this;
    }

    /**
     * Execute the query and return change events.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        $rows = $this->getContractLocalData();
        $events = $this->detectChanges($rows);
        $events = $this->applyDateFilter($events);

        return collect($events);
    }

    /**
     * Execute for export with Spanish column names.
     *
     * @return Collection<int, array{
     *     'Fecha cambio': string,
     *     Local: string,
     *     'Cesionario anterior': string,
     *     'Cesionario nuevo': string,
     *     'Contrato anterior': string,
     *     'Contrato nuevo': string,
     * }>
     */
    public function executeForExport(): Collection
    {
        return $this->execute()->map(fn (array $e) => [
            'Fecha cambio' => (string) $e['change_date'],
            'Local' => (string) ($e['local_code'] ?: $e['local_name'] ?: $e['local_id']),
            'Cesionario anterior' => (string) $e['old_concessionaire_name'],
            'Cesionario nuevo' => (string) $e['new_concessionaire_name'],
            'Contrato anterior' => (string) $e['old_contract_number'],
            'Contrato nuevo' => (string) $e['new_contract_number'],
        ]);
    }

    /**
     * Get all contract-local associations with concessionaire data.
     *
     * @return Collection<int, \stdClass>
     */
    private function getContractLocalData(): Collection
    {
        return DB::table('contract_local as cl')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
            ->join('concessionaire_contract as cc', function ($join): void {
                $join->on('cc.contract_id', '=', 'c.id')->where('cc.is_primary', true);
            })
            ->join('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNull('cn.deleted_at')
            ->select([
                'l.id as local_id',
                'l.code as local_code',
                'l.name as local_name',
                'c.id as contract_id',
                'c.number as contract_number',
                'c.start_date as contract_start_date',
                'cn.id as concessionaire_id',
                'cn.full_name as concessionaire_name',
            ])
            ->orderBy('l.id')
            ->orderBy('c.start_date')
            ->get();
    }

    /**
     * Detect concessionaire changes by comparing sequential contracts.
     *
     * @param  Collection<int, \stdClass>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function detectChanges(Collection $rows): array
    {
        $events = [];
        $grouped = $rows->groupBy('local_id');

        foreach ($grouped as $localId => $list) {
            $sorted = $list->sortBy('contract_start_date')->values();

            for ($i = 1; $i < $sorted->count(); $i++) {
                $prev = $sorted[$i - 1];
                $curr = $sorted[$i];

                // Skip if same concessionaire
                if ((int) $prev->concessionaire_id === (int) $curr->concessionaire_id) {
                    continue;
                }

                $events[] = [
                    'local_id' => (int) $localId,
                    'local_code' => (string) ($curr->local_code ?? ''),
                    'local_name' => (string) ($curr->local_name ?? ''),
                    'change_date' => (string) $curr->contract_start_date,
                    'old_concessionaire_id' => (int) $prev->concessionaire_id,
                    'old_concessionaire_name' => (string) $prev->concessionaire_name,
                    'new_concessionaire_id' => (int) $curr->concessionaire_id,
                    'new_concessionaire_name' => (string) $curr->concessionaire_name,
                    'old_contract_id' => (int) $prev->contract_id,
                    'old_contract_number' => (string) $prev->contract_number,
                    'new_contract_id' => (int) $curr->contract_id,
                    'new_contract_number' => (string) $curr->contract_number,
                ];
            }
        }

        return $events;
    }

    /**
     * Apply date filter to events.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function applyDateFilter(array $events): array
    {
        if ($this->changedFrom === null && $this->changedTo === null) {
            return $events;
        }

        return array_values(array_filter($events, function (array $e): bool {
            $date = (string) $e['change_date'];
            if ($date === '') {
                return false;
            }
            if ($this->changedFrom !== null && $date < $this->changedFrom) {
                return false;
            }
            if ($this->changedTo !== null && $date > $this->changedTo) {
                return false;
            }

            return true;
        }));
    }
}
