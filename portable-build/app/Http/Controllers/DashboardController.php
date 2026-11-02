<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\Announcement;
use App\Models\BkuTutupBulan;
use App\Models\KasTransaction;
use App\Models\RkasHeader;
use App\Models\Stage;
use App\Services\JuknisBosp2026Service;
use App\Services\KasService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $u = auth()->user();
        $tahun = (int) session('tahun', now()->year);
        $sid = $u->school_id;
        $sekolah = $u->school;

        // === Kas & anggaran ===
        $saldo = KasService::saldo($sid, $tahun);
        $pagu = (int) Allocation::where('tahun', $tahun)->sum('nilai');
        $pendapatan = (int) KasTransaction::where('tahun', $tahun)->where('type', 'pendapatan')->sum('total');
        $belanja = (int) KasTransaction::where('tahun', $tahun)->where('type', 'belanja')->sum('total');
        $bulanIni = (int) KasTransaction::where('tahun', $tahun)->where('type', 'belanja')
            ->where('tanggal', '>=', now()->format('Y-m').'-01')
            ->where('tanggal', '<', now()->modify('first day of next month')->format('Y-m-d'))->sum('total');

        $serapan = $pagu > 0 ? min(999, round($belanja / $pagu * 100, 1)) : 0;
        $diterimaPct = $pagu > 0 ? round($pendapatan / $pagu * 100, 1) : 0;
        $kasTotal = $saldo['tunai'] + $saldo['bank'];
        $tunaiPct = $kasTotal > 0 ? round($saldo['tunai'] / $kasTotal * 100, 1) : 0;
        $bankPct = 100 - $tunaiPct;

        // === Pajak ===
        $qb = fn () => DB::table('kas_taxes as p')->join('kas_transactions as t', 'p.kas_transaction_id', '=', 't.id')
            ->where('t.school_id', $sid)->where('t.type', 'belanja');
        $pajakTerima = (int) $qb()->sum('p.nilai');
        $pajakSudah = (int) $qb()->where('p.status', 'sudah')->sum('p.nilai');
        $pajakBelum = $pajakTerima - $pajakSudah;
        $pajakPct = $pajakTerima > 0 ? round($pajakSudah / $pajakTerima * 100, 1) : 100;

        // === Belanja per rekening (Top 5) ===
        $topRekening = DB::table('kas_details as d')
            ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
            ->leftJoin('rekenings as r', 'd.rekening_id', '=', 'r.id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->selectRaw('d.rekening_id id, r.kode, r.nama, sum(d.nilai) j')
            ->groupBy('d.rekening_id', 'r.kode', 'r.nama')->orderByDesc('j')->limit(5)->get()
            ->map(fn ($x) => ['kode' => $x->kode, 'nama' => $x->nama, 'jumlah' => (int) $x->j,
                'persen' => $belanja > 0 ? round($x->j / $belanja * 100, 1) : 0])->values();

        // === Realisasi per tahap ===
        $perTahap = Stage::where('tahun', $tahun)->orderBy('urutan')->get()->map(function ($s) use ($tahun, $pagu) {
            $p = (int) KasTransaction::where('tahun', $tahun)->where('type', 'pendapatan')->where('stage_id', $s->id)->sum('total');
            $b = (int) KasTransaction::where('tahun', $tahun)->where('type', 'belanja')->where('stage_id', $s->id)->sum('total');

            return ['nama' => $s->nama, 'pendapatan' => $p, 'belanja' => $b,
                'persenSerapan' => $p > 0 ? round($b / $p * 100, 1) : 0,
                'persenPagu' => $pagu > 0 ? round($b / $pagu * 100, 1) : 0];
        })->filter(fn ($x) => $x['pendapatan'] || $x['belanja'])->values();

        // === Grafik belanja per bulan ===
        $perBulan = array_fill(1, 12, 0);
        foreach (DB::table('kas_transactions')->where('school_id', $sid)->where('tahun', $tahun)->where('type', 'belanja')
            ->select('tanggal', 'total')->get() as $trx) {
            $perBulan[(int) substr((string) $trx->tanggal, 5, 2)] += (int) $trx->total;
        }
        $maxBulan = max(1, max($perBulan));

        // === Serapan per komponen (anggaran vs realisasi) ===
        $realKomponen = DB::table('kas_details as d')
            ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
            ->leftJoin('rekenings as r', 'd.rekening_id', '=', 'r.id')
            ->leftJoin('komponens as k', 'r.komponen_id', '=', 'k.id')
            ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->selectRaw("coalesce(k.nama, '(tanpa komponen)') nama, sum(d.nilai) j")
            ->groupBy(DB::raw("coalesce(k.nama, '(tanpa komponen)')"))->pluck('j', 'nama');
        $rkasAktif = RkasHeader::where('school_id', $sid)->where('tahun', $tahun)
            ->where('status', '!=', 'draft')->orderByDesc('revisi_ke')->first()
            ?? RkasHeader::where('school_id', $sid)->where('tahun', $tahun)->orderByDesc('revisi_ke')->first();
        $anggKomponen = $rkasAktif ? DB::table('rkas_details as rd')
            ->leftJoin('rekenings as r', 'rd.rekening_id', '=', 'r.id')
            ->leftJoin('komponens as k', 'r.komponen_id', '=', 'k.id')
            ->where('rd.rkas_header_id', $rkasAktif->id)
            ->selectRaw("coalesce(k.nama, '(tanpa komponen)') nama, sum(rd.jumlah) j")
            ->groupBy(DB::raw("coalesce(k.nama, '(tanpa komponen)')"))->pluck('j', 'nama') : collect();
        $serapanKomponen = $anggKomponen->keys()->merge($realKomponen->keys())->unique()->sort()->map(fn ($n) => [
            'nama' => $n, 'anggaran' => (int) ($anggKomponen[$n] ?? 0), 'realisasi' => (int) ($realKomponen[$n] ?? 0),
        ])->values();

        $terbaru = KasTransaction::where('tahun', $tahun)->orderByDesc('tanggal')->orderByDesc('id')->take(7)->get();
        $pengumuman = Announcement::latest()->take(3)->get();

        // === Pengingat tenggat BKU, Permendikdasmen No. 8 Tahun 2026 Pasal 59, & Kepatuhan Proporsi RKAS ===
        $bulannyaAda = DB::table('kas_transactions')->where('school_id', $sid)->where('tahun', $tahun)
            ->pluck('tanggal')->map(fn ($t) => substr((string) $t, 0, 7))->unique()->sort()->values();
        $kunci = BkuTutupBulan::bulanTerkunci($sid, $tahun);
        $bkuTerbuka = $bulannyaAda->first(fn ($m) => ! in_array($m, $kunci));

        $statusTenggat = JuknisBosp2026Service::statusTenggatPelaporan($sid, $tahun);
        $ringkasanJuknis = ($rkasAktif && $sekolah)
            ? JuknisBosp2026Service::reviewKertasKerja($rkasAktif, $sekolah)
            : null;

        return view('dashboard', compact(
            'saldo', 'pagu', 'pendapatan', 'belanja', 'bulanIni', 'serapan', 'diterimaPct', 'tunaiPct', 'bankPct',
            'pajakTerima', 'pajakSudah', 'pajakBelum', 'pajakPct', 'topRekening', 'perTahap', 'perBulan', 'maxBulan',
            'terbaru', 'pengumuman', 'tahun', 'serapanKomponen', 'bkuTerbuka', 'statusTenggat', 'ringkasanJuknis', 'rkasAktif'
        ));
    }
}
