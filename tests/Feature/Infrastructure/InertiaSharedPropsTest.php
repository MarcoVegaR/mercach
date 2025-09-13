<?php

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

it('shares auth.user and can map for all configured permissions', function () {
    // Arrange
    $this->seed(PermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();

    // In our project the Inertia pages live under resources/js/pages (lowercase)
    // Configure the testing view finder accordingly so component existence checks pass
    config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

    // Act
    $response = $this->actingAs($user)->get('/dashboard');

    $configured = (array) config('permissions.permissions', []);

    // Assert
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('auth.user')
        ->has('auth.can')
        ->where('auth.can', function ($can) use ($configured): bool {
            // Normalize to array in case the prop is a Collection
            if ($can instanceof \Illuminate\Support\Collection) {
                $can = $can->toArray();
            } else {
                $can = (array) $can;
            }

            foreach ($configured as $perm) {
                if (! array_key_exists($perm, $can) || ! is_bool($can[$perm])) {

                    return false;
                }
            }

            return true;
        })
    );
});
