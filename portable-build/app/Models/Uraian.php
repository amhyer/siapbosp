<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Master uraian belanja (bekal uraian dari RKAS impor ARKAS). */
class Uraian extends Model
{
    protected $guarded = [];

    /** Nama tabel tunggal (bukan jamak "uraians"). */
    protected $table = 'uraian';

    public function satuan()
    {
        return $this->belongsTo(Satuan::class);
    }
}
