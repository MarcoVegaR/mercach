<?php

declare(strict_types=1);

namespace App\Contracts\Services\Charges;

interface ReassignmentServiceInterface
{
    /**
     * Registers a debt transfer and reassigns charges' debtor.
     * Skeleton only: no business logic decisions.
     *
     * @param  array<int>  $chargeIds
     * @param  array<string,mixed>  $payload  Keys: market_id, local_id, from_debtor_type, from_debtor_id, to_debtor_type, to_debtor_id, new_contract_id?, reason_id?, note?
     * @return array{transfer_id:int, items:int}
     */
    public function reassign(array $chargeIds, array $payload): array;
}
