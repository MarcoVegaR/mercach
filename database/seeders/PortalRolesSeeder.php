<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PortalRolesSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('permissions.guard', 'web');

        // Ensure required permissions exist (should be created by PermissionsSeeder)
        $permNames = [
            'portal.access',
            'settings.profile.view',
            'settings.profile.update',
            'settings.password.update',
            'settings.appearance.view',
            'settings.security.view',
            'settings.security.sessions.manage',
        ];
        $perms = Permission::whereIn('name', $permNames)->where('guard_name', $guard)->get();

        // Create or update the "concesionario" role for Portal users
        $role = Role::firstOrCreate([
            'name' => 'concesionario',
            'guard_name' => $guard,
        ]);

        // Assign the minimal portal permissions to the role
        $role->syncPermissions($perms);
    }
}
