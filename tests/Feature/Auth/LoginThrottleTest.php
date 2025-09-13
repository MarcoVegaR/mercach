<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    public function test_login_is_rate_limited_by_email_and_ip(): void
    {
        $user = User::factory()->create();

        // 5 intentos fallidos
        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => strtoupper($user->email), // normalizado por limitador
                'password' => 'wrong',
            ]);
        }

        // 6.º retorna 429 con Retry-After (solicitud JSON para ver cabeceras)
        $resp = $this->from('/login')->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $resp->assertStatus(429);
        $resp->assertHeader('Retry-After');
    }

    public function test_throttle_window_resets_after_time(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429);

        $this->travel(61)->seconds();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
    }

    public function test_global_ip_limit_is_applied(): void
    {
        // 50/min por IP (ver AppServiceProvider::boot login limiter)
        for ($i = 0; $i < 50; $i++) {
            $this->post('/login', ['email' => "user{$i}@ex.com", 'password' => 'wrong']);
        }

        $this->postJson('/login', ['email' => 'other@ex.com', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }
}
