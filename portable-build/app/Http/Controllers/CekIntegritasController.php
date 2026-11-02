<?php

namespace App\Http\Controllers;

use App\Models\ArkasImportRun;
use App\Services\AntreanArkasService;
use App\Services\TarikArkasService;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

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

    public function jalankan(Request $request, AntreanArkasService $antrean)
    {
        $data = $request->validate([
            'tahun' => 'required|integer|min:2020|max:2030',
            'berkas' => 'nullable|file|mimes:xlsx|max:40960',
        ]);
        $user = $request->user();
        $tahun = (int) $data['tahun'];
        $temporaryPath = null;
        $source = null;
        $file = $request->file('berkas');

        if ($file) {
            $source = 'Unggah file ekspor ARKAS';
        } elseif (TarikArkasService::tersedia()) {
            try {
                $hasilTarik = TarikArkasService::eksporDb($tahun);
                $temporaryPath = $hasilTarik['path'];
                $file = $temporaryPath;
                $source = 'Tarik langsung dari ARKAS di laptop ini (NPSN '.($hasilTarik['npsn'] ?: '?').')';
            } catch (Throwable $exception) {
                report($exception);

                return back()->with('error', 'Ekspor dari ARKAS lokal gagal. Pastikan ARKAS ditutup lalu coba lagi.');
            }
        } else {
            return back()->with('error', 'Di server pusat, unggah file ekspor ARKAS (Excel .xlsx) untuk diperiksa. Di aplikasi portabel, cukup klik tombol tarik.');
        }

        try {
            $run = $antrean->daftarkan(
                $file,
                $user,
                ArkasImportRun::JENIS_INTEGRITAS,
                $tahun,
                $source
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } finally {
            if ($temporaryPath !== null) {
                @unlink($temporaryPath);
            }
        }

        return redirect()->route('impor-arkas.status', $run);
    }
}
