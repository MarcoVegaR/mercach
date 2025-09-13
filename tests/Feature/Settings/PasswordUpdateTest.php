<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated()
    {
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create();
        $user->givePermissionTo('settings.password.update');

        $response = $this
            ->actingAs($user)
            ->from('/settings/password')
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'New-Passw0rd!',
                'password_confirmation' => 'New-Passw0rd!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/password');

        $this->assertTrue(Hash::check('New-Passw0rd!', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create();
        $user->givePermissionTo('settings.password.update');

        $response = $this
            ->actingAs($user)
            ->from('/settings/password')
            ->put('/settings/password', [
                'current_password' => 'wrong-password',
                'password' => 'New-Passw0rd!',
                'password_confirmation' => 'New-Passw0rd!',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/settings/password');
    }
}
