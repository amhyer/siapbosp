<?php

namespace App\Http\Controllers;

use App\Services\ArkasImportService;
use Illuminate\Http\Request;

class ImportArkasController extends Controller
{
    public function form()
    {
        return view('impor-arkas.form', ['tahun' => (int) session('tahun', now()->year)]);
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

    public function proses(Request $r)
    {
        $data = $r->validate([
            'berkas' => 'required|file|mimes:xlsx|max:20480',
            'tahun' => 'required|integer|min:2020|max:2030',
        ]);
        $u = auth()->user();
        if (! $u->school_id) {
            return back()->with('error', 'Akun Anda tidak terikat ke sekolah mana pun.');
        }

        $path = $data['berkas']->getRealPath();
        try {
            $lap = ArkasImportService::import($path, $u->school_id, $u->id, (int) $data['tahun']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Impor gagal: berkas tidak bisa dibaca sebagai Excel ARKAS.');
        }

        return view('impor-arkas.hasil', compact('lap'));
    }
}
