<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\School;
use App\Services\JuknisBosp2026Service;
use App\Services\TarikArkasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    public function edit()
    {
        $sekolah = auth()->user()->school;
        $tahun = (int) session('tahun', now()->year);

        return view('utility.profil', [
            'sekolah' => $sekolah,
            'jenjangList' => School::JENJANG_LIST,
            'aturanJuknis' => JuknisBosp2026Service::batasProporsi($sekolah, 'BOS Reguler'),
            'paguAktif' => JuknisBosp2026Service::paguSumberDana($sekolah, $tahun, 'BOS Reguler'),
            'tahun' => $tahun,
            'tarik' => TarikArkasService::status(),
        ]);
    }

    public function update(Request $r)
    {
        $data = $r->validate([
            'nama' => 'required|string|max:255',
            'jenjang' => 'required|string|max:20',
            'status_sekolah' => 'nullable|in:negeri,swasta',
            'desa' => 'nullable|string|max:120',
            'kecamatan' => 'nullable|string|max:120',
            'kab_kota' => 'nullable|string|max:120',
            'provinsi' => 'nullable|string|max:120',
            'alamat' => 'nullable|string|max:255',
            'jumlah_siswa' => 'nullable|integer|min:0',
            'satuan_biaya_bosp' => 'nullable|numeric|min:0',
            'nama_komite' => 'nullable|string|max:150',
            'email_komite' => 'nullable|email|max:150',
            'akhir_jabatan_bulan' => 'nullable|integer|min:1|max:12',
        ]);

        $sekolah = auth()->user()->school;
        $payload = [
            'nama' => $data['nama'],
            'jenjang' => $data['jenjang'],
            'desa' => $data['desa'] ?? $sekolah->desa,
            'kecamatan' => $data['kecamatan'] ?? $sekolah->kecamatan,
            'kab_kota' => $data['kab_kota'] ?? ($sekolah->kab_kota ?? ''),
            'provinsi' => $data['provinsi'] ?? ($sekolah->provinsi ?? ''),
            'alamat' => $data['alamat'] ?? $sekolah->alamat,
        ];
        if (array_key_exists('status_sekolah', $data) && $data['status_sekolah']) {
            $payload['status_sekolah'] = $data['status_sekolah'];
        }
        if (array_key_exists('jumlah_siswa', $data) && $data['jumlah_siswa'] !== null) {
            $payload['jumlah_siswa'] = (int) $data['jumlah_siswa'];
        }
        if (array_key_exists('satuan_biaya_bosp', $data) && $data['satuan_biaya_bosp'] !== null) {
            $payload['satuan_biaya_bosp'] = (int) $data['satuan_biaya_bosp'];
        }
        if (array_key_exists('nama_komite', $data)) {
            $payload['nama_komite'] = $data['nama_komite'];
        }
        if (array_key_exists('email_komite', $data)) {
            $payload['email_komite'] = $data['email_komite'];
        }
        if (array_key_exists('akhir_jabatan_bulan', $data) && $data['akhir_jabatan_bulan'] !== null) {
            $payload['akhir_jabatan_bulan'] = (int) $data['akhir_jabatan_bulan'];
        }

        $sekolah->update($payload);

        // Opsional: sinkronkan estimasi pagu siswa ke tabel alokasi BOSP Reguler tahun aktif bila diminta
        if ($r->boolean('sinkron_pagu') && $sekolah->estimasiPaguReguler() > 0) {
            $tahun = (int) session('tahun', now()->year);
            $bosReg = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
            Allocation::withoutGlobalScope('school')->updateOrCreate(
                ['school_id' => $sekolah->id, 'tahun' => $tahun, 'bos_type_id' => $bosReg->id, 'stage_id' => null],
                ['nilai' => $sekolah->estimasiPaguReguler()]
            );
        }

        return back()->with('ok', 'Profil sekolah & parameter Aktivasi ARKAS 4 tersimpan.');
    }

    /** Form ganti kata sandi (semua peran). */
    public function editPassword()
    {
        return view('utility.ganti-password');
    }

    public function updatePassword(Request $r)
    {
        $data = $r->validate([
            'password_lama' => 'required',
            'password' => 'required|min:6|confirmed',
        ], ['password.confirmed' => 'Konfirmasi kata sandi baru tidak sama.']);
        $u = auth()->user();
        if (! Hash::check($data['password_lama'], $u->password)) {
            return back()->with('error', 'Kata sandi lama salah.');
        }
        $u->update(['password' => $data['password']]);

        return back()->with('ok', 'Kata sandi berhasil diganti. Gunakan yang baru pada login berikutnya.');
    }
}
