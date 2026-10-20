<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required']);

        // ---- Throttle: maks. 5 percobaan per 5 menit per email+IP ----
        $kunci = strtolower($cred['email']).'|'.$r->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $detik = RateLimiter::availableIn($kunci);
            $this->logAuth('auth-block', $cred['email'], "Terblokir sementara {$detik} detik karena gagal berulang. IP {$r->ip()}");

            return back()->withErrors(['email' => "Terlalu banyak percobaan gagal. Coba lagi dalam {$detik} detik."])->onlyInput('email');
        }

        if (! Auth::attempt($cred, $r->boolean('remember'))) {
            RateLimiter::hit($kunci, 300);
            $this->logAuth('auth-gagal', $cred['email'], "Kata sandi salah. IP {$r->ip()}");

            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }

        $r->session()->regenerate();
        $u = Auth::user();
        if (! $u->is_active) {
            Auth::logout();
            $this->logAuth('auth-gagal', $cred['email'], "Akun nonaktif mencoba masuk. IP {$r->ip()}");

            return back()->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi Admin Dinas.']);
        }
        if ($u->school_id && ! $u->school?->is_active) {
            Auth::logout();
            $this->logAuth('auth-gagal', $cred['email'], "Sekolah nonaktif, akun mencoba masuk. IP {$r->ip()}");

            return back()->withErrors(['email' => 'Akun sekolah Anda nonaktif. Hubungi Admin Dinas.']);
        }
        RateLimiter::clear($kunci);
        $this->logAuth('auth-ok', $u->email, "Masuk sebagai {$u->role}. IP {$r->ip()}", $u->id, $u->school_id, $u->name);

        return redirect()->route($u->isDinas() ? 'dinas.dashboard' : 'dashboard');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Jejak audit khusus autentikasi (bisa ditulis tanpa login). */
    private function logAuth(string $aksi, string $email, string $ket, ?int $uid = null, ?int $sid = null, ?string $nama = null): void
    {
        ActivityLog::create([
            'school_id' => $sid,
            'user_id' => $uid,
            'user_name' => $nama ?? $email,
            'aksi' => $aksi,
            'keterangan' => $ket,
        ]);
    }
}
