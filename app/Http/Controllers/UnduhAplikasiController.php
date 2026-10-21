<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Unduh aplikasi SiapBOSP portable per akun sekolah (Tahap A3).
 *
 * Tiga berkas unduhan:
 *  - `unduh()`          — ZIP pasang lengkap; identitas sekolah disuntikkan ke dalamnya;
 *  - `unduhPemasang()`  — pemasang satu-berkas SiapBOSP-Setup-v12.exe (tanpa identitas);
 *  - `unduhIdentitas()` — berkas `akun-sekolah.conf` terpisah (NPSN + token API baru +
 *                         URL pusat) untuk diletakkan di sebelah pemasang EXE.
 *
 * Setiap unduhan yang memuat token mengaktifkan token API baru — token lama
 * otomatis nonaktif (sama seperti ganti token di Profil).
 */
class UnduhAplikasiController extends Controller
{
    public const BERKAS_INDUK = 'SiapBOSP-Portable-v12.zip';

    public const BERKAS_PEMASANG = 'SiapBOSP-Setup-v12.exe';

    /** Laptop ini menjalankan aplikasi portabel (folder "php" bundel ikut pemasangan) — unduhan paket disembunyikan/dipandu ulang. */
    public static function isPortable(): bool
    {
        return is_file(base_path('php/php.exe'));
    }

    /**
     * Folder artefak paket unduhan.
     *
     * Bisa dialihkan lewat `config('siapbos.paket_dir')` — dipakai pengujian
     * agar memakai folder semu sendiri dan tidak menyentuh berkas build asli.
     */
    protected function dirPaket(): string
    {
        return config('siapbos.paket_dir', storage_path('app/paket'));
    }

    public function unduh()
    {
        $u = auth()->user();
        if (! $u->school_id) {
            return back()->with('error', 'Akun Anda tidak terikat ke sekolah mana pun.');
        }
        $induk = $this->dirPaket().'/'.self::BERKAS_INDUK;
        if (! is_file($induk)) {
            if (self::isPortable()) {
                return back()->with('error', 'Anda memakai aplikasi portabel di laptop ini — paket pemasangan tidak ikut di dalamnya. Unduh paket dari website pusat SiapBOSP bila akan memasang di laptop lain.');
            }
            abort(404, 'Paket aplikasi belum tersedia di server. Hubungi Dinas.');
        }

        $sekolah = $u->school;
        $conf = $this->identitas($u, $this->rotasiToken($u));

        $tmp = tempnam(sys_get_temp_dir(), 'siapbosp').'.zip';
        copy($induk, $tmp);
        $zip = new ZipArchive;
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            abort(500, 'Paket aplikasi rusak. Hubungi Dinas.');
        }
        $zip->addFromString('SiapBOSP/launcher-windows/akun-sekolah.conf', $conf);
        $zip->close();

        return response()->download($tmp, 'SiapBOSP-Portable-v12-'.$sekolah->npsn.'.zip')
            ->deleteFileAfterSend(true);
    }

    /**
     * Pemasang satu-berkas (EXE) untuk laptop bendahara.
     * Identitas sekolah tidak menyatu di dalamnya — pasangkan dengan unduhIdentitas()
     * (berkas `akun-sekolah.conf` disimpan di folder yang sama dengan pemasang).
     */
    public function unduhPemasang()
    {
        $u = auth()->user();
        if (! $u->school_id) {
            return back()->with('error', 'Akun Anda tidak terikat ke sekolah mana pun.');
        }
        $exe = $this->dirPaket().'/'.self::BERKAS_PEMASANG;
        if (! is_file($exe)) {
            if (self::isPortable()) {
                return back()->with('error', 'Anda memakai aplikasi portabel di laptop ini — pemasang EXE tidak ikut di dalamnya. Unduh pemasang dari website pusat SiapBOSP bila akan memasang di laptop lain.');
            }
            abort(404, 'Pemasang EXE belum tersedia di server — pakai cara ZIP dulu. Hubungi Dinas.');
        }

        return response()->download($exe, self::BERKAS_PEMASANG);
    }

    /** Berkas identitas sekolah untuk alur pemasang EXE (token API baru dibuat). */
    public function unduhIdentitas()
    {
        $u = auth()->user();
        if (! $u->school_id) {
            return back()->with('error', 'Akun Anda tidak terikat ke sekolah mana pun.');
        }
        $conf = $this->identitas($u, $this->rotasiToken($u));

        return response($conf, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="akun-sekolah.conf"',
        ]);
    }

    /** Isi `akun-sekolah.conf` (format KEY=VALUE — dibaca KonfigLokal + pemasang). */
    protected function identitas(User $u, string $token): string
    {
        $sekolah = $u->school;

        return implode("\n", [
            '# Identitas sinkronisasi + pemasangan (dibuat otomatis saat unduh — JANGAN disebar)',
            'NPSN='.$sekolah->npsn,
            'SEKOLAH='.$sekolah->nama,
            'JENJANG='.$sekolah->jenjang,
            'DESA='.($sekolah->desa ?? ''),
            'KECAMATAN='.($sekolah->kecamatan ?? ''),
            'KAB_KOTA='.($sekolah->kab_kota ?? ''),
            'PROVINSI='.($sekolah->provinsi ?? ''),
            'ALAMAT='.($sekolah->alamat ?? ''),
            'TAHUN='.now()->year,
            'PUSAT_URL='.config('app.url'),
            'TOKEN='.$token,
            '# Token lama Anda otomatis nonaktif setelah unduhan ini.',
        ])."\n";
    }

    /** Buat token API baru untuk pengguna (token lama otomatis nonaktif). */
    protected function rotasiToken(User $u): string
    {
        $token = Str::random(64);
        $u->forceFill(['api_token' => hash('sha256', $token)])->save();

        return $token;
    }
}
