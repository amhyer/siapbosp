<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LpjSubmission extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = [
        'ditinjau_at' => 'datetime',
        'total_pendapatan' => 'integer',
        'total_belanja' => 'integer',
        'sisa_dana' => 'integer',
        'total_transaksi' => 'integer',
    ];

    public const STATUSES = ['dikirim', 'diterima', 'ditolak'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function frozenSnapshots(): HasMany
    {
        return $this->hasMany(SyncSnapshot::class, 'frozen_by_lpj_id');
    }

    /** Pengajuan terakhir sebuah sekolah untuk satu tahap. */
    public static function terakhir(int $schoolId, int $tahun, int $stageId): ?self
    {
        return static::withoutGlobalScope('school')->where('school_id', $schoolId)->where('tahun', $tahun)
            ->where('stage_id', $stageId)->latest('id')->first();
    }
}
