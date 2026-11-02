<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\KasTransaction;

/**
 * Jejak audit untuk penghapusan transaksi kas.
 *
 * ARKAS 4 tidak mengenal "hapus" langsung: koreksi dilakukan lewat pengajuan
 * ke pusat. SiapBOSP harus bisa dipakai offline, jadi penghapusan tetap
 * tersedia — tetapi setiap penghapusan WAJIB punya alasan dan meninggalkan
 * jejak di `activity_logs` (yang bersifat append-only), agar pemeriksa dapat
 * melihat apa yang dihapus, oleh siapa, kapan, dan mengapa.
 *
 * Sebelum ini, `KasTransaction::delete()` berjalan tanpa catatan apa pun.
 */
class JejakHapus
{
    /** Nilai kolom `activity_logs.aksi` untuk penghapusan transaksi. */
    public const AKSI = 'hapus_transaksi';

    /** Sebutan jenis transaksi dalam bahasa operator. */
    private const JENIS = [
        'pendapatan' => 'Pendapatan',
        'belanja' => 'Belanja',
        'setor_utang_pajak' => 'Setor Pajak',
        'ambil_simpanan' => 'Tarik Tunai',
        'mutasi_tunai_ke_bank' => 'Setor Tunai',
    ];

    /**
     * Catat rencana penghapusan. Panggil SEBELUM baris dihapus, karena setelah
     * `delete()` relasi & nilai aslinya tidak lagi dapat dibaca dengan aman.
     */
    public static function catat(KasTransaction $trx, string $alasan): ActivityLog
    {
        $jenis = self::JENIS[$trx->type] ?? ucfirst((string) $trx->type);
        $alasan = trim($alasan);

        return ActivityLog::create([
            'school_id' => $trx->school_id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name ?? 'Sistem',
            'aksi' => self::AKSI,
            'keterangan' => implode(' · ', array_filter([
                'Hapus '.$jenis,
                $trx->tanggal?->format('d/m/Y'),
                'BKU#'.($trx->no_bku ?? '—'),
                rp((int) $trx->total),
                'id #'.$trx->id,
                'alasan: '.$alasan,
            ])),
        ]);
    }

    /** Aturan validasi alasan—dipakai kedua controller agar seragam. */
    public static function aturan(): array
    {
        return [
            'alasan' => 'required|string|min:5|max:200',
        ];
    }

    /** Pesan galat berbahasa operator (bukan pesan bawaan Laravel). */
    public static function pesan(): array
    {
        return [
            'alasan.required' => 'Alasan penghapusan wajib diisi — setiap hapus transaksi tercatat di Log Sistem.',
            'alasan.min' => 'Alasan terlalu pendek (minimal 5 karakter) agar jejak audit bisa dipahami pemeriksa.',
            'alasan.max' => 'Alasan maksimal 200 karakter.',
        ];
    }
}
