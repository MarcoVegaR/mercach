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

        // Seed the single default admin user
        $this->call(UsersSeeder::class);

        // Seed test roles
        $this->call(RolesTestSeeder::class);

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
        // Seed catalog: Banks
        $this->call(BanksSeeder::class);
        // Seed catalog: Phone Area Codes
        $this->call(PhoneAreaCodesSeeder::class);
        // Seed catalog: Payment Types
        $this->call(PaymentTypesSeeder::class);

        // Reset permission cache to avoid stale state in dev/CI
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
