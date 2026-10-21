<?php

namespace App\Http\Controllers;

use App\Services\TarikArkasService;
use Illuminate\Http\Request;

class TarikArkasController extends Controller
{
    /** Tombol 1-klik "Tarik Data ARKAS" di Pengaturan (Utility → Profil). */
    public function tarik(Request $r)
    {
        $data = $r->validate(['tahun' => 'required|integer|min:2020|max:2030']);
        $u = auth()->user();
        if (! $u->school_id) {
            return back()->with('error', 'Akun Anda tidak terikat ke sekolah mana pun.');
        }
        if (! TarikArkasService::tersedia()) {
            return back()->with('error', 'Tarik ARKAS hanya tersedia di aplikasi portabel di laptop sekolah. Di server pusat, pakai unggah Excel manual di halaman Impor ARKAS.');
        }
        try {
            $lap = TarikArkasService::tarik($u->school_id, $u->id, (int) $data['tahun']);
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Tarik data ARKAS gagal: '.$e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Tarik data ARKAS gagal mendadak. Coba sekali lagi, atau pakai unggah Excel manual di halaman Impor ARKAS.');
        }

        return back()->with(['ok' => 'Data ARKAS berhasil ditarik dari laptop ini ke SiapBOSP.', 'tarik_laporan' => $lap]);
    }
}
