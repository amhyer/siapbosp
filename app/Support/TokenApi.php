<?php

namespace App\Support;

use App\Models\User;
use App\Services\Aktivitas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Siklus hidup token API (R-02).
 *
 * Token disimpan ter-hash SHA-256, hanya ditampilkan sekali saat diterbitkan,
 * punya masa berlaku, dan dicatat pemakaian terakhirnya untuk audit. Token
 * tetap dicabut otomatis saat akun dinonaktifkan atau sandi berubah
 * (lihat `DinasController` & `ProfilController`).
 */
class TokenApi
{
    /** Umur token (hari). Bisa diubah lewat `API_TOKEN_UMUR_HARI` di .env. */
    public static function umurHari(): int
    {
        $n = (int) env('API_TOKEN_UMUR_HARI', 180);

        return $n >= 1 && $n <= 3650 ? $n : 180;
    }

    /**
     * Terbitkan token baru (menggantikan yang lama).
     *
     * @return array{token: string, kedaluwarsa: Carbon}
     */
    public static function terbitkan(User $user): array
    {
        $token = Str::random(64);
        $kedaluwarsa = now()->addDays(self::umurHari());

        $user->forceFill([
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => $kedaluwarsa,
            'api_token_used_at' => null,
        ])->save();

        Aktivitas::catat('token', 'Token API diterbitkan, berlaku s.d. '.$kedaluwarsa->format('d/m/Y'),
            $user->school_id);

        return ['token' => $token, 'kedaluwarsa' => $kedaluwarsa];
    }

    /** Cabut token (tanpa mengubah sandi). */
    public static function cabut(User $user, string $alasan = 'dicabut pengguna'): void
    {
        if (! $user->api_token) {
            return;
        }

        $user->forceFill(['api_token' => null, 'api_token_expires_at' => null, 'api_token_used_at' => null])->save();
        Aktivitas::catat('token', 'Token API dicabut: '.$alasan, $user->school_id);
    }

    /** Apakah token milik pengguna sudah kedaluwarsa. */
    public static function kedaluwarsa(User $user): bool
    {
        return $user->api_token !== null
            && $user->api_token_expires_at !== null
            && $user->api_token_expires_at->isPast();
    }

    /**
     * Catat pemakaian token — paling sering sekali per 10 menit agar tidak
     * menambah satu tulisan basis data pada setiap permintaan API.
     */
    public static function sentuh(User $user): void
    {
        $terakhir = $user->api_token_used_at;

        if ($terakhir !== null && $terakhir->gt(now()->subMinutes(10))) {
            return;
        }

        $user->forceFill(['api_token_used_at' => now()])->saveQuietly();
    }
}
