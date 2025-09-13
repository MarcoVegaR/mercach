<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        $router->get('/remember-check', function () {
            return response()->json([
                'auth' => auth()->check(),
                'remember' => auth()->viaRemember(),
            ]);
        })->middleware('web');
    }

    public function test_remember_me_cookie_allows_login_without_session(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertRedirect('/dashboard');

        // Extraer cookie remember_web_*
        $cookies = $response->headers->getCookies();
        $recaller = null;
        foreach ($cookies as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web_')) {
                $recaller = $cookie;
                break;
            }
        }
        $this->assertNotNull($recaller, 'Missing remember_web_* cookie');

        // Nueva request sin sesión pero con cookie recaller
        $this->flushSession();
        $this->withCookie($recaller->getName(), $recaller->getValue())
            ->get('/remember-check')
            ->assertOk()
            ->assertJson([
                'auth' => true,
            ]);
    }

    public function test_logout_revokes_remember_authentication(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ])->assertRedirect('/dashboard');

        $this->post('/logout')->assertRedirect('/');

        $this->get('/remember-check')
            ->assertOk()
            ->assertJson([
                'auth' => false,
            ]);
    }
}
