<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\BkuTutupBulan;
use App\Models\KasTransaction;
use App\Models\LpjSubmission;
use App\Models\RkasHeader;
use App\Models\TutupBuku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus seluruh DATA transaksional satu sekolah (reset).
 * Yang dihapus: transaksi kas (+rincian/pajak/lampiran berkas), RKAS,
 * tutup buku, dan pengajuan LPJ.
 * Yang DIPERTAHANKAN: jejak audit (append-only — R-12), profil sekolah, akun
 * pengguna, penandatangan, pagu/alokasi, notifikasi. Aksi sendiri dicatat
 * sebagai 1 baris log baru.
 */
class HapusDataService
{
    public static function hapusSekolah(int $schoolId, int $userId, string $namaPelaku): array
    {
        return DB::transaction(function () use ($schoolId, $userId, $namaPelaku) {
            $trxIds = KasTransaction::withoutGlobalScope('school')
                ->where('school_id', $schoolId)->pluck('id');
            $berkas = Attachment::whereIn('kas_transaction_id', $trxIds)->pluck('path')->all();

            $hasil = [
                'transaksi' => KasTransaction::withoutGlobalScope('school')
                    ->where('school_id', $schoolId)->delete(),
                'rkas' => RkasHeader::withoutGlobalScope('school')
                    ->where('school_id', $schoolId)->delete(),
                'tutup_buku' => TutupBuku::withoutGlobalScope('school')
                    ->where('school_id', $schoolId)->delete()
                    + BkuTutupBulan::withoutGlobalScope('school')
                        ->where('school_id', $schoolId)->delete(),
                'lpj' => LpjSubmission::withoutGlobalScope('school')
                    ->where('school_id', $schoolId)->delete(),
                // R-12: log TIDAK dihapus (append-only). Reset data sekolah tidak
                // boleh menghapus bukti aktivitas yang sedang diperiksa.
                'log' => ActivityLog::where('school_id', $schoolId)->count(),
                'lampiran_berkas' => 0,
            ];
            foreach (array_chunk($berkas, 100) as $potong) {
                Storage::delete($potong);
                foreach ($potong as $f) {
                    if (! Storage::exists($f)) {
                        $hasil['lampiran_berkas']++;
                    }
                }
            }

            ActivityLog::create([
                'school_id' => $schoolId, 'user_id' => $userId, 'user_name' => $namaPelaku,
                'aksi' => 'hapus_data',
                'keterangan' => 'Reset data sekolah: '.$hasil['transaksi'].' transaksi, '
                    .$hasil['rkas'].' RKAS, '.$hasil['tutup_buku'].' tutup buku, '
                    .$hasil['lpj'].' LPJ, '.$hasil['lampiran_berkas'].' berkas lampiran.',
            ]);

            return $hasil;
        });
    }
}
