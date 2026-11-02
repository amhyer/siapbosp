<?php

namespace App\Http\Controllers;

use App\Exceptions\ArkasRunAktifException;
use App\Models\ArkasImportRun;
use App\Services\AntreanArkasService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use RuntimeException;

class ImportArkasController extends Controller
{
    public function form(): View
    {
        return view('impor-arkas.form', [
            'tahun' => (int) session('tahun', now()->year),
            'imporTerakhir' => ArkasImportRun::query()
                ->where('user_id', auth()->id())
                ->where('school_id', auth()->user()->school_id)
                ->where('jenis', ArkasImportRun::JENIS_IMPOR)
                ->latest('id')
                ->first(),
        ]);
    }

    public function status(ArkasImportRun $impor): View
    {
        abort_unless(
            $impor->user_id === auth()->id() && $impor->school_id === auth()->user()->school_id,
            404
        );

        if ($impor->status === 'completed') {
            return match ($impor->jenis) {
                ArkasImportRun::JENIS_INTEGRITAS => view('impor-arkas.cek-integritas', [
                    'tahun' => (int) $impor->tahun,
                    'portabel' => UnduhAplikasiController::isPortable(),
                    'hasil' => $impor->result,
                    'sumber' => $impor->sumber ?? 'Berkas tersimpan privat',
                ]),
                ArkasImportRun::JENIS_REKONSILIASI => view('rekonsiliasi.hasil', ['hasil' => $impor->result]),
                ArkasImportRun::JENIS_RKAS_CSV => view('impor-arkas.status', compact('impor')),
                default => view('impor-arkas.hasil', ['lap' => $impor->result]),
            };
        }

        return view('impor-arkas.status', compact('impor'));
    }

    /** Unduh agen portable TarikArkas (zip ±40 MB). */
    public function unduhAgen()
    {
        $path = storage_path('app/agen/TarikArkas-v1.0.0.zip');
        abort_unless(is_file($path), 404, 'Berkas agen belum tersedia di server.');

        return response()->download($path, 'TarikArkas-v1.0.0.zip');
    }

    /** Unduh aplikasi jendela TarikArkas-GUI (zip ±44 MB). */
    public function unduhGui()
    {
        $path = storage_path('app/agen/TarikArkas-GUI-v1.0.0.zip');
        abort_unless(is_file($path), 404, 'Berkas aplikasi GUI belum tersedia di server.');

        return response()->download($path, 'TarikArkas-GUI-v1.0.0.zip');
    }

    public function proses(Request $request, AntreanArkasService $antrean)
    {
        $data = $request->validate([
            'berkas' => 'required|file|mimes:xlsx|max:20480',
            'tahun' => 'required|integer|min:2020|max:2030',
        ]);

        try {
            $impor = $antrean->daftarkan(
                $data['berkas'], $request->user(), ArkasImportRun::JENIS_IMPOR, (int) $data['tahun']
            );
        } catch (ArkasRunAktifException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'Antrean impor tidak tersedia. Berkas belum diproses; coba lagi atau hubungi administrator.');
        }

        return redirect()->route('impor-arkas.status', $impor);
    }
}
