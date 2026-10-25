<?php

namespace Tests\Feature;

use App\Services\BackupTerjadwalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTerjadwalTest extends TestCase
{
    public function test_backup_sqlite_terenkripsi_dan_retensi_menghapus_berkas_lama(): void
    {
        Storage::fake('local');
        config(['backup.password' => 'BackupMakassar2026!']);
        config(['backup.retention_days' => 30]);
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::connection('sqlite')->getPdo();
        $disk = Storage::disk('local');
        $disk->put('backups/siapbosp-auto-lama.zip', 'cadangan lama');
        touch($disk->path('backups/siapbosp-auto-lama.zip'), now()->subDays(45)->timestamp);

        $path = app(BackupTerjadwalService::class)->buat();

        $this->assertFileExists($path);
        $this->assertFalse($disk->exists('backups/siapbosp-auto-lama.zip'));
        $archive = new ZipArchive;
        $this->assertSame(true, $archive->open($path));
        $archive->setPassword('BackupMakassar2026!');
        $this->assertIsString($archive->getFromName('MANIFEST.json'));
        $isiDatabase = $archive->getFromName('database.sqlite');
        $this->assertIsString($isiDatabase);
        $this->assertNotSame('', $isiDatabase);
        $archive->close();
    }
}
