<?php

namespace App\Services;

use App\Models\KasTransaction;
use App\Models\LpjSubmission;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Models\User;

class LpjManifestService
{
    /**
     * Menghitung sidik jari kriptografis dan metrik resmi LPJ suatu tahap (Lapis 2).
     */
    public static function hitungManifestTahap(int $schoolId, int $tahun, int $stageId): array
    {
        $stage = Stage::findOrFail($stageId);

        // 1. Ambil seluruh transaksi sah di tahap ini
        $transaksi = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('tahun', $tahun)
            ->where('stage_id', $stageId)
            ->whereNull('legacy_duplicate_of')
            ->orderBy('tanggal')
            ->orderBy('no_bku')
            ->orderBy('id')
            ->get();

        $totalPendapatan = (int) $transaksi->where('type', 'pendapatan')->sum('total');
        $totalBelanja = (int) $transaksi->where('type', 'belanja')->sum('total');
        $sisaDana = $totalPendapatan - $totalBelanja;
        $totalTrx = $transaksi->count();

        // 2. Tautan ke LPJ tahap sebelumnya (Tamper-evident Audit Chain)
        $previousStage = Stage::where('tahun', $tahun)
            ->where('urutan', '<', $stage->urutan)
            ->orderByDesc('urutan')
            ->first();

        $previousManifestHash = null;
        if ($previousStage) {
            $prevLpj = LpjSubmission::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('tahun', $tahun)
                ->where('stage_id', $previousStage->id)
                ->where('status', 'diterima')
                ->latest('id')
                ->first();

            $previousManifestHash = $prevLpj?->manifest_hash ?? 'GENESIS_UNLINKED';
        } else {
            $previousManifestHash = 'GENESIS';
        }

        // 3. Susun daftar transaksi terurut untuk hashing deterministik
        $transaksiRingkas = $transaksi->map(fn (KasTransaction $t) => [
            'id' => $t->arkas_id ?? (string) $t->id,
            'bku' => $t->no_bku,
            'tgl' => $t->tanggal ? $t->tanggal->format('Y-m-d') : '',
            'type' => $t->type,
            'tot' => (int) $t->total,
            'hash' => $t->content_hash ?? '',
        ])->values()->all();

        $manifestPayload = [
            'school_id' => $schoolId,
            'tahun' => $tahun,
            'stage_id' => $stageId,
            'stage_urutan' => $stage->urutan,
            'previous_manifest_hash' => $previousManifestHash,
            'total_pendapatan' => $totalPendapatan,
            'total_belanja' => $totalBelanja,
            'sisa_dana' => $sisaDana,
            'total_transaksi' => $totalTrx,
            'transaksi' => $transaksiRingkas,
        ];

        $manifestHash = hash('sha256', json_encode($manifestPayload));

        return [
            'manifest_hash' => $manifestHash,
            'previous_manifest_hash' => $previousManifestHash,
            'total_pendapatan' => $totalPendapatan,
            'total_belanja' => $totalBelanja,
            'sisa_dana' => $sisaDana,
            'total_transaksi' => $totalTrx,
            'payload' => $manifestPayload,
        ];
    }

    /**
     * Sekolah mengajukan LPJ Tahap lengkap dengan manifest & tanda tangan sekolah.
     */
    public static function ajukanLpj(int $schoolId, int $tahun, int $stageId, int $userId, ?string $signature = null): LpjSubmission
    {
        $perhitungan = self::hitungManifestTahap($schoolId, $tahun, $stageId);
        $user = User::find($userId);

        $schoolSignature = $signature ?? hash('sha256', implode('|', [
            $perhitungan['manifest_hash'],
            $userId,
            $user?->name ?? 'Bendahara',
            now()->toIso8601String(),
        ]));

        return LpjSubmission::withoutGlobalScope('school')->updateOrCreate(
            [
                'school_id' => $schoolId,
                'tahun' => $tahun,
                'stage_id' => $stageId,
            ],
            [
                'status' => 'dikirim',
                'manifest_hash' => $perhitungan['manifest_hash'],
                'previous_manifest_hash' => $perhitungan['previous_manifest_hash'],
                'total_pendapatan' => $perhitungan['total_pendapatan'],
                'total_belanja' => $perhitungan['total_belanja'],
                'sisa_dana' => $perhitungan['sisa_dana'],
                'total_transaksi' => $perhitungan['total_transaksi'],
                'school_signature' => $schoolSignature,
                'dikirim_oleh' => $user?->name ?? 'Operator',
                'catatan_dinas' => null,
            ]
        );
    }

    /**
     * Dinas mengesahkan LPJ: mengunci periode pembukuan dan membekukan snapshot Lapis 1.
     */
    public static function sahkanLpj(LpjSubmission $sub, User $dinasUser, ?string $catatan = null, ?string $signature = null): LpjSubmission
    {
        $dinasSignature = $signature ?? hash('sha256', implode('|', [
            $sub->manifest_hash,
            $dinasUser->id,
            $dinasUser->name,
            'DISAHKAN',
            now()->toIso8601String(),
        ]));

        $sub->update([
            'status' => 'diterima',
            'ditinjau_oleh' => $dinasUser->name,
            'ditinjau_at' => now(),
            'dinas_signature' => $dinasSignature,
            'catatan_dinas' => $catatan,
        ]);

        // Kunci periode buku agar transaksi tahap ini tidak bisa diedit/ditambah lagi
        TutupBuku::withoutGlobalScope('school')->firstOrCreate([
            'school_id' => $sub->school_id,
            'tahun' => $sub->tahun,
            'stage_id' => $sub->stage_id,
        ]);

        // Bekukan snapshot monitoring Lapis 1 untuk periode yang telah disahkan
        SyncSnapshotService::bekukanSnapshotUntukTahap(
            $sub->school_id,
            $sub->tahun,
            $sub->stage_id,
            $sub->id
        );

        return $sub;
    }

    /**
     * Verifikasi integritas: memeriksa apakah data transaksi di database masih utuh
     * dan cocok dengan manifest_hash yang telah disahkan (Tamper Detection).
     */
    public static function verifikasiIntegritas(LpjSubmission $sub): bool
    {
        if (! $sub->manifest_hash) {
            return false;
        }

        $fresh = self::hitungManifestTahap($sub->school_id, $sub->tahun, $sub->stage_id);

        return hash_equals($sub->manifest_hash, $fresh['manifest_hash']);
    }
}
