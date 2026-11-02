<?php

namespace App\Services;

use App\Models\KasTransaction;
use App\Models\Stage;
use App\Models\SyncSnapshot;
use Carbon\Carbon;

class SyncSnapshotService
{
    /**
     * Catat atau perbarui snapshot harian (Lapis 1: Monitoring Real-Time).
     * Jika snapshot pada tanggal ini sudah dibekukan (frozen oleh pengesahan LPJ),
     * snapshot tidak akan diubah untuk menjamin integritas riwayat.
     */
    public static function rekamSnapshot(int $schoolId, int $tahun, ?string $tanggal = null): SyncSnapshot
    {
        $snapshotDate = $tanggal ? Carbon::parse($tanggal)->format('Y-m-d') : now()->format('Y-m-d');

        // Cek apakah snapshot hari ini sudah ada
        $existing = SyncSnapshot::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('tahun', $tahun)
            ->whereDate('snapshot_date', $snapshotDate)
            ->first();

        if ($existing && $existing->is_frozen) {
            return $existing;
        }

        // Ambil seluruh transaksi aktif sampai dengan tanggal snapshot
        $transaksi = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('tahun', $tahun)
            ->whereNull('legacy_duplicate_of')
            ->whereDate('tanggal', '<=', $snapshotDate)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $totalPendapatan = (int) $transaksi->where('type', 'pendapatan')->sum('total');
        $totalBelanja = (int) $transaksi->where('type', 'belanja')->sum('total');
        $saldo = $totalPendapatan - $totalBelanja;
        $totalTrx = $transaksi->count();

        // Susun sidik jari kriptografis deterministik dari transaksi
        $rows = $transaksi->map(fn (KasTransaction $t) => [
            'arkas_id' => $t->arkas_id ?? (string) $t->id,
            'content_hash' => $t->content_hash ?? '',
            'total' => (int) $t->total,
            'tanggal' => $t->tanggal ? $t->tanggal->format('Y-m-d') : '',
            'type' => $t->type,
        ])->values()->all();

        $manifestHash = hash('sha256', json_encode([
            'school_id' => $schoolId,
            'tahun' => $tahun,
            'snapshot_date' => $snapshotDate,
            'rows' => $rows,
        ]));

        // Ringkasan per tahap untuk kebutuhan visualisasi monitoring Dinas
        $ringkasanTahap = [];
        foreach ($transaksi->groupBy('stage_id') as $stId => $group) {
            $ringkasanTahap[$stId ?: 'tanpa_tahap'] = [
                'pendapatan' => (int) $group->where('type', 'pendapatan')->sum('total'),
                'belanja' => (int) $group->where('type', 'belanja')->sum('total'),
                'jumlah_trx' => $group->count(),
            ];
        }

        $payload = [
            'total_pendapatan' => $totalPendapatan,
            'total_belanja' => $totalBelanja,
            'saldo' => $saldo,
            'total_transaksi' => $totalTrx,
            'manifest_hash' => $manifestHash,
            'payload_summary' => [
                'tahap' => $ringkasanTahap,
                'diambil_pada' => now()->toIso8601String(),
            ],
            'is_frozen' => false,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing;
        }

        return SyncSnapshot::withoutGlobalScope('school')->create(
            [
                'school_id' => $schoolId,
                'tahun' => $tahun,
                'snapshot_date' => $snapshotDate,
            ] + $payload
        );
    }

    /**
     * Membekukan snapshot yang relevan setelah LPJ disahkan di Lapis 2.
     */
    public static function bekukanSnapshotUntukTahap(int $schoolId, int $tahun, int $stageId, int $lpjId): int
    {
        $stage = Stage::find($stageId);
        $stageUrutan = $stage?->urutan ?? 1;

        // Rentang perkiraan bulan tahap (misal Tahap 1: Jan-Jun, Tahap 2: Jul-Des jika 2 tahap)
        $totalTahap = Stage::where('tahun', $tahun)->count() ?: 2;
        $bulanPerTahap = (int) ceil(12 / max(1, $totalTahap));
        $bulanAkhir = min(12, $stageUrutan * $bulanPerTahap);
        $tanggalBatas = sprintf('%04d-%02d-%02d', $tahun, $bulanAkhir, Carbon::create($tahun, $bulanAkhir)->daysInMonth);

        return SyncSnapshot::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('tahun', $tahun)
            ->whereDate('snapshot_date', '<=', $tanggalBatas)
            ->where('is_frozen', false)
            ->update([
                'is_frozen' => true,
                'frozen_at' => now(),
                'frozen_by_lpj_id' => $lpjId,
            ]);
    }
}
