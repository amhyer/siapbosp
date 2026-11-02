<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\KasTax;
use App\Models\KasTransaction;
use App\Models\School;
use Carbon\Carbon;

class RingkasanService
{
    /**
     * Ringkasan satu sekolah untuk satu tahun anggaran.
     * Mengisi atribut virtual di objek School: pagu, pendapatan, belanja,
     * pajakBelum, serapan, last, notAktif.
     */
    public static function sekolah(School $s, int $tahun): School
    {
        $sid = $s->id;
        $s->pagu = (int) Allocation::withoutGlobalScope('school')->where('school_id', $sid)->where('tahun', $tahun)->sum('nilai');
        $s->pendapatan = (int) KasTransaction::withoutGlobalScope('school')->where('school_id', $sid)->where('tahun', $tahun)->where('type', 'pendapatan')->sum('total');
        $s->belanja = (int) KasTransaction::withoutGlobalScope('school')->where('school_id', $sid)->where('tahun', $tahun)->where('type', 'belanja')->sum('total');
        $s->pajakBelum = (int) KasTax::whereHas('transaksi', fn ($q) => $q->withoutGlobalScope('school')->where('school_id', $sid)->where('type', 'belanja'))->where('status', 'belum')->sum('nilai');
        $s->serapan = $s->pagu > 0 ? round($s->belanja / $s->pagu * 100, 1) : 0;
        $last = KasTransaction::withoutGlobalScope('school')->where('school_id', $sid)->max('created_at');
        $s->last = $last;
        $s->notAktif = ! $last || Carbon::parse($last)->lt(now()->subDays(30));

        return $s;
    }
}
