<?php

namespace App\Jobs;

use App\Models\ArkasImportRun;
use App\Models\User;
use App\Services\ArkasImportService;
use App\Services\CekIntegritasService;
use App\Services\RekonsiliasiService;
use App\Services\RkasCsvImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProsesImporArkas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public array $backoff = [30];

    public function __construct(public int $importRunId)
    {
        $this->onQueue('imports');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('arkas-import-run-'.$this->importRunId))
            ->expireAfter($this->timeout + 60)
            ->dontRelease()];
    }

    public function handle(): void
    {
        $run = ArkasImportRun::findOrFail($this->importRunId);
        if (! in_array($run->status, ['queued', 'processing'], true)) {
            Storage::disk('local')->delete($run->file_path);

            return;
        }
        $run->update(['status' => 'processing', 'started_at' => now(), 'error' => null]);

        try {
            $user = User::with('school')->findOrFail($run->user_id);
            if ($user->is_active === false || $user->school?->is_active === false) {
                throw new RuntimeException('Akun atau sekolah dinonaktifkan sebelum pekerjaan ARKAS berjalan.');
            }

            $path = Storage::disk('local')->path($run->file_path);
            if (! is_file($path)) {
                throw new RuntimeException('Berkas ARKAS tidak ditemukan di penyimpanan privat.');
            }

            Auth::login($user);
            try {
                $hasil = match ($run->jenis) {
                    ArkasImportRun::JENIS_IMPOR => ArkasImportService::import(
                        $path, $run->school_id, $run->user_id, $run->tahun
                    ),
                    ArkasImportRun::JENIS_INTEGRITAS => CekIntegritasService::bandingkan(
                        $path, $user->school ?? throw new RuntimeException('Sekolah akun tidak ditemukan.'),
                        (int) $run->tahun
                    ),
                    ArkasImportRun::JENIS_REKONSILIASI => RekonsiliasiService::bandingkan(
                        $run->school_id, $path, (int) $run->tahun
                    ),
                    ArkasImportRun::JENIS_RKAS_CSV => RkasCsvImportService::import(
                        $path, $run->school_id, (int) $run->tahun, $run->id
                    ),
                    default => throw new RuntimeException('Jenis pekerjaan ARKAS tidak dikenal.'),
                };
            } finally {
                Auth::logout();
            }

            $run->update([
                'status' => 'completed',
                'result' => $hasil,
                'completed_at' => now(),
            ]);
            Storage::disk('local')->delete($run->file_path);
        } catch (RuntimeException $exception) {
            Auth::logout();
            if ($exception instanceof \Illuminate\Database\QueryException || $exception instanceof \PDOException) {
                throw $exception;
            }
            report($exception);
            $run->update([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            Storage::disk('local')->delete($run->file_path);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Auth::logout();
        $run = ArkasImportRun::find($this->importRunId);
        if ($run) {
            $run->update([
                'status' => 'failed',
                'error' => 'Pekerjaan ARKAS gagal setelah beberapa percobaan. Hubungi administrator bila masalah berulang.',
                'completed_at' => now(),
            ]);
            Storage::disk('local')->delete($run->file_path);
        }
    }
}
