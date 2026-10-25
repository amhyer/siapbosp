<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak audit. Bersifat APPEND-ONLY (R-12): baris tidak boleh diubah atau
 * dihapus lewat Eloquent, agar bukti pemeriksaan tidak bisa "dirapikan"
 * oleh akun sekolah yang datanya sedang diperiksa.
 *
 * Catatan: `DB::table('activity_logs')->delete()` tetap bisa menembus ini
 * (query builder tidak memicu event model). Karena itu `HapusDataService`
 * juga tidak lagi menghapus log.
 */
class ActivityLog extends Model
{
    protected $guarded = [];

    public const UPDATED_AT = null;

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Jejak audit bersifat append-only dan tidak boleh diubah.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Jejak audit tidak boleh dihapus.');
        });
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
