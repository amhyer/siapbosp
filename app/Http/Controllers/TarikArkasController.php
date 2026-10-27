<?php

namespace App\Http\Controllers;

use App\Services\TarikArkasService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TarikArkasController extends Controller
{
    /**
     * Halaman GET "Sinkron ARKAS" (tautan sidebar) — membuka Utility → Profil
     * yang kartu Sinkron ARKAS-nya ditandai id="sinkron-arkas" agar langsung
     * terlihat di layar.
     */
    public function sinkron(): View
    {
        return app(ProfilController::class)->edit();
    }

    /** Tombol 1-klik "Sinkron ARKAS" di Utility → Profil Sekolah. */
    public function tarik(Request $r)
    {
        $data = $r->validate(['tahun' => 'required|integer|min:2020|max:2030']);
        $u = auth()->user();

        if (! $u->school_id) {
            return back()->with('error', 'Akun Anda tidak terikat ke sekolah mana pun.');
        }
        if (! TarikArkasService::tersedia()) {
            return back()->with('error', 'Sinkron ARKAS hanya tersedia di aplikasi portabel di laptop sekolah. Di server pusat, pakai unggah Excel manual di halaman Impor ARKAS.');
        }

        try {
            $hasil = TarikArkasService::tarikLengkap($u->school_id, $u->id, (int) $data['tahun']);

            return back()->with('ok', "Sinkron ARKAS selesai: {$hasil['ringkasan']} (NPSN: {$hasil['npsn']}, TA {$hasil['tahun']})");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Sinkron ARKAS gagal mendadak: '.$e->getMessage().'. Coba sekali lagi, atau pakai unggah Excel manual di halaman Impor ARKAS.');
        }
    }
}
