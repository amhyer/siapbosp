<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\School;
use App\Services\Aktivitas;
use App\Services\JuknisBosp2026Service;
use App\Services\TarikArkasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'bolehSinkronPagu' => self::bolehSinkronPagu(),
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

        // Jumlah siswa & biaya satuan adalah data Dapodik yang diisi sekolah
        // (self-report) — boleh diperbarui. Yang TIDAK boleh adalah menulis
        // tabel `allocations` (pagu resmi): sinkron pagu hanya untuk Dinas,
        // atau aplikasi portabel satu sekolah. Halaman tinjauan Dinas sendiri
        // mengabaikan angka estimasi ini (lihat JuknisBosp2026Service).
        $pengguna = auth()->user();
        $bolehSinkronPagu = self::bolehSinkronPagu();

        $sekolah = $pengguna->school;
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

        $sebelum = $sekolah->only(['jumlah_siswa', 'satuan_biaya_bosp', 'jenjang']);
        $sekolah->update($payload);

        // Opsional: sinkronkan estimasi pagu siswa ke tabel alokasi BOSP Reguler
        // tahun aktif — HANYA Dinas/superadmin yang boleh menetapkan pagu.
        if ($bolehSinkronPagu && $r->boolean('sinkron_pagu') && $sekolah->estimasiPaguReguler() > 0) {
            $tahun = (int) session('tahun', now()->year);
            $bosReg = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
            Allocation::withoutGlobalScope('school')->updateOrCreate(
                ['school_id' => $sekolah->id, 'tahun' => $tahun, 'bos_type_id' => $bosReg->id, 'stage_id' => null],
                ['nilai' => $sekolah->estimasiPaguReguler()]
            );
            Aktivitas::catat('pagu', 'Pagu BOSP Reguler '.$tahun.' disinkronkan dari profil sekolah: '
                .rp($sekolah->estimasiPaguReguler()));
        }

        if ($sebelum != $sekolah->only(['jumlah_siswa', 'satuan_biaya_bosp', 'jenjang'])) {
            Aktivitas::catat('profil', 'Parameter dasar pagu diperbarui: jumlah siswa '
                .(int) ($sebelum['jumlah_siswa'] ?? 0).' → '.(int) $sekolah->jumlah_siswa
                .', biaya satuan '.(int) ($sebelum['satuan_biaya_bosp'] ?? 0).' → '.(int) $sekolah->satuan_biaya_bosp);
        }

        $catatan = (! $bolehSinkronPagu && $r->boolean('sinkron_pagu'))
            ? ' Sinkronisasi pagu diabaikan: pagu resmi ditetapkan Dinas melalui menu Alokasi.'
            : '';

        return back()->with('ok', 'Profil sekolah & parameter Aktivasi ARKAS 4 tersimpan.'.$catatan);
    }

    /**
     * Apakah pengguna berhak menulis pagu resmi (tabel `allocations`).
     *
     * Di server pusat hanya Dinas/superadmin; di aplikasi portabel (satu sekolah
     * per laptop) pemilik akun sekolah boleh menyinkronkan pagunya sendiri.
     */
    public static function bolehSinkronPagu(): bool
    {
        return in_array(auth()->user()?->role, ['superadmin', 'dinas'], true)
            || UnduhAplikasiController::isPortable();
    }

    /** Form ganti kata sandi (semua peran). */
    public function editPassword()
    {
        return view('utility.ganti-password');
    }

    public function updatePassword(Request $r)
    {
        $u = auth()->user();
        $data = $r->validate([
            'password_lama' => 'required',
            'password' => ['required', 'string', 'confirmed', new \App\Rules\SandiKuat($u->email, $u->name)],
        ], ['password.confirmed' => 'Konfirmasi kata sandi baru tidak sama.']);
        if (! Hash::check($data['password_lama'], $u->password)) {
            return back()->with('error', 'Kata sandi lama salah.');
        }
        if (Hash::check($data['password'], $u->password)) {
            return back()->with('error', 'Kata sandi baru tidak boleh sama dengan kata sandi lama.');
        }
        // Sandi baru juga mencabut sesi/perangkat lain: token API dihanguskan
        // dan hash sandi di sesi ini diperbarui agar pengguna tidak ikut
        // ter-logout oleh `PastikanAkunAktif`.
        $u->forceFill([
            'password' => $data['password'],
            'api_token' => null,
            'api_token_expires_at' => null,
            'api_token_used_at' => null,
            'remember_token' => Str::random(60),
            // Kewajiban ganti sandi (hasil impor/reset Dinas) selesai di sini.
            'must_change_password' => false,
        ])->save();
        $r->session()->put('password_hash_web', (string) $u->getAuthPassword());

        return back()->with('ok', 'Kata sandi berhasil diganti. Sesi di perangkat lain dan token API lama sudah dicabut.');
    }
}
