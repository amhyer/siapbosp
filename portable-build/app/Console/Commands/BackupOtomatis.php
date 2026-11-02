<?php

namespace App\Console\Commands;

use App\Services\BackupTerjadwalService;
use Illuminate\Console\Command;

class BackupOtomatis extends Command
{
    protected $signature = 'backup:automatic';

    protected $description = 'Buat backup database terenkripsi dan bersihkan backup otomatis yang kedaluwarsa';

    public function handle(BackupTerjadwalService $backup): int
    {
        $path = $backup->buat();
        $this->info('Backup terenkripsi tersimpan: '.$path);

        return self::SUCCESS;
    }
}
