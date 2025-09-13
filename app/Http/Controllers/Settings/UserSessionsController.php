<?php

namespace App\Http\Controllers\Settings;

use App\Http\Requests\Settings\LogoutOthersRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserSessionsController
{
    public function __invoke(LogoutOthersRequest $request): RedirectResponse
    {
        // Invalida sesiones en otros dispositivos (requiere password válida)
        Auth::logoutOtherDevices($request->string('password'));

        // Si usas sesiones en BD, elimina las demás manteniendo la actual
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        // Endurece la sesión actual
        $request->session()->regenerate();

        return back()->with('status', __('Sesiones cerradas en otros dispositivos.'));
    }
}
