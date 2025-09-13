<?php

declare(strict_types=1);

use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seeder = new PermissionsSeeder;
});

it('creates auditoria permissions when seeder runs', function () {
    // Ensure no permissions exist initially
    expect(Permission::count())->toBe(0);

    // Run the seeder
    $this->seeder->run();

    // Check that auditoria permissions were created
    expect(Permission::where('name', 'auditoria.view')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'auditoria.export')->where('guard_name', 'web')->exists())->toBeTrue();
});

it('seeds auditoria permissions with correct guard', function () {
    $this->seeder->run();

    $viewPermission = Permission::where('name', 'auditoria.view')->first();
    $exportPermission = Permission::where('name', 'auditoria.export')->first();

    expect($viewPermission->guard_name)->toBe('web');
    expect($exportPermission->guard_name)->toBe('web');
});

it('does not create duplicate permissions on multiple runs', function () {
    // Run seeder twice
    $this->seeder->run();
    $this->seeder->run();

    // Should only have one of each permission
    expect(Permission::where('name', 'auditoria.view')->count())->toBe(1);
    expect(Permission::where('name', 'auditoria.export')->count())->toBe(1);
});

it('includes auditoria permissions in total permissions count', function () {
    $this->seeder->run();

    // Count all permissions - should include our 2 auditoria permissions plus others
    $totalPermissions = Permission::count();
    $auditoriaPermissions = Permission::where('name', 'LIKE', 'auditoria.%')->count();

    expect($auditoriaPermissions)->toBe(2);
    expect($totalPermissions)->toBeGreaterThanOrEqual(2);
});
