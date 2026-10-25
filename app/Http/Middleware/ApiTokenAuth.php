<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TokenApi;
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
        // Token yang masih tersimpan tidak boleh menembus penonaktifan akun/sekolah
        // (login web sudah memblokir, API harus setara).
        if ($user->is_active === false || $user->school?->is_active === false) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Akun atau sekolah pemilik token dinonaktifkan. Hubungi Admin Dinas.',
            ], 403);
        }
        // Token berbatas waktu (R-02): perangkat yang hilang tidak bisa
        // memakai token selamanya.
        if (TokenApi::kedaluwarsa($user)) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Token API sudah kedaluwarsa. Buat token baru di menu Profil lalu perbarui konfigurasi agen.',
            ], 401);
        }

        TokenApi::sentuh($user);
        auth()->setUser($user);

        return $next($request);
    }
}
