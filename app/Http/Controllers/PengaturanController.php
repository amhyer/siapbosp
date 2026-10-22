<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\BackupService;
use App\Services\SinkronisasiService;
use App\Services\TarikArkasService;
use App\Services\VersiArkasService;
use App\Support\KonfigLokal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan: Koneksi ARKAS (pusat), Backup, Deteksi Versi, dan Log Sistem.
 */
class PengaturanController extends Controller
{
    /** Status sinkronisasi data sekolah ke website pusat. */
    public function sinkronisasi()
    {
        $status = SinkronisasiService::status();

        return view('pengaturan.sinkronisasi', compact('status'));
    }

    /** Jalankan sinkronisasi ke pusat (idempoten). */
    public function sinkronisasiJalankan()
    {
        try {
            $hasil = SinkronisasiService::jalan();
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Sinkronisasi gagal: '.$e->getMessage());
        }

        return back()->with('ok', $hasil['pesan']);
    }

    /** Log sistem (jejak audit) sekolah ini. */
    public function log(Request $r)
    {
        $aksi = (string) $r->query('aksi', '');
        $rows = ActivityLog::with('user')
            ->when($aksi !== '', fn ($q) => $q->where('aksi', $aksi))
            ->orderByDesc('id')->limit(300)->get();
        $daftarAksi = ActivityLog::query()->distinct()->orderBy('aksi')->pluck('aksi');

        return view('pengaturan.log', compact('rows', 'aksi', 'daftarAksi'));
    }

    /** Halaman Backup: penjelasan + tombol unduh. */
    public function backup()
    {
        $db = config('database.connections.sqlite.database');
        $ukuran = ($db && is_file($db)) ? round(filesize($db) / 1048576, 2) : null;

        return view('pengaturan.backup', [
            'ukuran' => $ukuran,
            'terakhir' => DB::table('activity_logs')->where('aksi', 'backup')->orderByDesc('id')->value('keterangan'),
        ]);
    }

    /** Unduh backup data (SQLite + berkas konfigurasi). */
    public function backupUnduh()
    {
        return BackupService::unduh();
    }

    /** Deteksi versi ARKAS & kesiapan data master. */
    public function versi()
    {
        $hasil = VersiArkasService::deteksi();

        return view('pengaturan.versi', compact('hasil'));
    }

    /** Koneksi ARKAS: server tujuan, token, dan status koneksi. */
    public function koneksi()
    {
        $conf = KonfigLokal::baca(KonfigLokal::absolut('launcher-windows/akun-sekolah.conf'));
        $status = SinkronisasiService::status();

        return view('pengaturan.koneksi', [
            'conf' => $conf,
            'status' => $status,
            'tarik' => TarikArkasService::status() ?? ['db_ada' => false],
            'portabel' => UnduhAplikasiController::isPortable(),
        ]);
    }

    /** Jalankan pemeriksaan koneksi ke pusat. */
    public function koneksiUji()
    {
        try {
            $hasil = SinkronisasiService::uji();
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Pemeriksaan koneksi gagal: '.$e->getMessage());
        }

        return back()->with('ok', $hasil['pesan']);
    }
}
