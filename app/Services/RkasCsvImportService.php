<?php

namespace App\Services;

use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\ArkasImportRun;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RkasCsvImportService
{
    /** Import a bounded CSV as one new draft revision, inside the caller's account context. */
    public static function import(string $path, int $schoolId, int $tahun, int $runId): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Berkas CSV RKAS tidak dapat dibaca.');
        }
        if ((int) auth()->user()?->school_id !== $schoolId) {
            throw new RuntimeException('Akun tidak berwenang mengimpor RKAS sekolah ini.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Berkas CSV RKAS tidak dapat dibaca.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new RuntimeException('Berkas CSV kosong.');
            }
            $separator = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);

            $rekeningMap = Rekening::belanja()->pluck('id', 'kode');
            $rows = [];
            $unknown = [];
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $columns = array_map('trim', str_getcsv($line, $separator));
                if (count($columns) < 5 || empty($columns[0]) || str_contains(strtolower($columns[0]), 'kode')) {
                    continue;
                }

                [$code, $description, $unit, $quantity, $price, $source] = array_pad($columns, 6, null);
                $accountId = $rekeningMap[$code] ?? null;
                if (! $accountId) {
                    $unknown[] = $lineNumber.' (kode '.$code.')';
                    continue;
                }
                $rows[] = [
                    $accountId,
                    $description ?: 'Item RKAS',
                    $unit ?: 'paket',
                    max(1, (int) $quantity),
                    max(0, (int) str_replace(['.', ' '], '', (string) $price)),
                    $source ?: 'BOS Reguler',
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw new RuntimeException('Tidak ada rincian valid. Periksa kode rekeningnya.');
        }

        return DB::transaction(function () use ($schoolId, $tahun, $rows, $unknown, $runId): array {
            $run = ArkasImportRun::query()->lockForUpdate()->findOrFail($runId);
            if ($run->school_id !== $schoolId || $run->jenis !== ArkasImportRun::JENIS_RKAS_CSV) {
                throw new RuntimeException('Pekerjaan impor CSV RKAS tidak valid.');
            }
            if ($run->status === 'completed' && is_array($run->result)) {
                return $run->result;
            }
            School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();
            $revision = (int) RkasHeader::query()
                ->where('school_id', $schoolId)
                ->where('tahun', $tahun)
                ->max('revisi_ke') + 1;
            $header = RkasHeader::create([
                'tahun' => $tahun,
                'revisi_ke' => $revision,
                'status' => 'draft',
                'catatan' => 'Impor CSV',
            ]);
            foreach ($rows as [$accountId, $description, $unit, $quantity, $price, $source]) {
                $header->details()->create([
                    'rekening_id' => $accountId,
                    'uraian' => $description,
                    'satuan' => $unit,
                    'banyak' => $quantity,
                    'harga_satuan' => $price,
                    'jumlah' => $quantity * $price,
                    'sumber_dana' => $source,
                ]);
            }

            $result = [
                'rkas_header_id' => $header->id,
                'revisi_ke' => $revision,
                'jumlah_rincian' => count($rows),
                'kode_tak_dikenal' => $unknown,
            ];
            $run->update(['status' => 'completed', 'result' => $result, 'completed_at' => now()]);

            return $result;
        });
    }
}
