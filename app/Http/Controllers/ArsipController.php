<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BkuTutupBulan;
use App\Models\KasTax;
use App\Models\RkasHeader;
use App\Models\TutupBuku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Arsip — tempat dokumen & riwayat resmi beserta datanya:
 * RKAS yang disahkan, periode BKU yang ditutup, dokumen pajak, serta
 * riwayat impor & ekspor.
 */
class ArsipController extends Controller
{
    /**
     * Arsip terpadu ala ARKAS 4: tiga filter sejajar — Format (BKU/RKAS/Semua),
     * Sumber dana, dan Tahun — lalu daftar dokumen sah beserta tombol cetak.
     *
     * Hanya dokumen yang benar-benar sah yang masuk: RKAS berstatus
     * disahkan/dikunci dan BKU bulan yang sudah ditutup.
     */
    public function index(Request $r)
    {
        $sid = auth()->user()->school_id;

        $format = (string) $r->query('format', 'semua');
        if (! in_array($format, ['semua', 'bku', 'rkas'], true)) {
            $format = 'semua';
        }
        $sumber = trim((string) $r->query('sumber', ''));

        // Daftar tahun yang punya dokumen sah (ARKAS: 2018…tahun ini + "Semua").
        $tahunRkas = RkasHeader::where('school_id', $sid)
            ->whereIn('status', ['disahkan', 'dikunci'])->pluck('tahun');
        $tahunBku = BkuTutupBulan::where('school_id', $sid)->pluck('tahun');
        $daftarTahun = $tahunRkas->merge($tahunBku)->map(fn ($t) => (int) $t)
            ->unique()->sortDesc()->values();

        $tahun = (string) $r->query('tahun', 'semua');
        $tahunInt = ctype_digit($tahun) ? (int) $tahun : null;

        $dokumen = collect();

        // --- Dokumen RKAS (satu baris per dokumen sah) ---
        if ($format === 'semua' || $format === 'rkas') {
            $rkas = RkasHeader::with('details')
                ->where('school_id', $sid)
                ->whereIn('status', ['disahkan', 'dikunci'])
                ->when($sumber !== '', fn ($q) => $q->where('sumber_dana', $sumber))
                ->when($tahunInt, fn ($q) => $q->where('tahun', $tahunInt))
                ->orderByDesc('tahun')->orderByDesc('revisi_ke')
                ->get();

            foreach ($rkas as $h) {
                $dokumen->push([
                    'jenis' => 'rkas',
                    'nama' => 'RKAS '.$h->sumber_dana.' '.$h->tahun,
                    'sub' => 'Ver. '.$h->labelJenis().' — '.($h->ditinjau_at ?? $h->updated_at)?->format('d M Y'),
                    'sumber' => $h->sumber_dana,
                    'tahun' => (int) $h->tahun,
                    'status' => $h->labelStatusArkas(),
                    'nilai' => (int) $h->details->sum('jumlah'),
                    'cetak' => route('rkas.pdf', $h),
                    'buka' => route('rkas.show', $h),
                ]);
            }
        }

        // --- Dokumen BKU (satu baris per bulan yang sudah ditutup) ---
        if ($format === 'semua' || $format === 'bku') {
            $bku = BkuTutupBulan::where('school_id', $sid)
                ->when($tahunInt, fn ($q) => $q->where('tahun', $tahunInt))
                ->orderByDesc('tahun')->orderByDesc('bulan')
                ->get();

            $namaBulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

            foreach ($bku as $b) {
                $bulanInt = (int) $b->bulan;
                $ym = sprintf('%04d-%02d', (int) $b->tahun, $bulanInt);

                // Sumber dana pada BKU: sekolah ini memakai satu BKU per tahun,
                // jadi labelnya "Semua sumber dana" agar tidak menyesatkan.
                $labelSumber = 'Semua sumber dana';
                if ($sumber !== '' && mb_strtolower($sumber) !== 'semua sumber dana') {
                    continue;
                }

                $dokumen->push([
                    'jenis' => 'bku',
                    'nama' => 'BKU '.($namaBulan[$bulanInt] ?? $bulanInt).' '.$b->tahun,
                    'sub' => 'Ditutup '.($b->created_at?->format('d M Y') ?? '—'),
                    'sumber' => $labelSumber,
                    'tahun' => (int) $b->tahun,
                    'status' => 'Sudah Selesai',
                    'nilai' => null,
                    'cetak' => route('bku.cetak', ['bulan' => $ym]),
                    'buka' => route('bku', ['bulan' => $ym]),
                ]);
            }
        }

        $dokumen = $dokumen->sortByDesc(fn ($d) => $d['tahun'] * 1000 + ($d['jenis'] === 'rkas' ? 500 : 0));

        // Pilihan sumber dana: gabungan nilai yang benar-benar ada di arsip.
        $daftarSumber = RkasHeader::where('school_id', $sid)
            ->whereIn('status', ['disahkan', 'dikunci'])
            ->distinct()->orderBy('sumber_dana')->pluck('sumber_dana')
            ->filter()->values();

        return view('arsip.index', [
            'dokumen' => $dokumen->values(),
            'format' => $format,
            'sumber' => $sumber,
            'tahun' => $tahun,
            'daftarTahun' => $daftarTahun,
            'daftarSumber' => $daftarSumber,
        ]);
    }

    /** RKAS yang sudah disahkan/dikunci (dokumen sah untuk pemeriksaan). */
    public function rkas(Request $r)
    {
        $tahun = (int) $r->query('tahun', session('tahun', now()->year));
        $items = RkasHeader::with('details')
            ->where('tahun', $tahun)
            ->whereIn('status', ['disahkan', 'dikunci'])
            ->orderByDesc('revisi_ke')->get();

        return view('arsip.rkas', compact('items', 'tahun'));
    }

    /** Periode BKU yang sudah ditutup (bulan & tahap). */
    public function bku(Request $r)
    {
        $tahun = (int) $r->query('tahun', session('tahun', now()->year));
        $sid = auth()->user()->school_id;

        $bulan = BkuTutupBulan::where('school_id', $sid)->where('tahun', $tahun)
            ->orderByDesc('bulan')->get();
        $tahap = TutupBuku::where('school_id', $sid)->where('tahun', $tahun)
            ->with('stage')->orderByDesc('stage_id')->get();

        return view('arsip.bku', compact('bulan', 'tahap', 'tahun'));
    }

    /** Dokumen pajak: pajak dipungut, status setor/lapor, NTPN, & lampiran. */
    public function pajak(Request $r)
    {
        $tahun = (int) $r->query('tahun', session('tahun', now()->year));
        $sid = auth()->user()->school_id;

        $pajak = KasTax::with(['transaksi', 'rekeningPajak'])
            ->whereHas('transaksi', fn ($q) => $q->where('school_id', $sid)->where('tahun', $tahun))
            ->orderByDesc('id')->limit(300)->get();

        return view('arsip.pajak', compact('pajak', 'tahun'));
    }

    /** Riwayat impor data ARKAS (hanya sekolah ini). */
    public function impor(Request $r)
    {
        $rows = ActivityLog::with('user')
            ->where('school_id', auth()->user()->school_id)
            ->where('aksi', 'impor_arkas')
            ->orderByDesc('id')->limit(200)->get();

        return view('arsip.impor', compact('rows'));
    }

    /** Riwayat ekspor/sinkronisasi ke pusat. */
    public function ekspor(Request $r)
    {
        $rows = ActivityLog::with('user')
            ->where('school_id', auth()->user()->school_id)
            ->whereIn('aksi', ['ekspor', 'sinkron', 'sinkronisasi', 'backup'])
            ->orderByDesc('id')->limit(200)->get();
        // `sync_states` tidak punya kolom school_id (status sinkronisasi satu
        // perangkat), jadi di server pusat multi-sekolah isinya disembunyikan:
        // tidak ada gunanya dan berpotensi membocorkan jejak perangkat lain.
        $sync = \App\Http\Controllers\UnduhAplikasiController::isPortable()
            ? DB::table('sync_states')->get()
            : collect();

        return view('arsip.ekspor', compact('rows', 'sync'));
    }
}
