<?php

namespace App\Services;

use App\Models\ActivityLog;

class Aktivitas
{
    /** Catat satu aksi ke jejak audit. */
    public static function catat(string $aksi, string $keterangan = '', ?int $schoolId = null): void
    {
        $u = auth()->user();
        ActivityLog::create([
            'school_id' => $schoolId ?? $u?->school_id,
            'user_id' => $u?->id,
            'user_name' => $u?->name ?? 'Sistem',
            'aksi' => $aksi,
            'keterangan' => $keterangan,
        ]);
    }
}
