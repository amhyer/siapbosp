<?php

namespace App\Services;

use App\Models\BkuTutupBulan;
use App\Models\KasTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Status BKU 12 bulan ala ARKAS 4 — satu sumber kebenaran.
 *
 * Sebelumnya perhitungan ini hanya hidup di dalam PembukuanController::bku().
 * Halaman Penatausahaan juga butuh status yang sama, jadi logikanya dipindah
 * ke sini agar kedua halaman tidak pernah berbeda kesimpulan.
 *
 * Status per bulan:
 *  - belum-pajak : bulan sudah ditutup tetapi masih ada pajak berstatus belum
 *  - tertutup    : bulan sudah ditutup
 *  - selesai     : ada transaksi dan bulannya sudah lewat (menunggu ditutup)
 *  - draf        : ada transaksi (atau bulan berjalan)
 *  - tanpa-dana  : belum ada transaksi, padahal dana sudah masuk di bulan lain
 *  - kosong      : belum ada transaksi sama sekali
 */
class BkuBulan
{
    public const NAMA = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public const PENDEK = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Label & tanda yang dipakai grid ringkas (halaman Penatausahaan). */
    public const LABEL = [
        'belum-pajak' => 'Selesai, Pajak Belum',
        'tertutup' => 'Sudah Selesai',
        'selesai' => 'Sudah Selesai',
        'draf' => 'Draf',
        'tanpa-dana' => 'Belum Dibuat',
        'kosong' => 'Belum Dibuat',
    ];

    public const TANDA = [
        'belum-pajak' => '!',
        'tertutup' => '✓',
        'selesai' => '✓',
        'draf' => '•',
        'tanpa-dana' => '–',
        'kosong' => '–',
    ];

    /**
     * Grid 12 bulan satu tahun anggaran.
     *
     * @return array<int, array{ym:string, nama:string, pendek:string, masuk:int,
     *               keluar:int, n:int, status:string, pajak_belum:int, now:bool}>
     */
    public static function grid(int $schoolId, int $tahun, ?string $bulanKini = null): array
    {
        $bulanKini = $bulanKini ?? now()->format('Y-m');

        $terkunci = BkuTutupBulan::withoutGlobalScope('school')
            ->where('school_id', $schoolId)->where('tahun', $tahun)->pluck('bulan')->all();

        // Pajak belum lapor per bulan (status "Sudah Selesai — Belum Lapor Pajak", ARKAS 4 Bab XVIII)
        $pajakBelumPerBulan = DB::table('kas_taxes as p')
            ->join('kas_transactions as t', 'p.kas_transaction_id', '=', 't.id')
            ->where('t.school_id', $schoolId)
            ->where('t.tahun', $tahun)
            ->where('p.status', 'belum')
            ->selectRaw('substr(CAST(t.tanggal AS VARCHAR), 1, 7) as ym, sum(p.nilai) as j')
            ->groupBy('ym')
            ->pluck('j', 'ym');

        $masukBulan = [];
        $keluarBulan = [];
        $trxPertama = null;
        $semua = KasTransaction::where('school_id', $schoolId)->where('tahun', $tahun)
            ->orderBy('tanggal')->orderBy('id')->get(['tanggal', 'type', 'total']);

        foreach ($semua as $t) {
            $kunci = $t->tanggal->format('m');
            $ym = $t->tanggal->format('Y-m');
            $trxPertama ??= $ym;
            if ($t->type === 'pendapatan') {
                $masukBulan[$kunci] = [($masukBulan[$kunci][0] ?? 0) + 1, ($masukBulan[$kunci][1] ?? 0) + (int) $t->total];
            } elseif ($t->type === 'belanja') {
                $keluarBulan[$kunci] = [($keluarBulan[$kunci][0] ?? 0) + 1, ($keluarBulan[$kunci][1] ?? 0) + (int) $t->total];
            }
        }

        $grid = [];
        for ($m = 1; $m <= 12; $m++) {
            $kunci = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $ym = $tahun.'-'.$kunci;
            [$nIn, $masuk] = $masukBulan[$kunci] ?? [0, 0];
            [$nOut, $keluar] = $keluarBulan[$kunci] ?? [0, 0];
            $n = $nIn + $nOut;
            $pajakBelum = (int) ($pajakBelumPerBulan[$ym] ?? 0);
            $status = match (true) {
                in_array($ym, $terkunci, true) && $pajakBelum > 0 => 'belum-pajak',
                in_array($ym, $terkunci, true) => 'tertutup',
                $n > 0 && $ym < $bulanKini => 'selesai',
                $n > 0 || $ym === $bulanKini => 'draf',
                $trxPertama !== null && $ym < $trxPertama => 'tanpa-dana',
                default => 'kosong',
            };
            $grid[] = [
                'ym' => $ym,
                'nama' => self::NAMA[$m],
                'pendek' => self::PENDEK[$m],
                'masuk' => (int) $masuk,
                'keluar' => (int) $keluar,
                'n' => $n,
                'status' => $status,
                'pajak_belum' => $pajakBelum,
                'now' => $ym === $bulanKini,
            ];
        }

        return $grid;
    }
}
