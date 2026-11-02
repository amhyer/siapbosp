<?php

namespace App\Http\Controllers;

use App\Exceptions\ArkasRunAktifException;
use App\Models\ArkasImportRun;
use App\Services\AntreanArkasService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Rekonsiliasi ARKAS ↔ SiapBOSP: unggah file, bandingkan, tanpa menulis DB.
 */
class RekonsiliasiController extends Controller
{
    public function form()
    {
        return view('rekonsiliasi.form', ['tahun' => (int) session('tahun', now()->year)]);
    }

    public function proses(Request $request, AntreanArkasService $antrean)
    {
        $data = $request->validate([
            'berkas' => 'required|file|mimes:xlsx|max:40960',
            'tahun' => 'required|integer|min:2020|max:2030',
        ]);

        try {
            $run = $antrean->daftarkan(
                $data['berkas'],
                $request->user(),
                ArkasImportRun::JENIS_REKONSILIASI,
                (int) $data['tahun'],
                'Unggah file ekspor ARKAS'
            );
        } catch (ArkasRunAktifException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'Berkas tidak dapat disimpan untuk antrean. Coba lagi atau hubungi administrator.');
        }

        return redirect()->route('impor-arkas.status', $run);
    }
}
