<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\ArkasKode;
use App\Models\BosType;
use App\Models\KasNota;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\Stage;
use App\Services\KasService;
use App\Support\Csv;
use App\Support\SumberDana;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan resmi format ARKAS (mengikuti lembar workbook ARKAS v1.4): RKAS Bulanan,
 * Rincian RKAS per Tahap, Realisasi Anggaran, Realisasi Penerimaan & Belanja,
 * Realisasi Habis Pakai, Realisasi Modal, Daftar Kas Umum, Daftar Nota,
 * Register Penutupan Kas. Baca-saja dari data SiapBOS hasil impor ARKAS.
 */
class LaporanArkasController extends Controller
{
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** Kategori Realisasi Penerimaan & Belanja (format resmi ARKAS). */
    private const KATEGORI = [
        'Belanja Operasional' => '5.1.02.',
        'Belanja Modal Peralatan dan Mesin' => '5.2.02.',
        'Belanja Modal Aset Tetap Lainnya' => '5.2.05.',
    ];

    /** Konteks bersama: sekolah, tahun, bulan terpilih (1-12 & 'Y-mm'). */
    private function ctx(Request $r): array
    {
        $tahun = (int) session('tahun', now()->year);
        $raw = (string) $r->input('bulan', date('n'));
        if (preg_match('/^\d{4}-\d{1,2}$/', $raw)) {
            $bulan = (int) substr($raw, 5);
        } else {
            $bulan = (int) $raw;
        }
        $bulan = $bulan >= 1 && $bulan <= 12 ? $bulan : (int) date('n');

        return [$r->user()->school_id, $tahun, $bulan, sprintf('%d-%02d', $tahun, $bulan)];
    }

    /** Kop laporan: sumber dana + RKAS terakhir (sel D9/D10 lembar ARKAS). */
    private function kop(int $sid, int $tahun): array
    {
        $bos = BosType::query()->whereIn('id',
            Allocation::where('school_id', $sid)->where('tahun', $tahun)->select('bos_type_id'))
            ->orderBy('id')->pluck('nama')->all();
        $rkas = RkasHeader::where('school_id', $sid)->where('tahun', $tahun)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'draft'))
            ->orderByDesc('revisi_ke')->first()
            // Hasil impor ARKAS berstatus draft — tetap dipakai bila belum ada RKAS disahkan.
            ?? RkasHeader::where('school_id', $sid)->where('tahun', $tahun)
                ->orderByDesc('revisi_ke')->first();

        return [
            'sumber' => implode(' + ', $bos) ?: 'BOSP Reguler',
            'tahun' => $tahun,
            'revisiLabel' => ($rkas->revisi_ke ?? 1) > 1 ? ' perubahan revisi ke-'.($rkas->revisi_ke - 1) : '',
            'rkas' => $rkas,
        ];
    }

    /** Label 'kode uraian' dari master kode ARKAS. */
    private static function codeLabel(array $kodeNama, string $kode): string
    {
        if ($kode === '') {
            return '—';
        }

        return trim($kode.' '.($kodeNama[$kode] ?? ''));
    }

    /** Belanja per rekening per bulan: ym, rid, kode, nama, jumlah. */
    private function belanjaRek(int $sid, int $tahun): Collection
    {
        return DB::table('kas_details as d')
            ->join('kas_transactions as t', 't.id', '=', 'd.kas_transaction_id')
            ->join('rekenings as r', 'r.id', '=', 'd.rekening_id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->selectRaw('substr(CAST(t.tanggal AS VARCHAR),1,7) as ym, d.rekening_id as rid, r.kode, r.nama, sum(d.nilai) as j')
            ->groupBy('ym', 'd.rekening_id', 'r.kode', 'r.nama')->get();
    }

    /** Anggaran per rekening dari RKAS terakhir (non-draft). */
    private function anggaranRekening(?RkasHeader $rkas): Collection
    {
        return $rkas
            ? $rkas->details()->selectRaw('rekening_id, sum(jumlah) j')->groupBy('rekening_id')->pluck('j', 'rekening_id')
            : collect();
    }

    // ==================== 1. RKAS Bulanan ====================

    /**
     * Realisasi per sumber dana — pagu, rencana RKAS, penerimaan, belanja,
     * dan sisa tiap sumber dana (BOS Reguler, Kinerja, BOSP Daerah, Afirmasi,
     * SILPA, Lainnya).
     */
    public function realisasiSumber(Request $r)
    {
        $sid = $r->user()->school_id;
        $tahun = (int) session('tahun', now()->year);

        $pagu = [];
        foreach (Allocation::with('bosType')->where('school_id', $sid)->where('tahun', $tahun)->get() as $a) {
            $nama = $a->bosType?->nama ?? 'Tanpa Jenis BOS';
            $pagu[$nama] = ($pagu[$nama] ?? 0) + (int) $a->nilai;
        }

        $rencana = [];
        foreach (DB::table('rkas_details as rd')
            ->join('rkas_headers as rh', 'rd.rkas_header_id', '=', 'rh.id')
            ->where('rh.school_id', $sid)->where('rh.tahun', $tahun)
            ->whereIn('rh.status', ['disetujui', 'dikirim_dinas', 'disahkan', 'dikunci'])
            ->select('rd.sumber_dana', DB::raw('SUM(rd.jumlah) j'), DB::raw('COUNT(*) n'))
            ->groupBy('rd.sumber_dana')->get() as $x) {
            $label = SumberDana::label($x->sumber_dana);
            $rencana[$label] = ($rencana[$label] ?? 0) + (int) $x->j;
        }

        $terima = [];
        $belanja = [];
        foreach (KasTransaction::with('bosType')->where('school_id', $sid)->where('tahun', $tahun)->get() as $t) {
            $nama = $t->bosType?->nama ?? 'Tanpa Jenis BOS';
            if ($t->type === 'pendapatan') {
                $terima[$nama] = ($terima[$nama] ?? 0) + (int) $t->total;
            } elseif ($t->type === 'belanja') {
                $belanja[$nama] = ($belanja[$nama] ?? 0) + (int) $t->total;
            }
        }

        $nama = array_values(array_unique(array_merge(array_keys($pagu), array_keys($rencana), array_keys($terima), array_keys($belanja))));
        $baris = [];
        foreach ($nama as $n) {
            $p = (int) ($pagu[$n] ?? 0);
            $b = (int) ($belanja[$n] ?? 0);
            $baris[] = [
                'sumber' => $n,
                'pagu' => $p,
                'rencana' => (int) ($rencana[$n] ?? 0),
                'penerimaan' => (int) ($terima[$n] ?? 0),
                'belanja' => $b,
                'sisa' => $p - $b,
                'serapan' => $p > 0 ? round($b * 100 / $p, 1) : null,
            ];
        }
        usort($baris, fn ($a, $b) => $b['pagu'] <=> $a['pagu']);

        $total = [
            'pagu' => array_sum(array_column($baris, 'pagu')),
            'rencana' => array_sum(array_column($baris, 'rencana')),
            'penerimaan' => array_sum(array_column($baris, 'penerimaan')),
            'belanja' => array_sum(array_column($baris, 'belanja')),
        ];
        $total['sisa'] = $total['pagu'] - $total['belanja'];
        $total['serapan'] = $total['pagu'] > 0 ? round($total['belanja'] * 100 / $total['pagu'], 1) : null;

        return view('laporan-arkas.realisasi-sumber', [
            'baris' => $baris, 'total' => $total, 'tahun' => $tahun,
        ]);
    }

    public function rkasBulanan(Request $r)
    {
        [$sid, $tahun, $bulan, $bulanStr] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);
        $kodeNama = ArkasKode::peta($sid, $tahun);

        // Belanja satu bulan per (rekening, kode kegiatan).
        $it = DB::table('kas_details as d')
            ->join('kas_transactions as t', 't.id', '=', 'd.kas_transaction_id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->whereRaw('substr(CAST(t.tanggal AS VARCHAR),1,7) = ?', [$bulanStr])
            ->selectRaw("concat(d.rekening_id,'|',coalesce(d.kode_kegiatan,'')) as k, sum(d.nilai) as j")
            ->groupBy('d.rekening_id', 'd.kode_kegiatan')->pluck('j', 'k');

        $rows = [];
        $no = 1;
        foreach ($kop['rkas']?->details()->with('rekening')->orderBy('id')->get() ?? [] as $d) {
            $keg = (string) ($d->kode_kegiatan ?? '');
            $b = (int) ($it[$d->rekening_id.'|'.$keg] ?? 0);
            if ($keg === '') {
                $b += (int) ($it[$d->rekening_id.'|'] ?? 0);
            }
            // Program = kode level-1 (dua digit pertama, mis. '03.').
            $kodeL1 = $keg !== '' ? substr($keg, 0, max(1, strlen($keg) > 1 ? 3 : 2)) : '';
            $rows[] = [
                'no' => $no++, 'program' => self::codeLabel($kodeNama, $kodeL1),
                'kegiatan' => $keg !== '' ? ($keg.' '.($kodeNama[$keg] ?? '')) : '—',
                'rek' => $d->rekening->kode ?? '—', 'rekNama' => $d->rekening->nama ?? '',
                'uraian' => $d->uraian, 'anggaran' => (int) $d->jumlah, 'belanja' => $b,
                'satuan' => $d->satuan, 'harga' => (int) $d->harga_satuan, 'total' => (int) $d->jumlah,
                'status' => $b > 0 ? 'Sudah' : 'Belum',
            ];
        }

        return view('laporan-arkas.rkas-bulanan', ['rows' => $rows, 'kop' => $kop, 'bulan' => $bulan,
            'namaBulan' => self::BULAN]);
    }

    // ==================== 2. Rincian RKAS per Tahap ====================

    public function rkasTahap(Request $r)
    {
        [$sid, $tahun] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);
        $kodeNama = ArkasKode::peta($sid, $tahun);
        $tahaps = Stage::where('tahun', $tahun)->orderBy('urutan')->get();
        $nTahap = max(5, $tahaps->count());

        $rows = [];
        $totalTahap = array_fill(1, $nTahap, 0);
        $no = 1;
        foreach ($kop['rkas']?->details()->with('rekening')->orderBy('id')->get() ?? [] as $d) {
            $keg = (string) ($d->kode_kegiatan ?? '');
            $kodeL1 = $keg !== '' ? substr($keg, 0, max(1, strlen($keg) > 1 ? 3 : 2)) : '';
            $vol = json_decode((string) ($d->periode_volume ?? '[]'), true);
            $vol = is_array($vol) ? array_map('floatval', $vol) : [];
            $totVol = array_sum(array_values($vol));
            $tahapNilai = [];
            for ($i = 1; $i <= $nTahap; $i++) {
                if ($totVol > 0 && (float) ($vol[$i] ?? 0) > 0) {
                    $t = (int) round((int) $d->jumlah * (float) $vol[$i] / $totVol);
                } else {
                    $t = (int) floor((int) $d->jumlah / max(1, $nTahap));
                }
                $tahapNilai[$i] = $t;
                $totalTahap[$i] = ($totalTahap[$i] ?? 0) + $t;
            }
            $rows[] = [
                'no' => $no++, 'rek' => $d->rekening->kode ?? '—',
                'program' => self::codeLabel($kodeNama, $kodeL1), 'uraian' => $d->uraian,
                'volume' => $d->banyak, 'satuan' => $d->satuan, 'tarif' => (int) $d->harga_satuan,
                'jumlah' => (int) $d->jumlah, 'tahap' => $tahapNilai,
            ];
        }
        $jumlahTahap = array_sum($totalTahap);

        return view('laporan-arkas.rkas-tahap', ['rows' => $rows, 'kop' => $kop, 'tahaps' => $tahaps,
            'nTahap' => $nTahap, 'totalTahap' => $totalTahap, 'jumlahTahap' => $jumlahTahap]);
    }

    // ==================== 2b. Kertas Kerja RAPBS per Tahap ====================

    /** Angka Romawi untuk label tahap/triwulan (ARKAS memakai I, II, III, IV). */
    private static function romawi(int $n): string
    {
        return ['I', 'II', 'III', 'IV'][$n - 1] ?? (string) $n;
    }

    /**
     * Kertas Kerja RAPBS — tiga varian cetak ARKAS 4.3.1:
     *
     *  - per Tahap (baku): "KERTAS KERJA … (RKAS) PER TAHAP", kolom Tahap 1..n,
     *    tanpa tanda tangan (sama seperti rapbs-tahap-output.pdf).
     *  - per Triwulan (?format=triwulan): judul "… PER TRIWULAN", kolom I–IV
     *    (rapbs-tri-output.pdf).
     *  - per Bulan (?format=bulan): "RINCIAN KERTAS KERJA PERBULAN", satu kolom
     *    Jumlah berisi rencana bulan terpilih, ditutup tanda tangan 3 kolom
     *    (Komite Sekolah / Kepala Sekolah / Bendahara Sekolah).
     *
     * Angka periode memakai RkasDetail::nominalPerBulan() untuk triwulan/bulan,
     * dan proporsi `periode_volume` untuk tahap (aturan layar Rincian RKAS).
     */
    public function rapbsCetak(Request $r)
    {
        [$sid, $tahun, $bulan] = $this->ctx($r);
        $format = in_array($r->query('format'), ['bulan', 'triwulan'], true) ? (string) $r->query('format') : 'tahap';
        $kop = $this->kop($sid, $tahun);
        $kodeNama = ArkasKode::peta($sid, $tahun);
        $tahaps = Stage::where('tahun', $tahun)->orderBy('urutan')->get();
        $nTahap = max(2, $tahaps->count());
        $nPeriode = $format === 'tahap' ? $nTahap : ($format === 'triwulan' ? 4 : 0);

        // A. Penerimaan per sumber dana (alokasi resmi tahun anggaran).
        $kodeRef = collect(RkasHeader::SUMBER_DANA_REF_ARKAS)->pluck('kode', 'nama');
        $penerimaan = [];
        foreach (Allocation::where('school_id', $sid)->where('tahun', $tahun)->with('bosType')->get() as $a) {
            $nama = (string) ($a->bosType->nama ?? 'BOSP Reguler');
            $penerimaan[$nama] = ($penerimaan[$nama] ?? 0) + (int) $a->nilai;
        }
        $penerimaan = collect($penerimaan)
            ->map(fn ($nilai, $nama) => ['kode' => (string) ($kodeRef[$nama] ?? ''), 'nama' => $nama, 'nilai' => (int) $nilai])
            ->sortBy('kode')->values()->all();

        // B. Belanja: pohon program/kegiatan/sub-kegiatan + baris rincian.
        $baris = [];
        $ringkas = [];
        $total = 0;
        $totalPeriode = array_fill(1, max(1, $nPeriode), 0);
        $totalBulan = 0;

        foreach ($kop['rkas']?->details()->with('rekening')->orderBy('kode_kegiatan')->orderBy('id')->get() ?? [] as $d) {
            $keg = preg_replace('/\s+/', '', (string) $d->kode_kegiatan);
            $seg = $keg === '' ? [] : array_values(array_filter(explode('.', trim($keg, '.'))));
            $jumlah = (int) $d->jumlah;
            $bulanan = $d->nominalPerBulan();
            $nilaiBulan = (int) ($bulanan[$bulan] ?? 0);

            // Kolom periode: tahap (proporsi periode_volume) atau triwulan (3 bulan).
            $periodeNilai = [];
            if ($format === 'tahap') {
                $vol = json_decode((string) ($d->periode_volume ?? '[]'), true);
                $vol = is_array($vol) ? array_map('floatval', $vol) : [];
                $totVol = array_sum($vol);
                for ($i = 1; $i <= $nTahap; $i++) {
                    $periodeNilai[$i] = ($totVol > 0 && (float) ($vol[$i] ?? 0) > 0)
                        ? (int) round($jumlah * (float) $vol[$i] / $totVol)
                        : (int) floor($jumlah / max(1, $nTahap));
                }
            } elseif ($format === 'triwulan') {
                for ($t = 1; $t <= 4; $t++) {
                    $nilai = 0;
                    for ($m = ($t - 1) * 3 + 1; $m <= $t * 3; $m++) {
                        $nilai += (int) ($bulanan[$m] ?? 0);
                    }
                    $periodeNilai[$t] = $nilai;
                }
            }

            $prefix = '';
            foreach ($seg as $i => $bagian) {
                $prefix = $prefix === '' ? $bagian : $prefix.'.'.$bagian;
                $tipe = ['program', 'kegiatan', 'sub'][$i] ?? 'sub';
                if (! isset($ringkas[$prefix])) {
                    $ringkas[$prefix] = count($baris);
                    $baris[] = ['tipe' => $tipe, 'kode' => $prefix.'.',
                        'uraian' => (string) ($kodeNama[$prefix.'.'] ?? ($kodeNama[$prefix] ?? '')),
                        'rek' => '', 'volume' => null, 'satuan' => '', 'tarif' => null,
                        'jumlah' => 0, 'bulan' => 0, 'periode' => array_fill(1, max(1, $nPeriode), 0)];
                }
                $baris[$ringkas[$prefix]]['jumlah'] += $jumlah;
                $baris[$ringkas[$prefix]]['bulan'] += $nilaiBulan;
                foreach ($periodeNilai as $i2 => $v) {
                    $baris[$ringkas[$prefix]]['periode'][$i2] += $v;
                }
            }

            $baris[] = ['tipe' => 'rincian', 'kode' => '', 'uraian' => (string) $d->uraian,
                'rek' => (string) ($d->rekening->kode ?? '—'), 'volume' => $d->banyak,
                'satuan' => (string) $d->satuan, 'tarif' => (int) $d->harga_satuan,
                'jumlah' => $jumlah, 'bulan' => $nilaiBulan, 'periode' => $periodeNilai];

            $total += $jumlah;
            $totalBulan += $nilaiBulan;
            foreach ($periodeNilai as $i2 => $v) {
                $totalPeriode[$i2] += $v;
            }
        }

        $namaBulanTeks = self::BULAN[$bulan];
        $romawiTahap = [];
        foreach ($tahaps->values() as $i => $t) {
            $romawiTahap[] = self::romawi((int) ($t->urutan ?: $i + 1));
        }
        if ($romawiTahap === []) {
            $romawiTahap = ['I', 'II'];
        }
        $labelPeriode = match ($format) {
            'bulan' => $namaBulanTeks.' '.$tahun,
            'triwulan' => self::daftarRomawi(4),
            default => self::daftarRomawi(count($romawiTahap), $romawiTahap),
        };

        $sekolah = $r->user()->school;

        return view('laporan-arkas.rapbs-cetak', [
            'format' => $format, 'baris' => $baris, 'penerimaan' => $penerimaan,
            'totalPenerimaan' => array_sum(array_column($penerimaan, 'nilai')),
            'total' => $total, 'totalPeriode' => $totalPeriode, 'totalBulan' => $totalBulan,
            'nTahap' => $nTahap, 'nPeriode' => $nPeriode,
            'kop' => $kop, 'sekolah' => $sekolah, 'tahun' => $tahun, 'bulan' => $bulan,
            'namaBulan' => self::BULAN, 'labelBulan' => $namaBulanTeks,
            'labelKopPeriode' => ['bulan' => 'Bulan', 'triwulan' => 'Triwulan'][$format] ?? 'Tahap',
            'labelPeriode' => $labelPeriode,
            'penandatangan' => Penandatangan::orderBy('id')->get(),
            'tempatTtd' => trim((string) ($sekolah->kecamatan ?? '')) !== ''
                ? 'Kec. '.$sekolah->kecamatan : (string) ($sekolah->kab_kota ?? ''),
            'tanggalTtd' => now()->translatedFormat('d F Y'),
        ]);
    }

    /** Label daftar angka Romawi ala ARKAS: "I,II,III dan IV" atau "I dan II". */
    private static function daftarRomawi(int $n, array $romawi = []): string
    {
        $romawi = $romawi !== [] ? $romawi : array_map(fn ($i) => self::romawi($i), range(1, $n));
        if (count($romawi) === 1) {
            return $romawi[0];
        }

        return implode(',', array_slice($romawi, 0, -1)).' dan '.end($romawi);
    }

    // ==================== 2c. RKA / DPA Unit Kerja ====================

    /**
     * Nama akun standar BOSP yang dipakai lembar RKA/DPA Unit Kerja ARKAS.
     * Diambil apa adanya dari rka-unit-output.pdf & rka-unit-tahap-output.pdf
     * (termasuk salah ketik "Rincian Anggran" yang memang begitu di ARKAS).
     * ARKAS hanya mencetak sampai 4 segmen kode rekening, tidak sampai rincian.
     */
    private const AKUN_RKA = [
        '5' => 'BELANJA',
        '5.1' => 'BELANJA OPERASI',
        '5.1.02' => 'BELANJA BARANG DAN JASA',
        '5.1.02.01' => 'BELANJA BARANG',
        '5.1.02.02' => 'BELANJA JASA',
        '5.1.02.03' => 'BELANJA PEMELIHARAAN',
        '5.1.02.04' => 'BELANJA PERJALANAN DINAS',
        '5.2' => 'BELANJA MODAL',
        '5.2.02' => 'BELANJA MODAL PERALATAN DAN MESIN',
        '5.2.05' => 'BELANJA MODAL ASET TETAP LAINNYA',
    ];

    /**
     * Lembar RKA Unit Kerja / DPA (RKAS) Unit Kerja — dua sumbu pilihan:
     *
     *  - `varian=rka` (baku): judul "LEMBAR KERTAS KERJA / UNIT KERJA".
     *  - `varian=dpa`: judul "LEMBAR RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)
     *    / UNIT KERJA" — isi tabel sama seperti rka-unit-tahap-output.pdf.
     *  - `format=triwulan` (baku): bagian kedua "Rencana Pelaksanaan Anggaran /
     *    Unit Kerja per Triwulan" (kolom I–IV).
     *  - `format=tahap`: bagian kedua "… per Tahap" (kolom Tahap I, II, …).
     *
     * Angka: bagian pertama = subtotal per akun (prefix kode rekening sampai 4
     * segmen, aturan yang sama dengan ARKAS). Bagian kedua = rencana per periode
     * dari RkasDetail::nominalPerBulan() (triwulan = 3 bulan, tahap = 6 bulan),
     * sedangkan pendapatan mengikuti tahap pencairan pada Allocation.
     */
    public function rkaUnit(Request $r)
    {
        [$sid, $tahun] = $this->ctx($r);
        $format = $r->query('format') === 'tahap' ? 'tahap' : 'triwulan';
        $varian = in_array($r->query('varian'), ['dpa', 'rka22', 'dpa22'], true)
            ? (string) $r->query('varian') : 'rka';
        $kop = $this->kop($sid, $tahun);
        $sekolah = $r->user()->school;
        $tahaps = Stage::where('tahun', $tahun)->orderBy('urutan')->get();

        $details = $kop['rkas']?->details()->with('rekening')->get() ?? collect();

        // Varian 2.2: "Rincian Anggaran Belanja Langsung Berdasarkan Program dan
        // Kegiatan" — tabel yang sama sekali berbeda (lihat rka-unit-22-output.pdf).
        if (in_array($varian, ['rka22', 'dpa22'], true)) {
            return $this->rkaUnit22($r, $varian, $kop, $sekolah, $tahun, $details);
        }

        // Bagian 1 — subtotal per akun sampai 4 segmen kode rekening.
        $nilaiAkun = array_fill_keys(array_keys(self::AKUN_RKA), 0);
        foreach ($details as $d) {
            $seg = explode('.', trim((string) ($d->rekening->kode ?? '')));
            for ($n = 1; $n <= 4; $n++) {
                if (count($seg) < $n) {
                    break;
                }
                $prefix = implode('.', array_slice($seg, 0, $n));
                if (array_key_exists($prefix, $nilaiAkun)) {
                    $nilaiAkun[$prefix] += (int) $d->jumlah;
                }
            }
        }
        $totalBelanja = (int) $details->sum('jumlah');

        $penerimaan = (int) Allocation::where('school_id', $sid)->where('tahun', $tahun)->sum('nilai');

        // Bagian 2 — rencana per periode.
        $nPeriode = $format === 'tahap' ? max(1, min(4, $tahaps->count() ?: 2)) : 4;
        $panjangPeriode = $format === 'tahap' ? intdiv(12, $nPeriode) : 3;

        $operasi = array_fill(1, $nPeriode, 0);
        $modal = array_fill(1, $nPeriode, 0);
        foreach ($details as $d) {
            $kode = trim((string) ($d->rekening->kode ?? ''));
            if (! str_starts_with($kode, '5.1') && ! str_starts_with($kode, '5.2')) {
                continue;
            }
            $bulanan = $d->nominalPerBulan();
            for ($p = 1; $p <= $nPeriode; $p++) {
                $nilai = 0;
                for ($m = ($p - 1) * $panjangPeriode + 1; $m <= $p * $panjangPeriode; $m++) {
                    $nilai += (int) ($bulanan[$m] ?? 0);
                }
                if (str_starts_with($kode, '5.2')) {
                    $modal[$p] += $nilai;
                } else {
                    $operasi[$p] += $nilai;
                }
            }
        }

        // Pendapatan: ikut tahap pencairan (Tahap 1 → triwulan I, Tahap 2 →
        // triwulan III). Alokasi tanpa tahap dibagi rata ke semua periode.
        $pendapatan = array_fill(1, $nPeriode, 0);
        foreach (Allocation::where('school_id', $sid)->where('tahun', $tahun)->with('stage')->get() as $a) {
            $nilai = (int) $a->nilai;
            $urutan = (int) ($a->stage->urutan ?? 0);
            if ($urutan >= 1) {
                $p = $format === 'tahap' ? min($nPeriode, $urutan) : min($nPeriode, ($urutan - 1) * 2 + 1);
                $pendapatan[$p] += $nilai;

                continue;
            }
            $bagi = intdiv($nilai, $nPeriode);
            for ($p = 1; $p <= $nPeriode; $p++) {
                $pendapatan[$p] += $p === $nPeriode ? $nilai - $bagi * ($nPeriode - 1) : $bagi;
            }
        }

        $barisPeriode = [
            ['no' => '1', 'uraian' => 'Pendapatan', 'nilai' => $pendapatan, 'total' => array_sum($pendapatan), 'tebal' => true],
            ['no' => '2.1', 'uraian' => 'Belanja Operasi', 'nilai' => $operasi, 'total' => array_sum($operasi), 'tebal' => true],
            ['no' => '2.2', 'uraian' => 'Belanja Modal', 'nilai' => $modal, 'total' => array_sum($modal), 'tebal' => true],
            ['no' => '3.1', 'uraian' => 'Penerimaan Pembiayaan', 'nilai' => array_fill(1, $nPeriode, 0), 'total' => 0, 'tebal' => false],
            ['no' => '4.1', 'uraian' => 'Pengeluaran Pembiayaan', 'nilai' => array_fill(1, $nPeriode, 0), 'total' => 0, 'tebal' => false],
        ];

        $kepsek = Penandatangan::where('jabatan', 'like', '%kepala%')->orderBy('id')->first()
            ?? Penandatangan::orderBy('id')->first();

        return view('laporan-arkas.rka-unit-cetak', [
            'varian' => $varian, 'format' => $format,
            'sekolah' => $sekolah, 'tahun' => $tahun, 'kop' => $kop,
            'akun' => self::AKUN_RKA, 'nilaiAkun' => $nilaiAkun,
            'penerimaan' => $penerimaan, 'totalBelanja' => $totalBelanja,
            'defisit' => $penerimaan - $totalBelanja,
            'barisPeriode' => $barisPeriode, 'nPeriode' => $nPeriode,
            'labelPeriode' => $format === 'tahap' ? 'Tahap' : 'Triwulan',
            'tahaps' => $tahaps, 'kepsek' => $kepsek,
            'tempatTtd' => trim((string) ($sekolah->kecamatan ?? '')) !== ''
                ? 'Kec. '.$sekolah->kecamatan : (string) ($sekolah->kab_kota ?? ''),
            'tanggalTtd' => now()->translatedFormat('d F Y'),
        ]);
    }

    /**
     * RKA/DPA Unit Kerja 2.2 — "Rincian Anggaran Belanja Langsung Berdasarkan
     * Program dan Kegiatan" (rka-unit-22-output.pdf, dpa-unit-22-output.pdf).
     *
     * Tiga tingkat baris seperti ARKAS: program (`03.`), sub program (`03.01.`),
     * lalu rincian dengan uraian = nama kegiatan (`03.01.01.`) beserta kolom
     * `Lokasi Kegiatan`, `Target Kinerja (Kuantitatif)`, `Barang & Jasa`, `Modal`,
     * dan `Jumlah (9 = 7 + 8)`.
     *
     * `lokasi_kegiatan`/`target_kinerja` di rincian bersifat opsional; bila kosong
     * dipakai nilai yang sama dengan sampel ARKAS: lokasi = nama sekolah,
     * target = volume + satuan rincian.
     */
    private function rkaUnit22(Request $r, string $varian, array $kop, $sekolah, int $tahun, $details)
    {
        $kodeNama = ArkasKode::peta((int) $sekolah->id, $tahun);
        $lokasiDefault = (string) $sekolah->nama;

        $baris = [];
        $ringkas = [];
        $total = 0;
        $totalBarangJasa = 0;
        $totalModal = 0;

        foreach ($details->sortBy('kode_kegiatan') as $d) {
            $keg = preg_replace('/\s+/', '', (string) $d->kode_kegiatan);
            $seg = $keg === '' ? [] : array_values(array_filter(explode('.', trim($keg, '.'))));
            $jumlah = (int) $d->jumlah;
            $kodeRek = trim((string) ($d->rekening->kode ?? ''));
            $modal = str_starts_with($kodeRek, '5.2');
            $barangJasa = $modal ? 0 : $jumlah;

            // Baris ringkasan program (1 segmen) dan sub program (2 segmen).
            foreach ([1, 2] as $level) {
                if (count($seg) < $level) {
                    break;
                }
                $prefix = implode('.', array_slice($seg, 0, $level));
                if (! isset($ringkas[$prefix])) {
                    $ringkas[$prefix] = count($baris);
                    $baris[] = ['tipe' => $level === 1 ? 'program' : 'subprogram',
                        'kode' => $prefix.'.',
                        'uraian' => (string) ($kodeNama[$prefix.'.'] ?? ''),
                        'rekening' => '', 'kegiatan' => '', 'lokasi' => '', 'target' => '',
                        'barang_jasa' => 0, 'modal' => 0, 'jumlah' => 0];
                }
                $baris[$ringkas[$prefix]]['barang_jasa'] += $barangJasa;
                $baris[$ringkas[$prefix]]['modal'] += $modal ? $jumlah : 0;
                $baris[$ringkas[$prefix]]['jumlah'] += $jumlah;
            }

            $baris[] = ['tipe' => 'rincian',
                'kode' => $keg !== '' ? $keg : '',
                'uraian' => (string) ($kodeNama[$keg] ?? ($kodeNama[trim($keg, '.').'.'] ?? '')),
                'rekening' => $kodeRek,
                'program' => $seg[0] ?? '',
                'subprogram' => $seg[1] ?? '',
                'kegiatan' => $seg[2] ?? '',
                'lokasi' => trim((string) ($d->lokasi_kegiatan ?? '')) !== ''
                    ? (string) $d->lokasi_kegiatan : $lokasiDefault,
                'target' => trim((string) ($d->target_kinerja ?? '')) !== ''
                    ? (string) $d->target_kinerja
                    : trim($d->banyak.' '.(string) $d->satuan),
                'barang_jasa' => $barangJasa, 'modal' => $modal ? $jumlah : 0, 'jumlah' => $jumlah];

            $total += $jumlah;
            $totalBarangJasa += $barangJasa;
            $totalModal += $modal ? $jumlah : 0;
        }

        return view('laporan-arkas.rka-unit-22-cetak', [
            'varian' => $varian, 'kop' => $kop, 'sekolah' => $sekolah, 'tahun' => $tahun,
            'baris' => $baris, 'total' => $total, 'totalBarangJasa' => $totalBarangJasa,
            'totalModal' => $totalModal,
            'judulVarian' => $varian === 'dpa22'
                ? 'LEMBAR RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)'
                : 'LEMBAR KERTAS KERJA',
            // Dua dokumen ARKAS memakai judul tabel berbeda untuk isi yang sama.
            'subJudul' => $varian === 'dpa22'
                ? 'Rekapitulasi Belanja Langsung Berdasarkan Program dan Kegiatan'
                : 'Rincian Anggaran Belanja Langsung Berdasarkan Program dan Kegiatan',
            'pemerintah' => trim((string) ($sekolah->kab_kota ?? '')) !== ''
                ? 'PEMERINTAH '.strtoupper((string) $sekolah->kab_kota)
                : 'PEMERINTAH '.strtoupper((string) ($sekolah->provinsi ?? '')),
            'kepsek' => Penandatangan::where('jabatan', 'like', '%kepala%')->orderBy('id')->first()
                ?? Penandatangan::orderBy('id')->first(),
            'tempatTtd' => trim((string) ($sekolah->kecamatan ?? '')) !== ''
                ? 'Kec. '.$sekolah->kecamatan : (string) ($sekolah->kab_kota ?? ''),
            'tanggalTtd' => now()->translatedFormat('d F Y'),
        ]);
    }

    /**
     * APBS / RKAS seluruh sumber dana (apbs-all-output.pdf, 14 halaman).
     *
     * Bentuk lembar ARKAS:
     *  - Kop `RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)`, `TAHUN ANGGARAN : …`,
     *    identitas Nama Sekolah / Alamat / Kabupaten / Provinsi / NPSN.
     *  - `A. PENERIMAAN`: kolom No. / Kode Penerimaan / Jumlah per sumber dana
     *    (kode `4.3.1.xx.` dari ref_sumber_dana) + baris `Total Penerimaan`.
     *  - `B. BELANJA`: lima tingkat baris — program (`03.`), sub program (`03.01.`),
     *    kegiatan (`03.01.01.`), kegiatan+rekening (`…5.1.02.01.01.0052`), dan
     *    rincian (`…   001. <uraian>`). Tiap baris membawa satu kolom Jumlah dan
     *    dua belas kolom `Sumber Dana dan Alokasi Anggaran` = 6 kelompok dana
     *    (BOSP REGULER · BOSP DAERAH · AFIRMASI · SiLPA · BOSP LAINNYA · KINERJA)
     *    x (Belanja Operasi · Belanja Modal).
     *  - Penutup: baris `Jumlah` + tanda tangan Kepala Sekolah / Komite Sekolah /
     *    Bendahara Sekolah.
     */
    public function apbs(Request $r)
    {
        [$sid, $tahun] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);
        $kodeNama = ArkasKode::peta($sid, $tahun);
        $sekolah = $r->user()->school;

        // A. PENERIMAAN per sumber dana (alokasi resmi tahun anggaran).
        $kodeRef = collect(RkasHeader::SUMBER_DANA_REF_ARKAS)->pluck('kode', 'nama');
        $penerimaan = [];
        foreach (Allocation::where('school_id', $sid)->where('tahun', $tahun)->with('bosType')->get() as $a) {
            $nama = (string) ($a->bosType->nama ?? 'BOSP Reguler');
            $penerimaan[$nama] = ($penerimaan[$nama] ?? 0) + (int) $a->nilai;
        }
        $penerimaan = collect($penerimaan)
            ->map(fn ($nilai, $nama) => [
                'kode' => (string) ($kodeRef[$nama] ?? ''),
                // ARKAS mencetak "BOSP Reguler" walau ref_sumber_dana menulis "BOS Reguler".
                'nama' => preg_replace('/^BOS /', 'BOSP ', $nama),
                'nilai' => (int) $nilai,
            ])
            ->sortBy('kode')->values()->all();

        // B. BELANJA — enam kelompok dana, urut sama dengan kepala kolom ARKAS.
        $kelompok = [
            'reguler' => 'BOSP REGULER', 'bosda' => 'BOSP DAERAH', 'afirmasi' => 'AFIRMASI',
            'silpa' => 'SiLPA', 'lainnya' => 'BOSP LAINNYA', 'kinerja' => 'KINERJA',
        ];
        $danaKosong = fn () => array_map(
            fn () => ['operasi' => 0, 'modal' => 0], $kelompok
        );

        $baris = [];
        $indeks = [];
        $nomorRincian = [];
        $pastikan = function (string $kunci, string $keg, string $rek, string $uraian, int $level) use (&$baris, &$indeks, $danaKosong) {
            if (! isset($indeks[$kunci])) {
                $indeks[$kunci] = count($baris);
                $baris[] = ['keg' => $keg, 'rek' => $rek, 'uraian' => $uraian,
                    'level' => $level, 'jumlah' => 0, 'dana' => $danaKosong()];
            }

            return $indeks[$kunci];
        };

        $details = $kop['rkas']?->details()->with('rekening')
            ->orderBy('kode_kegiatan')->orderBy('id')->get() ?? collect();

        foreach ($details as $d) {
            $keg = trim(preg_replace('/\s+/', '', (string) $d->kode_kegiatan), '.');
            $seg = $keg === '' ? [] : explode('.', $keg);
            $rek = trim((string) ($d->rekening->kode ?? ''));
            $jumlah = (int) $d->jumlah;
            $grup = SumberDana::kunci((string) $d->sumber_dana);
            $grup = isset($kelompok[$grup]) ? $grup : 'lainnya';
            $modal = str_starts_with($rek, '5.2');

            // Level 1–4: ringkasan program, sub program, kegiatan, kegiatan+rekening.
            $kegLabel = $seg === [] ? '' : implode('.', $seg).'.';
            $kunciList = [];
            for ($lv = 1; $lv <= 3; $lv++) {
                if (count($seg) < $lv) {
                    break;
                }
                $prefix = implode('.', array_slice($seg, 0, $lv));
                $kunciList[] = $pastikan('L'.$lv.'|'.$prefix, $prefix.'.', '',
                    (string) ($kodeNama[$prefix.'.'] ?? ''), $lv);
            }
            $kunciRek = $kegLabel.'|'.$rek;
            $kunciList[] = $pastikan('L4|'.$kunciRek, $kegLabel, $rek,
                (string) ($d->rekening->nama ?? ''), 4);

            // Level 5: baris rincian dengan nomor urut `001.` per blok rekening.
            $nomorRincian[$kunciRek] = ($nomorRincian[$kunciRek] ?? 0) + 1;
            $kunciList[] = $pastikan('L5|'.$kunciRek.'|'.$d->id, $kegLabel, $rek,
                sprintf('%03d. ', $nomorRincian[$kunciRek]).(string) $d->uraian, 5);

            foreach ($kunciList as $i) {
                $baris[$i]['jumlah'] += $jumlah;
                $baris[$i]['dana'][$grup][$modal ? 'modal' : 'operasi'] += $jumlah;
            }
        }

        // Total diambil dari baris rincian (level 5) supaya rincian tanpa kode kegiatan
        // tetap terhitung dan tidak ada angka yang dobel.
        $total = 0;
        $totalDana = $danaKosong();
        foreach ($baris as $b) {
            if ($b['level'] !== 5) {
                continue;
            }
            $total += $b['jumlah'];
            foreach ($kelompok as $g => $label) {
                $totalDana[$g]['operasi'] += $b['dana'][$g]['operasi'];
                $totalDana[$g]['modal'] += $b['dana'][$g]['modal'];
            }
        }

        return view('laporan-arkas.apbs-cetak', [
            'kop' => $kop, 'sekolah' => $sekolah, 'tahun' => $tahun,
            'penerimaan' => $penerimaan,
            'totalPenerimaan' => array_sum(array_column($penerimaan, 'nilai')),
            'kelompok' => $kelompok, 'baris' => $baris,
            'total' => $total, 'totalDana' => $totalDana,
            'penandatangan' => Penandatangan::orderBy('id')->get(),
            'tempatTtd' => trim((string) ($sekolah->kecamatan ?? '')) !== ''
                ? 'Kec. '.$sekolah->kecamatan : (string) ($sekolah->kab_kota ?? ''),
            'tanggalTtd' => now()->translatedFormat('d F Y'),
        ]);
    }

    // ==================== 2d. RPD K7 (Rekapitulasi Realisasi Penggunaan Dana) ====================

    /**
     * Dua belas kolom SUB PROGRAM lembar RPD K7, urut seperti rpd-k7-output.pdf
     * (ARKAS 4.3.1). Nomor kolom = segmen ke-2 kode kegiatan ARKAS.
     */
    private const SUB_PROGRAM_RPD = [
        1 => 'Pembiayaan Penerimaan Peserta Didik Baru',
        2 => 'Pembiayaan Pengembangan Perpustakaan',
        3 => 'Pembiayaan Kegiatan Pembelajaran dan Ekstrakurikuler',
        4 => 'Kegiatan Pembiayaan Asesmen/Evaluasi Pembelajaran dan Ekstrakurikuler',
        5 => 'Pembiayaan Administrasi Kegiatan Sekolah',
        6 => 'Pembiayaan Pengembangan Profesi Guru dan Tenaga Kependidikan',
        7 => 'Pembiayaan Langganan Daya dan/atau Jasa',
        8 => 'Pembiayaan Pemeliharaan Sarana dan Prasarana Sekolah',
        9 => 'Penyediaan Alat Multi Media Pembelajaran',
        10 => 'Pembiayaan Penyelenggaraan Bursa Kerja Khusus, Praktik Kerja Industri atau Praktik Kerja Lapangan di Dalam Negeri, Pemantauan Kebekerjaan, Pemagangan Guru, dan Lembaga Sertifikasi Profesi Pihak Pertama',
        11 => 'Pembiayaan Penyelenggaraan Kegiatan Uji Komp Keahlian, Sertifikasi Kompetensi Keahlian, dan Uji Komp Kemampuan Bhs Inggris Berstandar Internasional dan Bhs Asing Lainnya bagi Kelas Akhir SMK atau SMALB',
        12 => 'Pembiayaan untuk Pembayaran Honor',
    ];

    /** Baris 8 standar (dipakai bila master kode kegiatan belum terimpor). */
    private const STANDAR_RPD = [
        1 => 'Pengembangan Standar Isi',
        2 => 'Pengembangan Standar Proses',
        3 => 'Pengembangan pendidik dan tenaga kependidikan',
        4 => 'Pengembangan sarana dan prasarana sekolah',
        5 => 'Pengembangan standar pengelolaan',
        6 => 'Pengembangan standar pembiayaan',
        7 => 'Pengembangan dan implementasi sistem penilaian',
    ];

    /**
     * RPD K7 — Rekapitulasi Realisasi Penggunaan Dana BOSP, bentuk mengikuti
     * rpd-k7-output.pdf ARKAS 4.3.1:
     *   matriks 8 standar (baris) × 12 sub program (kolom) berisi **realisasi**
     *   belanja tahun berjalan, baris JUMLAH, lalu 4 baris saldo, lalu tanda
     *   tangan 2 kolom (Menyetujui/Kepala Sekolah & Bendahara).
     *
     * Kode kegiatan ARKAS berbentuk `SS.SP.KK.` (SS = standar, SP = sub program,
     * KK = kegiatan), jadi sel matriks = jumlah realisasi dengan prefix `SS.SP.`.
     * Nama standar diambil dari master `arkas_kodes` (level 1); bila master belum
     * ada, dipakai daftar baku yang sama dengan sample ARKAS.
     */
    public function rpdK7(Request $r)
    {
        [$sid, $tahun] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);
        $sekolah = $r->user()->school;

        $standar = ArkasKode::where('school_id', $sid)->where('tahun', $tahun)->where('level', 1)
            ->orderBy('kode')->get()
            ->map(fn ($k) => ['kode' => rtrim((string) $k->kode, '.'), 'nama' => (string) $k->uraian])
            ->values()->all();
        if ($standar === []) {
            $standar = collect(self::STANDAR_RPD)
                ->map(fn ($nama, $no) => ['kode' => sprintf('%02d', (int) $no), 'nama' => $nama])->values()->all();
        }

        // Realisasi per sel: prefix standar + sub program.
        $sel = [];
        $raw = DB::table('kas_details as d')
            ->join('kas_transactions as t', 't.id', '=', 'd.kas_transaction_id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->whereNotNull('d.kode_kegiatan')
            ->get(['d.kode_kegiatan', 'd.nilai']);
        foreach ($raw as $x) {
            $seg = array_values(array_filter(explode('.', trim((string) $x->kode_kegiatan, '.'))));
            if (count($seg) < 2) {
                continue;
            }
            $kunci = (int) $seg[0].'|'.(int) $seg[1];
            $sel[$kunci] = ($sel[$kunci] ?? 0) + (int) $x->nilai;
        }

        $totalKolom = array_fill_keys(array_keys(self::SUB_PROGRAM_RPD), 0);
        $baris = [];
        foreach ($standar as $st) {
            $nilai = [];
            foreach (array_keys(self::SUB_PROGRAM_RPD) as $sp) {
                $v = (int) ($sel[(int) $st['kode'].'|'.$sp] ?? 0);
                $nilai[$sp] = $v;
                $totalKolom[$sp] += $v;
            }
            $baris[] = ['kode' => $st['kode'], 'nama' => $st['nama'], 'nilai' => $nilai, 'total' => array_sum($nilai)];
        }

        $penggunaan = array_sum($totalKolom);
        $penerimaan = (int) KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $sid)->where('tahun', $tahun)->where('type', 'pendapatan')->sum('total');
        $ledger = KasService::ledger($sid, $tahun);
        $saldoAwal = (int) ($ledger['saldoAwalTahun'] ?? 0);

        $tgl = now()->translatedFormat('d F Y');

        return view('laporan-arkas.rpd-k7-cetak', [
            'kop' => $kop, 'sekolah' => $sekolah, 'tahun' => $tahun,
            'subProgram' => self::SUB_PROGRAM_RPD, 'baris' => $baris,
            'totalKolom' => $totalKolom, 'jumlah' => $penggunaan, 'penggunaan' => $penggunaan,
            'saldoAwal' => $saldoAwal, 'penerimaan' => $penerimaan,
            'saldoAkhir' => $saldoAwal + $penerimaan - $penggunaan,
            'periodeTeks' => '01 Januari '.$tahun.' s/d 31 Desember '.$tahun,
            'penandatangan' => Penandatangan::orderBy('id')->get(),
            'tempatTtd' => trim((string) ($sekolah->kecamatan ?? '')) !== ''
                ? 'Kec. '.$sekolah->kecamatan : (string) ($sekolah->kab_kota ?? ''),
            'tanggalTtd' => $tgl,
        ]);
    }

    // ==================== 3. Realisasi Anggaran (per bulan) ====================

    /** Baris Realisasi Anggaran per rekening: anggaran, realisasi per bulan, komponen, %. */
    private function barisRealisasi(Request $r): array
    {
        // ctx(): (sid, tahun, bulan int, 'Y-m'); lewati elemen ke-3.
        [$sid, $tahun, , $bulanStr] = $this->ctx($r);
        $rekenings = Rekening::pluck('kode', 'id');
        $rekeningsNama = Rekening::pluck('nama', 'id');
        $komponenNama = Rekening::leftJoin('komponens as k', 'rekenings.komponen_id', '=', 'k.id')
            ->select('rekenings.id', 'k.nama')->get()
            ->mapWithKeys(fn ($x) => [$x->id => (string) ($x->nama ?? '')]);

        $reks = [];
        foreach ($this->anggaranRekening($this->kop($sid, $tahun)['rkas']) as $rid => $a) {
            $reks[$rid] = ['kode' => (string) ($rekenings[$rid] ?? ''), 'nama' => (string) ($rekeningsNama[$rid] ?? ''),
                'komponen' => (string) ($komponenNama[$rid] ?? ''),
                'ang' => (int) $a, 'lalu' => 0, 'ini' => 0];
        }
        foreach ($this->belanjaRek($sid, $tahun) as $b) {
            if (! isset($reks[$b->rid])) {
                $reks[$b->rid] = ['kode' => (string) $b->kode, 'nama' => (string) $b->nama,
                    'komponen' => (string) ($komponenNama[$b->rid] ?? ''),
                    'ang' => 0, 'lalu' => 0, 'ini' => 0];
            }
            if ($b->ym < $bulanStr) {
                $reks[$b->rid]['lalu'] += (int) $b->j;
            } elseif ($b->ym === $bulanStr) {
                $reks[$b->rid]['ini'] += (int) $b->j;
            }
        }
        foreach ($reks as &$x) {
            $x['sd'] = $x['lalu'] + $x['ini'];
            $x['selisih'] = $x['ang'] - $x['sd'];
            $x['persen'] = $x['ang'] > 0 ? round($x['sd'] / $x['ang'] * 100, 1) : ($x['sd'] > 0 ? 100.0 : 0.0);
        }
        unset($x);
        usort($reks, fn ($a, $b) => strcmp($a['kode'], $b['kode']));
        $total = ['ang' => array_sum(array_column($reks, 'ang')), 'lalu' => array_sum(array_column($reks, 'lalu')),
            'ini' => array_sum(array_column($reks, 'ini'))];
        $total['sd'] = $total['lalu'] + $total['ini'];
        $total['selisih'] = $total['ang'] - $total['sd'];
        $total['persen'] = $total['ang'] > 0 ? round($total['sd'] / $total['ang'] * 100, 1) : 0.0;

        return [$reks, $total];
    }

    public function realisasi(Request $r)
    {
        [$reks, $total] = $this->barisRealisasi($r);
        [$sid, $tahun, $bulan] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);

        return view('laporan-arkas.realisasi', ['rows' => $reks, 'total' => $total, 'kop' => $kop,
            'bulan' => $bulan, 'namaBulan' => self::BULAN]);
    }

    /** ⬇ Ekspor CSV Realisasi Anggaran per rekening (pemisah titik-koma). */
    public function realisasiCsv(Request $r)
    {
        [$reks, $total] = $this->barisRealisasi($r);
        [$sid, $tahun, $bulan] = $this->ctx($r);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, Csv::baris(['Kode Rekening', 'Uraian', 'Komponen', 'Jumlah Anggaran', 'Realisasi s.d. Bulan Lalu',
            'Realisasi Bulan Ini', 'Jumlah Realisasi s.d. Bulan Ini', 'Selisih/ Kurang', 'Persen']), ';');
        foreach ($reks as $x) {
            fputcsv($out, Csv::baris([$x['kode'], $x['nama'], $x['komponen'], $x['ang'], $x['lalu'], $x['ini'],
                $x['sd'], $x['selisih'], $x['persen']]), ';');
        }
        fputcsv($out, Csv::baris(['Total', '', '', $total['ang'], $total['lalu'], $total['ini'], $total['sd'],
            $total['selisih'], $total['persen']]), ';');
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=realisasi_{$tahun}-{$bulan}.csv",
        ]);
    }

    // ==================== 4. Realisasi Penerimaan & Belanja ====================

    public function penerimaanBelanja(Request $r)
    {
        [$sid, $tahun, $bulan, $bulanStr] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);
        $kodeStokus = Rekening::pluck('kode', 'id');

        // Anggaran per kategori (RKAS terakhir).
        $angKategori = array_fill_keys(array_values(self::KATEGORI), 0);
        foreach ($this->anggaranRekening($kop['rkas']) as $rid => $j) {
            $kode = (string) ($kodeStokus[$rid] ?? '');
            foreach (self::KATEGORI as $pf) {
                if ($kode !== '' && str_starts_with($kode, $pf)) {
                    $angKategori[$pf] += (int) $j;
                }
            }
        }
        // Realisasi per kategori per rentang bulan.
        $laluK = array_fill_keys(array_values(self::KATEGORI), 0);
        $iniK = array_fill_keys(array_values(self::KATEGORI), 0);
        foreach ($this->belanjaRek($sid, $tahun) as $b) {
            foreach (array_values(self::KATEGORI) as $i => $pf) {
                if (str_starts_with((string) $b->kode, $pf)) {
                    if ($b->ym < $bulanStr) {
                        $laluK[$pf] += (int) $b->j;
                    } elseif ($b->ym === $bulanStr) {
                        $iniK[$pf] += (int) $b->j;
                    }
                }
            }
        }
        $rows = [];
        foreach (self::KATEGORI as $kut => $pf) {
            $rows[] = ['uraian' => $kut, 'ang' => $angKategori[$pf],
                'lalu' => $laluK[$pf], 'ini' => $iniK[$pf],
                'sd' => $laluK[$pf] + $iniK[$pf]];
            $i = count($rows) - 1;
            $rows[$i]['selisih'] = $rows[$i]['ang'] - $rows[$i]['sd'];
        }
        $total = ['ang' => array_sum(array_column($rows, 'ang')), 'lalu' => array_sum(array_column($rows, 'lalu')),
            'ini' => array_sum(array_column($rows, 'ini')), 'sd' => array_sum(array_column($rows, 'sd')),
            'selisih' => array_sum(array_column($rows, 'selisih'))];

        return view('laporan-arkas.penerimaan-belanja', ['rows' => $rows, 'total' => $total, 'kop' => $kop,
            'bulan' => $bulan, 'namaBulan' => self::BULAN]);
    }

    // ==================== 5 & 6. Realisasi Habis Pakai / Modal ====================

    public function habisPakai(Request $r)
    {
        return $this->detailKategori($r, '5.1.02.01', 'habis-pakai');
    }

    public function modal(Request $r)
    {
        return $this->detailKategori($r, '5.2.', 'modal');
    }

    private function detailKategori(Request $r, string $prefix, string $view)
    {
        [$sid, $tahun, $bulan, $bulanStr] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);

        $rows = DB::table('kas_details as d')
            ->join('kas_transactions as t', 't.id', '=', 'd.kas_transaction_id')
            ->join('rekenings as r', 'r.id', '=', 'd.rekening_id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->whereRaw('substr(CAST(t.tanggal AS VARCHAR),1,7) = ?', [$bulanStr])
            ->where('r.kode', 'like', $prefix.'%')
            ->orderBy('t.tanggal')->orderBy('d.id')
            ->get(['r.kode', 'r.nama', 't.tanggal', 'd.uraian', 'd.volume', 'd.nilai']);

        $out = [];
        foreach ($rows as $x) {
            $v = (float) $x->volume;
            $out[] = ['kode' => $x->kode, 'uraian' => $x->uraian ?: $x->nama,
                'tanggal' => (string) $x->tanggal, 'volume' => $v > 0 ? $v : null,
                'harga' => $v > 0 ? (int) round(((int) $x->nilai) / $v) : null, 'jumlah' => (int) $x->nilai];
        }
        $total = array_sum(array_column($out, 'jumlah'));

        return view('laporan-arkas.'.$view, ['rows' => $out, 'total' => $total, 'kop' => $kop,
            'bulan' => $bulan, 'namaBulan' => self::BULAN]);
    }

    // ==================== 6b. BHM (Barang Modal / Aset) ====================

    /**
     * BHM — Rekapitulasi Realisasi Belanja Dana BOSP (Barang Modal / Aset),
     * bentuk mengikuti bhm-output.pdf ARKAS 4.3.1. Kolom "ID Barang" milik
     * ARKAS tidak dicetak: SiapBOSP belum punya master barang/aset, jadi kolom
     * itu akan selalu kosong (dicatat sebagai selisih di docs).
     */
    public function bhm(Request $r)
    {
        [$sid, $tahun, $bulan, $bulanStr] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);
        $kodeNama = ArkasKode::peta($sid, $tahun);

        $raw = DB::table('kas_details as d')
            ->join('kas_transactions as t', 't.id', '=', 'd.kas_transaction_id')
            ->join('rekenings as r', 'r.id', '=', 'd.rekening_id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->whereRaw('substr(CAST(t.tanggal AS VARCHAR),1,7) = ?', [$bulanStr])
            ->where('r.kode', 'like', '5.2.%')
            ->orderBy('t.tanggal')->orderBy('d.id')
            ->get(['r.kode', 'r.nama', 't.tanggal', 't.no_bukti', 'd.kode_kegiatan', 'd.uraian',
                'd.volume', 'd.satuan', 'd.nilai']);

        $rows = [];
        foreach ($raw as $x) {
            $vol = (float) $x->volume;
            $nilai = (int) $x->nilai;
            $keg = trim((string) ($x->kode_kegiatan ?? ''));
            $rows[] = [
                'tanggal' => (string) $x->tanggal,
                'no_bukti' => (string) ($x->no_bukti ?? ''),
                'kegiatan' => $keg === '' ? '' : trim($keg.' '.($kodeNama[$keg] ?? '')),
                'rekening' => (string) $x->kode,
                'uraian' => (string) ($x->uraian ?: $x->nama),
                'volume' => $vol > 0 ? $vol : null,
                'satuan' => (string) ($x->satuan ?? ''),
                'harga' => $vol > 0 ? (int) round($nilai / $vol) : null,
                'jumlah' => $nilai,
            ];
        }

        $namaBulanTeks = self::BULAN[$bulan];
        $tglTeks = date('t', mktime(0, 0, 0, $bulan, 1, $tahun)).' '.$namaBulanTeks.' '.$tahun;

        return view('laporan-arkas.bhm-cetak', [
            'rows' => $rows, 'kop' => $kop, 'sekolah' => $r->user()->school,
            'penandatangan' => Penandatangan::orderBy('id')->get(),
            'bulan' => $bulan, 'tahun' => $tahun, 'namaBulan' => self::BULAN,
            'labelPeriode' => 'BULAN : '.strtoupper($namaBulanTeks).' TAHUN : '.$tahun,
            'tglAkhirTeks' => $tglTeks,
            'total' => array_sum(array_column($rows, 'jumlah')),
            'totalVolume' => array_sum(array_column($rows, 'volume')),
        ]);
    }

    // ==================== 7. Daftar Kas Umum ====================

    public function kasUmum(Request $r)
    {
        [$sid, $tahun, $bulan, $bulanStr] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);
        $kodeNama = ArkasKode::peta($sid, $tahun);

        $trs = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $sid)->where('tahun', $tahun)
            ->whereRaw('substr(CAST(tanggal AS VARCHAR),1,7) = ?', [$bulanStr])
            ->orderBy('tanggal')->orderBy('no_bku')->orderBy('id')
            ->with('details.rekening')->get();

        $notas = KasNota::where('school_id', $sid)->where('tahun', $tahun)
            ->get()->keyBy('arkas_id');

        $rows = [];
        $no = 1;
        foreach ($trs as $t) {
            // arkas_nota_id menyimpan id_kas_nota mentah; KasNota.arkas_id berawalan 'nota:'.
            $nota = $t->arkas_nota_id ? ($notas['nota:'.$t->arkas_nota_id] ?? null) : null;
            $items = [];
            if ($t->details->count() > 0) {
                foreach ($t->details as $d) {
                    $items[] = ['rek' => $d->rekening, 'uraian' => $d->uraian,
                        'nilai' => (int) $d->nilai, 'volume' => $d->volume,
                        'keg' => (string) ($d->kode_kegiatan ?? '')];
                }
            } else {
                $items[] = ['rek' => null, 'uraian' => null, 'nilai' => (int) $t->total,
                    'volume' => null, 'keg' => ''];
            }
            foreach ($items as $d) {
                $keg = $d['keg'];
                $rows[] = [
                    'no' => $no++,
                    'tgl' => $t->tanggal->format('Y-m-d'),
                    'no_bukti' => $t->arkas_bku ?: ($t->no_bku ? 'BKU#'.$t->no_bku : '—'),
                    'tanggal_nota' => $nota?->tanggal_nota?->format('Y-m-d'),
                    'no_nota' => $nota?->no_nota,
                    'uraian' => $d['uraian'] ?: $t->keterangan,
                    'kode' => $d['rek']?->kode ?? '',
                    'rekNama' => $d['rek']?->nama ?? '',
                    'volume' => $d['volume'],
                    'nominal' => $d['nilai'],
                    'kegiatan' => $keg,
                    'kegiatanNama' => $kodeNama[$keg] ?? '',
                    'penerima' => $nota?->nama_toko,
                    'alamat' => $nota?->alamat_toko,
                    'no_telp' => $nota?->no_telp,
                    'npwp' => $nota?->npwp,
                ];
            }
        }

        return view('laporan-arkas.kas-umum', ['rows' => $rows, 'kop' => $kop, 'bulan' => $bulan,
            'namaBulan' => self::BULAN]);
    }

    // ==================== 8. Daftar Nota ====================

    public function nota(Request $r)
    {
        [$sid, $tahun, $bulan, $bulanStr] = $this->ctx($r);
        $kop = $this->kop($sid, $tahun);

        $notas = KasNota::where('school_id', $sid)->where('tahun', $tahun)
            ->whereRaw('substr(CAST(tanggal_nota AS VARCHAR),1,7) = ?', [$bulanStr])
            ->orderBy('tanggal_nota')->get();

        $trx = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $sid)->whereNotNull('arkas_nota_id')
            ->orderBy('tanggal')->get()
            ->keyBy(fn ($t) => 'nota:'.$t->arkas_nota_id);

        $rows = [];
        $no = 1;
        foreach ($notas as $n) {
            $t = $trx[$n->arkas_id] ?? null;
            $rows[] = [
                'no' => $no++, 'no_bukti' => $n->no_bukti ?: '—',
                'tanggal_nota' => $n->tanggal_nota->format('Y-m-d'),
                'no_nota' => $n->no_nota,
                'pembayaran' => $t ? ($t->metode_bayar === 'tunai' ? 'Tunai' : 'Transfer') : '—',
                'nama' => $n->nama_toko, 'alamat' => $n->alamat_toko, 'no_telp' => $n->no_telp, 'npwp' => $n->npwp,
                'total' => (int) $n->total, 'lunas' => (int) $n->lunas_bayar,
                'tgl_transaksi' => $t?->tanggal?->format('Y-m-d') ?? $n->tanggal_nota->format('Y-m-d'),
                'uraian' => $t?->keterangan,
            ];
        }

        return view('laporan-arkas.nota', ['rows' => $rows, 'kop' => $kop, 'bulan' => $bulan,
            'namaBulan' => self::BULAN]);
    }

    // ==================== 9. Register Penutupan Kas ====================

    public function penutupanKas(Request $r)
    {
        [$sid, $tahun, $bulan] = $this->ctx($r);
        $data = $this->dataPenutupan($sid, $tahun, $bulan);
        $data['bulan'] = $bulan;
        $data['tahun'] = $tahun;
        $data['namaBulan'] = self::BULAN;
        $data['kop'] = $this->kop($sid, $tahun);

        return view('laporan-arkas.penutupan-kas', $data);
    }

    public function penutupanKasCetak(Request $r)
    {
        [$sid, $tahun, $bulan] = $this->ctx($r);
        $data = $this->dataPenutupan($sid, $tahun, $bulan);
        $data['bulan'] = $bulan;
        $data['tahun'] = $tahun;
        $data['namaBulan'] = self::BULAN;
        $data['kop'] = $this->kop($sid, $tahun);
        $data['sekolah'] = $r->user()->school;

        return view('laporan-arkas.penutupan-kas-cetak', $data);
    }

    private function dataPenutupan(int $sid, int $tahun, int $bulan): array
    {
        $akhir = sprintf('%d-%02d', $tahun, $bulan).'-'.date('t', mktime(0, 0, 0, $bulan, 1, $tahun));
        $lalu = mktime(0, 0, 0, $bulan - 1, 1, $tahun);
        $akhirLalu = sprintf('%d-%02d', (int) date('Y', $lalu), (int) date('m', $lalu)).'-'.date('t', $lalu);

        $l = KasService::ledger($sid, $tahun);
        $D = 0;
        $K = 0;
        $tunai = 0;
        $bank = 0;
        foreach ($l['items'] as $it) {
            $tgl = $it['trx']->tanggal->format('Y-m-d');
            if ($tgl <= $akhir) {
                $tunai += $it['dTunai'];
                $bank += $it['dBank'];
            }
            if ($tgl > $akhirLalu && $tgl <= $akhir) {
                $D += max(0, $it['dTunai'] + $it['dBank']);
                $K += max(0, -($it['dTunai'] + $it['dBank']));
            }
        }
        $A = $tunai + $bank;

        // Denominasi fisik kas tunai (greedy, gaya register ARKAS).
        $denLembar = [];
        $sisa = $tunai;
        foreach ([100000, 50000, 20000, 10000, 5000, 2000, 1000] as $v) {
            $jml = intdiv($sisa, $v);
            $denLembar[] = ['nilai' => $v, 'jumlah' => $jml, 'total' => $v * $jml];
            $sisa -= $v * $jml;
        }
        $denKeping = [];
        foreach ([500, 200, 100] as $v) {
            $jml = intdiv($sisa, $v);
            $denKeping[] = ['nilai' => $v, 'jumlah' => $jml, 'total' => $v * $jml];
            $sisa -= $v * $jml;
        }

        $penjab = Penandatangan::orderBy('id')->get();
        $bendahara = $penjab->first(fn ($p) => stripos($p->jabatan ?? '', 'bendahara') !== false);
        $kepsek = $penjab->first(fn ($p) => stripos($p->jabatan ?? '', 'kepala') !== false);

        return [
            'tanggal' => $akhir, 'tanggalLalu' => $akhirLalu,
            'pemegang' => $bendahara, 'kepsek' => $kepsek,
            'D' => $D, 'K' => $K, 'A' => $A,
            'tunai' => $tunai, 'bank' => $bank,
            'denLembar' => $denLembar, 'denKeping' => $denKeping,
            'perbedaan' => $A - ($tunai + $bank),
        ];
    }
}
