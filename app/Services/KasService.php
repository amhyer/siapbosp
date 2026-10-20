<?php

namespace App\Services;

use App\Models\KasTransaction;

class KasService
{
    /** Efek kas per baris transaksi: [deltaTunai, deltaBank] */
    public static function efek(string $type, ?string $metode, int $total): array
    {
        return match ($type) {
            'pendapatan' => [0, $total],
            'belanja' => ($metode === 'tunai') ? [-$total, 0] : [0, -$total],
            'ambil_simpanan' => [$total, -$total],
            'mutasi_tunai_ke_bank', 'mutasi-tunai-ke-bank' => [-$total, $total],
            'setor_pajak', 'setor_utang_pajak' => [0, -$total],
            default => [0, 0],
        };
    }

    /**
     * Saldo berjalan tunai/bank + daftar transaksi terurut.
     * Jika $tahun diisi: transaksi tahun-tahun SEBELUMNYA ikut dihitung sebagai
     * 'saldoAwalTahun' (SILPA terbawa otomatis), tetapi tidak masuk daftar items.
     */
    public static function ledger(int $schoolId, ?int $tahun = null): array
    {
        $q = KasTransaction::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->orderBy('tanggal')->orderBy('no_bku')->orderBy('id');
        if ($tahun) {
            $q->where('tahun', '<=', $tahun);
        }
        $rows = $q->get();
        $tunai = 0;
        $bank = 0;
        $out = [];
        $saldoAwalTahun = 0;
        foreach ($rows as $r) {
            [$dt, $db] = self::efek($r->type, $r->metode_bayar, (int) $r->total);
            $tunai += $dt;
            $bank += $db;
            if ($tahun && (int) $r->tahun < $tahun) {
                $saldoAwalTahun = $tunai + $bank;

                continue;
            }
            $out[] = ['trx' => $r, 'dTunai' => $dt, 'dBank' => $db, 'saldo' => $tunai + $bank];
        }

        return ['items' => $out, 'tunai' => $tunai, 'bank' => $bank, 'total' => $tunai + $bank,
            'saldoAwalTahun' => $saldoAwalTahun];
    }

    public static function saldo(int $schoolId, ?int $tahun = null): array
    {
        $l = self::ledger($schoolId, $tahun);

        return ['tunai' => $l['tunai'], 'bank' => $l['bank'], 'total' => $l['total']];
    }

    /** Nomor BKU berikutnya untuk bulan tertentu */
    public static function nextNoBku(int $schoolId, string $tanggal): int
    {
        // Rentang tanggal (bukan to_char) agar portable antar driver (pgsql, sqlite, mysql).
        $mulai = substr($tanggal, 0, 7).'-01';
        $akhir = (new \DateTimeImmutable($mulai))->modify('first day of next month')->format('Y-m-d');
        $max = KasTransaction::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->where('tanggal', '>=', $mulai)->where('tanggal', '<', $akhir)
            ->whereNotNull('no_bku')->max('no_bku');

        return (int) $max + 1;
    }
}
