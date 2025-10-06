<?php

declare(strict_types=1);

namespace App\Services\Charges;

use App\Contracts\Services\Charges\ReassignmentServiceInterface;
use App\Models\Charge;
use App\Models\DebtTransfer;
use App\Models\DebtTransferItem;
use Illuminate\Support\Facades\DB;

class ReassignmentService implements ReassignmentServiceInterface
{
    public function reassign(array $chargeIds, array $payload): array
    {
        return DB::transaction(function () use ($chargeIds, $payload) {
            $charges = Charge::query()
                ->whereIn('id', $chargeIds)
                ->lockForUpdate()
                ->get();

            if ($charges->isEmpty()) {
                return ['transfer_id' => 0, 'items' => 0];
            }

            // Collection is non-empty (early return above), so first() is non-null
            $currency = (string) ($charges->first()->getAttribute('currency') ?? ($payload['currency'] ?? 'EUR'));
            $total = (int) $charges->sum('amount_minor');

            $transfer = DebtTransfer::create([
                'executed_at' => now(),
                'performed_by_user_id' => (int) ($payload['performed_by_user_id'] ?? auth()->id() ?? 0),
                'market_id' => (int) $payload['market_id'],
                'local_id' => (int) $payload['local_id'],
                'from_debtor_type' => (string) $payload['from_debtor_type'],
                'from_debtor_id' => (int) $payload['from_debtor_id'],
                'to_debtor_type' => (string) $payload['to_debtor_type'],
                'to_debtor_id' => (int) $payload['to_debtor_id'],
                'new_contract_id' => isset($payload['new_contract_id']) ? (int) $payload['new_contract_id'] : null,
                'reason_id' => isset($payload['reason_id']) ? (int) $payload['reason_id'] : null,
                'note' => $payload['note'] ?? null,
                'total_amount_minor' => $total,
                'currency' => $currency,
            ]);

            $items = 0;
            foreach ($charges as $c) {
                // Snapshot before
                $prevDebtorType = (string) $c->getAttribute('debtor_type');
                $prevDebtorId = (int) $c->getAttribute('debtor_id');
                $prevContractId = $c->getAttribute('contract_id');

                // Update charge debtor to new target
                $c->setAttribute('debtor_type', (string) $payload['to_debtor_type']);
                $c->setAttribute('debtor_id', (int) $payload['to_debtor_id']);
                if (array_key_exists('new_contract_id', $payload)) {
                    $c->setAttribute('contract_id', $payload['new_contract_id'] ? (int) $payload['new_contract_id'] : null);
                }
                $c->save();

                // Record item
                DebtTransferItem::create([
                    'debt_transfer_id' => $transfer->getKey(),
                    'charge_id' => $c->getKey(),
                    'amount_minor' => (int) $c->getAttribute('amount_minor'),
                    'currency' => (string) $c->getAttribute('currency'),
                    'period' => (string) $c->getAttribute('period'),
                    'issued_on' => (string) $c->getAttribute('issued_on'),
                    'due_on' => (string) $c->getAttribute('due_on'),
                    'kind' => (string) $c->getAttribute('kind'),
                    'prev_debtor_type' => $prevDebtorType,
                    'prev_debtor_id' => $prevDebtorId,
                    'new_debtor_type' => (string) $payload['to_debtor_type'],
                    'new_debtor_id' => (int) $payload['to_debtor_id'],
                    'prev_contract_id' => $prevContractId ? (int) $prevContractId : null,
                    'new_contract_id' => isset($payload['new_contract_id']) ? (int) $payload['new_contract_id'] : null,
                ]);

                $items++;
            }

            return ['transfer_id' => (int) $transfer->getKey(), 'items' => $items];
        });
    }
}
