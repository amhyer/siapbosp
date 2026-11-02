<?php

namespace App\Services;

use App\Exceptions\ArkasRunAktifException;
use App\Jobs\ProsesImporArkas;
use App\Models\ArkasImportRun;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AntreanArkasService
{
    public function daftarkan(
        UploadedFile|string $berkas,
        User $user,
        string $jenis,
        ?int $tahun,
        ?string $sumber = null
    ): ArkasImportRun {
        if (! in_array($jenis, [
            ArkasImportRun::JENIS_IMPOR,
            ArkasImportRun::JENIS_INTEGRITAS,
            ArkasImportRun::JENIS_REKONSILIASI,
            ArkasImportRun::JENIS_RKAS_CSV,
        ], true)) {
            throw new RuntimeException('Jenis pekerjaan ARKAS tidak dikenal.');
        }
        if (! $user->school_id) {
            throw new RuntimeException('Akun Anda tidak terikat ke sekolah mana pun.');
        }
        if (ArkasImportRun::query()
            ->where('user_id', $user->id)
            ->where('school_id', $user->school_id)
            ->whereIn('status', ['queued', 'processing'])
            ->exists()) {
            throw new ArkasRunAktifException('Masih ada pekerjaan ARKAS yang berjalan. Tunggu hingga selesai sebelum mengunggah lagi.');
        }

        $sourcePath = $berkas instanceof UploadedFile ? $berkas->getRealPath() : $berkas;
        if (! is_string($sourcePath) || ! is_file($sourcePath)) {
            throw new RuntimeException('Berkas ARKAS tidak dapat dibaca.');
        }

        $extension = $jenis === ArkasImportRun::JENIS_RKAS_CSV ? 'csv' : 'xlsx';
        $filePath = 'imports/arkas/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk('local');
        $absolutePath = $disk->path($filePath);
        $directory = dirname($absolutePath);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori penyimpanan privat tidak tersedia.');
        }
        @chmod($directory, 0700);
        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Berkas ARKAS tidak dapat dibaca.');
        }
        try {
            $stored = $disk->put($filePath, $stream);
        } finally {
            fclose($stream);
        }
        if (! $stored) {
            throw new RuntimeException('Berkas ARKAS gagal disimpan pada penyimpanan privat.');
        }
        @chmod($absolutePath, 0600);

        try {
            $run = DB::transaction(function () use ($user, $filePath, $jenis, $sumber, $tahun): ArkasImportRun {
                $freshUser = User::query()->lockForUpdate()->findOrFail($user->id);
                if (! $freshUser->school_id) {
                    throw new RuntimeException('Akun Anda tidak lagi terikat ke sekolah mana pun.');
                }
                if (ArkasImportRun::query()
                    ->where('user_id', $freshUser->id)
                    ->where('school_id', $freshUser->school_id)
                    ->whereIn('status', ['queued', 'processing'])
                    ->exists()) {
                    throw new ArkasRunAktifException('Masih ada pekerjaan ARKAS yang berjalan. Tunggu hingga selesai sebelum mengunggah lagi.');
                }

                return ArkasImportRun::create([
                    'school_id' => $freshUser->school_id,
                    'user_id' => $freshUser->id,
                    'file_path' => $filePath,
                    'jenis' => $jenis,
                    'sumber' => $sumber,
                    'tahun' => $tahun,
                    'status' => 'queued',
                ]);
            });
            try {
                ProsesImporArkas::dispatch($run->id);
            } catch (Throwable $exception) {
                if ($run->fresh()?->status === 'queued') {
                    $run->delete();
                    Storage::disk('local')->delete($filePath);
                }

                throw $exception;
            }

            return $run->refresh();
        } catch (Throwable $exception) {
            if (! isset($run) || $run->fresh()?->status === 'queued') {
                Storage::disk('local')->delete($filePath);
            }

            throw $exception;
        }
    }
}
