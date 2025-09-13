<?php

use Database\Seeders\PermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('has all configured permissions created and assigned to admin', function () {
    // Arrange
    $this->seed(PermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $configured = (array) config('permissions.permissions', []);

    // Assert every permission exists and admin has it
    $admin = Role::where('name', 'admin')->firstOrFail();

    foreach ($configured as $perm) {
        $permModel = Permission::where('name', $perm)->first();
        expect($permModel)->not->toBeNull();
        expect($admin->hasPermissionTo($perm))->toBeTrue();
    }
});
