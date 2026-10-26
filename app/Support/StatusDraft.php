<?php

namespace App\Support;

use App\Models\BkuTutupBulan;
use App\Models\KasTransaction;
use Carbon\Carbon;

/**
 * Status "masih ada bulan yang belum ditutup" untuk satu tahun anggaran.
 *
 * Dipakai watermark DRAFT pada SEMUA cetakan (ARKAS 4 Bab XXI), bukan hanya
 * cetak BKU: dokumen apa pun yang memuat transaksi dari bulan yang BKU-nya
 * belum ditutup belum final dan harus bertanda air DRAFT.
 *
 * Dasarnya hanya bulan yang BENAR-BENAR ada transaksinya, supaya sekolah baru
 * (belum ada transaksi) tidak mencetak semua dokumen dengan tanda DRAFT.
 */
class StatusDraft
{
    /** Daftar bulan (Y-m) yang ada transaksinya tetapi BKU-nya belum ditutup. */
    public static function bulanBelumTutup(?int $tahun = null): array
    {
        $user = auth()->user();
        $sid = $user?->school_id;
        if (! $sid) {
            return [];
        }

        $tahun ??= (int) session('tahun', now()->year);

        // Bulan diturunkan di PHP (bukan substr SQL) agar sama hasilnya di
        // sqlite maupun pgsql.
        $bulanAda = KasTransaction::query()
            ->where('school_id', $sid)
            ->where('tahun', $tahun)
            ->pluck('tanggal')
            ->map(fn ($t) => Carbon::parse($t)->format('Y-m'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($bulanAda === []) {
            return [];
        }

        $terkunci = BkuTutupBulan::bulanTerkunci((int) $sid, $tahun);

        return array_values(array_diff($bulanAda, $terkunci));
    }

    /** Benar bila dokumen belum final sehingga perlu tanda air DRAFT. */
    public static function ada(?int $tahun = null): bool
    {
        return self::bulanBelumTutup($tahun) !== [];
    }

    /** Penjelasan singkat untuk spanduk di atas dokumen. */
    public static function pesan(?int $tahun = null): string
    {
        $bulan = self::bulanBelumTutup($tahun);
        if ($bulan === []) {
            return 'dokumen ini belum difinalkan.';
        }

        $jumlah = count($bulan);

        return $jumlah === 1
            ? 'BKU bulan '.$bulan[0].' belum ditutup. Lakukan Tutup BKU bulan itu agar tanda air DRAFT hilang.'
            : $jumlah.' bulan dengan transaksi belum ditutup (mulai '.$bulan[0].'). Tutup BKU setiap bulan agar tanda air DRAFT hilang.';
    }
}
