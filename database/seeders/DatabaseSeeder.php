<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed permissions & admin role first
        $this->call(PermissionsSeeder::class);

        // Seed portal roles (e.g., 'concesionario')
        $this->call(PortalRolesSeeder::class);

        // Seed 'Gestor de Cobranza' role
        $this->call(GestorCobranzaRoleSeeder::class);

        // Seed 'Consultoría Jurídica' role
        $this->call(ConsultoriaJuridicaRoleSeeder::class);

        $this->call(RolesCleanupSeeder::class);

        // Seed the single default admin user
        $this->call(UsersSeeder::class);

        // Seed catalog: Local Types
        $this->call(LocalTypesSeeder::class);
        // Seed catalog: Local Statuses
        $this->call(LocalStatusesSeeder::class);
        // Seed catalog: Trade Categories
        $this->call(TradeCategoriesSeeder::class);
        // Seed catalog: Concessionaire Types
        $this->call(ConcessionaireTypesSeeder::class);
        // Seed catalog: Document Types
        $this->call(DocumentTypesSeeder::class);
        // Seed catalog: Contract Types
        $this->call(ContractTypesSeeder::class);
        // Seed catalog: Contract Statuses
        $this->call(ContractStatusesSeeder::class);
        // Seed catalog: Contract Modalities
        $this->call(ContractModalitiesSeeder::class);
        // Seed catalog: Expense Types
        $this->call(ExpenseTypesSeeder::class);
        // Seed catalog: Payment Statuses
        $this->call(PaymentStatusesSeeder::class);
        // Seed catalog: Charge Statuses (charges lifecycle)
        $this->call(ChargeStatusesSeeder::class);
        // Seed catalog: Banks
        $this->call(BanksSeeder::class);
        // Seed the receiving company bank account (100% Banco)
        $this->call(CompanyBankAccountsSeeder::class);
        // Seed catalog: Phone Area Codes
        $this->call(PhoneAreaCodesSeeder::class);
        // Seed catalog: Payment Types
        $this->call(PaymentTypesSeeder::class);
        // Seed catalog: Markets
        $this->call(MarketsSeeder::class);
        // Seed market tariffs (current EUR/m²)
        $this->call(MarketTariffsSeeder::class);
        // Seed catalog: Local Locations
        $this->call(LocalLocationSeeder::class);

        // Seed locals (units)
        $this->call(LocalsSeeder::class);

        // Seed concessionaires
        $this->call(ConcessionairesSeeder::class);

        // Seed a portal test user linked to a concessionaire
        $this->call(PortalTestUserSeeder::class);

        // Seed contracts (creates/confirm VIG contracts, updates local statuses)
        $this->call(ContractsSeeder::class);

        // Seed condo periods (1 complete period with expenses and exclusions)
        // $this->call(CondoPeriodsSeeder::class);

        // Seed Debt Transfer Reasons (optional minimal)
        $this->call(DebtTransferReasonsSeeder::class);

        // Seed Recovered Contracts (TERMINADOS para trazabilidad de deuda)
        $this->call(RecoveredContractsSeeder::class);

        // Seed Historical Debts
        $this->call(HistoricalDebtsSeeder::class);

        // Seed FxRates (October 2025 snapshot)
        $this->call(FxRatesOctober2025Seeder::class);

        // Reset permission cache to avoid stale state in dev/CI
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
