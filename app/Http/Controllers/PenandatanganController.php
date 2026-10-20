<?php

namespace App\Http\Controllers;

use App\Models\Penandatangan;
use App\Services\Aktivitas;
use App\Services\TtdGambar;
use Illuminate\Http\Request;

class PenandatanganController extends Controller
{
    public function index()
    {
        return view('utility.penandatangan', ['items' => Penandatangan::orderBy('id')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'jabatan' => 'required', 'nama' => 'required',
            'nip' => 'nullable', 'pangkat' => 'nullable',
        ]);
        Penandatangan::create($data);

        return back()->with('ok', 'Penandatangan ditambahkan.');
    }

    public function destroy(Penandatangan $penandatangan)
    {
        TtdGambar::hapus($penandatangan->ttd_path);
        $penandatangan->delete();

        return back()->with('ok', 'Penandatangan dihapus.');
    }

    /** Unggah gambar tanda tangan basah (PNG/JPG, maks. 1 MB). */
    public function unggahTtd(Request $r, Penandatangan $penandatangan)
    {
        $r->validate(['ttd' => 'required|image|mimes:png,jpg,jpeg|max:1024']);
        TtdGambar::hapus($penandatangan->ttd_path);
        $penandatangan->update(['ttd_path' => TtdGambar::simpan($r->file('ttd'), $penandatangan->id)]);
        Aktivitas::catat('penandatangan', "TTD {$penandatangan->nama} diunggah");

        return back()->with('ok', "Gambar TTD {$penandatangan->nama} tersimpan — akan tercetak otomatis di dokumen cetak & PDF.");
    }

    /** Hapus gambar TTD (tanggung jawab tetap kosong seperti semula). */
    public function hapusTtd(Penandatangan $penandatangan)
    {
        TtdGambar::hapus($penandatangan->ttd_path);
        $penandatangan->update(['ttd_path' => null]);

        return back()->with('ok', "Gambar TTD {$penandatangan->nama} dihapus.");
    }
}
