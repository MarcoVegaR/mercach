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

        // Reset permission cache to avoid stale state in dev/CI
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
