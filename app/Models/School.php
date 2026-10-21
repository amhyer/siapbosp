<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $guarded = [];

    protected $casts = [
        'jumlah_siswa' => 'integer',
        'satuan_biaya_bosp' => 'integer',
        'akhir_jabatan_bulan' => 'integer',
    ];

    public const JENJANG_LIST = ['PAUD', 'SD', 'SMP', 'SMA', 'SMK', 'SLB', 'PKBM', 'SKB'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function alamatLengkap(): string
    {
        return collect([$this->desa, $this->kecamatan, $this->kab_kota, $this->provinsi])->filter()->implode(', ');
    }

    public function isSwasta(): bool
    {
        return strtolower((string) ($this->status_sekolah ?? 'negeri')) === 'swasta';
    }

    /** Kategori satuan pendidikan menurut Permendikdasmen No. 8 Tahun 2026 (Pasal 38, 42, 48). */
    public function kategoriJuknis(): string
    {
        $j = strtoupper(trim((string) ($this->jenjang ?? 'SD')));
        if (in_array($j, ['PAUD', 'TK', 'KB', 'TPA', 'SPS'], true)) {
            return 'paud';
        }
        if (in_array($j, ['PKBM', 'SKB', 'KESETARAAN'], true)) {
            return 'kesetaraan';
        }

        return 'dikdasmen';
    }

    /** Estimasi pagu tahunan BOSP Reguler dari jumlah siswa × indeks biaya satuan Kemendikdasmen. */
    public function estimasiPaguReguler(): int
    {
        return max(0, (int) ($this->jumlah_siswa ?? 0) * (int) ($this->satuan_biaya_bosp ?? 0));
    }

    /** Sesuai ARKAS 4 §2.5: jika bulan akhir jabatan Kepala Sekolah bukan Desember, muncul Periode 1 & 2. */
    public function isPergantianKepsekMidYear(): bool
    {
        $b = (int) ($this->akhir_jabatan_bulan ?: 12);

        return $b >= 1 && $b < 12;
    }
}
