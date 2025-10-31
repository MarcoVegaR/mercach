<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasLinkedConcessionaire
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        // Must have portal.access and at least one linked concessionaire
        try {
            $hasPerm = $user->can('portal.access');
        } catch (\Throwable $e) {
            $hasPerm = false;
        }
        $hasLink = false;
        try {
            $hasLink = $user->concessionaires()->exists();
        } catch (\Throwable $e) {
            $hasLink = false;
        }

        if (! $hasPerm || ! $hasLink) {
            return redirect()->route('portal.index')->with('error', 'No tienes autorización para acceder al Portal.');
        }

        return $next($request);
    }
}
