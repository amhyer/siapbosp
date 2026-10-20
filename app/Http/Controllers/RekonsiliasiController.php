<?php

namespace App\Http\Controllers;

use App\Services\RekonsiliasiService;
use Illuminate\Http\Request;

/**
 * Rekonsiliasi ARKAS ↔ SiapBOS: unggah file, bandingkan, tanpa menulis DB.
 */
class RekonsiliasiController extends Controller
{
    public function form()
    {
        return view('rekonsiliasi.form', ['tahun' => (int) session('tahun', now()->year)]);
    }

    public function proses(Request $r)
    {
        $data = $r->validate([
            'berkas' => 'required|file|mimes:xlsx|max:20480',
            'tahun' => 'required|integer|min:2020|max:2030',
        ]);
        try {
            $hasil = RekonsiliasiService::bandingkan(
                auth()->user()->school_id, $data['berkas']->getRealPath(), (int) $data['tahun']);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Berkas tidak bisa dibaca sebagai Excel ARKAS.');
        }

        return view('rekonsiliasi.hasil', compact('hasil'));
    }
}
