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
use Illuminate\Support\Facades\Gate;

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

    /**
     * Log sistem (jejak audit) SEKOLAH INI.
     *
     * ActivityLog tidak memakai global scope, jadi penyaringan school_id wajib
     * eksplisit — tanpa itu operator sebuah sekolah dapat membaca keterangan
     * transaksi sekolah lain.
     */
    public function log(Request $r)
    {
        $aksi = (string) $r->query('aksi', '');
        $sid = auth()->user()->school_id;
        $rows = ActivityLog::with('user')
            ->where('school_id', $sid)
            ->when($aksi !== '', fn ($q) => $q->where('aksi', $aksi))
            ->orderByDesc('id')->limit(300)->get();
        $daftarAksi = ActivityLog::query()->where('school_id', $sid)
            ->distinct()->orderBy('aksi')->pluck('aksi');

        return view('pengaturan.log', compact('rows', 'aksi', 'daftarAksi'));
    }

    /** Halaman Backup: penjelasan + tombol unduh. */
    public function backup()
    {
        $db = config('database.connections.sqlite.database');
        $ukuran = ($db && is_file($db)) ? round(filesize($db) / 1048576, 2) : null;

        return view('pengaturan.backup', [
            'ukuran' => $ukuran,
            'bolehUnduh' => self::bolehUnduhBackup(),
            'terakhir' => DB::table('activity_logs')
                ->where('school_id', auth()->user()->school_id)
                ->where('aksi', 'backup')->orderByDesc('id')->value('keterangan'),
        ]);
    }

    /**
     * Unduh backup data (SQLite + berkas konfigurasi).
     *
     * Berkas SQLite memuat SELURUH sekolah (dan hash sandi/token semua akun).
     * Di server pusat multi-sekolah, unduhan ini hanya untuk superadmin;
     * di aplikasi portabel (satu sekolah per laptop) pemilik akun sekolah
     * tetap boleh mencadangkan datanya sendiri.
     */
    public function backupUnduh()
    {
        abort_unless(self::bolehUnduhBackup(), 403,
            'Backup basis data penuh hanya dapat diunduh superadmin di server pusat. Gunakan aplikasi portabel untuk mencadangkan data satu sekolah.');

        return BackupService::unduh();
    }

    /** Apakah pengguna berhak mengunduh dump basis data penuh. */
    public static function bolehUnduhBackup(): bool
    {
        return Gate::allows('backup');
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
