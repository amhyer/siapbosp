<?php

namespace App\Console\Commands;

use App\Models\ArkasImportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BersihkanImporArkas extends Command
{
    protected $signature = 'arkas-imports:prune';

    protected $description = 'Tandai antrean ARKAS yang kedaluwarsa dan hapus file privat serta riwayat lama';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $staleCount = 0;
        ArkasImportRun::query()
            ->whereIn('status', ['queued', 'processing'])
            ->where('created_at', '<', now()->subDay())
            ->chunkById(500, function ($runs) use ($disk, &$staleCount): void {
                foreach ($runs as $run) {
                    $run->update([
                        'status' => 'failed',
                        'error' => 'Antrean tidak diproses dalam 24 jam. Hubungi administrator; jangan unggah ulang sebelum status ini diperiksa.',
                        'completed_at' => now(),
                    ]);
                    $disk->delete($run->file_path);
                    $staleCount++;
                }
            });

        $expiredCount = 0;
        $retentionCutoff = now()->subDays(90);
        ArkasImportRun::query()
            ->whereIn('status', ['completed', 'failed'])
            ->chunkById(500, function ($runs) use ($disk, $retentionCutoff, &$expiredCount): void {
                foreach ($runs as $run) {
                    // Run selesai tidak lagi memerlukan sumber unggahan. Ini juga
                    // menutup celah file tertinggal bila worker mati pasca-update status.
                    $disk->delete($run->file_path);
                    if ($run->created_at->lt($retentionCutoff)) {
                        $run->delete();
                        $expiredCount++;
                    }
                }
            });

        // Bersihkan file yatim akibat penghapusan akun/sekolah atau proses
        // web yang mati di antara penyimpanan file dan pembuatan record antrean.
        $knownPaths = array_fill_keys(ArkasImportRun::query()
            ->whereIn('status', ['queued', 'processing'])
            ->pluck('file_path')->all(), true);
        foreach ($disk->allFiles('imports/arkas') as $path) {
            if (isset($knownPaths[$path])) {
                continue;
            }
            try {
                if ($disk->lastModified($path) < now()->subDay()->timestamp) {
                    $disk->delete($path);
                }
            } catch (\Throwable) {
                // Berkas bisa saja dihapus bersamaan oleh worker lain.
            }
        }

        $this->info('Antrean kedaluwarsa: '.$staleCount.'; riwayat lama: '.$expiredCount.'.');

        return self::SUCCESS;
    }
}
