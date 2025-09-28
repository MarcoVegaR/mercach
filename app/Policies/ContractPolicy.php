<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'catalogs.contract';
    }

    /**
     * Update allowed by permission; BORR-only enforced by domain service to provide redirect with flash.
     */
    public function update(User $user, $model): bool
    {
        return $this->can($user, 'update');
    }

    /**
     * Delete only allowed in BORR state.
     */
    public function delete(User $user, $model): bool
    {
        // Keep permission check only; state will be enforced by Service with domain error (redirect with flash),
        // which preserves existing controller test expectations.
        return $this->can($user, 'delete');
    }

    /**
     * Confirm only allowed in BORR state (uses update permission).
     */
    public function confirm(User $user, Contract $model): bool
    {
        if (! $this->can($user, 'update')) {
            return false;
        }
        $model->loadMissing('status:id,code');

        return strtoupper((string) ($model->status?->code ?: '')) === 'BORR';
    }

    /**
     * Terminate only allowed in VIG or EXT state (uses update permission).
     */
    public function terminate(User $user, Contract $model): bool
    {
        if (! $this->can($user, 'update')) {
            return false;
        }
        $model->loadMissing('status:id,code');

        return in_array(strtoupper((string) ($model->status?->code ?: '')), ['VIG', 'EXT'], true);
    }

    /**
     * Extend only allowed in VIG or EXT state (uses update permission).
     */
    public function extend(User $user, Contract $model): bool
    {
        return $this->terminate($user, $model);
    }
}
