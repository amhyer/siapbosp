<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * BKU per bulan ala ARKAS 4: satu baris per bulan (Y-m) yang ditutup.
 * Terpisah dari TutupBuku (kunci per tahap) agar tabel lama dan seluruh
 * logikanya tetap utuh — bulan riwayat tidak lagi bisa digesek.
 */
class BkuTutupBulan extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['tahun' => 'integer'];

    /** Apakah bulan (Y-m) sudah ditutup untuk sekolah ini. */
    public static function terkunciBulan(int $schoolId, string $bulan): bool
    {
        return static::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->where('tahun', (int) substr($bulan, 0, 4))->where('bulan', $bulan)->exists();
    }

    /** Peta bulan (Y-m) yang ditutup pada satu tahun — untuk grid BKU. */
    public static function bulanTerkunci(int $schoolId, int $tahun): array
    {
        return static::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->where('tahun', $tahun)->pluck('bulan')->all();
    }
}
