<?php

namespace App\Http\Controllers;

use App\Models\KasDetail;
use App\Models\RkasDetail;
use App\Models\RkasHeader;
use Illuminate\Http\Request;

/**
 * HPP — Halaman Pemetaan Pembelanjaan.
 * Memetakan tiap rincian belanja (khususnya dari SIPLah) ke kode rekening,
 * kegiatan (uraian RKAS), dan rincian RKAS agar bisa dieksekusi dan
 * direkomendasikan saat pergeseran/perubahan.
 */
class HppController extends Controller
{
    private function tahun(): int
    {
        return (int) session('tahun', now()->year);
    }

    public function index(Request $r)
    {
        $tahun = $this->tahun();
        $q = KasDetail::with(['transaksi', 'rekening', 'rkasDetail'])
            ->whereHas('transaksi', fn ($w) => $w->where('tahun', $tahun)->where('type', 'belanja'));
        if ($r->filled('sumber')) {
            $q->where('sumber', $r->sumber);
        }
        if ($r->filled('status')) {
            $r->status === 'terpetakan'
                ? $q->whereNotNull('rkas_detail_id')
                : $q->whereNull('rkas_detail_id');
        }
        $items = $q->orderByDesc('id')->paginate(20)->withQueryString();

        $rkasList = RkasHeader::with('details.rekening')
            ->where('tahun', $tahun)->where('status', '!=', 'draft')
            ->orderByDesc('revisi_ke')->first();

        return view('hpp.index', [
            'items' => $items, 'tahun' => $tahun,
            'rincian' => $rkasList ? $rkasList->details : collect(),
            'sumber' => $r->sumber, 'status' => $r->status,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'peta' => 'required|array',
            'peta.*.rkas_detail_id' => 'nullable|integer|exists:rkas_details,id',
            'peta.*.sumber' => 'nullable|in:langsung,siplah',
        ]);
        // KasDetail & RkasDetail TIDAK memakai global scope (dipakai juga tanpa
        // sesi pada impor), jadi kepemilikan sekolah harus diverifikasi di sini.
        // Tanpa ini, operator sekolah A bisa mengubah rincian belanja sekolah B
        // hanya dengan menebak id-nya.
        $sid = auth()->user()->school_id;
        $n = 0;
        $ditolak = 0;
        foreach ($data['peta'] as $id => $row) {
            $d = KasDetail::whereKey($id)
                ->whereHas('transaksi', fn ($q) => $q->where('school_id', $sid))
                ->first();
            if (! $d) {
                $ditolak++;

                continue;
            }
            $rkasDetailId = ($row['rkas_detail_id'] ?? null) ?: null;
            if ($rkasDetailId !== null && ! RkasDetail::whereKey($rkasDetailId)
                ->whereHas('header', fn ($q) => $q->where('school_id', $sid))->exists()) {
                $ditolak++;

                continue;
            }
            $d->update([
                'rkas_detail_id' => $rkasDetailId,
                'sumber' => $row['sumber'] ?? $d->sumber,
            ]);
            $n++;
        }

        if ($ditolak > 0) {
            return back()->with('error', "Pemetaan tersimpan untuk {$n} rincian. {$ditolak} baris ditolak karena bukan milik sekolah Anda.");
        }

        return back()->with('ok', "Pemetaan HPP tersimpan untuk {$n} rincian.");
    }
}
