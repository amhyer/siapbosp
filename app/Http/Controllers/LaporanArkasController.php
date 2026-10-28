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
