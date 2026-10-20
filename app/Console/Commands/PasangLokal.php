<?php

namespace App\Console\Commands;

use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use App\Support\KonfigLokal;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Pemasang aplikasi lokal (Tahap D).
 *
 * Menyiapkan: .env + APP_KEY, database SQLite, migrasi, master referensi,
 * sekolah (dari akun-sekolah.conf unduhan website), akun bendahara lokal,
 * dan token untuk Tarik ARKAS (agen.conf) — semuanya otomatis.
 *
 * Dijalankan oleh launcher-windows/Pasang-SiapBOSP.bat (klik 2x, sekali).
 */
class PasangLokal extends Command
{
    protected $signature = 'siapbos:pasang-lokal
        {--nama= : Nama bendahara/operator lokal}
        {--email= : Email login lokal}
        {--sandi= : Kata sandi login lokal (minimal 6 karakter)}
        {--npsn= : NPSN sekolah (bila tidak ada akun-sekolah.conf)}
        {--sekolah= : Nama sekolah (bila tidak ada akun-sekolah.conf)}
        {--tahun= : Tahun anggaran (default: tahun berjalan)}
        {--server-url= : Alamat server lokal untuk agen (default dari server.conf)}
        {--akun-conf=launcher-windows/akun-sekolah.conf : Berkas identitas sekolah}
        {--agen-conf=launcher-windows/agen.conf : Tujuan konfigurasi agen Tarik ARKAS}
        {--status : Hanya periksa apakah aplikasi sudah terpasang}';

    protected $description = 'Pasang SiapBOSP lokal di laptop (dipanggil oleh PASANG-SIAPBOSP.bat)';

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->status();
        }

        $tahun = (int) ($this->option('tahun') ?: now()->year);
        $akunPath = KonfigLokal::absolut($this->option('akun-conf'));
        $agenPath = KonfigLokal::absolut($this->option('agen-conf'));
        $conf = KonfigLokal::baca($akunPath);

        $npsn = trim((string) ($this->option('npsn') ?: ($conf['NPSN'] ?? '')));
        $sekolah = trim((string) ($this->option('sekolah') ?: ($conf['SEKOLAH'] ?? '')));
        $nama = trim((string) $this->option('nama'));
        $email = strtolower(trim((string) $this->option('email')));
        $sandi = (string) $this->option('sandi');

        if ($nama === '') {
            $this->error('Nama bendahara wajib diisi.');

            return 1;
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email login tidak valid: "'.$email.'".');

            return 1;
        }
        if (strlen($sandi) < 6) {
            $this->error('Kata sandi minimal 6 karakter.');

            return 1;
        }
        if ($npsn === '') {
            $this->error('NPSN sekolah tidak diketahui. Unduh paket ini dari website (per akun sekolah) atau isi kolom NPSN saat pemasangan.');

            return 1;
        }

        $this->siapkanEnv();

        // Folder sementara + cache: jaga-jaga bila alat ekstrak membuang folder kosong.
        foreach (['temp', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $d) {
            if (! is_dir(base_path($d))) {
                @mkdir(base_path($d), 0777, true);
            }
        }

        // php.ini bundel: pasang path ABSOLUT (relatif bermasalah saat CWD berbeda).
        $this->tulisPhpIni();

        // Database SQLite: buat berkasnya bila belum ada.
        $db = $this->pathDatabase();
        if ($db !== null && $db !== ':memory:' && ! is_file($db)) {
            @mkdir(dirname($db), 0777, true);
            touch($db);
            $this->line('Berkas database SQLite dibuat.');
        }

        $this->line('Menyiapkan tabel database...');
        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            $this->error('Migrasi database gagal — baca pesan di atas.');

            return 1;
        }
        $this->callSilent('db:seed', ['--class' => 'Database\\Seeders\\MasterSeeder', '--force' => true]);

        $sekolahRecord = School::updateOrCreate(['npsn' => $npsn], [
            'nama' => $sekolah !== '' ? $sekolah : ('Sekolah '.$npsn),
            'jenjang' => ($conf['JENJANG'] ?? '') ?: 'SD',
            'desa' => ($conf['DESA'] ?? '') ?: null,
            'kecamatan' => ($conf['KECAMATAN'] ?? '') ?: null,
            'kab_kota' => ($conf['KAB_KOTA'] ?? '') ?: 'Kota Makassar',
            'provinsi' => ($conf['PROVINSI'] ?? '') ?: 'Sulawesi Selatan',
            'alamat' => ($conf['ALAMAT'] ?? '') ?: null,
            'is_active' => true,
        ]);

        $operator = User::updateOrCreate(['email' => $email], [
            'name' => $nama,
            'password' => $sandi,
            'role' => 'operator',
            'school_id' => $sekolahRecord->id,
        ]);

        // Token lokal untuk agen Tarik ARKAS (menggantikan salin-tempel manual).
        $token = Str::random(64);
        $operator->forceFill(['api_token' => hash('sha256', $token)])->save();

        $serverUrl = trim((string) $this->option('server-url'));
        if ($serverUrl === '') {
            $serverConf = KonfigLokal::baca(dirname($agenPath).DIRECTORY_SEPARATOR.'server.conf');
            $serverUrl = trim((string) ($serverConf['SERVER_URL'] ?? ''));
            if (! preg_match('#^https?://#i', $serverUrl)) {
                $serverUrl = 'http://127.0.0.1:8000';
            }
        }
        KonfigLokal::tulisAgen($agenPath, $serverUrl, $token, $tahun);

        $this->newLine();
        $this->info('✅ SiapBOSP lokal siap dipakai.');
        $this->line('   Sekolah       : '.$sekolahRecord->nama.' ('.$npsn.')');
        $this->line('   Login lokal   : '.$email);
        $this->line('   Tahun aktif   : '.$tahun);
        $this->line('   Master        : '.Rekening::count().' rekening, '.RekeningPajak::count().' jenis pajak, '
            .Stage::where('tahun', $tahun)->count().' tahap tahun '.$tahun);
        $this->line('   Token Tarik-ARKAS: otomatis terpasang di launcher-windows/agen.conf.');
        if (empty($conf['PUSAT_URL']) || empty($conf['TOKEN'])) {
            $this->warn('   Catatan: identitas sinkron ke pusat belum ada — unduh paket ini dari website agar Sinkron-Pusat bisa dipakai.');
        }

        return 0;
    }

    /**
     * php.ini bundel: tulis path ABSOLUT untuk temp + log. Path relatif seperti
     * "temp" membuat php.exe dengan CWD berbeda (dijalankan `artisan serve`)
     * gagal membuat berkas sementara — unggahan dan SQLite ikut terdampak.
     */
    protected function tulisPhpIni(): void
    {
        $ini = base_path('php/php.ini');
        if (! is_file($ini)) {
            return;
        }

        $ganti = [
            'sys_temp_dir' => base_path('temp'),
            'upload_tmp_dir' => base_path('temp'),
            'error_log' => base_path('storage/logs/php-error.log'),
        ];

        $isi = @file_get_contents($ini);
        if ($isi === false) {
            return;
        }

        foreach ($ganti as $kunci => $nilai) {
            $baris = $kunci.' = "'.$nilai.'"';
            $pola = '/^\h*'.preg_quote($kunci, '/').'\h*=.*$/m';
            if (preg_match($pola, $isi)) {
                $isi = preg_replace_callback($pola, fn () => $baris, $isi, 1);
            } else {
                $isi = rtrim($isi, "\r\n")."\r\n".$baris."\r\n";
            }
        }

        @file_put_contents($ini, $isi);
        $this->line('php.ini bundel: path temp absolut dipasang.');
    }

    /** Buat .env dari template + kunci aplikasi bila belum ada. */
    protected function siapkanEnv(): void
    {
        $env = base_path('.env');
        if (! is_file($env)) {
            $tmpl = base_path('.env.portable');
            if (is_file($tmpl)) {
                copy($tmpl, $env);
                $this->line('Konfigurasi awal (.env) dibuat.');
            }
        }
        if (empty(config('app.key')) && ! app()->runningUnitTests()) {
            $this->callSilent('key:generate', ['--force' => true]);
            $this->line('Kunci aplikasi (APP_KEY) dibuat.');
        }
    }

    /**
     * Path SQLite absolut. `DB_DATABASE` yang relatif diukur dari folder
     * APLIKASI (base_path) — bukan folder kerja proses — supaya pemasang
     * aman dijalankan dari folder ekstrak mana pun (CWD bisa berbeda).
     */
    protected function pathDatabase(): ?string
    {
        if (config('database.default') !== 'sqlite') {
            return null;
        }
        $db = (string) config('database.connections.sqlite.database');
        if ($db === '') {
            return null;
        }
        if ($db === ':memory:') {
            return $db;
        }
        $absolut = preg_match('#^[A-Za-z]:[\\\\/]#', $db)
            || str_starts_with($db, '/')
            || str_starts_with($db, '\\\\');
        if ($absolut) {
            return $db;
        }
        $db = base_path($db);
        config(['database.connections.sqlite.database' => $db]);

        return $db;
    }

    /** Periksa kelengkapan pemasangan (dipakai SiapBOSP.bat). */
    protected function status(): int
    {
        $masalah = [];
        if (! is_file(base_path('.env'))) {
            $masalah[] = '.env belum ada';
        } elseif (empty(config('app.key'))) {
            $masalah[] = 'APP_KEY belum dibuat';
        }
        $db = $this->pathDatabase();
        if ($db !== null && $db !== ':memory:' && ! is_file($db)) {
            $masalah[] = 'database belum ada';
        } else {
            try {
                if (School::count() === 0) {
                    $masalah[] = 'sekolah belum terdaftar';
                }
                if (User::whereNotNull('school_id')->count() === 0) {
                    $masalah[] = 'akun bendahara belum dibuat';
                }
            } catch (\Throwable) {
                $masalah[] = 'tabel database belum lengkap';
            }
        }
        if ($masalah) {
            $this->error('Belum terpasang: '.implode(', ', $masalah).'.');

            return 1;
        }
        $this->info('Terpasang.');

        return 0;
    }
}
