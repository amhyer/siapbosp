<?php

namespace Tests\Feature;

use Tests\TestCase;
use ZipArchive;

/**
 * Regresi bug pembangun paket (18 Okt 2026): `ZipArchive::addFile()` bersifat
 * LAZY — isi berkas sumber baru disalin saat `close()`, bukan saat `addFile()`.
 * Menghapus berkas sumber sebelum `close()` membuat `close()` gagal dengan
 * "Can't open file: No such file or directory".
 *
 * Karena itu `siapbos:paket-portable` WAJIB menghapus folder temp ekstraksi
 * agen SETELAH `$zip->close()` (lihat komentar di PaketPortable.php).
 */
class PaketPortableTest extends TestCase
{
    public function test_close_zip_butuh_berkas_sumber_masih_ada(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uji-zip-'.uniqid();
        mkdir($dir, 0777, true);
        try {
            $src = $dir.DIRECTORY_SEPARATOR.'sumber.bin';
            $out = $dir.DIRECTORY_SEPARATOR.'hasil.zip';
            file_put_contents($src, random_bytes(20000));

            $z = new ZipArchive();
            $this->assertTrue($z->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            $this->assertTrue($z->addFile($src, 'sumber.bin'));

            // Sebelum close: isi belum ditulis (lazy).
            clearstatcache();
            $this->assertFalse(is_file($out), 'ZipArchive ternyata menulis isi lebih awal — komentar PaketPortable.php perlu ditinjau.');

            // Sumber dihapus lebih awal — inilah pola yang dulu membuat build gagal.
            unlink($src);
            $this->assertFalse(@$z->close());
            clearstatcache();
            $this->assertFalse(is_file($out));
        } finally {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                is_file($f) && @unlink($f);
            }
            @rmdir($dir);
        }
    }
}
