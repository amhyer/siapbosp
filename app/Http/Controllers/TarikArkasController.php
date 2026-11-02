<?php

namespace App\Http\Controllers;

use App\Services\TarikArkasService;
use Illuminate\Http\Request;

class TarikArkasController extends Controller
{
    /**
     * Penarikan data ARKAS dijalankan dari aplikasi desktop di laptop sekolah
     * (tombol di sidebar — satu-satunya tombol penarikan). Halaman ini hanya
     * menjalankan proses tarik lewat POST `/utility/tarik-arkas`.
     */

    /** Tombol 1-klik "Tarik Data dari ARKAS" (sidebar aplikasi portabel). */
    public function tarik(Request $r)
    {
        $data = $r->validate(['tahun' => 'required|integer|min:2020|max:2030']);
        $u = auth()->user();

        if (! $u->school_id) {
            return back()->with('error', 'Akun Anda tidak terikat ke sekolah mana pun.');
        }
        if (! TarikArkasService::tersedia()) {
            return back()->with('error', 'Penarikan data ARKAS hanya tersedia di aplikasi portabel di laptop sekolah. Di website pusat, unduh aplikasi desktop dari halaman Aplikasi Desktop atau unggah Excel manual di halaman Impor ARKAS.');
        }

        try {
            $hasil = TarikArkasService::tarikLengkap($u->school_id, $u->id, (int) $data['tahun']);

            return back()->with('ok', "Penarikan ARKAS selesai: {$hasil['ringkasan']} (NPSN: {$hasil['npsn']}, TA {$hasil['tahun']})");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Penarikan ARKAS gagal mendadak: '.$e->getMessage().'. Coba sekali lagi, atau pakai unggah Excel manual di halaman Impor ARKAS.');
        }
    }
}
