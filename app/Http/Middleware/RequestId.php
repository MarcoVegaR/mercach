<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para generar y compartir request IDs únicos.
 *
 * Genera un UUID único para cada request y lo hace disponible en:
 * - Request attributes (para otros middlewares/controladores)
 * - Laravel Context (para logging automático)
 * - Response headers (para debugging frontend)
 */
class RequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');
        $requestId = $incoming ?: (string) Str::uuid();

        // Share in request attributes for processors
        $request->attributes->set('request_id', $requestId);

        // Add to Laravel Context for automatic logging inclusion
        Context::add([
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
        ]);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
