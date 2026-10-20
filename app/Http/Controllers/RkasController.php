<?php

namespace App\Http\Controllers;

use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\User;
use App\Notifications\InfoNotif;
use App\Services\Aktivitas;
use App\Services\EmailNotif;
use App\Services\RkasExportService;
use Illuminate\Http\Request;

class RkasController extends Controller
{
    public function index()
    {
        $tahun = (int) session('tahun', now()->year);
        $items = RkasHeader::with('details')->where('tahun', $tahun)->orderByDesc('revisi_ke')->get();

        return view('rkas.index', compact('items', 'tahun'));
    }

    public function create()
    {
        $tahun = (int) session('tahun', now()->year);
        $sid = auth()->user()->school_id;

        // Rekomendasi pergeseran/perubahan: rincian RKAS non-draft terakhir yang
        // sudah ada realisasinya (khususnya via SIPLah) — tawarkan dibawa ke revisi.
        $header = RkasHeader::where('school_id', $sid)->where('tahun', $tahun)
            ->where('status', '!=', 'draft')->orderByDesc('revisi_ke')->first();
        $rekomendasi = [];
        if ($header) {
            $realisasi = \DB::table('kas_details as d')
                ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
                ->where('t.school_id', $sid)->where('t.tahun', $tahun)->where('t.type', 'belanja')
                ->selectRaw('d.rekening_id, sum(d.nilai) j, max(case when d.sumber = ? then 1 else 0 end) s', ['siplah'])
                ->groupBy('d.rekening_id')->get()->keyBy('rekening_id');
            foreach ($header->details()->with('rekening')->get() as $det) {
                $r = $realisasi[$det->rekening_id] ?? null;
                if ($r && (int) $r->j > 0) {
                    $rekomendasi[] = [
                        'rekening_id' => $det->rekening_id,
                        'kode' => $det->rekening->kode ?? '',
                        'nama' => $det->rekening->nama ?? '',
                        'uraian' => $det->uraian,
                        'anggaran' => (int) $det->jumlah,
                        'realisasi' => (int) $r->j,
                        'siplah' => (int) $r->s === 1,
                    ];
                }
            }
        }

        return view('rkas.create', [
            'rekenings' => Rekening::belanja()->with('komponen')->orderBy('kode')->get(),
            'tahun' => $tahun,
            'rekomendasi' => $rekomendasi,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'tahun' => 'required|integer', 'no_rkas' => 'nullable', 'catatan' => 'nullable',
            'rekening_id' => 'required|array|min:1', 'rekening_id.*' => 'required|integer|exists:rekenings,id',
            'uraian' => 'required|array', 'uraian.*' => 'required|string',
            'satuan' => 'array', 'satuan.*' => 'nullable|string',
            'banyak' => 'array', 'banyak.*' => 'nullable|numeric|min:1',
            'harga' => 'array', 'harga.*' => 'nullable|numeric|min:0',
            'sumber' => 'array', 'sumber.*' => 'nullable|string',
        ]);
        $tahun = (int) $data['tahun'];
        $revisi = (int) RkasHeader::where('school_id', auth()->user()->school_id)->where('tahun', $tahun)->max('revisi_ke') + 1;
        $h = RkasHeader::create(['tahun' => $tahun, 'revisi_ke' => $revisi, 'no_rkas' => $data['no_rkas'] ?? null, 'catatan' => $data['catatan'] ?? null, 'status' => 'draft']);
        foreach ($data['rekening_id'] as $i => $rid) {
            $banyak = max(1, (int) ($data['banyak'][$i] ?? 1));
            $harga = (int) ($data['harga'][$i] ?? 0);
            $h->details()->create([
                'rekening_id' => $rid, 'uraian' => $data['uraian'][$i],
                'satuan' => $data['satuan'][$i] ?: 'paket', 'banyak' => $banyak,
                'harga_satuan' => $harga, 'jumlah' => $banyak * $harga,
                'sumber_dana' => $data['sumber'][$i] ?: 'BOS Reguler',
            ]);
        }

        return redirect()->route('rkas.show', $h)->with('ok', "RKAS revisi ke-$revisi tersimpan.");
    }

    public function impor(Request $r)
    {
        $r->validate(['berkas' => 'required|file|max:2048']);
        $tahun = (int) session('tahun', now()->year);
        $isi = file($r->file('berkas')->getRealPath());
        if (! $isi) {
            return back()->with('error', 'Berkas kosong.');
        }
        $sep = substr_count($isi[0], ';') >= substr_count($isi[0], ',') ? ';' : ',';
        $baris = [];
        $takDikenal = [];
        $rekeningMap = Rekening::belanja()->pluck('id', 'kode');
        foreach ($isi as $no => $b) {
            $kol = array_map('trim', str_getcsv($b, $sep));
            if (count($kol) < 5 || empty($kol[0]) || str_contains(strtolower($kol[0]), 'kode')) {
                continue;
            }
            [$kode, $uraian, $satuan, $banyak, $harga, $sumber] = array_pad($kol, 6, null);
            $rid = $rekeningMap[$kode] ?? null;
            if (! $rid) {
                $takDikenal[] = ($no + 1)." (kode $kode)";

                continue;
            }
            $baris[] = [$rid, $uraian ?: 'Item RKAS', $satuan ?: 'paket', max(1, (int) $banyak), max(0, (int) str_replace(['.', ' '], '', (string) $harga)), $sumber ?: 'BOS Reguler'];
        }
        if (! $baris) {
            return back()->with('error', 'Tidak ada rincian valid. Periksa kode rekeningnya.');
        }
        $sid = auth()->user()->school_id;
        $revisi = (int) RkasHeader::where('school_id', $sid)->where('tahun', $tahun)->max('revisi_ke') + 1;
        $h = RkasHeader::create(['tahun' => $tahun, 'revisi_ke' => $revisi, 'status' => 'draft', 'catatan' => 'Impor CSV']);
        foreach ($baris as [$rid, $uraian, $satuan, $banyak, $harga, $sumber]) {
            $h->details()->create(['rekening_id' => $rid, 'uraian' => $uraian, 'satuan' => $satuan, 'banyak' => $banyak, 'harga_satuan' => $harga, 'jumlah' => $banyak * $harga, 'sumber_dana' => $sumber]);
        }
        $msg = 'RKAS revisi ke-'.$revisi.' dibuat dari '.count($baris).' rincian CSV.';
        if ($takDikenal) {
            $msg .= ' Kode tak dikenal baris: '.implode(', ', $takDikenal);
        }

        return redirect()->route('rkas.show', $h)->with('ok', $msg);
    }

    public function show(RkasHeader $rkasHeader)
    {
        $rkasHeader->load('details.rekening');

        return view('rkas.show', ['h' => $rkasHeader]);
    }

    /** Unduh RKAS sebagai PDF resmi (A4 landscape). */
    public function pdf(RkasHeader $rkasHeader)
    {
        $rkasHeader->load('details.rekening.komponen');
        $sekolah = auth()->user()->school;

        return response(RkasExportService::pdf($rkasHeader, $sekolah,
            Penandatangan::orderBy('id')->get()), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.
                    RkasExportService::namaBerkas($rkasHeader, $sekolah, 'pdf').'"',
            ]);
    }

    /** Unduh RKAS sebagai Excel (sheet RKAS + Ringkasan Komponen). */
    public function xlsx(RkasHeader $rkasHeader)
    {
        $rkasHeader->load('details.rekening.komponen');
        $sekolah = auth()->user()->school;
        $path = RkasExportService::xlsx($rkasHeader, $sekolah);

        return response()->download($path, RkasExportService::namaBerkas($rkasHeader, $sekolah, 'xlsx'))
            ->deleteFileAfterSend(true);
    }

    public function aksi(Request $r, RkasHeader $rkasHeader)
    {
        $aksi = $r->validate(['aksi' => 'required|in:ajukan,setujui,kunci,kirim_dinas'])['aksi'];

        // Alur persetujuan Dinas: sekolah mengirim RKAS yang sudah disetujui Kepsek (atau hasil revisi)
        if ($aksi === 'kirim_dinas') {
            if (! in_array($rkasHeader->status, ['disetujui', 'revisi'])) {
                return back()->with('error', 'RKAS harus disetujui Kepala Sekolah dulu (atau sedang berstatus revisi) sebelum dikirim ke Dinas.');
            }
            $rkasHeader->update(['status' => 'dikirim_dinas', 'dikirim_dinas_at' => now(),
                'catatan_dinas' => null, 'ditinjau_oleh' => null, 'ditinjau_at' => null]);
            $namaSekolah = auth()->user()->school->nama;
            $penerimaDinas = User::whereIn('role', ['dinas', 'superadmin'])->get();
            $penerimaDinas->each(fn ($u) => $u->notify(
                new InfoNotif('📋', "RKAS masuk: {$namaSekolah}",
                    "RKAS TA {$rkasHeader->tahun} revisi ke-{$rkasHeader->revisi_ke} menunggu tinjauan Anda.", route('dinas.rkas'))));
            EmailNotif::kirim($penerimaDinas, "RKAS masuk: {$namaSekolah}", [
                "RKAS TA {$rkasHeader->tahun} revisi ke-{$rkasHeader->revisi_ke} dari {$namaSekolah} menunggu tinjauan Anda.",
                'Silakan masuk ke akun Dinas untuk meninjau dan mengesahkannya.',
            ], route('dinas.rkas'));
            Aktivitas::catat('rkas', "RKAS TA {$rkasHeader->tahun} revisi ke-{$rkasHeader->revisi_ke} dikirim ke Dinas");

            return back()->with('ok', 'RKAS terkirim ke Dinas untuk disahkan. Pantau statusnya di halaman ini.');
        }

        $map = ['ajukan' => 'diajukan', 'setujui' => 'disetujui', 'kunci' => 'dikunci'];
        if ($aksi === 'setujui' && auth()->user()->role !== 'kepsek') {
            return back()->with('error', 'Hanya Kepala Sekolah yang dapat menyetujui.');
        }
        if ($aksi === 'kunci' && $rkasHeader->status !== 'disahkan') {
            return back()->with('error', 'RKAS hanya bisa dikunci setelah DISAHKAN oleh Dinas.');
        }
        $rkasHeader->update(['status' => $map[$aksi]]);

        return back()->with('ok', 'Status RKAS diperbarui: '.$map[$aksi]);
    }
}
