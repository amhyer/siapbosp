<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArkasKode extends Model
{
    protected $guarded = [];

    /** Nama uraian kode kegiatan (cache per permintaan proses). */
    public static function peta(int $schoolId, int $tahun): array
    {
        return static::query()->where('school_id', $schoolId)->where('tahun', $tahun)
            ->orderBy('level')->orderBy('kode')
            ->pluck('uraian', 'kode')->all();
    }
}
