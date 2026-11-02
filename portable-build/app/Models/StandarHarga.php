<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StandarHarga extends Model
{
    protected $guarded = [];

    /** Ambil standar harga milik Dinas (global) + milik sekolah ini. */
    public static function untukSekolah(?int $schoolId): Builder
    {
        return static::query()->where(function (Builder $q) use ($schoolId) {
            $q->whereNull('school_id');
            if ($schoolId) {
                $q->orWhere('school_id', $schoolId);
            }
        });
    }
}
