<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupTerjadwalService
{
    public function buat(): string
    {
        $password = trim((string) config('backup.password'));
        if ($password === '') {
            throw new RuntimeException('BACKUP_PASSWORD wajib diisi sebelum backup otomatis dijalankan.');
        }

        if (! class_exists(ZipArchive::class) || ! method_exists(ZipArchive::class, 'setEncryptionName')) {
            throw new RuntimeException('Ekstensi ZIP server wajib mendukung enkripsi AES-256 untuk backup otomatis.');
        }

        $dumpPath = tempnam(sys_get_temp_dir(), 'siapbosp_db_');
        $zipPath = tempnam(sys_get_temp_dir(), 'siapbosp_zip_');
        if ($dumpPath === false || $zipPath === false) {
            if (is_string($dumpPath)) {
                @unlink($dumpPath);
            }
            if (is_string($zipPath)) {
                @unlink($zipPath);
            }
            throw new RuntimeException('Tidak dapat membuat berkas sementara backup.');
        }

        @chmod($dumpPath, 0600);
        @chmod($zipPath, 0600);
        $maskAwal = umask(0077);

        try {
            $driver = (string) config('database.default');
            $entryName = match ($driver) {
                'sqlite' => $this->dumpSqlite($dumpPath),
                'pgsql' => $this->dumpPostgres($dumpPath),
                default => throw new RuntimeException("Backup otomatis belum mendukung driver database '{$driver}'."),
            };

            $archive = new ZipArchive;
            if ($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Arsip ZIP backup tidak dapat dibuat.');
            }
            if (! $archive->addFile($dumpPath, $entryName)) {
                $archive->close();
                throw new RuntimeException('Dump basis data tidak dapat ditambahkan ke arsip ZIP.');
            }
            if (! $archive->setEncryptionName($entryName, ZipArchive::EM_AES_256, $password)) {
                $archive->close();
                throw new RuntimeException('Enkripsi AES-256 gagal. Periksa dukungan libzip/PHP ZipArchive.');
            }
            $manifest = json_encode([
                'aplikasi' => 'SiapBOSP',
                'jenis' => 'backup-terjadwal',
                'dibuat' => now()->toIso8601String(),
                'driver' => $driver,
                'database' => $entryName,
                'terenkripsi' => true,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (! $archive->addFromString('MANIFEST.json', $manifest)) {
                $archive->close();
                throw new RuntimeException('Manifest backup tidak dapat ditambahkan ke arsip ZIP.');
            }
            if (! $archive->close()) {
                throw new RuntimeException('Arsip backup gagal ditutup dengan sempurna.');
            }

            $nama = 'backups/siapbosp-auto-'.now()->format('Ymd-His').'.zip';
            $disk = Storage::disk('local');
            $disk->makeDirectory('backups');
            @chmod($disk->path('backups'), 0700);
            $stream = fopen($zipPath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Arsip backup sementara tidak dapat dibaca.');
            }
            try {
                $tersimpan = $disk->put($nama, $stream);
            } finally {
                fclose($stream);
            }
            if (! $tersimpan) {
                throw new RuntimeException('Arsip backup gagal disimpan ke penyimpanan privat.');
            }
            @chmod($disk->path($nama), 0600);
            $this->hapusBackupKedaluwarsa();

            return $disk->path($nama);
        } finally {
            umask($maskAwal);
            @unlink($dumpPath);
            @unlink($zipPath);
        }
    }

    private function dumpSqlite(string $dumpPath): string
    {
        $database = (string) config('database.connections.sqlite.database');
        if ($database === '' || ($database !== ':memory:' && ! is_file($database))) {
            throw new RuntimeException('Berkas database SQLite tidak ditemukan; backup otomatis dibatalkan.');
        }

        $destination = DB::connection()->getPdo()->quote($dumpPath);
        $maskAwal = umask(0077);
        try {
            @unlink($dumpPath);
            DB::statement('VACUUM INTO '.$destination);
        } finally {
            umask($maskAwal);
        }
        @chmod($dumpPath, 0600);

        return 'database.sqlite';
    }

    private function dumpPostgres(string $dumpPath): string
    {
        $connection = (array) config('database.connections.pgsql');
        $process = new Process([
            'pg_dump',
            '--no-owner',
            '--no-privileges',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '5432'),
            '--username='.(string) ($connection['username'] ?? ''),
            '--dbname='.(string) ($connection['database'] ?? ''),
            '--file='.$dumpPath,
        ], base_path(), ['PGPASSWORD' => (string) ($connection['password'] ?? '')], null, 900);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($dumpPath) || filesize($dumpPath) < 100) {
            Log::warning('Backup otomatis pg_dump gagal', [
                'exit_code' => $process->getExitCode(),
                'stderr' => mb_substr($process->getErrorOutput(), 0, 2000),
            ]);
            @unlink($dumpPath);
            throw new RuntimeException('pg_dump gagal (exit '.$process->getExitCode().'); detail teknis dicatat di log server.');
        }
        @chmod($dumpPath, 0600);

        return 'database.sql';
    }

    private function hapusBackupKedaluwarsa(): void
    {
        $disk = Storage::disk('local');
        $batasWaktu = now()->subDays(max(1, (int) config('backup.retention_days', 30)))->timestamp;
        foreach ($disk->files('backups') as $path) {
            if (! str_starts_with(basename($path), 'siapbosp-auto-')) {
                continue;
            }
            if ($disk->lastModified($path) < $batasWaktu) {
                $disk->delete($path);
            }
        }
    }
}
