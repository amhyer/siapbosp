<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\KasTransaction;
use App\Support\SumberDana;
use Illuminate\Support\Facades\DB;

/**
 * Data Sekolah — ringkasan tahun anggaran & sumber dana milik sekolah ini.
 */
class DataSekolahController extends Controller
{
    /** Tahun anggaran: pagu, rencana, realisasi, dan status tiap tahun. */
    public function tahun()
    {
        $sid = auth()->user()->school_id;
        $tahunSekarang = (int) session('tahun', now()->year);
        $tahunList = DB::table('allocations')->where('school_id', $sid)->distinct()->orderByDesc('tahun')->pluck('tahun');

        $baris = [];
        foreach ($tahunList as $t) {
            $pagu = (int) Allocation::where('school_id', $sid)->where('tahun', $t)->sum('nilai');
            $rencana = (int) DB::table('rkas_details as rd')
                ->join('rkas_headers as rh', 'rd.rkas_header_id', '=', 'rh.id')
                ->where('rh.school_id', $sid)->where('rh.tahun', $t)
                ->whereIn('rh.status', ['disetujui', 'dikirim_dinas', 'disahkan', 'dikunci'])
                ->sum('rd.jumlah');
            $realisasi = (int) KasTransaction::where('school_id', $sid)->where('tahun', $t)->sum('total');
            $trx = (int) KasTransaction::where('school_id', $sid)->where('tahun', $t)->count();
            $tutup = (int) DB::table('tutup_bukus')->where('school_id', $sid)->where('tahun', $t)->count();
            $baris[] = [
                'tahun' => (int) $t,
                'pagu' => $pagu,
                'rencana' => $rencana,
                'realisasi' => $realisasi,
                'trx' => $trx,
                'tutup_tahap' => $tutup,
                'sisa' => $pagu - $realisasi,
                'serapan' => $pagu > 0 ? round($realisasi * 100 / $pagu, 1) : 0,
                'aktif' => (int) $t === $tahunSekarang,
            ];
        }

        return view('datasekolah.tahun', ['baris' => $baris, 'tahunSekarang' => $tahunSekarang]);
    }

    /** Sumber dana: pagu & pemakaian tiap sumber dana sekolah ini. */
    public function sumberDana()
    {
        $sid = auth()->user()->school_id;
        $tahun = (int) session('tahun', now()->year);

        $pagu = Allocation::with('bosType')->where('school_id', $sid)->where('tahun', $tahun)->get()
            ->groupBy(fn ($a) => $a->bosType?->nama ?? 'Tanpa jenis BOS')
            ->map(fn ($rows) => (int) $rows->sum('nilai'));

        $rkas = DB::table('rkas_details as rd')
            ->join('rkas_headers as rh', 'rd.rkas_header_id', '=', 'rh.id')
            ->where('rh.school_id', $sid)->where('rh.tahun', $tahun)
            ->whereIn('rh.status', ['disetujui', 'dikirim_dinas', 'disahkan', 'dikunci'])
            ->select('rd.sumber_dana', DB::raw('SUM(rd.jumlah) j'), DB::raw('COUNT(*) n'))
            ->groupBy('rd.sumber_dana')->get()->keyBy('sumber_dana');

        $realisasi = DB::table('kas_transactions as t')
            ->leftJoin('kas_details as d', 'd.kas_transaction_id', '=', 't.id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->select('d.sumber', DB::raw('SUM(d.nilai) j'))
            ->groupBy('d.sumber')->pluck('j', 'sumber');

        $nama = array_keys($pagu->all());
        foreach ($rkas as $s => $r) {
            if (! in_array($s, $nama, true)) {
                $nama[] = $s;
            }
        }

        $baris = [];
        foreach ($nama as $s) {
            $baris[] = [
                'sumber' => $s,
                'label' => SumberDana::label($s),
                'ikon' => SumberDana::ikon($s),
                'pagu' => (int) ($pagu[$s] ?? 0),
                'rencana' => (int) ($rkas[$s]->j ?? 0),
                'realisasi' => (int) ($realisasi[$s] ?? 0),
                'rincian' => (int) ($rkas[$s]->n ?? 0),
            ];
        }
        usort($baris, fn ($a, $b) => $b['pagu'] <=> $a['pagu']);

        return view('datasekolah.sumber-dana', ['baris' => $baris, 'tahun' => $tahun]);
    }
}
