<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * Sumber dana milik sekolah, diimpor apa adanya dari sheet ARKAS
 * `Ref_Sumber_Dana` (master `ref_sumber_dana` di arkas.db).
 *
 * Baris ber-`is_sumber_dana_tambahan` = sumber dana tambahan yang benar-benar
 * dipakai ARKAS (mis. Banpem hasil unduhan "Unduh dan Perbarui Data") —
 * daftar inilah yang menjadi chip penyaring tab "Sumber Dana Tambahan" di
 * halaman Penganggaran. Lihat docs/UJI-ARKAS-LAPTOP-SUMBER-DANA.md §6.
 */
class SumberDanaSekolah extends Model
{
    use BelongsToSchool;

    /** nama() ≠ pluralisasi otomatis Laravel (sumber_dana_sekolahs). */
    protected $table = 'sumber_dana_sekolah';

    protected $guarded = [];

    protected $casts = ['is_sumber_dana_tambahan' => 'boolean'];
}
