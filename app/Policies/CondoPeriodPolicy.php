<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CondoPeriod;
use App\Models\User;

class CondoPeriodPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'condo_period';
    }

    public function update(User $user, $model): bool
    {
        if (! $model instanceof CondoPeriod) {
            return false;
        }

        return parent::update($user, $model) && $model->isDraft() && ! $model->hasCharges();
    }

    public function setActive(User $user, $model): bool
    {
        if (! $model instanceof CondoPeriod) {
            return false;
        }

        return parent::setActive($user, $model) && $model->isDraft() && ! $model->hasCharges();
    }

    public function delete(User $user, $model): bool
    {
        if (! $model instanceof CondoPeriod) {
            return false;
        }

        return parent::delete($user, $model) && $model->isDraft() && ! $model->hasCharges();
    }

    public function finalize(User $user, CondoPeriod $model): bool
    {
        return $this->can($user, 'finalize') && $model->isDraft();
    }

    public function reopen(User $user, CondoPeriod $model): bool
    {
        return $this->can($user, 'reopen') && $model->isFinal() && ! $model->hasCharges();
    }
}
