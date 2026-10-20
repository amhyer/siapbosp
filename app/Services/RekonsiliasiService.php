<?php

namespace App\Services;

use App\Models\KasTransaction;

/**
 * Rekonsiliasi ARKAS ↔ SiapBOS (baca-saja, tanpa menulis DB).
 * Membandingkan tiap baris transaksional file dengan transaksi SiapBOS
 * via kunci arkas_id (baru + legacy), plus saldo berjalan per bulan
 * dan peta nomor BKU SiapBOS ↔ BKU ARKAS.
 */
class RekonsiliasiService
{
    public static function bandingkan(int $schoolId, string $path, int $tahun): array
    {
        $baris = ArkasImportService::ekstrakKas($path, $tahun);
        $sipbos = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $schoolId)->where('tahun', $tahun)
            ->whereNotNull('arkas_id')->get()->keyBy('arkas_id');

        $hasil = [
            'tahun' => $tahun, 'total_arkas' => count($baris),
            'cocok' => 0, 'selisih_nominal' => [], 'hilang_di_sipbos' => [],
            'lebih_di_sipbos' => [], 'peta_bku' => [], 'saldo_bulanan' => [],
        ];
        $terlihat = [];
        foreach ($baris as $b) {
            $t = ($b['key_baru'] && isset($sipbos[$b['key_baru']]))
                ? $sipbos[$b['key_baru']]
                : ($sipbos[$b['key_legacy']] ?? null);
            if (! $t) {
                $hasil['hilang_di_sipbos'][] = $b;

                continue;
            }
            $terlihat[] = $t->arkas_id;
            if ((int) $t->total !== (int) $b['nominal']) {
                $hasil['selisih_nominal'][] = ['arkas' => $b, 'sipbos' => [
                    'no_bku' => $t->no_bku, 'total' => (int) $t->total,
                    'keterangan' => $t->keterangan,
                ]];

                continue;
            }
            $hasil['cocok']++;
            $hasil['peta_bku'][] = [
                'no_bku' => $t->no_bku, 'tanggal' => $t->tanggal->format('Y-m-d'),
                'bku_arkas' => $t->arkas_bku, 'jenis_arkas' => $t->arkas_jenis_bku,
                'uraian' => $t->keterangan, 'total' => (int) $t->total,
            ];
        }
        foreach ($sipbos as $key => $t) {
            if (! in_array($key, $terlihat, true)) {
                $hasil['lebih_di_sipbos'][] = [
                    'no_bku' => $t->no_bku, 'tanggal' => $t->tanggal->format('Y-m-d'),
                    'bku_arkas' => $t->arkas_bku, 'keterangan' => $t->keterangan,
                    'total' => (int) $t->total,
                ];
            }
        }

        // Saldo berjalan per bulan: ARKAS (dari nominal baris) vs SiapBOS (ledger).
        $perBulanArkas = array_fill(1, 12, 0);
        foreach ($baris as $b) {
            $perBulanArkas[(int) substr($b['tgl'], 5, 2)] += self::tanda($b['jenis']) * $b['nominal'];
        }
        $ledger = KasService::ledger($schoolId, $tahun);
        $jalan = $ledger['saldoAwalTahun'];
        $perBulanSipbos = [];
        $items = $ledger['items'];
        for ($m = 1; $m <= 12; $m++) {
            foreach ($items as $it) {
                if ((int) $it['trx']->tanggal->format('n') === $m) {
                    [$dt, $db] = KasService::efek($it['trx']->type, $it['trx']->metode_bayar, (int) $it['trx']->total);
                    $jalan += $dt + $db;
                }
            }
            $perBulanSipbos[$m] = $jalan;
        }
        $akum = 0;
        for ($m = 1; $m <= 12; $m++) {
            $akum += $perBulanArkas[$m];
            $hasil['saldo_bulanan'][$m] = ['arkas' => $akum, 'sipbos' => $perBulanSipbos[$m]];
        }

        return $hasil;
    }

    /** Tanda arus kas baris ARKAS: +masuk, -keluar, 0 netral/info. */
    protected static function tanda(string $jenis): int
    {
        if (in_array($jenis, [...ArkasImportService::AKSI_PENDAPATAN], true)) {
            return 1;
        }
        if (in_array($jenis, [...ArkasImportService::AKSI_BELANJA_TUNAI, ...ArkasImportService::AKSI_BELANJA_TRANSFER], true)) {
            return -1;
        }
        if (in_array($jenis, [...ArkasImportService::AKSI_SETOR_PAJAK], true)) {
            return -1;
        }

        return 0;
    }
}
