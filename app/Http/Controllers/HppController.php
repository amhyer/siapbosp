<?php

namespace App\Http\Controllers;

use App\Models\KasDetail;
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
        $n = 0;
        foreach ($data['peta'] as $id => $row) {
            $d = KasDetail::find($id);
            if (! $d) {
                continue;
            }
            $d->update([
                'rkas_detail_id' => $row['rkas_detail_id'] ?: null,
                'sumber' => $row['sumber'] ?? $d->sumber,
            ]);
            $n++;
        }

        return back()->with('ok', "Pemetaan HPP tersimpan untuk {$n} rincian.");
    }
}
