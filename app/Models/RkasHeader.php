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
