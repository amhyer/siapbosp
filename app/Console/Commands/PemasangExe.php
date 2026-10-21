<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Bangun pemasang satu-berkas `SiapBOSP-Setup-v12.exe` dari ZIP pasang portabel.
 *
 * Cara kerja (pola SFX sederhana — tanpa dependensi/unduhan tambahan):
 *  1. Compiler C# bawaan Windows (`csc.exe`) membangun lulann EXE kecil (±24 KB,
 *     lengkap ikon + program ekstraksi).
 *  2. Perintah ini menjahit payload ZIP (±73 MB) DI BELAKANG EXE ditutup
 *     trailer: [int64 panjang payload][magic "SIAPBOSPACK"].
 *  3. Saat diklik 2x, EXE membaca payload dari dirinya sendiri, mengekstrak ke
 *     %TEMP%\SiapBOSP-Setup, menjalankan PASANG-SIAPBORB.bat di dalamnya
 *     (yang menyalin aplikasi ke %LocalAppData%\Programs\SiapBOSP,
 *     menyiapkan database/akun/token), lalu membersihkan folder payload.
 *  - Identitas sekolah: bila `akun-sekolah.conf` berada DI SEBELAH EXE (unduhan
 *    "Unduh Identitas" dari web), otomatis disuntik ke payload sehingga pemasang
 *    tidak menanya NPSN; tanpa itu, pemasang bertanya manual.
 *
 * Jalankan ulang setiap kali ZIP pasang diperbarui:
 *   php artisan siapbos:paket-portable  →  php artisan siapbos:pemasang-exe
 */
class PemasangExe extends Command
{
    protected $signature = 'siapbos:pemasang-exe
        {--zip=storage/app/paket/SiapBOSP-Portable-v12.zip : ZIP pasang yang dibundel}
        {--nama=SiapBOSP-Setup-v12.exe : Nama berkas hasil}
        {--ikon=launcher-windows/SiapBOSP.ico : Ikon EXE}';

    protected $description = 'Bangun pemasang satu-berkas EXE (SiapBOSP-Setup) dari ZIP pasang portabel';

    /** Trailer di belakang EXE: [payload][int64 panjang little-endian][magic 11 byte]. */
    public const MAGIC = 'SIAPBOSPACK';

    public function handle(): int
    {
        $zip = base_path((string) $this->option('zip'));
        if (! is_file($zip)) {
            $this->error('ZIP pasang belum ada: '.$this->option('zip'));
            $this->line('  Bangun dulu: php artisan siapbos:paket-portable');

            return 1;
        }

        $ikon = base_path((string) $this->option('ikon'));
        $csc = $this->temukanCsc();
        if ($csc === null) {
            $this->error('Compiler C# bawaan Windows (csc.exe) tidak ditemukan.');

            return 1;
        }
        $referensi = $this->referensiCompression();
        if ($referensi === null) {
            $this->error('Referensi System.IO.Compression(.FileSystem) tidak ditemukan di GAC.');

            return 1;
        }

        // 1) Bangun lulann EXE kecil dengan csc.
        $dirTemp = storage_path('app/paket/tmp-pemasang');
        @mkdir($dirTemp, 0777, true);
        $csPath = $dirTemp.DIRECTORY_SEPARATOR.'Pemasang.cs';
        file_put_contents($csPath, $this->sumberCsharp());
        $kulit = $dirTemp.DIRECTORY_SEPARATOR.'kulit-pemasang.exe';
        @unlink($kulit);

        $argum = [
            escapeshellarg($csc),
            '/nologo', '/optimize+', '/target:exe',
            '/out:'.escapeshellarg($kulit),
        ];
        if (is_file($ikon)) {
            $argum[] = '/win32icon:'.escapeshellarg($ikon);
        }
        foreach ($referensi as $lib) {
            $argum[] = '/r:'.escapeshellarg($lib);
        }
        $argum[] = escapeshellarg($csPath);

        exec(implode(' ', $argum).' 2>&1', $out, $kode);
        foreach ($out as $o) {
            $this->line('  '.$o);
        }

        if ($kode !== 0 || ! is_file($kulit) || filesize($kulit) < 10000) {
            $this->error('Kompilasi kulit pemasang gagal (kode '.$kode.').');

            return 1;
        }

        // 2) Jahit payload + trailer di belakang kulit EXE.
        $hasil = storage_path('app/paket/'.(string) $this->option('nama'));
        @unlink($hasil);
        $oku = fopen($kulit, 'rb');
        $penulis = fopen($hasil, 'wb');
        stream_copy_to_stream($oku, $penulis);
        fclose($oku);
        $payload = fopen($zip, 'rb');
        stream_copy_to_stream($payload, $penulis);
        fclose($payload);
        fwrite($penulis, pack('P', filesize($zip)));
        fwrite($penulis, self::MAGIC);
        fclose($penulis);
        unlink($kulit);
        unlink($csPath);
        @rmdir($dirTemp);

        $mb = round(filesize($hasil) / 1048576, 1);
        $this->info('OK: '.$this->option('nama')." ({$mb} MB)");
        $this->line('   Payload   : '.basename($zip).' ('.round(filesize($zip) / 1048576, 1).' MB)');
        $this->line('   Ikon      : '.(is_file($ikon) ? basename($ikon) : 'bawaan'));
        $this->line('   Selalu unduh berkas "akun-sekolah.conf" di sebelah EXE agar pasang otomatis.');

        return 0;
    }

    /** Path `csc.exe` compiler bawaan .NET Framework (tersedia di semua Windows modern). */
    protected function temukanCsc(): ?string
    {
        foreach (['Framework64', 'Framework'] as $ada) {
            $dir = getenv('WINDIR').DIRECTORY_SEPARATOR.'Microsoft.NET'.DIRECTORY_SEPARATOR.$ada;
            if (! is_dir($dir)) {
                continue;
            }
            foreach (array_reverse((array) @glob($dir.DIRECTORY_SEPARATOR.'v4.0.*')) as $d) {
                if (is_file($d.DIRECTORY_SEPARATOR.'csc.exe')) {
                    return $d.DIRECTORY_SEPARATOR.'csc.exe';
                }
            }
        }

        return null;
    }

    /** Referensi System.IO.Compression(.FileSystem) dari GAC. */
    protected function referensiCompression(): ?array
    {
        $gac = getenv('WINDIR').DIRECTORY_SEPARATOR.'Microsoft.NET'.DIRECTORY_SEPARATOR.'assembly'.DIRECTORY_SEPARATOR.'GAC_MSIL';
        $temu = [];
        foreach (['System.IO.Compression', 'System.IO.Compression.FileSystem'] as $asm) {
            foreach ((array) @glob($gac.DIRECTORY_SEPARATOR.$asm.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.$asm.'.dll') as $file) {
                $temu[$asm] = $file;
                break;
            }
        }
        if (count($temu) < 2) {
            return null;
        }

        return [$temu['System.IO.Compression'], $temu['System.IO.Compression.FileSystem']];
    }

    /** Program C# lulann pemasang (dieksekusi saat EXE dipakai). */
    protected function sumberCsharp(): string
    {
        return <<<'CSHARP'
using System;
using System.Diagnostics;
using System.IO;
using System.IO.Compression;
using System.Text;

// Pemasang SiapBOSP satu-berkas — dibangun otomatis oleh `siapbos:pemasang-exe`.
// Tugasnya: baca payload ZIP di belakang diri sendiri (trailer
// [int64 panjang][magic "SIAPBOSPACK"]), ekstrak ke %TEMP%\SiapBOSP-Setup,
// suntikkan akun-sekolah.conf (bila ada di sebelah EXE), jalankan
// PASANG-SIAPBOSP.bat, lalu bersihkan folder payload.
internal static class Pemasang
{
    static int Main()
    {
        var payload = Path.Combine(Path.GetTempPath(), "SiapBOSP-Setup");
        try
        {
            SiapkanFolderPayload(payload);
            EkstrakPayload(payload);

            var conf = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "akun-sekolah.conf");
            if (File.Exists(conf))
            {
                var tujuanConf = Path.Combine(payload, "SiapBOSP", "launcher-windows", "akun-sekolah.conf");
                Directory.CreateDirectory(Path.GetDirectoryName(tujuanConf));
                File.Copy(conf, tujuanConf, true);
            }

            var info = new ProcessStartInfo
            {
                FileName = "cmd.exe",
                Arguments = "/c \"" + Path.Combine(payload, "SiapBOSP", "PASANG-SIAPBOSP.bat") + "\"",
                UseShellExecute = false,
            };
            using (var p = Process.Start(info))
            {
                if (p == null)
                {
                    Console.Error.WriteLine("[GAGAL] Tidak bisa menjalankan pemasang.");
                    return 1;
                }
                p.WaitForExit();
                int rc = p.ExitCode;
                try
                {
                    Directory.Delete(payload, true);
                }
                catch
                {
                    // berkas payload masih terkunci di pemakaian ini — folder di
                    // %TEMP% akan dibersihkan otomatis pada pemakaian berikutnya
                }
                return rc;
            }
        }
        catch (Exception e)
        {
            Console.Error.WriteLine("[GAGAL] " + e.Message);
            return 1;
        }
    }

    static Stream BukaPayload()
    {
        var exe = typeof(Pemasang).Assembly.Location;
        const int trailer = 19; // 8 byte panjang + 11 byte magic
        using (var fs = File.OpenRead(exe))
        {
            if (fs.Length <= trailer)
            {
                throw new IOException("Pemasang rusak: payload tidak ada.");
            }
            fs.Seek(-trailer, SeekOrigin.End);
            var isi = new byte[trailer];
            fs.Read(isi, 0, isi.Length);
            var magic = Encoding.ASCII.GetString(isi, 8, 11);
            if (magic != "SIAPBOSPACK")
            {
                throw new IOException("Pemasang rusak: penanda payload tidak sah.");
            }
            long panjang = BitConverter.ToInt64(isi, 0);
            var mulai = fs.Length - trailer - panjang;
            if (mulai < 0 || panjang < 0)
            {
                throw new IOException("Pemasang rusak: ukuran payload tidak sah.");
            }
            var data = new byte[panjang];
            fs.Seek(mulai, SeekOrigin.Begin);
            var sudah = 0;
            while (sudah < panjang)
            {
                int baca = fs.Read(data, sudah, (int)(panjang - sudah));
                if (baca <= 0)
                {
                    throw new IOException("Pemasang rusak: payload terpendam.");
                }
                sudah += baca;
            }
            return new MemoryStream(data, false);
        }
    }

    static void SiapkanFolderPayload(string payload)
    {
        if (!Directory.Exists(payload))
        {
            Directory.CreateDirectory(payload);
            return;
        }
        try
        {
            Directory.Delete(payload, true);
        }
        catch
        {
            // payload lama terkunci — dibiarkan berkasnya tertimpa
        }
        if (!Directory.Exists(payload))
        {
            Directory.CreateDirectory(payload);
        }
    }

    static void EkstrakPayload(string payload)
    {
        using (var s = BukaPayload())
        using (var arsip = new ZipArchive(s, ZipArchiveMode.Read))
        {
            foreach (var e in arsip.Entries)
            {
                // entri folder ditandai FullName berakhiran "/": hanya buat foldernya,
                // karena folder kosong (bootstrap/cache, temp, storage/framework/...)
                // wajib tetap ada sebelum artisan pertama berjalan.
                var pathDalam = e.FullName.Replace('/', '\\');
                if (pathDalam.EndsWith("\\") || pathDalam.EndsWith("/"))
                {
                    Directory.CreateDirectory(Path.Combine(payload, pathDalam.TrimEnd('\\', '/')));
                    continue;
                }
                var tujuan = Path.Combine(payload, pathDalam);
                var dir = Path.GetDirectoryName(tujuan);
                if (!string.IsNullOrEmpty(dir) && !Directory.Exists(dir))
                    Directory.CreateDirectory(dir);
                e.ExtractToFile(tujuan, true);
            }
        }
    }
}
CSHARP;
    }
}
