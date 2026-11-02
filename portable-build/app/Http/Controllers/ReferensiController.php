<?php

namespace App\Http\Controllers;

use App\Models\ArkasKode;
use App\Models\BosType;
use App\Models\Komponen;
use App\Models\Ptk;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\Satuan;
use App\Models\Stage;
use App\Models\Uraian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Referensi (master data) — read-only di sisi sekolah; master diubah Dinas.
 * Tab: rekening, pajak, program, kegiatan, satuan, uraian, PTK, jenis BOS & tahap.
 */
class ReferensiController extends Controller
{
    /** Daftar Kegiatan (kode kegiatan ARKAS) — dipakai menyusun RKAS. */
    public function kegiatan(Request $r)
    {
        $cari = trim((string) $r->query('cari', ''));
        $kegiatan = ArkasKode::query()
            ->when($cari !== '', fn ($q) => $q->where(function ($x) use ($cari) {
                $x->where('kode', 'like', '%'.$cari.'%')->orWhere('uraian', 'like', '%'.$cari.'%');
            }))
            ->orderBy('kode')->paginate(60)->withQueryString();

        return view('referensi.kegiatan', compact('kegiatan', 'cari'));
    }

    public function index(Request $r)
    {
        $cari = trim((string) $r->query('cari', ''));
        $kegiatan = ArkasKode::query()
            ->when($cari !== '', fn ($q) => $q->where(function ($x) use ($cari) {
                $x->where('kode', 'like', '%'.$cari.'%')->orWhere('uraian', 'like', '%'.$cari.'%');
            }))
            ->orderBy('kode')->paginate(50)->withQueryString();

        $satuans = Satuan::orderBy('nama')->get();

        $sid = (int) auth()->user()->school_id;
        $uraian = Uraian::query()
            ->from('uraian as u')
            ->select('u.*')
            ->selectSub(
                DB::table('rkas_details as rd')
                    ->join('rkas_headers as rh', 'rd.rkas_header_id', '=', 'rh.id')
                    ->whereColumn('rd.uraian', 'u.uraian')
                    ->where('rh.school_id', $sid)
                    ->selectRaw('count(*)'),
                'dipakai'
            )
            ->orderBy('u.uraian')->limit(300)->get();

        $ptks = Ptk::where('school_id', auth()->user()->school_id)
            ->orderBy('nama')->limit(300)->get();

        return view('referensi.index', [
            'rekenings' => Rekening::with('komponen')->orderBy('kode')->get(),
            'pajaks' => RekeningPajak::orderBy('kode')->get(),
            'komponens' => Komponen::orderBy('kode')->get(),
            'bosTypes' => BosType::orderBy('id')->get(),
            'stages' => Stage::orderBy('tahun')->orderBy('urutan')->get(),
            'kegiatan' => $kegiatan,
            'satuans' => $satuans,
            'uraian' => $uraian,
            'ptks' => $ptks,
        ]);
    }
}
