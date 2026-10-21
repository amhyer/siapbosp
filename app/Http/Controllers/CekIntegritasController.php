<?php

namespace App\Http\Controllers;

use App\Services\CekIntegritasService;
use App\Services\TarikArkasService;
use Illuminate\Http\Request;

/**
 * "Cek Ulang Integritas Impor" — bandingkan data ARKAS terbaru dengan
 * pencatatan di SiapBOSP per bulan (khususnya setelah ARKAS digalakkan
 * ulang pasca Pergeseran / Perubahan RKAS).
 */
class CekIntegritasController extends Controller
{
    public function form()
    {
        $tahun = (int) session('tahun', now()->year);

        return view('impor-arkas.cek-integritas', [
            'tahun' => $tahun,
            'portabel' => TarikArkasService::tersedia(),
        ]);
    }

    public function jalankan(Request $r)
    {
        $data = $r->validate([
            'tahun' => 'required|integer|min:2020|max:2030',
            'berkas' => 'nullable|file|max:40960',
        ]);
        $tahun = (int) $data['tahun'];
        $u = auth()->user();
        if (! $u->school_id) {
            return back()->with('error', 'Akun Anda tidak terikat ke sekolah mana pun.');
        }
        $sekolah = $u->school;

        $sumber = null;
        $berkas = null;
        $sementara = false;
        try {
            if ($r->hasFile('berkas')) {
                $berkas = $r->file('berkas')->getRealPath();
                $sumber = 'Unggah file ekspor ARKAS';
            } elseif (TarikArkasService::tersedia()) {
                $hasilTarik = TarikArkasService::eksporDb($tahun);
                $berkas = $hasilTarik['path'];
                $sementara = true;
                $sumber = 'Tarik langsung dari ARKAS di laptop ini (NPSN '.($hasilTarik['npsn'] ?: '?').')';
            } else {
                return back()->with('error', 'Di server pusat, unggah file ekspor ARKAS (Excel) untuk diperiksa. Di aplikasi portabel, cukup klik tombol tarik.');
            }

            $hasil = CekIntegritasService::bandingkan($berkas, $sekolah, $tahun);
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Cek integritas gagal: '.$e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Cek integritas gagal mendadak. Pastikan file yang diunggah adalah ekspor ARKAS (Excel .xlsx).');
        } finally {
            if ($sementara && $berkas) {
                @unlink($berkas);
            }
        }

        return view('impor-arkas.cek-integritas', [
            'tahun' => $tahun,
            'portabel' => TarikArkasService::tersedia(),
            'hasil' => $hasil,
            'sumber' => $sumber,
        ]);
    }
}
