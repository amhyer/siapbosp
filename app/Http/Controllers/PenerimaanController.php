<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\BkuTutupBulan;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Services\JejakHapus;
use App\Services\KasService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PenerimaanController extends Controller
{
    private function base(): array
    {
        $schoolId = auth()->user()->school_id;

        return [
            'tahun' => (int) session('tahun', now()->year),
            'bosTypes' => BosType::orderBy('id')->get(),
            'stages' => Stage::where('tahun', (int) session('tahun', now()->year))->where('is_open', true)->orderBy('urutan')->get(),
            'saldo' => KasService::saldo($schoolId),
            'schoolId' => $schoolId,
        ];
    }

    public function pendapatan()
    {
        $b = $this->base();
        $b['rekenings'] = Rekening::pendapatan()->orderBy('kode')->get();
        $q = trim((string) request('q', ''));
        $b['q'] = $q;

        // Tab sumber dana ala ARKAS 4: menyaring riwayat per jenis BOS.
        $tab = (string) request('tab', '');
        $tab = array_key_exists($tab, RkasHeader::TAB_SUMBER_DANA) ? $tab : '';
        $b['tabAktif'] = $tab;
        $b['tabsSumber'] = RkasHeader::TAB_SUMBER_DANA;

        $b['items'] = KasTransaction::where('type', 'pendapatan')->where('tahun', $b['tahun'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('keterangan', 'ilike', "%{$q}%")
                ->orWhere('no_bukti', 'ilike', "%{$q}%")))
            ->when($tab !== '', fn ($w) => $w->whereIn('bos_type_id', RkasHeader::bosTypeIds($tab)))
            ->orderByDesc('tanggal')->orderByDesc('id')->with('details.rekening')->paginate(15)->withQueryString();
        // ala ARKAS (konfirmasi penerimaan dana): pagu vs yang diterima (SILPA ikut disebut)
        $b['pagu'] = Allocation::where('tahun', $b['tahun'])->sum('nilai');
        $b['diterimaTahun'] = (int) KasTransaction::where('tahun', $b['tahun'])->where('type', 'pendapatan')->sum('total');
        $b['silpa'] = KasService::ledger($b['schoolId'], $b['tahun'])['saldoAwalTahun'];

        return view('penerimaan.pendapatan', $b);
    }

    public function pendapatanStore(Request $r)
    {
        $data = $r->validate([
            'tanggal' => 'required|date', 'bos_type_id' => 'required|integer',
            'stage_id' => 'required|integer', 'keterangan' => 'required',
            'rekening_id' => 'required|array|min:1', 'rekening_id.*' => 'required|integer',
            'nilai' => 'required|array', 'nilai.*' => 'required|numeric|min:1',
        ]);
        $stage = Stage::find($data['stage_id']);
        if (! $stage || ! $stage->is_open) {
            return back()->with('error', 'Tahap ini sedang DITUTUP oleh Dinas.')->withInput();
        }
        if (TutupBuku::terkunci(auth()->user()->school_id, (int) session('tahun', now()->year), (int) $data['stage_id'])) {
            return back()->with('error', 'Tahap ini sudah DITUTUP bukunya.')->withInput();
        }
        if ($terkunci = $this->cekBulan($data['tanggal'])) {
            return back()->with('error', $terkunci)->withInput();
        }
        $tahun = (int) session('tahun', now()->year);
        $total = array_sum(array_map('intval', $data['nilai']));
        $t = KasTransaction::create([
            'tahun' => $tahun, 'type' => 'pendapatan',
            'no_bku' => KasService::nextNoBku(auth()->user()->school_id, $data['tanggal']),
            'tanggal' => $data['tanggal'], 'bos_type_id' => $data['bos_type_id'], 'stage_id' => $data['stage_id'],
            'metode_bayar' => 'transfer', 'keterangan' => $data['keterangan'], 'total' => $total,
            'created_by' => auth()->id(),
        ]);
        foreach ($data['rekening_id'] as $i => $rid) {
            $t->details()->create(['rekening_id' => $rid, 'uraian' => $data['keterangan'], 'nilai' => (int) $data['nilai'][$i]]);
        }

        // Bila form dibuka dari aksi cepat lembar BKU (?kembali=bku), operator
        // dikembalikan ke BKU dengan baris baru ditandai (kilat kuning).
        $balik = $r->input('kembali') === 'bku'
            ? redirect()->route('bku', ['bulan' => $t->tanggal->format('Y-m'), 'baru' => $t->id])
            : back();

        // ala ARKAS (konfirmasi penerimaan dana): penerimaan tidak boleh melebihi pagu —
        // di sini peringatan (tidak memblokir) agar sekolah tetap bisa mencatat kroneker.
        $pagu = Allocation::where('tahun', $tahun)->sum('nilai');
        $diterima = KasTransaction::where('tahun', $tahun)->where('type', 'pendapatan')->sum('total');
        if ($pagu > 0 && $diterima > $pagu) {
            return $balik->with('warning', 'Tersimpan, tetapi total penerimaan tahun ini ('.rp($diterima).
                ') sudah MELEWATI pagu ('.rp($pagu).'). Periksa kembali penyaluran dana atau pagu di menu Referensi.');
        }

        return $balik->with('ok', 'Pendapatan tersimpan, masuk Kas Bank. No BKU: '.$t->no_bku);
    }

    /**
     * 💰 Bunga bank — penerimaan insidental di luar RKAS (ala ARKAS: bukan inferensi anggaran),
     * masuk langsung Kas Bank, ikut mengangkat saldo; pada akhir tahun menjadi bagian SiLPA.
     */
    public function bungaStore(Request $r)
    {
        $data = $r->validate([
            'tanggal' => 'required|date', 'nilai' => 'required|numeric|min:1',
            'bos_type_id' => 'required|integer', 'stage_id' => 'required|integer',
        ]);
        $sid = auth()->user()->school_id;
        $stage = Stage::find($data['stage_id']);
        if (! $stage || ! $stage->is_open) {
            return back()->with('error', 'Tahap ini sedang DITUTUP oleh Dinas.')->withInput();
        }
        if (TutupBuku::terkunci($sid, (int) session('tahun', now()->year), (int) $data['stage_id'])) {
            return back()->with('error', 'Tahap ini sudah DITUTUP bukunya.')->withInput();
        }
        if ($terkunci = $this->cekBulan($data['tanggal'])) {
            return back()->with('error', $terkunci)->withInput();
        }
        // Rekening "Bunga Bank": pakai yang sudah ada (nama mengandung bunga), bila belum ada pakai rekening pendapatan pertama.
        $rek = Rekening::pendapatan()->orderBy('kode')->get()
            ->first(fn ($x) => str_contains(strtolower($x->nama), 'bunga'))
            ?? Rekening::pendapatan()->orderBy('kode')->first()
            ?? Rekening::firstOrCreate(['kode' => '8.1.03.01.01.0001'], ['nama' => 'Bunga Bank', 'tipe' => 'pendapatan']);
        $nilai = (int) $data['nilai'];
        $bulan = Carbon::parse($data['tanggal'])->locale('id')->translatedFormat('F Y');
        $t = KasTransaction::create([
            'tahun' => (int) session('tahun', now()->year), 'type' => 'pendapatan',
            'no_bku' => KasService::nextNoBku($sid, $data['tanggal']),
            'tanggal' => $data['tanggal'], 'bos_type_id' => $data['bos_type_id'], 'stage_id' => $data['stage_id'],
            'metode_bayar' => 'transfer', 'keterangan' => 'Bunga Bank '.$bulan, 'total' => $nilai,
            'created_by' => auth()->id(),
        ]);
        $t->details()->create(['rekening_id' => $rek->id, 'uraian' => $t->keterangan, 'nilai' => $nilai]);

        return back()->with('ok', 'Bunga Bank '.rp($nilai).' tersimpan — masuk Kas Bank. No BKU: '.$t->no_bku);
    }

    public function ambil()
    {
        $b = $this->base();
        $b['items'] = KasTransaction::where('type', 'ambil_simpanan')->where('tahun', $b['tahun'])->orderByDesc('tanggal')->orderByDesc('id')->paginate(15);

        return view('penerimaan.ambil', $b);
    }

    public function ambilStore(Request $r)
    {
        $data = $r->validate(['tanggal' => 'required|date', 'nilai' => 'required|numeric|min:1', 'keterangan' => 'required']);
        if ($terkunci = $this->cekBulan($data['tanggal'])) {
            return back()->with('error', $terkunci)->withInput();
        }
        $saldo = KasService::saldo(auth()->user()->school_id);
        if ($data['nilai'] > $saldo['bank']) {
            return back()->with('error', 'Saldo Kas Bank tidak mencukupi ('.rp($saldo['bank']).').');
        }
        KasTransaction::create([
            'tahun' => (int) session('tahun', now()->year), 'type' => 'ambil_simpanan',
            'no_bku' => KasService::nextNoBku(auth()->user()->school_id, $data['tanggal']),
            'tanggal' => $data['tanggal'], 'metode_bayar' => 'tunai',
            'keterangan' => $data['keterangan'], 'total' => (int) $data['nilai'], 'created_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Tarik tunai tersimpan: Bank → Tunai.');
    }

    public function mutasi()
    {
        $b = $this->base();
        $b['items'] = KasTransaction::where('type', 'mutasi_tunai_ke_bank')->where('tahun', $b['tahun'])->orderByDesc('tanggal')->orderByDesc('id')->paginate(15);

        return view('penerimaan.mutasi', $b);
    }

    public function mutasiStore(Request $r)
    {
        $data = $r->validate(['tanggal' => 'required|date', 'nilai' => 'required|numeric|min:1', 'keterangan' => 'required']);
        if ($terkunci = $this->cekBulan($data['tanggal'])) {
            return back()->with('error', $terkunci)->withInput();
        }
        $saldo = KasService::saldo(auth()->user()->school_id);
        if ($data['nilai'] > $saldo['tunai']) {
            return back()->with('error', 'Saldo Kas Tunai tidak mencukupi ('.rp($saldo['tunai']).').');
        }
        KasTransaction::create([
            'tahun' => (int) session('tahun', now()->year), 'type' => 'mutasi_tunai_ke_bank',
            'no_bku' => KasService::nextNoBku(auth()->user()->school_id, $data['tanggal']),
            'tanggal' => $data['tanggal'], 'metode_bayar' => 'transfer',
            'keterangan' => $data['keterangan'], 'total' => (int) $data['nilai'], 'created_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Setor tunai tersimpan: Tunai → Bank.');
    }

    public function destroy(Request $r, KasTransaction $kasTransaction)
    {
        abort_unless(in_array($kasTransaction->type, ['pendapatan', 'ambil_simpanan', 'mutasi_tunai_ke_bank']), 403);
        if (TutupBuku::terkunci(auth()->user()->school_id, $kasTransaction->tahun, $kasTransaction->stage_id)) {
            return back()->with('error', 'Tidak bisa dihapus: tahap ini sudah ditutup bukunya.');
        }
        if ($terkunci = $this->cekBulan($kasTransaction->tanggal->format('Y-m-d'))) {
            return back()->with('error', $terkunci);
        }
        if (auth()->user()->role !== 'operator') {
            return back()->with('error', 'Hanya operator yang boleh menghapus.');
        }
        // Setiap penghapusan wajib beralasan dan meninggalkan jejak audit
        // (append-only), karena ARKAS 4 sendiri tidak mengenal hapus langsung.
        $alasan = $r->validate(JejakHapus::aturan(), JejakHapus::pesan())['alasan'];
        JejakHapus::catat($kasTransaction, $alasan);
        $kasTransaction->delete();

        return back()->with('ok', 'Transaksi dihapus dan tercatat di Log Sistem.');
    }

    /** Bukti kunci BKU bulanan (ala ARKAS): catatan pada bulan yang sudah ditutup diblokir. */
    private function cekBulan(string $tanggal): ?string
    {
        if (BkuTutupBulan::terkunciBulan(auth()->user()->school_id, substr($tanggal, 0, 7))) {
            $nm = Carbon::parse(substr($tanggal, 0, 7).'-01')->translatedFormat('F Y');

            return "BKU bulan {$nm} sudah DITUTUP — transaksi tanggal itu tidak bisa dicatat/ubah. Kepala Sekolah dapat membuka kembali bulan itu bila ada koreksi.";
        }

        return null;
    }
}
