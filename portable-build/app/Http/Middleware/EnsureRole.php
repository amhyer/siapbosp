<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $u = $request->user();
        if (! $u) {
            return redirect()->route('login');
        }
        // Akun/sekolah yang dinonaktifkan Dinas harus langsung kehilangan akses,
        // bukan hanya diblokir pada saat login (sesi lama tetap hidup).
        // Perbandingan ketat: model yang baru dibuat di memori bisa belum
        // memuat kolom is_active (default DB), jangan sampai dianggap nonaktif.
        if ($u->is_active === false || $u->school?->is_active === false) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'pesan' => 'Akun atau sekolah Anda dinonaktifkan.'], 403);
            }
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun atau sekolah Anda dinonaktifkan. Hubungi Admin Dinas.']);
        }
        if (! in_array($u->role, $roles)) {
            abort(403, 'Anda tidak berhak mengakses halaman ini.');
        }

        return $next($request);
    }
}
