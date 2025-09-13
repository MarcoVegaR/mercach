<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionsBrowserController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $table = config('session.table', 'sessions');

        $rows = DB::table($table)
            ->select(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get();

        $currentId = $request->session()->getId();

        // Map and deduplicate by ip + normalized user agent (device/browser), keep latest activity
        $normalized = $rows->map(function ($row) use ($currentId) {
            $ua = (string) ($row->user_agent ?? '');
            $norm = str_contains($ua, ')') ? substr($ua, 0, strpos($ua, ')') + 1) : $ua;

            return [
                'id' => (string) $row->id,
                'ip' => $row->ip_address,
                'agent' => $row->user_agent,
                'agent_norm' => $norm,
                'last_activity' => (int) $row->last_activity,
                'current' => $row->id === $currentId,
            ];
        });

        $map = [];
        foreach ($normalized as $s) {
            $key = ($s['ip'] ?? '').'|'.$s['agent_norm'];
            if (! isset($map[$key]) || $s['last_activity'] > $map[$key]['last_activity']) {
                $map[$key] = $s;
            } elseif ($s['current']) {
                $map[$key]['current'] = true;
            }
        }

        $sessions = array_values(array_map(function ($s) {
            unset($s['agent_norm']);

            return $s;
        }, $map));

        return response()->json(['sessions' => $sessions]);
    }

    public function destroy(Request $request, string $id): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $currentId = $request->session()->getId();
        if ($id === $currentId) {
            return response()->json(['message' => 'No puedes cerrar la sesión actual.'], 422);
        }

        $table = config('session.table', 'sessions');
        $deleted = DB::table($table)
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Sesión no encontrada.'], 404);
        }

        // For SPA/AJAX (Inertia/XHR), return 204 JSON so the client can refresh the list without navigating
        if ($request->expectsJson() || $request->ajax() || $request->hasHeader('X-Inertia')) {
            return response()->json([], 204);
        }

        // Fallback to redirect back for non-AJAX requests
        return back()->with('status', __('Sesión cerrada.'));
    }
}
