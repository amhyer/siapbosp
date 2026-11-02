<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class PerencanaanPbd extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['skor_capaian' => 'float'];

    public const INDIKATOR_RAPOR = [
        'A.1' => 'A.1 Kemampuan Literasi Peserta Didik',
        'A.2' => 'A.2 Kemampuan Numerasi Peserta Didik',
        'A.3' => 'A.3 Karakter Peserta Didik',
        'C.3' => 'C.3 Pengalaman Pelatihan PTK',
        'D.1' => 'D.1 Kualitas Pembelajaran',
        'D.2' => 'D.2 Refleksi & Perbaikan Pembelajaran oleh Guru',
        'D.3' => 'D.3 Kepemimpinan Instruksional',
        'D.4' => 'D.4 Iklim Keamanan Satuan Pendidikan',
        'D.6' => 'D.6 Iklim Kesetaraan Gender',
        'D.8' => 'D.8 Iklim Kebinekaan',
        'D.10' => 'D.10 Iklim Inklusivitas',
        'E.1' => 'E.1 Partisipasi Warga Satuan Pendidikan',
        'E.2' => 'E.2 Proporsi Pemanfaatan Sumber Daya Sekolah untuk Peningkatan Mutu',
        'E.5' => 'E.5 Program dan Kebijakan Satuan Pendidikan',
    ];

    public function rkms()
    {
        return $this->hasMany(PerencanaanRkm::class, 'pbd_id');
    }
}
