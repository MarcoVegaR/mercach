<?php

namespace Tests\Feature\Routes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para rate limiting en endpoints caros (export, bulk).
 *
 * Verifica que los limiters 'exports' y 'bulk' funcionen correctamente
 * y retornen 429 cuando se exceden los límites.
 */
class ThrottleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function export_endpoint_is_rate_limited_per_user(): void
    {
        // Simular 10 requests (el límite)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->user)->get('/throttle-test-export-unique');
            $response->assertOk();
        }

        // El request 11 debe fallar con 429
        $response = $this->actingAs($this->user)->get('/throttle-test-export-unique');
        $response->assertStatus(429);
        $response->assertHeader('X-RateLimit-Limit', '10');
        $response->assertHeader('Retry-After');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bulk_endpoint_is_rate_limited_per_user(): void
    {
        // Simular 15 requests (el límite para bulk)
        for ($i = 0; $i < 15; $i++) {
            $response = $this->actingAs($this->user)->post('/throttle-test-bulk-unique', ['action' => 'activate']);
            $response->assertOk();
        }

        // El request 16 debe fallar con 429
        $response = $this->actingAs($this->user)->post('/throttle-test-bulk-unique', ['action' => 'activate']);
        $response->assertStatus(429);
        $response->assertHeader('X-RateLimit-Limit', '15');
        $response->assertHeader('Retry-After');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rate_limiting_is_per_user_not_global(): void
    {
        $user2 = User::factory()->create();

        // User 1 agota su límite
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->user)->get('/throttle-test-export-unique');
        }

        // User 1 debe estar limitado
        $response = $this->actingAs($this->user)->get('/throttle-test-export-unique');
        $response->assertStatus(429);

        // User 2 debe poder hacer requests normalmente
        $response = $this->actingAs($user2)->get('/throttle-test-export-unique');
        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_requests_are_limited_by_ip(): void
    {
        // Simular 10 requests no autenticados desde la misma IP
        for ($i = 0; $i < 10; $i++) {
            $response = $this->get('/throttle-test-export-unique');
            $response->assertOk();
        }

        // El siguiente request debe fallar
        $response = $this->get('/throttle-test-export-unique');
        $response->assertStatus(429);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/throttle-test-export-unique', function () {
            return response()->json(['message' => 'Export completed']);
        })->middleware(['web', 'throttle:exports']);

        $router->post('/throttle-test-bulk-unique', function () {
            return response()->json(['message' => 'Bulk action completed']);
        })->middleware(['web', 'throttle:bulk']);
    }
}
