<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Gambar tanda tangan basah digital:
 * disimpan privat di storage/app/ttd dan disematkan di PDF sebagai data URI.
 */
class TtdGambar
{
    /** Simpan berkas TTD — dirapikan dulu (tinggi maks. 320px, transparansi PNG dijaga). */
    public static function simpan(UploadedFile $file, int $penandatanganId): string
    {
        $src = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $src) {
            abort(422, 'Berkas bukan gambar yang bisa dibaca.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $max = 320;
        if ($h > $max) {
            $nw = max(1, (int) round($w * $max / $h));
            $dst = imagescale($src, $nw, $max);
            imagedestroy($src);
            $src = $dst;
        }
        ob_start();
        imagesavealpha($src, true);
        imagepng($src);
        $png = ob_get_clean();
        imagedestroy($src);
        $path = "ttd/{$penandatanganId}.png";
        Storage::disk('local')->put($path, $png);

        return $path;
    }

    /** data URI siap tempel di <img> (Dompdf & pratinjau tanpa file publik). */
    public static function dataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        $p = Storage::disk('local')->path($path);
        if (! is_file($p)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($p));
    }

    public static function hapus(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
