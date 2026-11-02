<?php

namespace App\Services;

use App\Models\KasTransaction;
use App\Models\Stage;
use Illuminate\Support\Facades\DB;

class EarlyWarningService
{
    /**
     * Memeriksa indikator kepatuhan dan potensi masalah sebelum tutup buku / pelaporan.
     *
     * @return array{
     *   total_peringatan: int,
     *   items: array<int, array{
     *     kategori: string,
     *     tipe: 'bahaya'|'peringatan'|'info',
     *     judul: string,
     *     pesan: string,
     *     aksi_label: ?string,
     *     aksi_url: ?string
     *   }>
     * }
     */
    public static function cekKepatuhan(int $schoolId, int $tahun): array
    {
        $warnings = [];

        // 1. Cek belanja > Rp 2.000.000 yang belum dipotong PPh/PPN
        $belanjaBesarTanpaPajak = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('tahun', $tahun)
            ->where('type', 'belanja')
            ->where('total', '>=', 2000000)
            ->whereDoesntHave('taxes', function ($q) {
                $q->where('nilai', '>', 0);
            })
            ->select('id', 'no_bku', 'total', 'keterangan', 'tanggal')
            ->orderByDesc('tanggal')
            ->get();

        if ($belanjaBesarTanpaPajak->isNotEmpty()) {
            $count = $belanjaBesarTanpaPajak->count();
            $contoh = $belanjaBesarTanpaPajak->take(3)->map(fn ($t) => "BKU #{$t->no_bku} (".rp($t->total).')')->implode(', ');
            $warnings[] = [
                'kategori' => 'pajak',
                'tipe' => 'peringatan',
                'judul' => "Terdapat {$count} Transaksi Belanja ≥ Rp 2.000.000 Tanpa Pungutan Pajak",
                'pesan' => "Transaksi belanja di atas Rp 2 juta wajib diverifikasi pungutan PPh Pasal 22 / PPN sesuai ketentuan juknis BOSP. Contoh: {$contoh}.",
                'aksi_label' => 'Periksa Belanja',
                'aksi_url' => route('belanja.index'),
            ];
        }

        // 2. Cek Saldo Kas Tunai Melebihi Batas Regulasi (Maks. Rp 10.000.000)
        $saldo = KasService::saldo($schoolId, $tahun);
        $batasTunai = 10000000; // Rp 10 Juta batas wajar kas tunai sekolah
        if ($saldo['tunai'] > $batasTunai) {
            $warnings[] = [
                'kategori' => 'kas',
                'tipe' => 'peringatan',
                'judul' => 'Saldo Kas Tunai Melebihi Batas Wajar Regulasi',
                'pesan' => 'Saldo kas tunai saat ini sebesar '.rp($saldo['tunai']).' melebihi batas simpanan kas tunai maksimal ('.rp($batasTunai).'). Segera setorkan kembali ke rekening bank atau batasi penarikan tunai.',
                'aksi_label' => 'Buku Kas Umum',
                'aksi_url' => route('penatausahaan.index'),
            ];
        }

        // 3. Cek Kas Minus
        if ($saldo['tunai'] < 0 || $saldo['bank'] < 0) {
            $bagian = [];
            if ($saldo['tunai'] < 0) {
                $bagian[] = 'Kas Tunai ('.rp($saldo['tunai']).')';
            }
            if ($saldo['bank'] < 0) {
                $bagian[] = 'Kas Bank ('.rp($saldo['bank']).')';
            }
            $warnings[] = [
                'kategori' => 'kas',
                'tipe' => 'bahaya',
                'judul' => 'Saldo Kas Terdeteksi Minus!',
                'pesan' => 'Posisi '.implode(' dan ', $bagian).' bernilai minus. Terjadi pengeluaran mendahului penerimaan/mutasi kas. Periksa urutan tanggal transaksi atau input penarikan dana.',
                'aksi_label' => 'Periksa BKU',
                'aksi_url' => route('bku'),
            ];
        }

        // 4. Pajak yang Sudah Dipungut tetapi Belum Disetor (NTPN)
        $pajakBelum = DB::table('kas_taxes as p')
            ->join('kas_transactions as t', 'p.kas_transaction_id', '=', 't.id')
            ->where('t.school_id', $schoolId)
            ->where('t.type', 'belanja')
            ->where('p.status', '!=', 'sudah')
            ->sum('p.nilai');

        if ($pajakBelum > 0) {
            $warnings[] = [
                'kategori' => 'pajak',
                'tipe' => 'peringatan',
                'judul' => 'Pajak Pungut Belum Disetor ke Kas Negara',
                'pesan' => 'Sebesar '.rp($pajakBelum).' pajak belanja telah dipungut namun belum memiliki bukti setor (NTPN). Setor dan rekam NTPN paling lambat tanggal 20 bulan berikutnya.',
                'aksi_label' => 'Setor Pajak',
                'aksi_url' => route('setorpajak.index'),
            ];
        }

        // 5. Cek Kepatuhan Syarat Penyaluran Tahap 2 (Minimal 50% Realisasi Tahap 1)
        $tahap1 = Stage::where('tahun', $tahun)->orderBy('urutan')->first();
        if ($tahap1) {
            $terimaTahap1 = (int) KasTransaction::withoutGlobalScope('school')
                ->where('school_id', $schoolId)->where('tahun', $tahun)
                ->where('type', 'pendapatan')->where('stage_id', $tahap1->id)->sum('total');
            $belanjaTahap1 = (int) KasTransaction::withoutGlobalScope('school')
                ->where('school_id', $schoolId)->where('tahun', $tahun)
                ->where('type', 'belanja')->where('stage_id', $tahap1->id)->sum('total');

            if ($terimaTahap1 > 0) {
                $pct = round($belanjaTahap1 / $terimaTahap1 * 100, 1);
                if ($pct < 50.0 && now()->month >= 6) {
                    $warnings[] = [
                        'kategori' => 'salur',
                        'tipe' => 'info',
                        'judul' => 'Realisasi Tahap 1 Belum Mencapai 50%',
                        'pesan' => "Serapan belanja Tahap 1 baru mencapai {$pct}% (".rp($belanjaTahap1).' dari '.rp($terimaTahap1).'). Sesuai juknis BOSP, penyaluran Tahap 2 mensyaratkan realisasi minimal 50% dari Tahap 1.',
                        'aksi_label' => 'Lihat LPJ Tahap',
                        'aksi_url' => route('laporan.tahap', ['stage' => $tahap1->id]),
                    ];
                }
            }
        }

        return [
            'total_peringatan' => count($warnings),
            'items' => $warnings,
        ];
    }
}
