<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class RkasHeader extends Model
{
    use BelongsToSchool;

    protected $guarded = [];

    protected $casts = ['dikirim_dinas_at' => 'datetime', 'ditinjau_at' => 'datetime'];

    public const LABEL_STATUS = [
        'draft' => 'Draft',
        'diajukan' => 'Diajukan ke Kepsek',
        'disetujui' => 'Disetujui Kepsek',
        'dikirim_dinas' => 'Menunggu Tinjauan Dinas',
        'revisi' => 'Perlu Revisi (Dinas)',
        'disahkan' => 'Disahkan Dinas',
        'dikunci' => 'Dikunci',
    ];

    /**
     * Padanan status internal SiapBOSP dengan kosakata status ARKAS 4 (Dinas).
     *
     * Alur internal tetap dipakai (ajukan ke Kepsek → setujui → kirim Dinas),
     * tetapi operator perlu melihat istilah yang sama dengan yang tertulis di
     * ARKAS 4 saat berkomunikasi dengan Dinas. Lihat
     * docs/MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md (P0-1).
     *
     * @var array<string,array{label:string,nada:string,catatan:string}>
     */
    public const LABEL_STATUS_ARKAS = [
        'draft' => ['label' => 'Draf', 'nada' => 'slate', 'catatan' => 'Masih disusun sekolah'],
        'diajukan' => ['label' => 'Draf', 'nada' => 'slate', 'catatan' => 'Menunggu persetujuan Kepala Sekolah, belum dikirim ke Dinas'],
        'disetujui' => ['label' => 'Siap Diajukan', 'nada' => 'sky', 'catatan' => 'Sudah disetujui Kepala Sekolah, belum dikirim ke Dinas'],
        'dikirim_dinas' => ['label' => 'Menunggu Review Dinas', 'nada' => 'indigo', 'catatan' => 'Sudah dikirim, menunggu tinjauan Dinas'],
        'revisi' => ['label' => 'Perlu Revisi', 'nada' => 'rose', 'catatan' => 'Dikembalikan Dinas untuk diperbaiki'],
        'disahkan' => ['label' => 'Disahkan', 'nada' => 'emerald', 'catatan' => 'Disahkan Dinas, siap lanjut ke BKU'],
        'dikunci' => ['label' => 'Diarsipkan', 'nada' => 'slate', 'catatan' => 'Dokumen final, tidak dapat diubah'],
    ];

    public const LABEL_JENIS = [
        'murni' => 'RKAS Murni',
        'pergeseran' => 'Pergeseran RKAS',
        'perubahan' => 'Perubahan RKAS',
    ];

    /**
     * Tab sumber dana seperti di ARKAS 4 v4.3.0:
     * BOSP Reguler · BOSP Daerah · BOSP Kinerja · SiLPA BOSP Kinerja ·
     * Lainnya · Sumber Dana Tambahan.
     *
     * Nilai `sumber_dana` di database tetap 7 nilai bebas (SUMBER_DANA_ARKAS),
     * lalu dipetakan ke tab di bawah ini agar pengguna ARKAS menemukan letak
     * dokumennya tanpa belajar istilah baru.
     *
     * Dua ejaan diterima untuk sumber dana yang sama: ARKAS Desktop 4.3.1
     * menamai baris `ref_sumber_dana` dengan "BOS …" (mis. `4.3.1.01. BOS
     * Reguler`), sedangkan layar ARKAS dan dokumen sekolah memakai "BOSP …".
     * Alias "BOS Daerah", "SiLPA BOS Kinerja", dan "BOS Afirmasi" diambil
     * langsung dari `ref_sumber_dana` paket ARKAS 4.3.1 (lihat
     * docs/ARKAS-4.3.1-RUJUKAN.md) supaya impor berkas ARKAS tetap terpetakan
     * ke tab yang benar walau nama sumber dananya memakai ejaan "BOS".
     *
     * @var array<string,array{label:string,sumber:array<int,string>}>
     */
    public const TAB_SUMBER_DANA = [
        'reguler' => ['label' => 'BOSP Reguler', 'sumber' => ['BOS Reguler', 'BOSP Reguler']],
        'daerah' => ['label' => 'BOSP Daerah', 'sumber' => ['BOS Daerah', 'BOSP Daerah']],
        'kinerja' => ['label' => 'BOSP Kinerja', 'sumber' => ['BOS Kinerja', 'BOSP Kinerja']],
        'silpa_kinerja' => ['label' => 'SiLPA BOSP Kinerja', 'sumber' => ['SiLPA BOS Kinerja', 'SiLPA BOSP Kinerja', 'SILPA']],
        'lainnya' => ['label' => 'Lainnya', 'sumber' => ['Sumber Dana Lainnya', 'Lainnya']],
        'tambahan' => ['label' => 'Sumber Dana Tambahan', 'sumber' => ['BOS Afirmasi', 'BOSP Afirmasi', 'Afirmasi']],
    ];

    /**
     * Id BosType yang termasuk satu tab sumber dana.
     *
     * Dipakai halaman yang menyaring TRANSAKSI per tab (pendapatan, belanja):
     * tab ARKAS memakai nama sumber dana, sedangkan transaksi menyimpan
     * `bos_type_id`. Pemetaan ini menjaga keduanya tetap sejalan.
     *
     * @return array<int,int>
     */
    public static function bosTypeIds(string $tab): array
    {
        $sumber = self::TAB_SUMBER_DANA[$tab]['sumber'] ?? null;
        if (! $sumber) {
            return [];
        }

        return \App\Models\BosType::whereIn('nama', $sumber)->pluck('id')->all();
    }

    public const SUMBER_DANA_ARKAS = [
        'BOS Reguler',
        'BOS Kinerja',
        'SiLPA BOSP Kinerja',
        'BOSP Afirmasi',
        'BOSP Daerah',
        'SILPA',
        'Sumber Dana Lainnya',
    ];

    public function details()
    {
        return $this->hasMany(RkasDetail::class);
    }

    public function acuan()
    {
        return $this->belongsTo(self::class, 'acuan_header_id');
    }

    public function total(): int
    {
        return (int) $this->details->sum('jumlah');
    }

    public function labelJenis(): string
    {
        return self::LABEL_JENIS[$this->jenis_rkas ?? 'murni'] ?? ucfirst((string) $this->jenis_rkas);
    }

    /**
     * Kunci tab sumber dana untuk sebuah nilai `sumber_dana`.
     * Nilai yang tidak dikenali jatuh ke tab "Lainnya" agar tidak ada dokumen
     * yang "hilang" dari daftar.
     */
    public static function tabDariSumber(?string $sumber): string
    {
        $cari = mb_strtolower(trim((string) $sumber));
        if ($cari === '') {
            return 'reguler';
        }

        foreach (self::TAB_SUMBER_DANA as $kunci => $tab) {
            foreach ($tab['sumber'] as $nilai) {
                if (mb_strtolower($nilai) === $cari) {
                    return $kunci;
                }
            }
        }

        // Cocokkan longgar (mis. "BOS Reguler " atau "BOSP Reguler 2026").
        // Alias TERPANJANG dicoba lebih dulu supaya nama spesifik menang atas
        // alias umum: "SiLPA BOS Afirmasi" harus jatuh ke Sumber Dana Tambahan
        // (alias "BOS Afirmasi"), bukan ke SiLPA Kinerja hanya karena memuat
        // kata "SiLPA". Urutan pita tab tetap mengikuti TAB_SUMBER_DANA.
        $alias = [];
        foreach (self::TAB_SUMBER_DANA as $kunci => $tab) {
            foreach ($tab['sumber'] as $nilai) {
                $alias[] = [mb_strtolower($nilai), $kunci];
            }
        }
        usort($alias, fn ($a, $b) => mb_strlen($b[0]) <=> mb_strlen($a[0]));
        foreach ($alias as [$nilai, $kunci]) {
            if (str_contains($cari, $nilai)) {
                return $kunci;
            }
        }

        return 'lainnya';
    }

    /** Label status menurut kosakata ARKAS 4 (dipakai di chip kedua). */
    public function labelStatusArkas(): string
    {
        return self::LABEL_STATUS_ARKAS[$this->status]['label']
            ?? self::LABEL_STATUS[$this->status]
            ?? (string) $this->status;
    }

    /** Nada warna chip ARKAS 4: slate|sky|indigo|rose|emerald. */
    public function nadaStatusArkas(): string
    {
        return self::LABEL_STATUS_ARKAS[$this->status]['nada'] ?? 'slate';
    }

    /** Penjelasan singkat status ARKAS 4 (untuk atribut title pada chip). */
    public function catatanStatusArkas(): string
    {
        return self::LABEL_STATUS_ARKAS[$this->status]['catatan'] ?? '';
    }

    /**
     * Apakah status ARKAS 4 berbeda dari label internal?
     * Dipakai agar chip kedua hanya muncul saat istilahnya memang beda
     * (mis. "Disetujui Kepsek" vs "Siap Diajukan").
     */
    public function statusArkasBerbeda(): bool
    {
        $internal = self::LABEL_STATUS[$this->status] ?? (string) $this->status;

        return $internal !== $this->labelStatusArkas();
    }
}
