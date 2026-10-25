<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang status akun untuk SELURUH rute bersesi (bukan hanya grup `role:*`).
 *
 * Sebelumnya pemeriksaan akun/sekolah nonaktif hanya ada di `EnsureRole`
 * sehingga rute yang hanya memakai middleware `auth` — mis. `POST /api-token`,
 * `/referensi`, `/ganti-password`, `/sesi/tahun`, `/notifikasi/*` — tetap
 * dapat dipakai akun yang sudah dinonaktifkan Dinas sampai sesinya habis.
 *
 * Dipasang di grup `web`, jadi berjalan sebelum middleware rute.
 */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        if (! $u || ! $request->hasSession()) {
            return $next($request);
        }

        // Perbandingan ketat (`=== false`) agar model yang baru dibuat di memori
        // (kolom belum dimuat, default ada di DB) tidak dianggap nonaktif.
        if ($u->is_active === false || $u->school?->is_active === false) {
            return $this->putuskan($request, 'Akun atau sekolah Anda dinonaktifkan. Hubungi Admin Dinas.');
        }

        // ---- Pencabutan sesi saat kredensial diubah -----------------------
        // Hash sandi disimpan di sesi saat login. Bila Dinas mereset sandi
        // (atau sandi diganti di perangkat lain), hash sesi tidak lagi sama
        // dengan hash di basis data -> semua sesi lama langsung mati.
        // Tanpa ini, sesi penyerang tetap hidup walau sandi korban direset.
        $kunci = 'password_hash_web';
        $hashDb = (string) $u->getAuthPassword();
        $hashSesi = $request->session()->get($kunci);

        if ($hashSesi === null) {
            // Sesi lama (dibuat sebelum fitur ini dipasang) diikat sekarang
            // agar reset sandi berikutnya ikut mencabutnya. Jangan berhenti di
            // sini: pemeriksaan wajib-ganti-sandi di bawah tetap harus jalan.
            $request->session()->put($kunci, $hashDb);
        } elseif (! hash_equals((string) $hashSesi, $hashDb)) {
            return $this->putuskan($request, 'Sesi Anda berakhir karena kata sandi akun diubah. Silakan masuk kembali.');
        }

        // Akun hasil impor / reset Dinas wajib mengganti sandi dulu (R-01),
        // supaya sandi yang pernah tertulis di layar/email tidak berlaku lama.
        if ($u->must_change_password === true
            && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'pesan' => 'Anda wajib mengganti kata sandi terlebih dahulu.',
                    'ubah_sandi' => route('password.edit'),
                ], 409);
            }

            return redirect()->route('password.edit')
                ->with('warning', 'Demi keamanan, ganti kata sandi Anda dulu sebelum memakai aplikasi.');
        }

        return $next($request);
    }

    /** Akhiri sesi (HTML -> halaman login, JSON -> 401). */
    private function putuskan(Request $request, string $pesan): Response
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'pesan' => $pesan], 401);
        }

        return redirect()->route('login')->withErrors(['email' => $pesan]);
    }
}
