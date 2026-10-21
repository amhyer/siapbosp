<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\BkuTutupBulan;
use App\Models\BosType;
use App\Models\KasNota;
use App\Models\KasTax;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\RkasHeader;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Services\JuknisBosp2026Service;
use App\Services\KasService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PengeluaranController extends Controller
{
    private function tahun(): int
    {
        return (int) session('tahun', now()->year);
    }

    public function belanja()
    {
        $q = trim((string) request('q', ''));
        $items = KasTransaction::where('type', 'belanja')->where('tahun', $this->tahun())
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('keterangan', 'ilike', "%{$q}%")
                ->orWhere('no_bukti', 'ilike', "%{$q}%")))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->with(['details.rekening', 'taxes'])->paginate(15)->withQueryString();

        return view('pengeluaran.belanja', compact('items') + ['tahun' => $this->tahun(), 'q' => $q]);
    }

    public function belanjaCreate()
    {
        return view('pengeluaran.belanja-form', [
            'rekenings' => Rekening::belanja()->with('komponen')->orderBy('kode')->get(),
            'pajaks' => RekeningPajak::orderBy('kode')->get(),
            'bosTypes' => BosType::orderBy('id')->get(),
            'stages' => Stage::where('tahun', $this->tahun())->where('is_open', true)->orderBy('urutan')->get(),
            'saldo' => KasService::saldo(auth()->user()->school_id, $this->tahun()),
        ]);
    }

    /** Endpoint JSON kalkulator rekomendasi pajak otomatis ala ARKAS 4 v4.3.0. */
    public function hitungPajak(Request $r)
    {
        $sumber = $r->query('sumber', 'langsung') === 'siplah' ? 'siplah' : 'langsung';
        $dpp = max(0, (int) $r->query('nilai', 0));
        $rekKode = null;
        if ($r->filled('rekening_id')) {
            $rekKode = Rekening::find((int) $r->query('rekening_id'))?->kode;
        } elseif ($r->filled('kode_rekening')) {
            $rekKode = (string) $r->query('kode_rekening');
        }
        $punyaNpwp = ! $r->boolean('tanpa_npwp');
        $ppn = (int) $r->query('ppn', 0);
        if (! in_array($ppn, [11, 12], true)) {
            // Tanpa parameter eksplisit: PPN 12% untuk 2026 ke atas
            // (penyesuaian PPN 12% pada ARKAS & SiPLah), 11% untuk tahun lama.
            $tahun = (int) $r->query('tahun', 0);
            if ($tahun < 2000) {
                $tanggal = (string) $r->query('tanggal', '');
                $tahun = (int) substr($tanggal, 0, 4);
            }
            $ppn = ($tahun >= 2026) ? 12 : 11;
        }

        return response()->json(JuknisBosp2026Service::rekomendasiPajakArkas(
            $sumber, $dpp, $rekKode, $punyaNpwp, $ppn
        ));
    }

    public function belanjaStore(Request $r)
    {
        $sid = auth()->user()->school_id;
        $data = $r->validate([
            'no_bukti' => 'nullable|string|max:80',
            'no_nota' => 'nullable|string|max:80',
            'tanggal' => 'required|date',
            'bos_type_id' => 'required|integer',
            'stage_id' => 'required|integer',
            'metode_bayar' => 'required|in:tunai,transfer',
            'keterangan' => 'required|string|max:500',
            'sumber' => 'nullable|in:langsung,siplah',
            'nama_toko' => 'nullable|string|max:160',
            'usaha_perseorangan' => 'nullable|boolean',
            'alamat_toko' => 'nullable|string|max:255',
            'no_telp' => 'nullable|string|max:40',
            'npwp' => 'nullable|string|max:30',
            'tanpa_npwp' => 'nullable|boolean',
            'ongkir_siplah' => 'nullable|numeric|min:0',
            'biaya_admin_siplah' => 'nullable|numeric|min:0',
            'rekening_id' => 'required|array|min:1',
            'rekening_id.*' => 'required|integer',
            'uraian' => 'array',
            'uraian.*' => 'nullable|string',
            'nilai' => 'required|array',
            'nilai.*' => 'required|numeric|min:1',
            'pajak_id' => 'array',
            'pajak_id.*' => 'nullable|integer',
            'billing' => 'array',
            'billing.*' => 'nullable|string',
            'nilai_pajak' => 'array',
            'nilai_pajak.*' => 'nullable|numeric|min:0',
            'lampiran' => 'array',
            'lampiran.*' => 'file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $stage = Stage::find($data['stage_id']);
        if (! $stage || ! $stage->is_open) {
            return back()->with('error', 'Tahap ini sedang DITUTUP oleh Dinas.')->withInput();
        }
        if (TutupBuku::terkunci($sid, $this->tahun(), (int) $data['stage_id'])) {
            return back()->with('error', 'Tahap ini sudah DITUTUP bukunya. Minta Kepala Sekolah membuka kembali jika perlu koreksi.')->withInput();
        }
        if ($terkunci = $this->cekBulan($data['tanggal'])) {
            return back()->with('error', $terkunci)->withInput();
        }

        $total = array_sum(array_map('intval', $data['nilai']));
        $saldo = KasService::saldo($sid);
        $tersedia = $data['metode_bayar'] === 'tunai' ? $saldo['tunai'] : $saldo['bank'];
        if ($total > $tersedia) {
            return back()->with('error', 'Saldo '.($data['metode_bayar'] === 'tunai' ? 'Tunai' : 'Bank').' tidak cukup ('.rp($tersedia).').')->withInput();
        }

        // Cek peringatan kuota kumulatif bulan rencana RKAS sebelum simpan (agar baseline realisasi belum termasuk transaksi ini)
        $sumPerRek = [];
        foreach ($data['rekening_id'] as $i => $rid) {
            $sumPerRek[(int) $rid] = ($sumPerRek[(int) $rid] ?? 0) + (int) $data['nilai'][$i];
        }
        $peringatanKuotaBulan = JuknisBosp2026Service::cekKuotaBulananRkas(
            $sid, $this->tahun(), substr($data['tanggal'], 0, 7), $sumPerRek
        );

        $noBku = KasService::nextNoBku($sid, $data['tanggal']);
        $notaKey = null;
        if (! empty($data['nama_toko']) || ! empty($data['npwp']) || ! empty($data['no_nota']) || (($data['sumber'] ?? 'langsung') === 'siplah')) {
            $notaKey = 'manual-'.$this->tahun().'-'.$noBku.'-'.uniqid();
            KasNota::create([
                'school_id' => $sid,
                'tahun' => $this->tahun(),
                'arkas_id' => 'nota:'.$notaKey,
                'no_bukti' => $data['no_bukti'] ?? ('BKU#'.$noBku),
                'tanggal_nota' => $data['tanggal'],
                'no_nota' => $data['no_nota'] ?? null,
                'nama_toko' => $data['nama_toko'] ?? (($data['sumber'] ?? '') === 'siplah' ? 'Penyedia SIPLah' : null),
                'usaha_perseorangan' => (bool) ($data['usaha_perseorangan'] ?? false),
                'alamat_toko' => $data['alamat_toko'] ?? null,
                'no_telp' => $data['no_telp'] ?? null,
                'npwp' => $data['npwp'] ?? null,
                'tanpa_npwp' => (bool) ($data['tanpa_npwp'] ?? false),
                'total' => $total,
                'lunas_bayar' => $total,
                'ongkir_siplah' => max(0, (int) ($data['ongkir_siplah'] ?? 0)),
                'biaya_admin_siplah' => max(0, (int) ($data['biaya_admin_siplah'] ?? 0)),
            ]);
        }

        $t = KasTransaction::create([
            'tahun' => $this->tahun(),
            'type' => 'belanja',
            'no_bukti' => $data['no_bukti'] ?? null,
            'arkas_nota_id' => $notaKey,
            'no_bku' => $noBku,
            'tanggal' => $data['tanggal'],
            'bos_type_id' => $data['bos_type_id'],
            'stage_id' => $data['stage_id'],
            'metode_bayar' => $data['metode_bayar'],
            'keterangan' => $data['keterangan'],
            'total' => $total,
            'created_by' => auth()->id(),
        ]);

        $sumber = $data['sumber'] ?? 'langsung';
        foreach ($data['rekening_id'] as $i => $rid) {
            $nilai = (int) $data['nilai'][$i];
            $t->details()->create([
                'rekening_id' => $rid,
                'uraian' => $data['uraian'][$i] ?? $data['keterangan'],
                'nilai' => $nilai,
                'sumber' => $sumber,
            ]);
        }

        // Pada SIPLah (ala ARKAS 4 Bab XVI): pajak otomatis dipungut/disetor marketplace sehingga tidak wajib lapor manual
        $statusPajakAwal = $sumber === 'siplah' ? 'sudah' : 'belum';
        foreach ($data['pajak_id'] ?? [] as $i => $pid) {
            if ($pid && (int) ($data['nilai_pajak'][$i] ?? 0) > 0) {
                $t->taxes()->create([
                    'rekening_pajak_id' => $pid,
                    'kode_billing' => $data['billing'][$i] ?? null,
                    'ntpn' => $sumber === 'siplah' ? 'SIPLAH-OTOMATIS' : null,
                    'nilai' => (int) $data['nilai_pajak'][$i],
                    'status' => $statusPajakAwal,
                ]);
            }
        }

        foreach ($data['lampiran'] ?? [] as $file) {
            $path = $file->store('lampiran/'.$sid);
            $t->attachments()->create([
                'path' => $path,
                'nama' => $file->getClientOriginalName(),
                'ukuran' => $file->getSize(),
            ]);
        }

        // Cek peringatan over-RKAS & kuota bulanan
        $warnings = $peringatanKuotaBulan;
        $rkas = RkasHeader::where('school_id', $sid)->where('tahun', $this->tahun())
            ->where('status', '!=', 'draft')->orderByDesc('revisi_ke')->first();
        if ($rkas) {
            $anggaran = $rkas->details()->selectRaw('rekening_id, sum(jumlah) j')->groupBy('rekening_id')->pluck('j', 'rekening_id');
            $realisasi = \DB::table('kas_details as d')
                ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
                ->where('t.school_id', $sid)->where('t.tahun', $this->tahun())->where('t.type', 'belanja')
                ->selectRaw('d.rekening_id, sum(d.nilai) j')->groupBy('d.rekening_id')->pluck('j', 'rekening_id');
            foreach ($sumPerRek as $rid => $nilai) {
                $paguRek = (int) ($anggaran[$rid] ?? 0);
                if ($paguRek > 0 && ((int) ($realisasi[$rid] ?? 0)) > $paguRek) {
                    $kode = Rekening::find($rid)?->kode;
                    $warnings[] = "Perhatian: realisasi rekening $kode sudah melewati anggaran RKAS (".rp($paguRek).').';
                    break;
                }
            }
        }
        $paguTh = (int) Allocation::where('tahun', $this->tahun())->sum('nilai');
        $totBelanjaTh = (int) KasTransaction::where('tahun', $this->tahun())->where('type', 'belanja')->sum('total');
        if ($paguTh > 0 && $totBelanjaTh > $paguTh) {
            $lebih = $totBelanjaTh - $paguTh;
            $warnings[] = '⛔ Total belanja tahun ini ('.rp($totBelanjaTh).') telah MELEBIHI pagu ('.rp($paguTh).') sebesar '.rp($lebih).'. Segera koordinasi dengan Dinas.';
        }

        $msg = 'Belanja tersimpan. No BKU: '.$t->no_bku;

        return redirect()->route('belanja.index')
            ->with('ok', $msg)
            ->with('warning', $warnings ? implode(' ', $warnings) : null);
    }

    /** Kwitansi siap cetak untuk satu transaksi belanja. */
    public function kwitansi(KasTransaction $belanja)
    {
        abort_unless($belanja->type === 'belanja', 404);
        $t = $belanja->load(['details.rekening', 'taxes.rekeningPajak', 'bosType', 'stage']);
        $sekolah = auth()->user()->school;
        $penandatangan = Penandatangan::orderBy('id')->get();

        return view('pengeluaran.kwitansi', compact('t', 'sekolah', 'penandatangan'));
    }

    /** 🖨️ Paket Dokumen SPJ (format ARKAS) untuk satu belanja. */
    public function spj(KasTransaction $belanja)
    {
        abort_unless($belanja->type === 'belanja', 404);
        $belanja->load(['details.rekening', 'taxes', 'bosType', 'stage']);
        $sid = auth()->user()->school_id;
        $sekolah = auth()->user()->school;
        $penandatangan = Penandatangan::orderBy('id')->get();
        $kepsek = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'kepala'));
        $komite = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'komite'))
            ?: $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'ketua'));

        $nota = null;
        if ($belanja->arkas_nota_id) {
            $nota = KasNota::where('school_id', $sid)->where('arkas_id', 'nota:'.$belanja->arkas_nota_id)->first();
        }
        if (! $nota && $belanja->no_bukti) {
            $nota = KasNota::where('school_id', $sid)->where('no_bukti', $belanja->no_bukti)->first();
        }
        if (! $nota) {
            $dari = $belanja->tanggal->copy()->subDays(15)->format('Y-m-d');
            $keg = $belanja->tanggal->copy()->addDays(15)->format('Y-m-d');
            $nota = KasNota::where('school_id', $sid)->whereBetween('tanggal_nota', [$dari, $keg])->get()
                ->sortBy(fn ($n) => abs((int) $n->total - (int) $belanja->total))->first();
        }

        $rincian = $belanja->details->map(fn ($d) => [
            'uraian' => $d->uraian, 'nilai' => (int) $d->nilai, 'rekening' => $d->rekening?->kode,
        ]);
        $totalHarga = (int) $rincian->sum('nilai');
        $pajak = (int) $belanja->taxes->sum('nilai');
        $nomorDefault = $belanja->no_bukti ?: sprintf('SP-%04d', (int) $belanja->no_bku);
        $tgl = $belanja->tanggal;

        return view('pengeluaran.spj', compact('belanja', 'sekolah', 'kepsek', 'komite', 'nota',
            'rincian', 'totalHarga', 'pajak', 'nomorDefault', 'tgl'));
    }

    public function belanjaShow(KasTransaction $belanja)
    {
        abort_unless($belanja->type === 'belanja', 404);
        $belanja->load(['details.rekening', 'taxes.rekeningPajak', 'stage', 'bosType']);

        return view('pengeluaran.belanja-show', ['t' => $belanja]);
    }

    public function setorPajak()
    {
        $sid = auth()->user()->school_id;
        $belum = KasTax::with(['transaksi.details', 'rekeningPajak'])
            ->whereHas('transaksi', fn ($q) => $q->where('school_id', $sid)->where('type', 'belanja'))
            ->where('status', 'belum')->orderBy('id')->get()->groupBy('kas_transaction_id');
        $riwayat = KasTransaction::whereIn('type', ['setor_pajak', 'setor_utang_pajak'])->where('tahun', $this->tahun())
            ->orderByDesc('tanggal')->with('taxes.rekeningPajak')->paginate(10);
        $rekeningPajaks = RekeningPajak::orderBy('kode')->get();

        return view('pengeluaran.setor-pajak', compact('belum', 'riwayat', 'rekeningPajaks'));
    }

    public function setorPajakStore(Request $r)
    {
        $sid = auth()->user()->school_id;
        $data = $r->validate([
            'tanggal' => 'required|date', 'keterangan' => 'required',
            'tax_id' => 'required|array|min:1', 'tax_id.*' => 'required|integer',
            'ntpn' => 'required|array', 'ntpn.*' => 'nullable|string',
        ]);
        if ($terkunci = $this->cekBulan($data['tanggal'])) {
            return back()->with('error', $terkunci)->withInput();
        }
        $taxes = KasTax::whereIn('id', $data['tax_id'])->where('status', 'belum')
            ->whereHas('transaksi', fn ($q) => $q->where('school_id', $sid))->get();
        if ($taxes->isEmpty()) {
            return back()->with('error', 'Rincian pajak tidak ditemukan atau sudah disetor.');
        }
        $total = (int) $taxes->sum('nilai');
        $saldo = KasService::saldo($sid);
        if ($total > $saldo['bank']) {
            return back()->with('error', 'Saldo Kas Bank tidak mencukupi ('.rp($saldo['bank']).').');
        }

        $t = KasTransaction::create([
            'tahun' => $this->tahun(), 'type' => 'setor_pajak',
            'belanja_transaction_id' => $taxes->first()->kas_transaction_id,
            'no_bku' => KasService::nextNoBku($sid, $data['tanggal']),
            'tanggal' => $data['tanggal'], 'metode_bayar' => 'transfer',
            'keterangan' => $data['keterangan'], 'total' => $total, 'created_by' => auth()->id(),
        ]);
        $ntpnMap = array_combine($data['tax_id'], $data['ntpn']);
        foreach ($taxes as $tax) {
            $tax->update(['status' => 'sudah', 'ntpn' => $ntpnMap[$tax->id] ?? null, 'paid_transaction_id' => $t->id]);
            $t->taxes()->create([
                'rekening_pajak_id' => $tax->rekening_pajak_id, 'kode_billing' => $tax->kode_billing,
                'ntpn' => $tax->ntpn, 'nilai' => $tax->nilai, 'status' => 'sudah',
            ]);
        }

        return back()->with('ok', 'Setor pajak tersimpan (total '.rp($total).')');
    }

    public function setorUtangStore(Request $r)
    {
        $sid = auth()->user()->school_id;
        $data = $r->validate([
            'tanggal' => 'required|date', 'keterangan' => 'required',
            'pajak_id' => 'required|array|min:1', 'pajak_id.*' => 'required|integer',
            'nilai_pajak' => 'required|array', 'nilai_pajak.*' => 'required|numeric|min:1',
            'ntpn' => 'array', 'ntpn.*' => 'nullable|string',
        ]);
        if ($terkunci = $this->cekBulan($data['tanggal'])) {
            return back()->with('error', $terkunci)->withInput();
        }
        $total = array_sum(array_map('intval', $data['nilai_pajak']));
        $saldo = KasService::saldo($sid);
        if ($total > $saldo['bank']) {
            return back()->with('error', 'Saldo Kas Bank tidak mencukupi ('.rp($saldo['bank']).').');
        }
        $t = KasTransaction::create([
            'tahun' => $this->tahun(), 'type' => 'setor_utang_pajak',
            'no_bku' => KasService::nextNoBku($sid, $data['tanggal']),
            'tanggal' => $data['tanggal'], 'metode_bayar' => 'transfer',
            'keterangan' => $data['keterangan'], 'total' => $total, 'created_by' => auth()->id(),
        ]);
        foreach ($data['pajak_id'] as $i => $pid) {
            $t->taxes()->create([
                'rekening_pajak_id' => $pid, 'ntpn' => $data['ntpn'][$i] ?? null,
                'nilai' => (int) $data['nilai_pajak'][$i], 'status' => 'sudah',
            ]);
        }

        return back()->with('ok', 'Setor utang pajak tersimpan.');
    }

    public function destroy(KasTransaction $kasTransaction)
    {
        abort_unless(in_array($kasTransaction->type, ['belanja', 'setor_utang_pajak']), 403);
        if (TutupBuku::terkunci(auth()->user()->school_id, $kasTransaction->tahun, $kasTransaction->stage_id)) {
            return back()->with('error', 'Tidak bisa dihapus: tahap ini sudah ditutup bukunya.');
        }
        if ($terkunci = $this->cekBulan($kasTransaction->tanggal->format('Y-m-d'))) {
            return back()->with('error', $terkunci);
        }
        if ($kasTransaction->type === 'belanja' && $kasTransaction->taxes()->where('status', 'sudah')->exists()) {
            return back()->with('error', 'Tidak bisa dihapus: pajaknya sudah disetor. Hapus dulu setor pajak terkait.');
        }
        if (auth()->user()->role !== 'operator') {
            return back()->with('error', 'Hanya operator yang boleh menghapus.');
        }
        $kasTransaction->delete();

        return back()->with('ok', 'Transaksi dihapus.');
    }

    private function cekBulan(string $tanggal): ?string
    {
        if (BkuTutupBulan::terkunciBulan(auth()->user()->school_id, substr($tanggal, 0, 7))) {
            $nm = Carbon::parse(substr($tanggal, 0, 7).'-01')->translatedFormat('F Y');

            return "BKU bulan {$nm} sudah DITUTUP — transaksi tanggal itu tidak bisa dicatat/ubah. Kepala Sekolah dapat membuka kembali bulan itu bila ada koreksi.";
        }

        return null;
    }
}
