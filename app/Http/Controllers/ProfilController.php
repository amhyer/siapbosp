<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    public function edit()
    {
        return view('utility.profil', ['sekolah' => auth()->user()->school]);
    }

    public function update(Request $r)
    {
        $data = $r->validate([
            'nama' => 'required', 'jenjang' => 'required', 'desa' => 'nullable',
            'kecamatan' => 'nullable', 'kab_kota' => 'nullable', 'provinsi' => 'nullable', 'alamat' => 'nullable',
        ]);
        auth()->user()->school->update($data);

        return back()->with('ok', 'Profil sekolah tersimpan.');
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
