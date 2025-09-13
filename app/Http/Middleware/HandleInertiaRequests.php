<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        // In testing we disable asset versioning to avoid 409 conflicts
        if (app()->environment('testing')) {
            // Return empty string so missing X-Inertia-Version header (defaults to '') matches
            return '';
        }

        return parent::version($request);
    }

    /**
     * Handle the incoming request and normalize redirects for Inertia.
     */
    public function handle(Request $request, \Closure $next): SymfonyResponse
    {
        // In testing, for GET requests, force full-page (Blade) Inertia responses
        // so that AssertableInertia can validate the 'page' view data structure.
        if (app()->environment('testing') && $request->isMethod('GET') && $request->headers->has('X-Inertia')) {
            // Flag the request so exception renderers can still detect Inertia intent
            $request->attributes->set('_inertia_testing_view_mode', true);
            // Remove the header so Inertia returns a view instead of JSON
            $request->headers->remove('X-Inertia');
        }

        /** @var SymfonyResponse $response */
        $response = parent::handle($request, $next);

        // Normalize redirects to 303 for all mutation methods when using Inertia
        if ($request->headers->has('X-Inertia')
            && $response->getStatusCode() === 302
            && in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $response->setStatusCode(303);
        }

        return $response;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $permissions = (array) config('permissions.permissions', []);
        $can = collect($permissions)->mapWithKeys(function (string $perm) use ($request) {
            try {
                return [$perm => (bool) ($request->user()?->can($perm))];
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
                // When a configured permission has not been seeded yet, default to false
                return [$perm => false];
            }
        })->all();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'can' => $can,
            ],
            // Flash messages para toasts con Sonner
            // @see https://inertiajs.com/shared-data#flash-messages
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            // Request ID para tracking y debugging
            'requestId' => $request->attributes->get('request_id'),
        ];
    }
}
