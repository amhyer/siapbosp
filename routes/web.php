<?php

use App\Http\Controllers\ArsipController;
use App\Http\Controllers\CekIntegritasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataSekolahController;
use App\Http\Controllers\DinasController;
use App\Http\Controllers\HapusDataController;
use App\Http\Controllers\HppController;
use App\Http\Controllers\ImportArkasController;
use App\Http\Controllers\LampiranController;
use App\Http\Controllers\LaporanArkasController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PembukuanController;
use App\Http\Controllers\PenandatanganController;
use App\Http\Controllers\PenerimaanController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PengeluaranController;
use App\Http\Controllers\PerencanaanController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\ReferensiController;
use App\Http\Controllers\RekonsiliasiController;
use App\Http\Controllers\RkasController;
use App\Http\Controllers\TarikArkasController;
use App\Http\Controllers\UnduhAplikasiController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    $u = auth()->user();
    if (! $u) {
        return redirect()->route('login');
    }

    return redirect()->route($u->isDinas() ? 'dinas.dashboard' : 'dashboard');
});

Route::get('/login', [LoginController::class, 'show'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::post('/sesi/tahun', function () {
        request()->session()->put('tahun', (int) request('tahun', now()->year));

        return back();
    })->name('sesi.tahun');
    Route::get('/referensi', [ReferensiController::class, 'index'])->name('referensi');
    Route::post('/api-token', function () {
        $token = Str::random(64);
        auth()->user()->forceFill(['api_token' => hash('sha256', $token)])->save();

        return back()->with([
            'token_baru' => $token,
            'ok' => 'Token API baru dibuat. Salin sekarang — token hanya ditampilkan sekali ini dan tidak akan bisa dilihat lagi.',
        ]);
    })->name('api-token.store');
    Route::get('/notifikasi/{id}/buka', function ($id) {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return redirect($n->data['url'] ?? route('dashboard'));
    })->name('notifikasi.buka');
    Route::get('/ganti-password', [ProfilController::class, 'editPassword'])->name('password.edit');
    Route::post('/ganti-password', [ProfilController::class, 'updatePassword'])->name('password.update');
    Route::post('/notifikasi/baca-semua', function () {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('ok', 'Semua notifikasi ditandai sudah dibaca.');
    })->name('notifikasi.bacaSemua');

    // ===== PANEL SEKOLAH =====
    Route::middleware('role:operator,kepsek')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ---- Hulu Pra-ARKAS: PBD/Rapor Pendidikan -> RKM -> RKT + Standar Harga (SSH/SBU) ----
        Route::get('/perencanaan', [PerencanaanController::class, 'index'])->name('perencanaan.index');
        Route::post('/perencanaan/pbd/template', [PerencanaanController::class, 'pbdTemplate'])->name('perencanaan.pbd.template');
        Route::post('/perencanaan/pbd', [PerencanaanController::class, 'pbdStore'])->name('perencanaan.pbd.store');
        Route::delete('/perencanaan/pbd/{pbd}', [PerencanaanController::class, 'pbdDestroy'])->name('perencanaan.pbd.destroy');
        Route::post('/perencanaan/rkm', [PerencanaanController::class, 'rkmStore'])->name('perencanaan.rkm.store');
        Route::delete('/perencanaan/rkm/{rkm}', [PerencanaanController::class, 'rkmDestroy'])->name('perencanaan.rkm.destroy');
        Route::post('/perencanaan/rkt', [PerencanaanController::class, 'rktStore'])->name('perencanaan.rkt.store');
        Route::delete('/perencanaan/rkt/{rkt}', [PerencanaanController::class, 'rktDestroy'])->name('perencanaan.rkt.destroy');
        Route::post('/perencanaan/rkt/ke-rkas', [PerencanaanController::class, 'dorongKeRkas'])->name('perencanaan.rkt.keRkas');
        Route::get('/perencanaan/ssh', [PerencanaanController::class, 'ssh'])->name('perencanaan.ssh');
        Route::post('/perencanaan/ssh', [PerencanaanController::class, 'sshStore'])->name('perencanaan.ssh.store');
        Route::post('/perencanaan/ssh/impor', [PerencanaanController::class, 'sshImpor'])->name('perencanaan.ssh.impor');
        Route::delete('/perencanaan/ssh/{ssh}', [PerencanaanController::class, 'sshDestroy'])->name('perencanaan.ssh.destroy');

        // ---- Penganggaran / Kertas Kerja RKAS ala ARKAS 4 ----
        Route::get('/rkas', [RkasController::class, 'index'])->name('rkas.index');
        Route::get('/rkas/buat', [RkasController::class, 'create'])->name('rkas.create');
        Route::post('/rkas', [RkasController::class, 'store'])->name('rkas.store');
        Route::post('/rkas/salin', [RkasController::class, 'salin'])->name('rkas.salin');
        Route::get('/rkas/{rkasHeader}', [RkasController::class, 'show'])->name('rkas.show')->whereNumber('rkasHeader');
        Route::get('/rkas/{rkasHeader}/salin-arkas', [RkasController::class, 'salinArkas'])->name('rkas.salinArkas')->whereNumber('rkasHeader');
        Route::post('/rkas/{rkasHeader}/detail/{detail}/toggle-arkas', [RkasController::class, 'toggleInputArkas'])->name('rkas.toggleArkas')->whereNumber('rkasHeader');
        Route::post('/rkas/{rkasHeader}/sisip/{detail}', [RkasController::class, 'sisipUraian'])->name('rkas.sisip')->whereNumber('rkasHeader');
        Route::get('/rkas/{rkasHeader}/pdf', [RkasController::class, 'pdf'])->name('rkas.pdf')->whereNumber('rkasHeader');
        Route::get('/rkas/{rkasHeader}/xlsx', [RkasController::class, 'xlsx'])->name('rkas.xlsx')->whereNumber('rkasHeader');
        Route::post('/rkas/{rkasHeader}/aksi', [RkasController::class, 'aksi'])->name('rkas.aksi')->whereNumber('rkasHeader');
        Route::post('/rkas-impor', [RkasController::class, 'impor'])->name('rkas.impor');
        Route::get('/hpp', [HppController::class, 'index'])->name('hpp.index');
        Route::post('/hpp', [HppController::class, 'store'])->name('hpp.store');
        Route::get('/rekonsiliasi', [RekonsiliasiController::class, 'form'])->name('rekonsiliasi.form');
        Route::post('/rekonsiliasi', [RekonsiliasiController::class, 'proses'])->name('rekonsiliasi.proses');

        // ---- Laporan resmi format ARKAS (9 laporan, baca-saja) ----
        Route::get('/laporan-arkas/rkas-bulanan', [LaporanArkasController::class, 'rkasBulanan'])->name('laporan-arkas.rkas-bulanan');
        Route::get('/laporan-arkas/rkas-tahap', [LaporanArkasController::class, 'rkasTahap'])->name('laporan-arkas.rkas-tahap');
        Route::get('/laporan-arkas/realisasi', [LaporanArkasController::class, 'realisasi'])->name('laporan-arkas.realisasi');
        Route::get('/laporan-arkas/realisasi-sumber-dana', [LaporanArkasController::class, 'realisasiSumber'])->name('laporan-arkas.realisasi-sumber');
        Route::get('/laporan-arkas/realisasi.csv', [LaporanArkasController::class, 'realisasiCsv'])->name('laporan-arkas.realisasi.csv');
        Route::get('/laporan-arkas/penerimaan-belanja', [LaporanArkasController::class, 'penerimaanBelanja'])->name('laporan-arkas.penerimaan-belanja');
        Route::get('/laporan-arkas/habis-pakai', [LaporanArkasController::class, 'habisPakai'])->name('laporan-arkas.habis-pakai');
        Route::get('/laporan-arkas/modal', [LaporanArkasController::class, 'modal'])->name('laporan-arkas.modal');
        Route::get('/laporan-arkas/daftar-kas-umum', [LaporanArkasController::class, 'kasUmum'])->name('laporan-arkas.kas-umum');
        Route::get('/laporan-arkas/daftar-nota', [LaporanArkasController::class, 'nota'])->name('laporan-arkas.nota');
        Route::get('/laporan-arkas/penutupan-kas', [LaporanArkasController::class, 'penutupanKas'])->name('laporan-arkas.penutupan-kas');
        Route::get('/laporan-arkas/penutupan-kas/cetak', [LaporanArkasController::class, 'penutupanKasCetak'])->name('laporan-arkas.penutupan-kas-cetak');
        Route::get('/impor-arkas', [ImportArkasController::class, 'form'])->name('impor-arkas.form');
        Route::get('/impor-arkas/unduh-agen', [ImportArkasController::class, 'unduhAgen'])->name('impor-arkas.unduh');
        Route::get('/impor-arkas/unduh-gui', [ImportArkasController::class, 'unduhGui'])->name('impor-arkas.unduhGui');
        Route::post('/impor-arkas', [ImportArkasController::class, 'proses'])->name('impor-arkas.proses');
        Route::get('/impor-arkas/cek-integritas', [CekIntegritasController::class, 'form'])->name('impor-arkas.cek-integritas');
        Route::post('/impor-arkas/cek-integritas', [CekIntegritasController::class, 'jalankan'])->name('impor-arkas.cek-integritas.jalankan');
        Route::get('/unduh-aplikasi', [UnduhAplikasiController::class, 'unduh'])->name('unduh-aplikasi');
        Route::get('/unduh-pemasang', [UnduhAplikasiController::class, 'unduhPemasang'])->name('unduh-pemasang');
        Route::get('/unduh-identitas', [UnduhAplikasiController::class, 'unduhIdentitas'])->name('unduh-identitas');

        Route::get('/penerimaan/pendapatan', [PenerimaanController::class, 'pendapatan'])->name('pendapatan.index');
        Route::post('/penerimaan/pendapatan', [PenerimaanController::class, 'pendapatanStore'])->name('pendapatan.store');
        Route::post('/penerimaan/bunga', [PenerimaanController::class, 'bungaStore'])->name('pendapatan.bunga');
        Route::get('/penerimaan/ambil-simpanan', [PenerimaanController::class, 'ambil'])->name('ambil.index');
        Route::post('/penerimaan/ambil-simpanan', [PenerimaanController::class, 'ambilStore'])->name('ambil.store');
        Route::get('/penerimaan/mutasi', [PenerimaanController::class, 'mutasi'])->name('mutasi.index');
        Route::post('/penerimaan/mutasi', [PenerimaanController::class, 'mutasiStore'])->name('mutasi.store');
        Route::delete('/penerimaan/{kasTransaction}', [PenerimaanController::class, 'destroy'])->name('penerimaan.destroy');

        Route::get('/pengeluaran/belanja', [PengeluaranController::class, 'belanja'])->name('belanja.index');
        Route::get('/pengeluaran/belanja/buat', [PengeluaranController::class, 'belanjaCreate'])->name('belanja.create');
        Route::get('/pengeluaran/belanja/hitung-pajak', [PengeluaranController::class, 'hitungPajak'])->name('belanja.hitungPajak');
        Route::post('/pengeluaran/belanja', [PengeluaranController::class, 'belanjaStore'])->name('belanja.store');
        Route::get('/pengeluaran/belanja/{belanja}', [PengeluaranController::class, 'belanjaShow'])->name('belanja.show')->whereNumber('belanja');
        Route::get('/pengeluaran/belanja/{belanja}/kwitansi', [PengeluaranController::class, 'kwitansi'])->name('belanja.kwitansi')->whereNumber('belanja');
        Route::get('/pengeluaran/belanja/{belanja}/spj', [PengeluaranController::class, 'spj'])->name('belanja.spj')->whereNumber('belanja');
        Route::get('/pengeluaran/setor-pajak', [PengeluaranController::class, 'setorPajak'])->name('setorpajak.index');
        Route::post('/pengeluaran/setor-pajak', [PengeluaranController::class, 'setorPajakStore'])->name('setorpajak.store');
        Route::post('/pengeluaran/setor-utang-pajak', [PengeluaranController::class, 'setorUtangStore'])->name('setorutang.store');
        Route::delete('/pengeluaran/{kasTransaction}', [PengeluaranController::class, 'destroy'])->name('pengeluaran.destroy');
        Route::post('/pengeluaran/belanja/{trx}/lampiran', [LampiranController::class, 'store'])->name('lampiran.store');
        Route::get('/lampiran/{attachment}/unduh', [LampiranController::class, 'download'])->name('lampiran.unduh');
        Route::delete('/lampiran/{attachment}', [LampiranController::class, 'destroy'])->name('lampiran.destroy');

        Route::get('/pembukuan/bku', [PembukuanController::class, 'bku'])->name('bku');
        Route::get('/pembukuan/bku/cetak', [PembukuanController::class, 'bkuCetak'])->name('bku.cetak');
        Route::get('/pembukuan/bku.csv', [PembukuanController::class, 'bkuCsv'])->name('bku.csv');
        Route::get('/pembukuan/buku-pembantu', [PembukuanController::class, 'bukuPembantu'])->name('pembukuan.bukuPembantu');
        Route::get('/pembukuan/rekap-realisasi', [PembukuanController::class, 'rekapRealisasi'])->name('pembukuan.rekapRealisasi');
        Route::get('/pembukuan/sptjm', [PembukuanController::class, 'sptjm'])->name('pembukuan.sptjm');
        Route::post('/pembukuan/bku/tutup', [PembukuanController::class, 'tutupBulan'])->name('bku.tutup');
        Route::post('/pembukuan/bku/buka', [PembukuanController::class, 'bukaBulan'])->name('bku.buka');
        Route::get('/pembukuan/kas', [PembukuanController::class, 'kas'])->name('kas');
        Route::get('/pembukuan/register', [PembukuanController::class, 'register'])->name('pembukuan.register');

        Route::get('/laporan/tahap', [LaporanController::class, 'tahap'])->name('laporan.tahap');
        Route::get('/laporan/pajak-triwulan', [LaporanController::class, 'pajakTriwulan'])->name('laporan.pajak-tw');
        Route::get('/laporan/lpj', [LaporanController::class, 'lpj'])->name('laporan.lpj');
        Route::get('/laporan/lpj.zip', [LaporanController::class, 'lpjZip'])->name('laporan.lpj.zip');
        Route::get('/laporan/lpj.pdf', [LaporanController::class, 'lpjPdf'])->name('laporan.lpj.pdf');
        Route::get('/laporan/lpj.xlsx', [LaporanController::class, 'lpjXlsx'])->name('laporan.lpj.xlsx');

        Route::post('/laporan/tahap/{stage}/tutup', [LaporanController::class, 'tutup'])->name('laporan.tahap.tutup');
        Route::post('/laporan/lpj/kumpulkan/{stage}', [LaporanController::class, 'kumpulkan'])->name('laporan.lpj.kumpulkan');
        Route::post('/laporan/tahap/{stage}/buka', [LaporanController::class, 'buka'])->name('laporan.tahap.buka');

        Route::get('/utility/profil', [ProfilController::class, 'edit'])->name('profil.edit');
        Route::put('/utility/profil', [ProfilController::class, 'update'])->name('profil.update');
        Route::post('/utility/tarik-arkas', [TarikArkasController::class, 'tarik'])->name('tarik-arkas.tarik');
        Route::get('/utility/penandatangan', [PenandatanganController::class, 'index'])->name('penandatangan');
        Route::post('/utility/penandatangan', [PenandatanganController::class, 'store'])->name('penandatangan.store');
        Route::post('/utility/penandatangan/{penandatangan}/ttd', [PenandatanganController::class, 'unggahTtd'])->name('penandatangan.ttd');
        Route::delete('/utility/penandatangan/{penandatangan}/ttd', [PenandatanganController::class, 'hapusTtd'])->name('penandatangan.ttd.hapus');
        Route::delete('/utility/penandatangan/{penandatangan}', [PenandatanganController::class, 'destroy'])->name('penandatangan.destroy');
        Route::middleware('role:operator')->group(function () {
            Route::get('/utility/hapus-data', [HapusDataController::class, 'form'])->name('hapusdata.form');
            Route::delete('/utility/hapus-data', [HapusDataController::class, 'proses'])->name('hapusdata.proses');
        });

        // ===== ARSIP =====
        Route::get('/arsip/rkas', [ArsipController::class, 'rkas'])->name('arsip.rkas');
        Route::get('/arsip/bku', [ArsipController::class, 'bku'])->name('arsip.bku');
        Route::get('/arsip/pajak', [ArsipController::class, 'pajak'])->name('arsip.pajak');
        Route::get('/arsip/impor', [ArsipController::class, 'impor'])->name('arsip.impor');
        Route::get('/arsip/ekspor', [ArsipController::class, 'ekspor'])->name('arsip.ekspor');

        // ===== DATA SEKOLAH =====
        Route::get('/data-sekolah/tahun-anggaran', [DataSekolahController::class, 'tahun'])->name('datasekolah.tahun');
        Route::get('/data-sekolah/sumber-dana', [DataSekolahController::class, 'sumberDana'])->name('datasekolah.sumber-dana');

        // ===== PENGATURAN =====
        Route::get('/pengaturan/koneksi-arkas', [PengaturanController::class, 'koneksi'])->name('pengaturan.koneksi');
        Route::post('/pengaturan/koneksi-arkas/uji', [PengaturanController::class, 'koneksiUji'])->name('pengaturan.koneksi.uji');
        Route::get('/pengaturan/sinkronisasi', [PengaturanController::class, 'sinkronisasi'])->name('pengaturan.sinkronisasi');
        Route::post('/pengaturan/sinkronisasi/jalankan', [PengaturanController::class, 'sinkronisasiJalankan'])->name('pengaturan.sinkronisasi.jalankan');
        Route::get('/pengaturan/backup', [PengaturanController::class, 'backup'])->name('pengaturan.backup');
        Route::get('/pengaturan/backup/unduh', [PengaturanController::class, 'backupUnduh'])->name('pengaturan.backup.unduh');
        Route::get('/pengaturan/deteksi-versi', [PengaturanController::class, 'versi'])->name('pengaturan.versi');
        Route::get('/pengaturan/log', [PengaturanController::class, 'log'])->name('pengaturan.log');

        // ===== DAFTAR KEGIATAN (kode kegiatan ARKAS) =====
        Route::get('/penganggaran/kegiatan', [ReferensiController::class, 'kegiatan'])->name('penganggaran.kegiatan');
    });

    // ===== PANEL DINAS =====
    Route::middleware('role:superadmin,dinas')->prefix('dinas')->name('dinas.')->group(function () {
        Route::get('/', [DinasController::class, 'dashboard'])->name('dashboard');
        Route::get('/sekolah', [DinasController::class, 'sekolah'])->name('sekolah');
        Route::post('/sekolah', [DinasController::class, 'sekolahStore'])->name('sekolah.store');
        Route::post('/sekolah/{school}/toggle', [DinasController::class, 'sekolahToggle'])->name('sekolah.toggle');
        Route::post('/sekolah/impor', [DinasController::class, 'imporSekolah'])->name('sekolah.impor');
        Route::get('/sekolah/{school}', [DinasController::class, 'sekolahShow'])->name('sekolah.show');
        Route::delete('/sekolah/{school}/hapus-data', [HapusDataController::class, 'dinasProses'])->name('sekolah.hapusdata');
        Route::get('/konsolidasi', [DinasController::class, 'konsolidasi'])->name('konsolidasi');
        Route::get('/konsolidasi.csv', [DinasController::class, 'konsolidasiCsv'])->name('konsolidasi.csv');
        Route::get('/konsolidasi.xlsx', [DinasController::class, 'konsolidasiXlsx'])->name('konsolidasi.xlsx');
        Route::get('/tahap', [DinasController::class, 'tahap'])->name('tahap');
        Route::post('/tahap/{stage}/toggle', [DinasController::class, 'tahapToggle'])->name('tahap.toggle');
        Route::post('/tahap/buat', [DinasController::class, 'tahapBuat'])->name('tahap.buat');
        Route::get('/pengguna', [DinasController::class, 'pengguna'])->name('pengguna');
        Route::post('/pengguna', [DinasController::class, 'penggunaStore'])->name('pengguna.store');
        Route::post('/pengguna/impor', [DinasController::class, 'penggunaImpor'])->name('pengguna.impor');
        Route::put('/pengguna/{user}', [DinasController::class, 'penggunaUpdate'])->name('pengguna.update');
        Route::post('/pengguna/{user}/reset', [DinasController::class, 'penggunaReset'])->name('pengguna.reset');
        Route::post('/pengguna/{user}/toggle', [DinasController::class, 'penggunaToggle'])->name('pengguna.toggle');
        Route::delete('/pengguna/{user}', [DinasController::class, 'penggunaHapus'])->name('pengguna.hapus');
        Route::get('/keamanan', [DinasController::class, 'keamanan'])->name('keamanan');
        Route::get('/alokasi', [DinasController::class, 'alokasi'])->name('alokasi');
        Route::post('/alokasi', [DinasController::class, 'alokasiStore'])->name('alokasi.store');
        Route::get('/master', [DinasController::class, 'master'])->name('master');
        Route::post('/master/impor', [DinasController::class, 'masterImpor'])->name('master.impor');
        Route::get('/master/template', [DinasController::class, 'masterTemplate'])->name('master.template');
        Route::post('/email-uji', [DinasController::class, 'emailUji'])->name('email-uji');
        Route::get('/rkas', [DinasController::class, 'rkas'])->name('rkas');
        Route::get('/rkas/{rkas}', [DinasController::class, 'rkasShow'])->name('rkas.show')->whereNumber('rkas');
        Route::post('/rkas/{rkas}/sahkan', [DinasController::class, 'rkasSahkan'])->name('rkas.sahkan')->whereNumber('rkas');
        Route::post('/rkas/{rkas}/revisi', [DinasController::class, 'rkasRevisi'])->name('rkas.revisi')->whereNumber('rkas');
        Route::get('/rkas/{rkas}/pdf', [DinasController::class, 'rkasPdf'])->name('rkas.pdf')->whereNumber('rkas');
        Route::get('/rkas/{rkas}/xlsx', [DinasController::class, 'rkasXlsx'])->name('rkas.xlsx')->whereNumber('rkas');
        Route::get('/lpj', [DinasController::class, 'lpj'])->name('lpj');
        Route::get('/lpj/{sub}', [DinasController::class, 'lpjShow'])->name('lpj.show');
        Route::get('/lpj/{sub}/pdf', [DinasController::class, 'lpjPdf'])->name('lpj.pdf');
        Route::get('/lpj/{sub}/xlsx', [DinasController::class, 'lpjXlsx'])->name('lpj.xlsx');

        Route::post('/lpj/{sub}/terima', [DinasController::class, 'lpjTerima'])->name('lpj.terima');
        Route::post('/lpj/{sub}/tolak', [DinasController::class, 'lpjTolak'])->name('lpj.tolak');
        Route::get('/aktivitas', [DinasController::class, 'aktivitas'])->name('aktivitas');
        Route::get('/backup', [DinasController::class, 'backup'])->name('backup');
        Route::get('/pengumuman', [DinasController::class, 'pengumuman'])->name('pengumuman');
        Route::post('/pengumuman', [DinasController::class, 'pengumumanStore'])->name('pengumuman.store');
        Route::delete('/pengumuman/{announcement}', [DinasController::class, 'pengumumanDestroy'])->name('pengumuman.destroy');
    });
});
