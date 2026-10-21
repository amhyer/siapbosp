<?php

namespace App\Http\Controllers;

use App\Models\KasTax;
use App\Models\KasTransaction;
use App\Models\LpjSubmission;
use App\Models\Penandatangan;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Models\User;
use App\Notifications\InfoNotif;
use App\Services\Aktivitas;
use App\Services\EmailNotif;
use App\Services\KasService;
use App\Services\LpjExportService;
use App\Services\LpjService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    private function tahun(): int
    {
        return (int) request('tahun', session('tahun', now()->year));
    }

    public function tahap()
    {
        $tahun = $this->tahun();
        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->get();
        $stageId = (int) request('stage', $stages->first()?->id);
        $items = KasTransaction::with(['details.rekening', 'taxes.rekeningPajak'])
            ->where('tahun', $tahun)->where('stage_id', $stageId)->orderBy('tanggal')->orderBy('no_bku')->get();
        $saldo = KasService::saldo(auth()->user()->school_id, $tahun);
        $penandatangan = Penandatangan::orderBy('id')->get();
        $terkunci = TutupBuku::terkunci(auth()->user()->school_id, $tahun, $stageId);
        $terkunciMap = TutupBuku::where('tahun', $tahun)->pluck('stage_id')->toArray();
        $lpj = LpjSubmission::terakhir(auth()->user()->school_id, $tahun, $stageId);

        return view('laporan.tahap', compact('stages', 'stageId', 'items', 'saldo', 'tahun', 'penandatangan', 'terkunci', 'terkunciMap', 'lpj'));
    }

    public function tutup(Request $r, Stage $stage)
    {
        $tahun = (int) session('tahun', now()->year);
        TutupBuku::firstOrCreate(
            ['tahun' => $tahun, 'stage_id' => $stage->id],
            ['ditutup_oleh' => auth()->user()->name]
        );

        return back()->with('ok', $stage->nama.' sudah ditutup bukunya. Transaksi di tahap ini kini terkunci.');
    }

    public function buka(Request $r, Stage $stage)
    {
        if (auth()->user()->role !== 'kepsek') {
            return back()->with('error', 'Hanya Kepala Sekolah yang dapat membuka kembali buku tahap.');
        }
        $tahun = (int) session('tahun', now()->year);
        TutupBuku::where('tahun', $tahun)->where('stage_id', $stage->id)->delete();

        return back()->with('ok', $stage->nama.' dibuka kembali.');
    }

    /** Data lengkap LPJ untuk satu tahap (atau kumulatif Tahap 1–tahap ini). */
    private function dataLpj(int $stageId, bool $kumulatif = false): array
    {
        return LpjService::data(auth()->user()->school_id, $this->tahun(), $stageId, $kumulatif);
    }

    /** LPJ lengkap satu tahap: sampul + pengesahan + BKU + rincian + rekap + K7. Cetak / simpan PDF via browser. */
    public function lpj()
    {
        $tahun = $this->tahun();
        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->get();
        abort_if($stages->isEmpty(), 404, 'Belum ada tahap untuk tahun ini.');
        $stageId = (int) request('stage', $stages->first()->id);
        $kumulatif = (bool) request('kumulatif');
        [$stages, $stage, $items, $bkuRows, $saldoAwal, $totalMasuk, $totalKeluar,
            $totalBelanja, $rekapRekening, $rekapPajak, $tahun, $kumulatif, $cakupan] = $this->dataLpj($stageId, $kumulatif);
        $sekolah = auth()->user()->school;
        $penandatangan = Penandatangan::orderBy('id')->get();

        return view('laporan.lpj-cetak', compact('stages', 'stage', 'items', 'bkuRows', 'saldoAwal',
            'totalMasuk', 'totalKeluar', 'totalBelanja', 'rekapRekening', 'rekapPajak',
            'tahun', 'sekolah', 'penandatangan', 'kumulatif', 'cakupan'));
    }

    /** LPJ versi PDF asli (server-side, siap cetak & arsip). */
    public function lpjPdf()
    {
        $tahun = $this->tahun();
        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->get();
        abort_if($stages->isEmpty(), 404, 'Belum ada tahap untuk tahun ini.');
        $vars = $this->dataLpj((int) request('stage', $stages->first()->id), (bool) request('kumulatif'));
        $sekolah = auth()->user()->school;

        return response(LpjExportService::pdf($vars, $sekolah, Penandatangan::orderBy('id')->get()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.LpjExportService::namaBerkas($vars, $sekolah, 'pdf').'"',
        ]);
    }

    /** LPJ versi Excel multi-sheet (Ringkasan, BKU, K7, Pajak). */
    public function lpjXlsx()
    {
        $tahun = $this->tahun();
        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->get();
        abort_if($stages->isEmpty(), 404, 'Belum ada tahap untuk tahun ini.');
        $vars = $this->dataLpj((int) request('stage', $stages->first()->id), (bool) request('kumulatif'));
        $sekolah = auth()->user()->school;
        $path = LpjExportService::xlsx($vars, $sekolah);

        return response()->download($path, LpjExportService::namaBerkas($vars, $sekolah, 'xlsx'))
            ->deleteFileAfterSend(true);
    }

    /** Sekolah mengirim LPJ tahap ini ke Dinas (wajib sudah tutup buku). */
    public function kumpulkan(Stage $stage)
    {
        $sid = auth()->user()->school_id;
        $tahun = $this->tahun();
        if (! TutupBuku::terkunci($sid, $tahun, $stage->id)) {
            return back()->with('error', 'Tutup buku tahap ini terlebih dahulu, baru LPJ bisa dikirim ke Dinas.');
        }
        $last = LpjSubmission::terakhir($sid, $tahun, $stage->id);
        if ($last && $last->status === 'dikirim') {
            return back()->with('error', 'LPJ tahap ini sudah dikirim dan sedang menunggu tinjauan Dinas.');
        }
        if ($last && $last->status === 'diterima') {
            return back()->with('error', 'LPJ tahap ini sudah DITERIMA oleh Dinas.');
        }
        LpjSubmission::create([
            'school_id' => $sid, 'stage_id' => $stage->id, 'tahun' => $tahun,
            'status' => 'dikirim', 'dikirim_oleh' => auth()->user()->name, 'catatan_dinas' => null,
        ]);
        $namaSekolah = auth()->user()->school->nama;
        $penerima = User::whereIn('role', ['dinas', 'superadmin'])->get();
        $penerima->each(fn ($u) => $u->notify(
            new InfoNotif('📨', "LPJ masuk: {$namaSekolah}",
                "{$stage->nama} {$tahun} menunggu tinjauan Anda.", route('dinas.lpj'))));
        EmailNotif::kirim($penerima, "LPJ masuk: {$namaSekolah}", [
            "{$namaSekolah} mengirim LPJ {$stage->nama} tahun {$tahun} dan menunggu tinjauan Anda.",
            'Silakan masuk ke akun Dinas untuk memeriksa, menerima, atau mengembalikan LPJ tersebut.',
        ], route('dinas.lpj'));
        Aktivitas::catat('lpj', "LPJ {$stage->nama} {$tahun} dikirim ke Dinas");

        return back()->with('ok', "LPJ {$stage->nama} terkirim ke Dinas. Statusnya bisa dipantau di halaman ini.");
    }

    /** Paket LPJ satu tahap dalam bentuk ZIP berisi CSV (Excel-friendly). */
    public function lpjZip()
    {
        $tahun = $this->tahun();
        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->get();
        abort_if($stages->isEmpty(), 404, 'Belum ada tahap untuk tahun ini.');
        $stageId = (int) request('stage', $stages->first()->id);
        [$stages, $stage, $items, $bkuRows, $saldoAwal, $totalMasuk, $totalKeluar,
            $totalBelanja, $rekapRekening, $rekapPajak, $tahun] = $this->dataLpj($stageId);
        $sekolah = auth()->user()->school;

        $csv = function (array $rows) {
            $s = fopen('php://temp', 'r+');
            foreach ($rows as $r) {
                fputcsv($s, $r, ';');
            }
            rewind($s);
            $c = stream_get_contents($s);
            fclose($s);

            return "\xEF\xBB\xBF".$c; // BOM agar rapi dibuka di Excel
        };

        $bku = [['No BKU', 'Tanggal', 'Uraian', 'Jenis', 'Masuk', 'Keluar', 'Saldo'], ['', '', 'SALDO AWAL TAHAP', '', '', '', $saldoAwal]];
        foreach ($bkuRows as $row) {
            $t = $row['trx'];
            $bku[] = [$t->no_bku, $t->tanggal->format('d/m/Y'), $t->keterangan, $t->labelType(), $row['masuk'], $row['keluar'], $row['saldo']];
        }
        $bku[] = ['', '', 'JUMLAH', '', $totalMasuk, $totalKeluar, $saldoAwal + $totalMasuk - $totalKeluar];

        $rinci = [['Tanggal', 'Uraian Belanja', 'Kode Rekening', 'Nama Rekening', 'Nilai']];
        foreach ($items->where('type', 'belanja') as $t) {
            foreach ($t->details as $d) {
                $rinci[] = [$t->tanggal->format('d/m/Y'), $d->uraian ?? $t->keterangan, $d->rekening->kode ?? '', $d->rekening->nama ?? '', (int) $d->nilai];
            }
        }

        $rekap = [['Kode Rekening', 'Nama Rekening', 'Komponen', 'Jumlah']];
        foreach ($rekapRekening as $r) {
            $rekap[] = [$r['rekening']->kode ?? '', $r['rekening']->nama ?? '', $r['rekening']?->komponen?->nama ?? '', $r['jumlah']];
        }

        $pajak = [['Jenis Pajak', 'Dipungut', 'Disetor', 'Belum Disetor']];
        foreach ($rekapPajak as $r) {
            $pajak[] = [$r['nama'], $r['dipungut'], $r['disetor'], $r['belum']];
        }

        $info = "PAKET LPJ {$stage->nama} TAHUN {$tahun}\r\n{$sekolah->nama} (NPSN {$sekolah->npsn})\r\n"
              .'Dibuat: '.now()->format('d/m/Y H:i').' oleh '.auth()->user()->name."\r\n\r\n"
              ."Isi paket:\r\n1. 01_BKU.csv - Buku Kas Umum tahap\r\n2. 02_Rincian_Belanja.csv\r\n"
              ."3. 03_Rekap_per_Rekening.csv\r\n4. 04_Rekap_Pajak_K7.csv\r\n"
              .'Semua CSV memakai pemisah titik-koma agar langsung rapi di Microsoft Excel.';

        $tmp = tempnam(sys_get_temp_dir(), 'lpj_');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('00_INFORMASI.txt', $info);
        $zip->addFromString('01_BKU.csv', $csv($bku));
        $zip->addFromString('02_Rincian_Belanja.csv', $csv($rinci));
        $zip->addFromString('03_Rekap_per_Rekening.csv', $csv($rekap));
        $zip->addFromString('04_Rekap_Pajak_K7.csv', $csv($pajak));
        $zip->close();

        $nama = 'LPJ_'.preg_replace('/[^A-Za-z0-9]+/', '_', $sekolah->nama).'_'.$stage->nama.'_'.$tahun.'.zip';

        return response()->download($tmp, $nama, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    /** 🧾 Kertas kerja pajak triwulan: pungut (kode billing) vs setor (NTPN) per bulan. */
    public function pajakTriwulan(Request $r)
    {
        $tahun = $this->tahun();
        $tw = max(1, min(4, (int) $r->query('tw', intdiv((int) date('n') - 1, 3) + 1)));
        $namaTw = 'TW'.['', ' I (Jan–Mar)', ' II (Apr–Jun)', ' III (Jul–Sep)', ' IV (Okt–Des)'][$tw];
        $awal = Carbon::create($tahun, ($tw - 1) * 3 + 1, 1)->startOfDay();
        $akhir = (clone $awal)->addMonths(2)->endOfMonth();
        $bulan = [($tw - 1) * 3 + 1, ($tw - 1) * 3 + 2, ($tw - 1) * 3 + 3];

        // Pungutan pada triwulan ini (mengikuti tanggal transaksi belanja)
        $pungut = KasTax::whereHas('transaksi', fn ($q) => $q
            ->where('type', 'belanja')->where('tahun', $tahun)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()]))
            ->with(['rekeningPajak', 'transaksi'])->get();
        // Setoran pada triwulan ini (mengikuti tanggal transaksi setor pajak)
        $paidIds = KasTransaction::whereIn('type', ['setor_pajak', 'setor_utang_pajak'])
            ->where('tahun', $tahun)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->pluck('id');
        $setoris = $paidIds->isEmpty() ? collect()
            : KasTax::whereIn('paid_transaction_id', $paidIds)->with(['rekeningPajak', 'transaksi'])->get();

        // Matriks per jenis pajak per bulan
        $jenis = [];
        $fmt = fn ($t) => (int) $t->tanggal->format('n');
        foreach ($pungut as $p) {
            $jid = $p->rekening_pajak_id ?? 0;
            $jenis[$jid]['nama'] = $p->rekeningPajak?->nama ?? '(tanpa jenis)';
            $jenis[$jid]['dipungut'][$fmt($p->transaksi)] = ($jenis[$jid]['dipungut'][$fmt($p->transaksi)] ?? 0) + $p->nilai;
        }
        foreach ($setoris as $s) {
            $tgl = KasTransaction::find($s->paid_transaction_id)?->tanggal;
            if (! $tgl) {
                continue;
            }
            $jid = $s->rekening_pajak_id ?? 0;
            $jenis[$jid]['nama'] = $s->rekeningPajak?->nama ?? '(tanpa jenis)';
            $jenis[$jid]['disetor'][(int) $tgl->format('n')] = ($jenis[$jid]['disetor'][(int) $tgl->format('n')] ?? 0) + $s->nilai;
        }
        foreach ($jenis as $jid => &$j) {
            $j['td'] = array_sum($j['dipungut'] ?? []);
            $j['ts'] = array_sum($j['disetor'] ?? []);
            $j['sisa'] = $j['td'] - $j['ts'];
        }
        unset($j);

        // Sisa kewajiban sampai akhir triwulan (seluruh waktu)
        $totPungutAkhir = KasTax::whereHas('transaksi', fn ($q) => $q
            ->where('type', 'belanja')->where('tahun', $tahun)->where('tanggal', '<=', $akhir->toDateString()))->sum('nilai');
        $totSetorAkhir = KasTax::whereHas('transaksi', fn ($q) => $q
            ->whereIn('type', ['setor_pajak', 'setor_utang_pajak'])->where('tahun', $tahun)->where('tanggal', '<=', $akhir->toDateString()))->sum('nilai');

        $data = compact('tahun', 'tw', 'namaTw', 'awal', 'akhir', 'bulan', 'jenis', 'pungut', 'setoris', 'totPungutAkhir', 'totSetorAkhir');

        if ($r->boolean('csv')) {
            $nm = "Pajak_Triwulan_{$tw}_{$tahun}.csv";
            $tmp = fopen('php://temp', 'r+');
            fputcsv($tmp, ["Rekap Pajak TW{$tw} Tahun {$tahun} (pungut per bulan)"], ';');
            $nmBulan = fn ($b) => ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$b];
            fputcsv($tmp, array_merge(['Jenis Pajak'], array_map($nmBulan, $bulan), ['Dipungut (TW)', 'Disetor (TW)', 'Sisa']), ';');
            foreach ($jenis as $j) {
                fputcsv($tmp, array_merge([$j['nama']],
                    array_map(fn ($b) => $j['dipungut'][$b] ?? 0, $bulan),
                    [$j['td'], $j['ts'], $j['sisa']]), ';');
            }
            rewind($tmp);
            $csv = stream_get_contents($tmp) ?: '';
            fclose($tmp);

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$nm}\"",
            ]);
        }

        return view('laporan.pajak-triwulan', $data);
    }
}
