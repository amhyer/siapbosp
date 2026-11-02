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

    /** Simpan formulir modal Data Penanggung Jawab ala ARKAS 4. */
    public function simpanPenanggungJawab(Request $r)
    {
        $school = auth()->user()->school;
        if (! $school) {
            return back()->with('error', 'Sekolah tidak ditemukan.');
        }

        $data = $r->validate([
            'nama_kepsek' => 'required|string|max:150',
            'nip_kepsek' => 'nullable|string|max:50',
            'nama_bendahara' => 'required|string|max:150',
            'nip_bendahara' => 'nullable|string|max:50',
            'nama_komite' => 'nullable|string|max:150',
            'email_komite' => 'nullable|email|max:150',
            'akhir_jabatan_bulan' => 'nullable|integer|min:1|max:12',
        ]);

        Penandatangan::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $school->id, 'jabatan' => 'Kepala Sekolah'],
            ['nama' => $data['nama_kepsek'], 'nip' => $data['nip_kepsek'] ?? null]
        );

        Penandatangan::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $school->id, 'jabatan' => 'Bendahara'],
            ['nama' => $data['nama_bendahara'], 'nip' => $data['nip_bendahara'] ?? null]
        );

        $school->update([
            'nama_komite' => $data['nama_komite'] ?? null,
            'email_komite' => $data['email_komite'] ?? null,
            'akhir_jabatan_bulan' => $data['akhir_jabatan_bulan'] ?? 12,
        ]);

        if (! empty($data['nama_komite'])) {
            Penandatangan::withoutGlobalScope('school')->updateOrCreate(
                ['school_id' => $school->id, 'jabatan' => 'Komite Sekolah'],
                ['nama' => $data['nama_komite']]
            );
        }

        Aktivitas::catat('penandatangan', 'Data Penanggung Jawab diperbarui');

        return back()->with('ok', 'Data Penanggung Jawab berhasil diperbarui.');
    }
}
