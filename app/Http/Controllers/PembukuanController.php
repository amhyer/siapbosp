<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\ArkasKode;
use App\Models\BkuTutupBulan;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\Stage;
use App\Services\KasService;
use App\Support\Csv;
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
        $silpaTahunLalu = KasService::ledger($sid, $tahun)['saldoAwalTahun'];
        $danaTersedia = $silpaTahunLalu + $pendapatanTh;
        $bisaBuka = auth()->user()->role === 'kepsek';
        $bisaDianggarkanUlang = max(0, $pagu - $belanjaTh);

        // Pajak belum lapor per bulan (status "Sudah Selesai — Belum Lapor Pajak" ala ARKAS 4 Bab XVIII)
        $pajakBelumPerBulan = DB::table('kas_taxes as p')
            ->join('kas_transactions as t', 'p.kas_transaction_id', '=', 't.id')
            ->where('t.school_id', $sid)
            ->where('t.tahun', $tahun)
            ->where('p.status', 'belum')
            ->selectRaw('substr(CAST(t.tanggal AS VARCHAR), 1, 7) as ym, sum(p.nilai) as j')
            ->groupBy('ym')
            ->pluck('j', 'ym');

        // Grid 12 bulan: status per bulan ala ARKAS (agregasi PHP — portable semua driver)
        $tutupRecords = BkuTutupBulan::withoutGlobalScope('school')
            ->where('school_id', $sid)->where('tahun', $tahun)->get()->keyBy('bulan');
        $terkunciMap = $tutupRecords->keys()->all();

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
            $kunci = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $ym = $tahun.'-'.$kunci;
            [$nIn, $masuk] = $masukBulan[$kunci] ?? [0, 0];
            [$nOut, $keluar] = $keluarBulan[$kunci] ?? [0, 0];
            $n = $nIn + $nOut;
            $pajakBelum = (int) ($pajakBelumPerBulan[$ym] ?? 0);
            $status = match (true) {
                in_array($ym, $terkunciMap, true) && $pajakBelum > 0 => 'belum-pajak',
                in_array($ym, $terkunciMap, true) => 'tertutup',
                $n > 0 && $ym < $bulanKini => 'selesai',
                $n > 0 || $ym === $bulanKini => 'draf',
                $firstAktif !== null && $ym < $firstAktif => 'tanpa-dana',
                default => 'kosong',
            };
            $bulanGrid[] = [
                'ym' => $ym, 'nama' => $namaBulan[$m], 'masuk' => (int) $masuk,
                'keluar' => (int) $keluar, 'n' => $n, 'status' => $status,
                'pajak_belum' => $pajakBelum, 'now' => $ym === $bulanKini,
            ];
        }

        $infoTutup = $tutupRecords[$bulan] ?? null;

        return view('pembukuan.bku', compact('rows', 'bulan', 'saldoAwal', 'tahun', 'kodeNama',
            'saldoSekarang', 'pendapatanTh', 'belanjaTh', 'pagu', 'danaTersedia', 'bisaDianggarkanUlang',
            'bulanGrid', 'terkunciMap', 'bisaBuka', 'silpaTahunLalu', 'infoTutup'));
    }

    /** Nama bulan & hari Indonesia — dokumen cetak tidak boleh bergantung APP_LOCALE. */
    private const BULAN_ID = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private const HARI_ID = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    /** Format cetak BKU pada parameter ?format= (ARKAS 4 Bab XXI). */
    public const FORMAT_CETAK = [
        'bulanan' => 'Bulanan',
        'tahunan' => 'Tahunan',
        'tahapan' => 'Tahapan',
        'akumulasi' => 'Akumulasi',
    ];

    /**
     * Cetak BKU dengan pemilih Format + Periode ala ARKAS 4.
     *
     * Format hanya mengubah rentang tanggal (dan penyaringan tahap), bukan cara
     * saldo dihitung: kolom Saldo selalu posisi kas sesungguhnya per baris, dan
     * baris "Saldo Awal" adalah posisi kas tepat sebelum tanggal awal periode.
     */
    public function bkuCetak()
    {
        $format = (string) request('format', 'bulanan');
        if (! array_key_exists($format, self::FORMAT_CETAK)) {
            $format = 'bulanan'; // format tak dikenal jangan sampai merusak halaman cetak
        }

        $sid = auth()->user()->school_id;
        $tahun = (int) session('tahun', now()->year);

        // Bulan acuan: dipakai apa adanya pada format Bulanan dan sebagai batas
        // akhir pada format Akumulasi. Tahunnya selalu mengikuti tahun anggaran
        // aktif agar rentang tidak melompat ke buku tahun lain.
        $masukan = (string) request('bulan', now()->format('Y-m'));
        $acuan = preg_match('/^\d{4}-\d{2}$/', $masukan) === 1 ? $masukan : now()->format('Y-m');
        $bulan = $tahun.'-'.substr($acuan, 5, 2);

        $awalBulan = Carbon::parse($bulan.'-01')->startOfMonth();
        $akhirBulan = $awalBulan->copy()->endOfMonth();
        $namaBulanAwal = self::BULAN_ID[(int) $awalBulan->format('n')];
        $namaBulanAkhir = self::BULAN_ID[(int) $akhirBulan->format('n')];
        $tglAkhirBulanTeks = $akhirBulan->format('d').' '.$namaBulanAkhir.' '.$tahun;
        $awalTahun = Carbon::create($tahun, 1, 1)->startOfDay();
        $akhirTahun = Carbon::create($tahun, 12, 31)->startOfDay();

        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->orderBy('id')->get();
        $tahap = $stages->firstWhere('id', (int) request('tahap')) ?? $stages->first();

        switch ($format) {
            case 'tahunan':
                $awal = $awalTahun;
                $akhir = $akhirTahun;
                $labelPeriode = 'TAHUN ANGGARAN '.$tahun;
                $labelSaldoAwal = 'Saldo Awal Tahun';
                $catatan = 'Format Tahunan mencetak seluruh transaksi 1 Januari – 31 Desember '.$tahun.'.';
                break;
            case 'akumulasi':
                $awal = $awalTahun;
                $akhir = $akhirBulan;
                $labelPeriode = '1 JANUARI – '.strtoupper($tglAkhirBulanTeks);
                $labelSaldoAwal = 'Saldo Awal Tahun';
                $catatan = 'Format Akumulasi mencetak seluruh transaksi sejak 1 Januari sampai akhir '
                    .$namaBulanAkhir.' '.$tahun.'.';
                break;
            case 'tahapan':
                $awal = $awalTahun;
                $akhir = $akhirTahun;
                $labelPeriode = 'TAHAPAN '.strtoupper((string) ($tahap->nama ?? '—')).' TAHUN '.$tahun;
                $labelSaldoAwal = 'Saldo Awal Tahun';
                $catatan = 'Format Tahapan hanya mencetak baris '.(string) ($tahap->nama ?? '—')
                    .' tahun '.$tahun.'. Kolom Saldo tetap posisi kas sesungguhnya, jadi baris tahap lain '
                    .'tidak ditampilkan tetapi tetap memengaruhi saldo.';
                break;
            default:
                $awal = $awalBulan;
                $akhir = $akhirBulan;
                $labelPeriode = 'BULAN '.strtoupper($namaBulanAwal).' TAHUN '.$tahun;
                $labelSaldoAwal = 'Saldo Bulan Lalu';
                $catatan = 'Format Bulanan mencetak transaksi '.$namaBulanAwal.' '.$tahun.' saja.';
        }

        // Nama bulan penutup HARUS diturunkan dari tanggal akhir rentang yang sudah
        // final — format Tahunan/Tahapan berakhir 31 Desember, bukan bulan acuan.
        $namaBulanAkhir = self::BULAN_ID[(int) $akhir->format('n')];

        $l = KasService::ledger($sid, $tahun);
        $saldoAwal = $l['saldoAwalTahun']; // SILPA tahun lalu terbawa otomatis
        $tunaiAwal = 0;
        $bankAwal = 0;
        $posTunai = 0;
        $posBank = 0;
        $snapTunai = null;
        $snapBank = null;
        $rows = [];

        foreach ($l['items'] as $it) {
            $t = $it['trx'];
            $d = $it['dTunai'] + $it['dBank'];

            if ($t->tanggal->lt($awal)) {
                $saldoAwal += $d;
                $tunaiAwal += $it['dTunai'];
                $bankAwal += $it['dBank'];

                continue;
            }
            if ($t->tanggal->gt($akhir)) {
                continue;
            }

            // Posisi kas bergerak untuk SEMUA transaksi dalam rentang; hanya baris
            // yang lolos saringan tahap yang dicetak (lihat $catatan).
            $posTunai += $it['dTunai'];
            $posBank += $it['dBank'];

            if ($format === 'tahapan' && (int) $t->stage_id !== (int) ($tahap->id ?? 0)) {
                continue;
            }

            $rows[] = [
                'trx' => $t,
                'masuk' => max(0, $d),
                'keluar' => max(0, -$d),
                'saldo' => $saldoAwal + $posTunai + $posBank,
            ];
            $snapTunai = $tunaiAwal + $posTunai;
            $snapBank = $bankAwal + $posBank;
        }

        // Penutup memakai posisi kas pada baris TERAKHIR YANG DICETAK supaya konsisten
        // dengan kolom Saldo; bila tak ada baris, pakai posisi akhir rentang.
        $saldoAkhir = $rows !== [] ? end($rows)['saldo'] : $saldoAwal + $posTunai + $posBank;
        $tunaiAkhir = $snapTunai ?? ($tunaiAwal + $posTunai);
        $bankAkhir = $snapBank ?? ($bankAwal + $posBank);

        // Watermark DRAFT aktif bila ADA bulan dalam rentang yang belum ditutup.
        // Rentang dihitung dari indeks bulan (tahun*12+bulan) agar bebas dari API Carbon.
        $rentangBulan = [];
        $mulaiIdx = (int) $awal->format('Y') * 12 + (int) $awal->format('n');
        $akhirIdx = (int) $akhir->format('Y') * 12 + (int) $akhir->format('n');
        for ($i = $mulaiIdx; $i <= $akhirIdx; $i++) {
            $rentangBulan[] = sprintf('%04d-%02d', intdiv($i - 1, 12), (($i - 1) % 12) + 1);
        }
        $bulanTerbuka = array_values(array_diff($rentangBulan, BkuTutupBulan::bulanTerkunci($sid, $tahun)));
        $isDraft = $bulanTerbuka !== [];
        $draftPesan = '';
        if ($isDraft) {
            $draftPesan = $format === 'bulanan'
                ? 'BKU bulan ini belum ditutup. Lakukan Tutup BKU Bulan Ini terlebih dahulu agar tanda air DRAFT hilang.'
                : count($bulanTerbuka).' bulan dalam periode ini belum ditutup (mulai '.$bulanTerbuka[0].'). '
                    .'Tutup BKU setiap bulan agar tanda air DRAFT hilang.';
        }

        $penandatangan = Penandatangan::orderBy('id')->get();
        $domBos = KasTransaction::where('school_id', $sid)->where('tahun', $tahun)
            ->selectRaw('bos_type_id, sum(total) j')->groupBy('bos_type_id')->orderByDesc('j')->first();
        $kopSumber = $domBos?->bosType?->nama ?? 'BOSP Reguler';
        $revisiKe = (int) RkasHeader::where('school_id', $sid)->where('tahun', $tahun)->max('revisi_ke');
        $kodeNama = ArkasKode::peta($sid, $tahun);
        $formatPilihan = self::FORMAT_CETAK;
        $tglAkhirTeks = $akhir->format('d').' '.$namaBulanAkhir.' '.$akhir->format('Y');
        $hariIniTeks = self::HARI_ID[(int) $akhir->format('N')].' '.$tglAkhirTeks;

        return view('pembukuan.bku-cetak', compact('rows', 'bulan', 'saldoAwal', 'tahun', 'penandatangan',
            'tunaiAwal', 'bankAwal', 'tunaiAkhir', 'bankAkhir', 'kopSumber', 'revisiKe', 'kodeNama', 'isDraft',
            'format', 'formatPilihan', 'labelPeriode', 'labelSaldoAwal', 'catatan', 'draftPesan',
            'awal', 'akhir', 'saldoAkhir', 'stages', 'tahap', 'tglAkhirTeks', 'hariIniTeks'));
    }

    public function bkuCsv()
    {
        $bulan = request('bulan', now()->format('Y-m'));
        [$rows, $saldoAwal, $tahun] = $this->hitungBulan($bulan);
        $kodeNama = ArkasKode::peta(auth()->user()->school_id, $tahun);

        return response()->streamDownload(function () use ($rows, $saldoAwal, $kodeNama) {
            $out = fopen('php://output', 'w');
            fputcsv($out, Csv::baris(['Tanggal', 'Kegiatan', 'Rekening', 'No. Bukti', 'Uraian', 'Penerimaan', 'Pengeluaran', 'Saldo']), ';');
            $saldo = $saldoAwal;
            fputcsv($out, Csv::baris(['', 'Saldo Awal Bulan', '', '', '', '', '', $saldoAwal]), ';');
            foreach ($rows as $row) {
                $saldo += $row['masuk'] - $row['keluar'];
                $t = $row['trx'];
                $keg = ($t->details->first(fn ($d) => $d->kode_kegiatan) ?? $t->details->first())?->kode_kegiatan ?? '';
                $kegLabel = $keg !== '' ? trim($keg.' '.($kodeNama[$keg] ?? '')) : '';
                $rek = $t->details->map(fn ($d) => $d->rekening->kode ?? '')->filter()->unique()->implode(', ');
                fputcsv($out, Csv::baris([
                    $t->tanggal->format('d/m/Y'), $kegLabel, $rek,
                    $t->arkas_bku ?? ($t->no_bku ? 'BKU#'.$t->no_bku : ''),
                    $t->keterangan, $row['masuk'] ?: '', $row['keluar'] ?: '', $saldo,
                ]), ';');
            }
            fclose($out);
        }, "bku_{$bulan}.csv", ['Content-Type' => 'text/csv']);
    }

    /** Register resmi Kas Tunai / Kas Bank per bulan. */
    public function register()
    {
        $jenis = request('jenis', 'tunai');
        abort_unless(in_array($jenis, ['tunai', 'bank']), 404);
        $bulan = request('bulan', now()->format('Y-m'));
        $tahunAktif = (int) session('tahun', now()->year);
        $l = KasService::ledger(auth()->user()->school_id, null);
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

    /** 📚 Format BKU Semester ala ARKAS: Buku Pembantu Rincian Objek Belanja. */
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

    /** 📄 Format BKU Resmi ke-4 ala ARKAS 4 Bab XXI: Surat Pernyataan Tanggung Jawab Mutlak (SPTJM). */
    public function sptjm(Request $r)
    {
        [$semester, $tahun, $awal, $akhir] = $this->persiapanSemester($r);
        $sid = auth()->user()->school_id;
        $sekolah = auth()->user()->school;
        $penandatangan = Penandatangan::orderBy('id')->get();
        $kepsek = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'kepala'));
        $bendahara = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'bendahara'));

        $ledgerTh = KasService::ledger($sid, $tahun);
        $saldoAwal = 0;
        $penerimaan = 0;
        $pengeluaran = 0;
        foreach (KasService::ledger($sid, null)['items'] as $it) {
            $tgl = $it['trx']->tanggal->format('Y-m-d');
            $delta = $it['dTunai'] + $it['dBank'];
            if ($tgl < $awal) {
                $saldoAwal += $delta;
            } elseif ($tgl <= $akhir) {
                if ($delta > 0) {
                    $penerimaan += $delta;
                } else {
                    $pengeluaran += -$delta;
                }
            }
        }
        $sisaKas = $saldoAwal + $penerimaan - $pengeluaran;
        $nomorSurat = $r->query('nomor', sprintf('421/%03d/SPTJM-BOSP/%d', $semester, $tahun));

        return view('pembukuan.sptjm', compact(
            'semester', 'tahun', 'awal', 'akhir', 'sekolah', 'kepsek', 'bendahara',
            'saldoAwal', 'penerimaan', 'pengeluaran', 'sisaKas', 'nomorSurat'
        ));
    }

    private function persiapanSemester(Request $r): array
    {
        $tahun = (int) session('tahun', now()->year);
        $semester = (int) $r->query('semester', (int) now()->format('m') <= 6 ? 1 : 2);
        abort_unless(in_array($semester, [1, 2], true), 404);
        [$awal, $akhir] = $this->rentangSemester($semester, $tahun);

        return [$semester, $tahun, $awal, $akhir];
    }

    private function rentangSemester(int $semester, int $tahun): array
    {
        return $semester === 1
            ? [$tahun.'-01-01', $tahun.'-06-30']
            : [$tahun.'-07-01', $tahun.'-12-31'];
    }

    /**
     * 🔒 Tutup BKU satu bulan (ala ARKAS 4 Bab XVII):
     * - Bulan sebelum penerimaan dana pertama tahun berjalan dianggap otomatis selesai.
     * - Bulan sejak penerimaan dana pertama wajib ditutup berurutan (kronologis).
     * - Mendukung pencatatan Bunga Bank/Jasa Giro & Pajak Bunga/Biaya Admin Bank saat penutupan bulan.
     * - Menandai status `belum_lapor_pajak` apabila masih terdapat pajak bulan itu yang belum disetor NTPN.
     */
    public function tutupBulan(Request $r)
    {
        $data = $r->validate([
            'bulan' => 'required|date_format:Y-m',
            'bunga_bank' => 'nullable|numeric|min:0',
            'pajak_bunga' => 'nullable|numeric|min:0',
        ]);
        $bulan = $data['bulan'];
        $tahun = (int) substr($bulan, 0, 4);
        $sid = auth()->user()->school_id;

        $trxPertama = KasTransaction::where('tahun', $tahun)->orderBy('tanggal')->value('tanggal');
        if (! $trxPertama) {
            return back()->with('error', 'Belum ada transaksi pada tahun itu, bulan tidak perlu ditutup.');
        }

        // Validasi kronologis ala ARKAS 4: cek apakah ada bulan sebelumnya sejak bulan aktif pertama yang belum ditutup
        $ymPertama = Carbon::parse($trxPertama)->format('Y-m');
        if ($bulan > $ymPertama) {
            $terkunci = BkuTutupBulan::bulanTerkunci($sid, $tahun);
            $cursor = Carbon::parse($ymPertama.'-01');
            $target = Carbon::parse($bulan.'-01');
            while ($cursor->lt($target)) {
                $ymCek = $cursor->format('Y-m');
                if (! in_array($ymCek, $terkunci, true)) {
                    $nmCek = $cursor->copy()->locale('id')->translatedFormat('F Y');

                    return back()->with('error', "BKU wajib ditutup secara berurutan (kronologis sesuai ARKAS 4): tutup bulan {$nmCek} terlebih dahulu.");
                }
                $cursor->addMonth();
            }
        }

        $bunga = max(0, (int) ($data['bunga_bank'] ?? 0));
        $pajakBunga = max(0, (int) ($data['pajak_bunga'] ?? 0));
        $tglAkhirBulan = Carbon::parse($bulan.'-01')->endOfMonth()->format('Y-m-d');
        $nama = Carbon::parse($bulan.'-01')->locale('id')->translatedFormat('F Y');

        if ($bunga > 0 && ! BkuTutupBulan::terkunciBulan($sid, $bulan)) {
            $stage = Stage::where('tahun', $tahun)->where('is_open', true)->orderBy('urutan')->first();
            $bos = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
            $rekBunga = Rekening::firstOrCreate(
                ['kode' => '8.1.03.01.01.0001'],
                ['nama' => 'Pendapatan Jasa Giro / Bunga Bank BOSP', 'tipe' => 'pendapatan']
            );
            $trxBunga = KasTransaction::create([
                'tahun' => $tahun,
                'type' => 'pendapatan',
                'no_bku' => KasService::nextNoBku($sid, $tglAkhirBulan),
                'tanggal' => $tglAkhirBulan,
                'bos_type_id' => $bos->id,
                'stage_id' => $stage?->id,
                'metode_bayar' => 'transfer',
                'keterangan' => 'Bunga Bank / Jasa Giro '.$nama,
                'total' => $bunga,
                'created_by' => auth()->id(),
            ]);
            $trxBunga->details()->create([
                'rekening_id' => $rekBunga->id,
                'uraian' => $trxBunga->keterangan,
                'nilai' => $bunga,
            ]);
        }

        if ($pajakBunga > 0 && ! BkuTutupBulan::terkunciBulan($sid, $bulan)) {
            $stage = Stage::where('tahun', $tahun)->where('is_open', true)->orderBy('urutan')->first();
            $bos = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
            $rekAdmin = Rekening::firstOrCreate(
                ['kode' => '5.1.02.02.01.0059'],
                ['nama' => 'Pajak Bunga / Biaya Administrasi Rekening Bank BOSP', 'tipe' => 'belanja']
            );
            $trxAdmin = KasTransaction::create([
                'tahun' => $tahun,
                'type' => 'belanja',
                'no_bku' => KasService::nextNoBku($sid, $tglAkhirBulan),
                'tanggal' => $tglAkhirBulan,
                'bos_type_id' => $bos->id,
                'stage_id' => $stage?->id,
                'metode_bayar' => 'transfer',
                'keterangan' => 'Pajak Bunga / Biaya Administrasi Bank '.$nama,
                'total' => $pajakBunga,
                'created_by' => auth()->id(),
            ]);
            $trxAdmin->details()->create([
                'rekening_id' => $rekAdmin->id,
                'uraian' => $trxAdmin->keterangan,
                'nilai' => $pajakBunga,
            ]);
        }

        $belumPajak = DB::table('kas_taxes as p')
            ->join('kas_transactions as t', 'p.kas_transaction_id', '=', 't.id')
            ->where('t.school_id', $sid)
            ->whereRaw('substr(CAST(t.tanggal AS VARCHAR), 1, 7) = ?', [$bulan])
            ->where('p.status', 'belum')
            ->exists();

        BkuTutupBulan::updateOrCreate(
            ['school_id' => $sid, 'tahun' => $tahun, 'bulan' => $bulan],
            [
                'ditutup_oleh' => auth()->user()->name,
                'bunga_bank' => $bunga,
                'pajak_bunga' => $pajakBunga,
                'belum_lapor_pajak' => $belumPajak,
            ]
        );

        $pesan = "BKU bulan {$nama} sudah DITUTUP — seluruh transaksi bulan itu terkunci.";
        $warn = $belumPajak
            ? "Status BKU {$nama}: Belum Lapor Pajak — masih ada pajak bulan ini yang belum disetor NTPN-nya."
            : null;

        return redirect()->route('bku', ['bulan' => $bulan])->with('ok', $pesan)->with('warning', $warn);
    }

    /** 🔓 Buka kembali BKU satu bulan — khusus Kepala Sekolah. */
    public function bukaBulan(Request $r)
    {
        if (auth()->user()->role !== 'kepsek') {
            return back()->with('error', 'Hanya Kepala Sekolah yang dapat membuka kembali bulan BKU.');
        }
        $bulan = $r->validate(['bulan' => 'required|date_format:Y-m'])['bulan'];
        BkuTutupBulan::where('tahun', (int) substr($bulan, 0, 4))->where('bulan', $bulan)->delete();

        $nama = Carbon::parse($bulan.'-01')->translatedFormat('F Y');

        return redirect()->route('bku', ['bulan' => $bulan])->with('ok', "BKU bulan {$nama} dibuka kembali — bulan itu boleh diisi lagi.");
    }
}
