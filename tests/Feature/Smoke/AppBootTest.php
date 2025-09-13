<?php

declare(strict_types=1);

namespace Tests\Feature\Smoke;

use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class AppBootTest extends TestCase
{
    /**
     * Define test-only routes for this test suite.
     */
    protected function defineRoutes($router): void
    {
        // Route to trigger a 403 for testing the Inertia error page
        $router->get('/denied', function () {
            throw new AccessDeniedHttpException('Denied');
        })->middleware('web')->name('test.denied');
    }

    public function test_home_page_renders_inertia_component(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('welcome', false));
    }

    public function test_health_check_up_returns_200(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_inertia_404_page_renders(): void
    {
        $response = $this->withHeader('X-Inertia', 'true')->get('/nonexistent');
        $response->assertStatus(404);
        $response->assertInertia(fn (Assert $page) => $page->component('errors/404'));
    }

    public function test_inertia_403_page_renders(): void
    {
        $response = $this->withHeader('X-Inertia', 'true')->get('/denied');
        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page->component('errors/403'));
    }
}
