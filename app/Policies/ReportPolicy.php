<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    /**
     * Determine if the user can view bank validations report.
     */
    public function viewBankValidations(User $user): bool
    {
        return $user->can('reports.bank_validations.view');
    }

    /**
     * Determine if the user can export bank validations report.
     */
    public function exportBankValidations(User $user): bool
    {
        return $user->can('reports.bank_validations.export');
    }
}
