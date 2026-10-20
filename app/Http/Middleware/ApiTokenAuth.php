<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Autentikasi API sederhana berbasis bearer token.
 * Token disimpan TER-HASH (sha256) di database; nilai asli hanya ditampilkan
 * satu kali saat pembuatan. Hanya header Authorization yang diterima —
 * query string ?api_token= sudah tidak didukung agar token tidak bocor
 * ke log server, riwayat browser, atau referer.
 */
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $user = $token ? User::where('api_token', hash('sha256', $token))->first() : null;
        if (! $user) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Token API tidak valid atau tidak diberikan. Gunakan header: Authorization: Bearer <token> (token di query string tidak lagi didukung).',
            ], 401);
        }
        auth()->setUser($user);

        return $next($request);
    }
}
