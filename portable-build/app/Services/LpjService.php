<?php

namespace App\Services;

use App\Models\KasTransaction;
use App\Models\Stage;

/** Rangkaian data dokumen LPJ satu tahap — dipakai panel Sekolah maupun tinjauan Dinas. */
class LpjService
{
    public static function data(int $sid, int $tahun, int $stageId, bool $kumulatif = false): array
    {
        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->get();
        $stage = $stages->firstWhere('id', $stageId) ?? abort(404, 'Tahap tidak ditemukan untuk tahun ini.');
        // Kumulatif: semua tahap dari awal tahun s.d. tahap terpilih (laporan triwulan/semester)
        $stageIds = $kumulatif
            ? $stages->where('urutan', '<=', $stage->urutan)->pluck('id')->map(fn ($x) => (int) $x)->all()
            : [(int) $stage->id];
        $cakupan = $kumulatif ? "Kumulatif {$stages->first()->nama}–{$stage->nama}" : null;
        $items = KasTransaction::withoutGlobalScope('school')
            ->with(['details.rekening.komponen', 'taxes.rekeningPajak'])
            ->where('school_id', $sid)->where('tahun', $tahun)->whereIn('stage_id', $stageIds)
            ->orderBy('tanggal')->orderBy('no_bku')->get();

        // Saldo berjalan dari ledger (sudah membawa SILPA) → ambil baris milik tahap ini
        $ledger = KasService::ledger($sid, $tahun);
        $bkuRows = [];
        $saldoAwal = $ledger['saldoAwalTahun'] ?? 0;
        $sudahMasukTahap = $kumulatif;
        foreach ($ledger['items'] as $it) {
            if (! in_array((int) $it['trx']->stage_id, $stageIds)) {
                if (! $sudahMasukTahap) {
                    $saldoAwal = $it['saldo'];
                } // saldo tepat sebelum cakupan ini dimulai

                continue;
            }
            $sudahMasukTahap = true;
            $ef = $it['dTunai'] + $it['dBank'];
            $bkuRows[] = ['trx' => $it['trx'], 'masuk' => max(0, $ef), 'keluar' => max(0, -$ef), 'saldo' => $it['saldo']];
        }
        $totalMasuk = array_sum(array_column($bkuRows, 'masuk'));
        $totalKeluar = array_sum(array_column($bkuRows, 'keluar'));
        $totalBelanja = (int) $items->where('type', 'belanja')->sum('total');

        // Rekap belanja per rekening
        $perRek = [];
        foreach ($items->where('type', 'belanja') as $t) {
            foreach ($t->details as $d) {
                $perRek[$d->rekening_id]['rekening'] = $d->rekening;
                $perRek[$d->rekening_id]['jumlah'] = ($perRek[$d->rekening_id]['jumlah'] ?? 0) + (int) $d->nilai;
            }
        }
        $rekapRekening = collect($perRek)->sortBy(fn ($r) => $r['rekening']->kode ?? 'z')->values();

        // Rekap pajak (K7): pajak yang dipungut lewat belanja tahap ini
        $perPajak = [];
        foreach ($items->where('type', 'belanja') as $t) {
            foreach ($t->taxes as $p) {
                $k = $p->rekening_pajak_id;
                $perPajak[$k]['nama'] = $p->rekeningPajak->nama ?? '(pajak)';
                $perPajak[$k]['dipungut'] = ($perPajak[$k]['dipungut'] ?? 0) + (int) $p->nilai;
                if ($p->status === 'sudah') {
                    $perPajak[$k]['disetor'] = ($perPajak[$k]['disetor'] ?? 0) + (int) $p->nilai;
                }
            }
        }
        $rekapPajak = [];
        foreach ($perPajak as $r) {
            $r['disetor'] = $r['disetor'] ?? 0;
            $r['belum'] = $r['dipungut'] - $r['disetor'];
            $rekapPajak[] = $r;
        }

        return [$stages, $stage, $items, $bkuRows, $saldoAwal, $totalMasuk, $totalKeluar,
            $totalBelanja, $rekapRekening, $rekapPajak, $tahun, $kumulatif, $cakupan];
    }
}
