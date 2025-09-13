<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\MakeCatalog::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            AuthenticateSession::class,
            RequestId::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Register route middleware aliases (Laravel 12 without Http/Kernel)
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Cloudflare Tunnel proxy trust (disabled for local development)
        // If you need to re-enable for a tunnel, uncomment the block below.
        // $middleware->trustProxies(
        //     at: '*',
        //     headers: Request::HEADER_X_FORWARDED_FOR
        //         | Request::HEADER_X_FORWARDED_HOST
        //         | Request::HEADER_X_FORWARDED_PORT
        //         | Request::HEADER_X_FORWARDED_PROTO,
        // );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isInertiaTesting = function (\Illuminate\Http\Request $request): bool {
            // Consider testing flag as Inertia intent when header is removed by middleware
            return $request->hasHeader('X-Inertia') || (bool) $request->attributes->get('_inertia_testing_view_mode');
        };
        $exceptions->renderable(function (\App\Exceptions\DomainActionException $e, \Illuminate\Http\Request $request) {
            // Si es una request de Inertia, redirigir con flash error
            if ($request->hasHeader('X-Inertia') || (bool) $request->attributes->get('_inertia_testing_view_mode')) {
                return back()->with('error', $e->getMessage());
            }

            // Para requests normales, usar el comportamiento por defecto
            return null;
        });

        // Páginas de error Inertia para 403, 404, 500
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->hasHeader('X-Inertia') || (bool) $request->attributes->get('_inertia_testing_view_mode')) {
                if (app()->environment('testing') && $request->isMethod('GET')) {
                    // Ensure Inertia returns a view (with 'page' data) for tests
                    $request->headers->remove('X-Inertia');
                }

                return \Inertia\Inertia::render('errors/403')->toResponse($request)->setStatusCode(403);
            }

            return null;
        });

        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->hasHeader('X-Inertia') || (bool) $request->attributes->get('_inertia_testing_view_mode')) {
                if (app()->environment('testing') && $request->isMethod('GET')) {
                    // Ensure Inertia returns a view (with 'page' data) for tests
                    $request->headers->remove('X-Inertia');
                }

                return \Inertia\Inertia::render('errors/404')->toResponse($request)->setStatusCode(404);
            }

            return null;
        });

        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if (($request->hasHeader('X-Inertia') || (bool) $request->attributes->get('_inertia_testing_view_mode')) && $e->getStatusCode() >= 500) {
                if (app()->environment('testing') && $request->isMethod('GET')) {
                    // Ensure Inertia returns a view (with 'page' data) for tests
                    $request->headers->remove('X-Inertia');
                }

                return \Inertia\Inertia::render('errors/500')->toResponse($request)->setStatusCode(500);
            }

            return null;
        });

        // Para errores 500 genéricos (no HttpException)
        $exceptions->renderable(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (($request->hasHeader('X-Inertia') || (bool) $request->attributes->get('_inertia_testing_view_mode')) && ! $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && app()->environment('production')) {
                return \Inertia\Inertia::render('errors/500')->toResponse($request)->setStatusCode(500);
            }

            return null;
        });
    })->create();
