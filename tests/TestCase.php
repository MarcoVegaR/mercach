<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Track which test classes have already registered their routes.
     * Keyed by "<class>|<app-hash>" to handle app refreshes between tests.
     *
     * @var array<string,bool>
     */
    protected static array $routesRegisteredFor = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Configure Inertia testing to use lowercase pages directory
        config()->set('inertia.testing.page_paths', [resource_path('js/pages')]);

        // Allow each test class to define its own routes via defineRoutes($router)
        $class = static::class;
        $appKey = $class.'|'.spl_object_hash(app());
        if (method_exists($this, 'defineRoutes') && ! isset(self::$routesRegisteredFor[$appKey])) {
            $this->defineRoutes(app('router'));
            // Refresh route caches/lookups and URL generator after dynamic route registration
            $routes = app('router')->getRoutes();
            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
            app('url')->setRoutes($routes);
            self::$routesRegisteredFor[$appKey] = true;
        }
    }

    /**
     * Issue a POST request including a valid CSRF token in session and header.
     */
    protected function postWithCsrf(string $uri, array $data = [], array $headers = [])
    {
        $token = bin2hex(random_bytes(32));

        return $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post($uri, array_merge(['_token' => $token], $data), $headers);
    }

    /**
     * Issue a PATCH request including a valid CSRF token in session and header.
     */
    protected function patchWithCsrf(string $uri, array $data = [], array $headers = [])
    {
        $token = bin2hex(random_bytes(32));

        return $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->patch($uri, array_merge(['_token' => $token], $data), $headers);
    }
}
