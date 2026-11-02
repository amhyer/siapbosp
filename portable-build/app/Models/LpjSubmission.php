<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LpjSubmission extends Model
{
    protected $guarded = [];

    protected $casts = ['ditinjau_at' => 'datetime'];

    public const STATUSES = ['dikirim', 'diterima', 'ditolak'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    /** Pengajuan terakhir sebuah sekolah untuk satu tahap. */
    public static function terakhir(int $schoolId, int $tahun, int $stageId): ?self
    {
        return static::where('school_id', $schoolId)->where('tahun', $tahun)
            ->where('stage_id', $stageId)->latest('id')->first();
    }
}
