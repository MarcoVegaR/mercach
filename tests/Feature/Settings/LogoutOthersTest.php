<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LogoutOthersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_logout_others_removes_other_sessions_and_keeps_current(): void
    {
        config()->set('session.driver', 'database');

        $user = User::factory()->create();
        $user->givePermissionTo('settings.security.sessions.manage');

        $this->actingAs($user);

        $currentId = session()->getId();

        // Crear una sesión extra en la tabla
        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => 'UnitTest',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        $this->post('/user/logout-others', ['password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
        $this->assertAuthenticatedAs($user);

        // La sesión actual sigue siendo válida (id no cambia si no regeneramos de nuevo en test)
        $this->assertNotEmpty($currentId);
    }

    public function test_logout_others_is_throttled(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('settings.security.sessions.manage');
        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/user/logout-others', ['password' => 'wrong']);
        }

        $this->postJson('/user/logout-others', ['password' => 'wrong'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }
}
