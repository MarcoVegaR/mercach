<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        // Portal users (concesionarios) redirect to /portal
        // Check role instead of permission because admin has all permissions
        if ($user && $user->hasRole('concesionario') && ! $user->hasRole('admin')) {
            $home = '/portal';
        } else {
            $home = config('fortify.home', '/dashboard');
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended($home);
    }
}
