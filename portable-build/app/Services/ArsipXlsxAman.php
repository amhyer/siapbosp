<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/** Bounds ZIP metadata before PhpSpreadsheet expands an uploaded XLSX workbook. */
class ArsipXlsxAman
{
    private const MAX_ENTRIES = 512;

    private const MAX_TOTAL_UNCOMPRESSED = 209715200; // 200 MiB

    private const MAX_ENTRY_UNCOMPRESSED = 67108864; // 64 MiB

    private const MAX_COMPRESSION_RATIO = 200;

    public static function periksa(string $path): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Dukungan ZIP tidak tersedia untuk memeriksa file XLSX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Berkas XLSX bukan arsip Excel yang dapat dibaca.');
        }

        try {
            if ($zip->numFiles < 3 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('Struktur XLSX tidak valid atau memiliki terlalu banyak entri.');
            }

            $total = 0;
            $names = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if (! is_array($entry) || ! isset($entry['name'], $entry['size'], $entry['comp_size'])) {
                    throw new RuntimeException('Metadata arsip XLSX tidak valid.');
                }
                $name = str_replace('\\', '/', (string) $entry['name']);
                if ($name === '' || str_starts_with($name, '/') || str_contains($name, "\0")
                    || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                    throw new RuntimeException('Arsip XLSX memuat jalur file yang tidak aman.');
                }
                if (isset($names[$name])) {
                    throw new RuntimeException('Arsip XLSX memuat nama entri duplikat.');
                }
                $names[$name] = true;
                $size = (int) $entry['size'];
                $compressed = (int) $entry['comp_size'];
                if ($size < 0 || $compressed < 0 || $size > self::MAX_ENTRY_UNCOMPRESSED) {
                    throw new RuntimeException('Entri di dalam XLSX melampaui batas ukuran.');
                }
                $total += $size;
                if ($total > self::MAX_TOTAL_UNCOMPRESSED) {
                    throw new RuntimeException('Isi XLSX setelah diekstrak melampaui batas 200 MB.');
                }
                if ($size > 1048576 && ($compressed === 0 || $size / $compressed > self::MAX_COMPRESSION_RATIO)) {
                    throw new RuntimeException('Rasio kompresi XLSX terlalu tinggi; arsip ditolak.');
                }
            }

            if (! isset($names['[Content_Types].xml'], $names['xl/workbook.xml'])) {
                throw new RuntimeException('Berkas tidak berisi struktur workbook XLSX yang valid.');
            }
        } finally {
            $zip->close();
        }
    }
}
