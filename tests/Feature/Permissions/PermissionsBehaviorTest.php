<?php

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// TestCase & RefreshDatabase are bound globally in tests/Pest.php

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('forbids access to protected settings routes without permission', function (string $method, string $uri) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->json($method, $uri, $method === 'PATCH' ? [
        'name' => 'Any',
        'email' => $user->email,
    ] : ($method === 'PUT' ? [
        'current_password' => 'password',
        'password' => 'New-Passw0rd!',
        'password_confirmation' => 'New-Passw0rd!',
    ] : []));

    $response->assertForbidden();
})->with([
    ['GET', '/settings/profile'],
    ['PATCH', '/settings/profile'],
    ['GET', '/settings/password'],
    ['PUT', '/settings/password'],
    ['GET', '/settings/appearance'],
]);

it('allows admin to access all protected settings routes', function (string $method, string $uri) {
    $user = User::factory()->create();
    $admin = Role::where('name', 'admin')->firstOrFail();
    $user->assignRole($admin);

    $response = $this->actingAs($user)->json($method, $uri, $method === 'PATCH' ? [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ] : ($method === 'PUT' ? [
        'current_password' => 'password',
        'password' => 'New-Passw0rd!',
        'password_confirmation' => 'New-Passw0rd!',
    ] : ($method === 'DELETE' ? [
        'password' => 'password',
    ] : [])));

    // GETs should be 200, mutating forms redirect
    if ($method === 'GET') {
        $response->assertOk();
    } else {
        $response->assertStatus(302);
    }
})->with([
    ['GET', '/settings/profile'],
    ['PATCH', '/settings/profile'],
    ['GET', '/settings/password'],
    ['PUT', '/settings/password'],
    ['GET', '/settings/appearance'],
]);
