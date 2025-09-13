<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Seed the application's permissions and admin role.
     */
    public function run(): void
    {
        $guard = config('permissions.guard', 'web');
        $perms = (array) config('permissions.permissions', []);
        $descriptions = (array) config('permissions.descriptions', []);

        // Create permissions
        $permissionModels = [];
        foreach ($perms as $name) {
            $permission = Permission::updateOrCreate(
                [
                    'name' => $name,
                    'guard_name' => $guard,
                ],
                [
                    'description' => $descriptions[$name] ?? null,
                ]
            );

            $permissionModels[] = $permission;
        }

        // Create admin role and assign all permissions
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $admin->syncPermissions($permissionModels);

        // Reset cache to avoid stale permissions in dev/CI
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
