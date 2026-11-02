<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Data PTK (Pendidik & Tensional Kependidikan) dari ARKAS `ptk`. */
class Ptk extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tahun' => 'integer',
        'masa_kerja_tahun' => 'integer',
        'masa_kerja_bulan' => 'integer',
        'jenis_ptk' => 'integer',
        'pernah_sertifikasi' => 'boolean',
        'is_cut_off' => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
