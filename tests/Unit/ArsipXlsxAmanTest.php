<?php

namespace Tests\Unit;

use App\Services\ArsipXlsxAman;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ArsipXlsxAmanTest extends TestCase
{
    public function test_fixture_xlsx_valid_lolos_pemeriksaan_arsip(): void
    {
        ArsipXlsxAman::periksa(base_path('tests/Fixtures/arkas-mini.xlsx'));
        $this->addToAssertionCount(1);
    }

    public function test_entri_jalur_traversal_di_arsip_ditolak(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-aman-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<types/>');
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        $zip->addFromString('../outside.xml', 'blocked');
        $zip->close();

        try {
            $this->expectException(RuntimeException::class);
            ArsipXlsxAman::periksa($path);
        } finally {
            @unlink($path);
        }
    }
}
