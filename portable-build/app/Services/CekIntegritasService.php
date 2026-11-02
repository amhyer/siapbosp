<?php

namespace App\Services;

use App\Models\KasTransaction;
use App\Models\School;

/**
 * "Cek Ulang Integritas Impor" — membandingkan data ARKAS (hasil ekspor
 * terbaru dari laptop) dengan pencatatan di SiapBOSP per bulan.
 *
 * Latar belakang: artikel resmi "Penanganan Kendala BOSP Reguler (TA
 * 2025&2026) Terkait Ketidaksesuaian Antara Data Anggaran dan Realisasi
 * Pasca Pergeseran / Perubahan" — setelah ARKAS digalakkan ulang, data
 * yang sudah tercatat di aplikasi bisa tak sinkron dengan ARKAS.
 *
 * Metode: baris yang sama seperti importer (ArkasImportService::ekstrakKas)
 * dibandingkan dengan transaksi SiapBOSP per bulan — jumlah baris & nilai.
 * Catatan lokal (diinput manual, bukan dari ARKAS) ditampilkan terpisah
 * supaya tak dianggap selisih.
 */
class CekIntegritasService
{
    /** Nama bulan Indonesia. */
    public const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Bandingkan file ekspor ARKAS dengan pencatatan SiapBOSP.
     *
     * @return array{tahun:int,bulan:array,total:array,catatan:array,contoh:array}
     */
    public static function bandingkan(string $path, School $sekolah, int $tahun): array
    {
        $rows = ArkasImportService::ekstrakKas($path, $tahun);

        $arkas = array_fill(1, 12, ['jumlah' => 0, 'nilai' => 0]);
        $contoh = [];
        foreach ($rows as $r) {
            $bulan = (int) substr((string) $r['tgl'], 5, 2);
            if ($bulan < 1 || $bulan > 12) {
                continue;
            }
            $arkas[$bulan]['jumlah']++;
            $arkas[$bulan]['nilai'] += (int) $r['nominal'];
            $contoh[$bulan][] = $r;
        }

        $lokal = array_fill(1, 12, ['jumlah' => 0, 'nilai' => 0]);
        $manual = array_fill(1, 12, ['jumlah' => 0, 'nilai' => 0]);
        $trx = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $sekolah->id)->where('tahun', $tahun)
            ->get(['tanggal', 'total', 'keterangan', 'no_bku', 'arkas_id', 'type']);
        foreach ($trx as $t) {
            $bulan = (int) $t->tanggal->format('n');
            if ($bulan < 1 || $bulan > 12) {
                continue;
            }
            if ($t->arkas_id) {
                $lokal[$bulan]['jumlah']++;
                $lokal[$bulan]['nilai'] += (int) $t->total;
            } else {
                $manual[$bulan]['jumlah']++;
                $manual[$bulan]['nilai'] += (int) $t->total;
            }
        }

        $hasilBulan = [];
        $adaSelisih = 0;
        for ($b = 1; $b <= 12; $b++) {
            $selisihJumlah = $arkas[$b]['jumlah'] - $lokal[$b]['jumlah'];
            $selisihNilai = $arkas[$b]['nilai'] - $lokal[$b]['nilai'];
            $status = 'cocok';
            if ($selisihJumlah !== 0 || $selisihNilai !== 0) {
                $status = 'beda';
                $adaSelisih++;
            }
            $hasilBulan[$b] = [
                'bulan' => $b,
                'nama' => self::BULAN[$b],
                'arkas' => $arkas[$b],
                'lokal' => $lokal[$b],
                'manual' => $manual[$b],
                'selisih_jumlah' => $selisihJumlah,
                'selisih_nilai' => $selisihNilai,
                'status' => $status,
            ];
        }

        $catatan = [];
        if ($adaSelisih === 0) {
            $catatan[] = 'Semua bulan cocok: pencatatan SiapBOSP sama dengan data ARKAS. Tidak perlu impor ulang.';
        } else {
            $catatan[] = $adaSelisih.' bulan berbeda. Jalankan **Impor ARKAS** sekali lagi (aman, idempoten) untuk menyelaraskan.';
            $catatan[] = 'Baris di bulan yang BKU-nya sudah ditutup tidak tersimpan — buka kembali periodenya bila ingin diselaraskan.';
            $catatan[] = 'Transaksi yang diinput manual di SiapBOSP (tanpa arkas_id) tidak dihitung sebagai selisih.';
        }

        return [
            'tahun' => $tahun,
            'bulan' => $hasilBulan,
            'total' => [
                'arkas_jumlah' => array_sum(array_column($arkas, 'jumlah')),
                'arkas_nilai' => array_sum(array_column($arkas, 'nilai')),
                'lokal_jumlah' => array_sum(array_column($lokal, 'jumlah')),
                'lokal_nilai' => array_sum(array_column($lokal, 'nilai')),
                'manual_jumlah' => array_sum(array_column($manual, 'jumlah')),
                'manual_nilai' => array_sum(array_column($manual, 'nilai')),
                'bulan_berbeda' => $adaSelisih,
            ],
            'catatan' => $catatan,
            'contoh' => $contoh,
        ];
    }
}
