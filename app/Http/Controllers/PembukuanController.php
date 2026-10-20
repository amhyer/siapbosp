<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\ArkasKode;
use App\Models\BkuTutupBulan;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\RkasHeader;
use App\Services\KasService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PembukuanController extends Controller
{
    private function hitungBulan(string $bulan): array
    {
        $tahun = (int) session('tahun', now()->year);
        $l = KasService::ledger(auth()->user()->school_id, $tahun);
        $saldoAwal = $l['saldoAwalTahun']; // SILPA tahun lalu terbawa otomatis
        $rows = [];
        foreach ($l['items'] as $it) {
            $ym = $it['trx']->tanggal->format('Y-m');
            if ($ym < $bulan) {
                $saldoAwal += $it['dTunai'] + $it['dBank'];

                continue;
            }
            if ($ym === $bulan) {
                $masuk = max(0, $it['dTunai'] + $it['dBank']);
                $keluar = max(0, -($it['dTunai'] + $it['dBank']));
                $rows[] = ['trx' => $it['trx'], 'masuk' => $masuk, 'keluar' => $keluar];
            }
        }

        return [$rows, $saldoAwal, $tahun];
    }

    public function bku()
    {
        $bulan = request('bulan', now()->format('Y-m'));
        [$rows, $saldoAwal, $tahun] = $this->hitungBulan($bulan);
        $sid = auth()->user()->school_id;
        $kodeNama = ArkasKode::peta($sid, $tahun);

        // ==== Panel kontrol ala ARKAS 4 ====
        $saldoSekarang = KasService::saldo($sid, null); // posisi kas saat ini (lintas tahun)
        $pendapatanTh = (int) KasTransaction::where('tahun', $tahun)->where('type', 'pendapatan')->sum('total');
        $belanjaTh = (int) KasTransaction::where('tahun', $tahun)->where('type', 'belanja')->sum('total');
        $pagu = (int) Allocation::where('tahun', $tahun)->sum('nilai');
        // ala ARKAS: Total Dana Tersedia = SILPA terbawa tahun lalu + saluran tahun ini
        $silpaTahunLalu = KasService::ledger($sid, $tahun)['saldoAwalTahun'];
        $danaTersedia = $silpaTahunLalu + $pendapatanTh;
        $bisaBuka = auth()->user()->role === 'kepsek';
        $bisaDianggarkanUlang = max(0, $pagu - $belanjaTh);

        // Grid 12 bulan: status per bulan ala ARKAS (agregasi PHP — portable semua driver)
        $terkunciMap = BkuTutupBulan::bulanTerkunci($sid, $tahun);
        $masukBulan = [];
        $keluarBulan = [];
        $trxPertama = null;
        $semua = KasTransaction::where('tahun', $tahun)->orderBy('tanggal')->orderBy('id')
            ->get(['tanggal', 'type', 'total']);
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
        $firstAktif = $trxPertama;
        $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
            'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $bulanKini = now()->format('Y-m');
        $bulanGrid = [];
        for ($m = 1; $m <= 12; $m++) {
            $kunci = str_pad((string) $m, 2, '0', STR_PAD_LEFT); // '01'..'12'
            $ym = $tahun.'-'.$kunci;
            [$nIn, $masuk] = $masukBulan[$kunci] ?? [0, 0];
            [$nOut, $keluar] = $keluarBulan[$kunci] ?? [0, 0];
            $n = $nIn + $nOut;
            $status = match (true) {
                in_array($ym, $terkunciMap, true) => 'tertutup',
                $n > 0 && $ym < $bulanKini => 'selesai',
                $n > 0 || $ym === $bulanKini => 'draf',
                $firstAktif !== null && $ym < $firstAktif => 'tanpa-dana',
                default => 'kosong',
            };
            $bulanGrid[] = ['ym' => $ym, 'nama' => $namaBulan[$m], 'masuk' => (int) $masuk,
                'keluar' => (int) $keluar, 'n' => $n, 'status' => $status, 'now' => $ym === $bulanKini];
        }

        return view('pembukuan.bku', compact('rows', 'bulan', 'saldoAwal', 'tahun', 'kodeNama',
            'saldoSekarang', 'pendapatanTh', 'belanjaTh', 'pagu', 'danaTersedia', 'bisaDianggarkanUlang',
            'bulanGrid', 'terkunciMap', 'bisaBuka', 'silpaTahunLalu'));
    }

    public function bkuCetak()
    {
        $bulan = request('bulan', now()->format('Y-m'));
        [$rows, $saldoAwal, $tahun] = $this->hitungBulan($bulan);
        $penandatangan = Penandatangan::orderBy('id')->get();
        $sid = auth()->user()->school_id;
        // Saldo tunai/bank awal & akhir bulan (gaya ARKAS: dua baris saldo + box Ditutup).
        $mulai = $bulan.'-01';
        $tunaiAwal = 0;
        $bankAwal = 0;
        $tunaiAkhir = 0;
        $bankAkhir = 0;
        foreach (KasService::ledger($sid, null)['items'] as $it) {
            $tgl = $it['trx']->tanggal->format('Y-m-d');
            if ($tgl < $mulai) {
                $tunaiAwal += $it['dTunai'];
                $bankAwal += $it['dBank'];
            }
        }
        $tunaiAkhir = $tunaiAwal;
        $bankAkhir = $bankAwal;
        foreach ($rows as $row) {
            [$dt, $db] = KasService::efek($row['trx']->type, $row['trx']->metode_bayar, (int) $row['trx']->total);
            $tunaiAkhir += $dt;
            $bankAkhir += $db;
        }
        // Kop: sumber dana dominan + revisi RKAS terakhir (gaya "BOSP Reguler Perubahan Revisi Ke 1").
        $domBos = KasTransaction::where('school_id', $sid)->where('tahun', $tahun)
            ->selectRaw('bos_type_id, sum(total) j')->groupBy('bos_type_id')->orderByDesc('j')->first();
        $kopSumber = $domBos?->bosType?->nama ?? 'BOSP Reguler';
        $revisiKe = (int) RkasHeader::where('school_id', $sid)->where('tahun', $tahun)->max('revisi_ke');
        $kodeNama = ArkasKode::peta($sid, $tahun);

        return view('pembukuan.bku-cetak', compact('rows', 'bulan', 'saldoAwal', 'tahun', 'penandatangan',
            'tunaiAwal', 'bankAwal', 'tunaiAkhir', 'bankAkhir', 'kopSumber', 'revisiKe', 'kodeNama'));
    }

    public function bkuCsv()
    {
        $bulan = request('bulan', now()->format('Y-m'));
        [$rows, $saldoAwal, $tahun] = $this->hitungBulan($bulan);
        $kodeNama = ArkasKode::peta(auth()->user()->school_id, $tahun);

        return response()->streamDownload(function () use ($rows, $saldoAwal, $kodeNama) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'Kegiatan', 'Rekening', 'No. Bukti', 'Uraian', 'Penerimaan', 'Pengeluaran', 'Saldo'], ';');
            $saldo = $saldoAwal;
            fputcsv($out, ['', 'Saldo Awal Bulan', '', '', '', '', '', $saldoAwal], ';');
            foreach ($rows as $row) {
                $saldo += $row['masuk'] - $row['keluar'];
                $t = $row['trx'];
                $keg = ($t->details->first(fn ($d) => $d->kode_kegiatan) ?? $t->details->first())?->kode_kegiatan ?? '';
                $kegLabel = $keg !== '' ? trim($keg.' '.($kodeNama[$keg] ?? '')) : '';
                $rek = $t->details->map(fn ($d) => $d->rekening->kode ?? '')->filter()->unique()->implode(', ');
                fputcsv($out, [
                    $t->tanggal->format('d/m/Y'), $kegLabel, $rek,
                    $t->arkas_bku ?? ($t->no_bku ? 'BKU#'.$t->no_bku : ''),
                    $t->keterangan, $row['masuk'] ?: '', $row['keluar'] ?: '', $saldo,
                ], ';');
            }
            fclose($out);
        }, "bku_{$bulan}.csv", ['Content-Type' => 'text/csv']);
    }

    /** Register resmi Kas Tunai / Kas Bank per bulan (gaya pembantu Simakda). */
    /** Register resmi Kas Tunai / Kas Bank per bulan (gaya pembantu Simakda). */
    public function register()
    {
        $jenis = request('jenis', 'tunai');
        abort_unless(in_array($jenis, ['tunai', 'bank']), 404);
        $bulan = request('bulan', now()->format('Y-m'));
        $tahunAktif = (int) session('tahun', now()->year);
        $l = KasService::ledger(auth()->user()->school_id, null); // semua tahun → SILPA otomatis masuk
        $saldoAwal = 0;
        $rows = [];
        foreach ($l['items'] as $it) {
            $d = $jenis === 'tunai' ? $it['dTunai'] : $it['dBank'];
            $ym = $it['trx']->tanggal->format('Y-m');
            if ($ym < $bulan) {
                $saldoAwal += $d;

                continue;
            }
            if ($ym === $bulan && $d != 0) {
                $rows[] = ['trx' => $it['trx'], 'masuk' => max(0, $d), 'keluar' => max(0, -$d)];
            }
        }
        $penandatangan = Penandatangan::orderBy('id')->get();
        $sekolah = auth()->user()->school;

        return view('pembukuan.register', compact('rows', 'bulan', 'saldoAwal', 'jenis', 'tahunAktif', 'penandatangan', 'sekolah'));
    }

    public function kas()
    {
        $tahun = (int) session('tahun', now()->year);
        $l = KasService::ledger(auth()->user()->school_id, $tahun);
        $mutasi = array_filter($l['items'], fn ($i) => $i['dTunai'] != 0 || $i['dBank'] != 0);

        return view('pembukuan.kas', ['items' => $mutasi, 'saldo' => $l, 'tahun' => $tahun]);
    }

    /**
     * 📚 Format BKU Semester ala ARKAS: Buku Pembantu Rincian Objek Belanja.
     * Pengeluaran rupel per kode rekening sepanjang semester dengan saldo
     * kumulatif; rekening diawali saldo pengeluaran sebelum semester.
     */
    public function bukuPembantu(Request $r)
    {
        [$semester, $tahun, $awal, $akhir] = $this->persiapanSemester($r);
        $sid = auth()->user()->school_id;

        $dets = DB::table('kas_details as d')
            ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
            ->leftJoin('rekenings as rk', 'd.rekening_id', '=', 'rk.id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->select('d.nilai', 't.tanggal', 't.no_bukti', 't.keterangan', 'rk.kode', 'rk.nama')
            ->orderBy('rk.kode')->orderBy('t.tanggal')->orderBy('t.id')->get();

        // Kelompokkan per rekening; transaksi sebelum semester = saldo pengeluaran awal.
        $kelompok = [];
        foreach ($dets as $d) {
            $kode = $d->kode ?: '—';
            $kelompok[$kode] ??= ['nama' => $d->nama, 'awalSaldo' => 0, 'rows' => []];
            $tgl = substr((string) $d->tanggal, 0, 10);
            if ($tgl < $awal) {
                $kelompok[$kode]['awalSaldo'] += (int) $d->nilai;

                continue;
            }
            if ($tgl > $akhir) {
                continue;
            }
            $kelompok[$kode]['rows'][] = ['bukti' => $d->no_bukti, 'tgl' => $tgl,
                'uraian' => $d->keterangan, 'nilai' => (int) $d->nilai];
        }
        ksort($kelompok);

        // Saldo kumulatif per baris sesuai gaya Buku Pembantu ARKAS.
        $totalMutasi = 0;
        $kelompok = array_map(function ($g) use (&$totalMutasi) {
            $saldo = (int) $g['awalSaldo'];
            foreach ($g['rows'] as &$row) {
                $saldo += $row['nilai'];
                $row['saldo'] = $saldo;
            }
            $g['akhir'] = $saldo;
            $g['mutasi'] = $saldo - (int) $g['awalSaldo'];
            $totalMutasi += $g['mutasi'];

            return $g;
        }, $kelompok);

        $sekolah = auth()->user()->school;
        $penandatangan = Penandatangan::orderBy('id')->get();

        return view('pembukuan.buku-pembantu', compact('semester', 'tahun', 'awal', 'akhir',
            'kelompok', 'totalMutasi', 'sekolah', 'penandatangan'));
    }

    /** 📚 Format BKU Semester ala ARKAS: Rekapitulasi Realisasi Belanja per rekening. */
    public function rekapRealisasi(Request $r)
    {
        [$semester, $tahun, $awal, $akhir] = $this->persiapanSemester($r);
        $sid = auth()->user()->school_id;

        $realisasi = DB::table('kas_details as d')
            ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
            ->leftJoin('rekenings as rk', 'd.rekening_id', '=', 'rk.id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->where('t.tanggal', '>=', $awal)->where('t.tanggal', '<=', $akhir)
            ->select('rk.kode', 'rk.nama', DB::raw('sum(d.nilai) j'))
            ->groupBy('rk.kode', 'rk.nama')->orderBy('rk.kode')->get()
            ->pluck('j', 'kode');

        // Pagu per rekening diambil dari RKAS aktif (revisi terakhir) bila ada.
        $header = RkasHeader::where('school_id', $sid)->where('tahun', $tahun)
            ->where('status', '!=', 'draft')->orderByDesc('revisi_ke')->first();
        $anggaran = collect();
        if ($header) {
            $anggaran = DB::table('rkas_details as rd')
                ->leftJoin('rekenings as rk', 'rd.rekening_id', '=', 'rk.id')
                ->where('rd.rkas_header_id', $header->id)
                ->select('rk.kode', 'rk.nama', DB::raw('sum(rd.jumlah) j'))
                ->groupBy('rk.kode', 'rk.nama')->orderBy('rk.kode')->get()
                ->mapWithKeys(fn ($x) => [$x->kode => (object) ['nama' => $x->nama, 'anggaran' => (int) $x->j]]);
        }

        // Gabungkan: semua rekening yang punya anggaran ATAU realisasi.
        $rows = $anggaran->keys()->merge($realisasi->keys())->unique()->sort()->values()
            ->map(fn ($kode) => [
                'kode' => $kode,
                'nama' => $anggaran[$kode]->nama ?? DB::table('rekenings')->where('kode', $kode)->value('nama'),
                'anggaran' => (int) ($anggaran[$kode]->anggaran ?? 0),
                'realisasi' => (int) ($realisasi[$kode] ?? 0),
                'punyaAnggaran' => isset($anggaran[$kode]),
            ])->map(function ($x) {
                $x['sisa'] = $x['punyaAnggaran'] ? $x['anggaran'] - $x['realisasi'] : null;

                return $x;
            })
            ->sortBy(fn ($x) => $x['kode'] === '—')->values();

        $sumAnggaran = (int) $rows->filter(fn ($x) => $x['punyaAnggaran'])->sum('anggaran');
        $sumRealisasi = (int) $rows->sum('realisasi');

        $sekolah = auth()->user()->school;
        $penandatangan = Penandatangan::orderBy('id')->get();

        return view('pembukuan.rekap-realisasi', compact('semester', 'tahun', 'rows', 'sumAnggaran',
            'sumRealisasi', 'sekolah', 'penandatangan'));
    }

    /** Satu pintu persiapan cetak semester: par (semester, tahun, tanggal awal, akhir). */
    private function persiapanSemester(Request $r): array
    {
        $tahun = (int) session('tahun', now()->year);
        $semester = (int) $r->query('semester', (int) now()->format('m') <= 6 ? 1 : 2);
        abort_unless(in_array($semester, [1, 2], true), 404);
        [$awal, $akhir] = $this->rentangSemester($semester, $tahun);

        return [$semester, $tahun, $awal, $akhir];
    }

    /** Rentang tanggal semester (awal & akhir inklusif): 1 = Januari–Juni, 2 = Juli–Desember. */
    private function rentangSemester(int $semester, int $tahun): array
    {
        return $semester === 1
            ? [$tahun.'-01-01', $tahun.'-06-30']
            : [$tahun.'-07-01', $tahun.'-12-31'];
    }

    /** 🔒 Tutup BKU satu bulan (ala ARKAS) — semua catatan/muhapus bulan itu terkunci. */
    public function tutupBulan(Request $r)
    {
        $bulan = $r->validate(['bulan' => 'required|date_format:Y-m'])['bulan'];
        $tahun = (int) substr($bulan, 0, 4);
        $ada = KasTransaction::where('tahun', $tahun)->exists();
        if (! $ada) {
            return back()->with('error', 'Belum ada transaksi pada tahun itu, bulan tidak perlu ditutup.');
        }
        BkuTutupBulan::firstOrCreate(
            ['tahun' => $tahun, 'bulan' => $bulan],
            ['ditutup_oleh' => auth()->user()->name]
        ); // log tercatat otomatis via event BkuTutupBulan::created
        $nama = Carbon::parse($bulan.'-01')->translatedFormat('F Y');

        return redirect()->route('bku', ['bulan' => $bulan])->with('ok', "BKU bulan {$nama} sudah DITUTUP — seluruh transaksi bulan itu terkunci.");
    }

    /** 🔓 Buka kembali BKU satu bulan — khusus Kepala Sekolah. */
    public function bukaBulan(Request $r)
    {
        if (auth()->user()->role !== 'kepsek') {
            return back()->with('error', 'Hanya Kepala Sekolah yang dapat membuka kembali bulan BKU.');
        }
        $bulan = $r->validate(['bulan' => 'required|date_format:Y-m'])['bulan'];
        BkuTutupBulan::where('tahun', (int) substr($bulan, 0, 4))->where('bulan', $bulan)->delete(); // log via event deleted

        $nama = Carbon::parse($bulan.'-01')->translatedFormat('F Y');

        return redirect()->route('bku', ['bulan' => $bulan])->with('ok', "BKU bulan {$nama} dibuka kembali — bulan itu boleh diisi lagi.");
    }
}
