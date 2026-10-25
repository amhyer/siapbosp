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
