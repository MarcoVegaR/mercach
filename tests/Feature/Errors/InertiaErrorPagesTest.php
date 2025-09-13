<?php

namespace Tests\Feature\Errors;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

/**
 * Tests para páginas de error Inertia.
 *
 * Verifica que las páginas de error se renderizan correctamente
 * cuando se accede con requests de Inertia vs requests normales.
 */
#[\PHPUnit\Framework\Attributes\Group('errors')]
class InertiaErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_denied_exception_renders_inertia_403_page(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/error-test-403-unique');

        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('errors/403')
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function not_found_exception_renders_inertia_404_page(): void
    {
        // Test directo de una ruta que no existe con header Inertia
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/error-test-nonexistent-route-404-unique-'.time());

        $response->assertStatus(404);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('errors/404')
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_inertia_requests_do_not_use_inertia_error_pages(): void
    {
        // Sin header X-Inertia, debe usar páginas de error normales de Laravel
        $response = $this->actingAs($this->user)
            ->get('/error-test-nonexistent-normal-'.time());

        $response->assertStatus(404);
        // No debe ser una respuesta Inertia
        $this->assertStringNotContainsString('inertia', $response->headers->get('content-type', ''));
    }

    protected function defineRoutes($router): void
    {
        $router->get('/error-test-403-unique', function () {
            throw new AccessDeniedHttpException('Test access denied');
        })->middleware('web');
    }
}
