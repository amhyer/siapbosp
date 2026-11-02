<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class Aktivitas
{
    /**
     * Catat satu aksi ke jejak audit (R-12).
     *
     * Ditulis dua kali: ke tabel `activity_logs` (dibaca aplikasi) dan ke
     * berkas `storage/logs/audit.log` (append-only di luar basis data). Bila
     * basis data direset atau dipulihkan, salinan berkas tetap menjadi bukti.
     */
    public static function catat(string $aksi, string $keterangan = '', ?int $schoolId = null): void
    {
        $u = auth()->user();
        $data = [
            'school_id' => $schoolId ?? $u?->school_id,
            'user_id' => $u?->id,
            'user_name' => $u?->name ?? 'Sistem',
            'aksi' => $aksi,
            'keterangan' => $keterangan,
        ];

        ActivityLog::create($data);

        try {
            Log::channel('audit')->info('audit', $data + ['ip' => request()?->ip()]);
        } catch (\Throwable) {
            // Kegagalan menulis berkas audit tidak boleh menggagalkan aksi
            // pengguna; salinan basis data tetap tercatat.
        }
    }
}
