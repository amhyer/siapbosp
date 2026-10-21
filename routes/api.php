<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

// ===== API Publik Baca-Saja v1 =====
// Autentikasi: header "Authorization: Bearer <token>" (token dibuat di Profil / halaman Pengguna Dinas).
Route::prefix('v1')->group(function () {
    Route::get('/ping', [ApiController::class, 'ping']); // publik: cek status layanan

    Route::middleware('auth.api')->group(function () {
        // Panel sekolah (token akun operator/kepsek)
        Route::get('/profil', [ApiController::class, 'profil']);
        Route::get('/tahap', [ApiController::class, 'tahap']);
        Route::get('/saldo', [ApiController::class, 'saldo']);
        Route::get('/transaksi', [ApiController::class, 'transaksi']);
        // Agen satu-klik di laptop sekolah: unggah Excel ARKAS (maks 20 MB).
        Route::post('/arkas-push', [ApiController::class, 'arkasPush'])->middleware('role:operator,kepsek');
        // Sync lokal → pusat (Tahap C): dorong transaksi idempoten.
        Route::post('/sync-push', [ApiController::class, 'syncPush'])->middleware('role:operator,kepsek');
        // Snapshot master pusat → lokal.
        Route::get('/sync-referensi', [ApiController::class, 'syncReferensi']);

        // Panel dinas (token akun dinas/superadmin)
        Route::middleware('role:superadmin,dinas')->group(function () {
            Route::get('/dinas/sekolah', [ApiController::class, 'dinasSekolah']);
            Route::get('/dinas/konsolidasi', [ApiController::class, 'dinasKonsolidasi']);
        });
    });
});
