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

    public function viewDailyBankReconciliation(User $user): bool
    {
        return $user->can('reports.daily_bank_reconciliation.view');
    }

    public function exportDailyBankReconciliation(User $user): bool
    {
        return $user->can('reports.daily_bank_reconciliation.export');
    }

    /**
     * Determine if the user can view unsigned contracts report.
     */
    public function viewContractsUnsigned(User $user): bool
    {
        return $user->can('reports.contracts_unsigned.view');
    }

    /**
     * Determine if the user can export unsigned contracts report.
     */
    public function exportContractsUnsigned(User $user): bool
    {
        return $user->can('reports.contracts_unsigned.export');
    }

    /**
     * Determine if the user can view concessionaire changes report.
     */
    public function viewConcessionaireChanges(User $user): bool
    {
        return $user->can('reports.concessionaire_changes.view');
    }

    /**
     * Determine if the user can export concessionaire changes report.
     */
    public function exportConcessionaireChanges(User $user): bool
    {
        return $user->can('reports.concessionaire_changes.export');
    }

    /**
     * Determine if the user can view recovered locals report.
     */
    public function viewLocalsRecovered(User $user): bool
    {
        return $user->can('reports.locals_recovered.view');
    }

    /**
     * Determine if the user can export recovered locals report.
     */
    public function exportLocalsRecovered(User $user): bool
    {
        return $user->can('reports.locals_recovered.export');
    }

    public function viewLocalsFinancialStatus(User $user): bool
    {
        return $user->can('reports.locals_financial_status.view');
    }

    public function exportLocalsFinancialStatus(User $user): bool
    {
        return $user->can('reports.locals_financial_status.export');
    }
}
