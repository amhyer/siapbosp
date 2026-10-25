<?php

namespace App\Http\Controllers;

use App\Exceptions\ArkasRunAktifException;
use App\Models\ArkasImportRun;
use App\Models\ArkasKode;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RkasDetail;
use App\Models\RkasHeader;
use App\Models\StandarHarga;
use App\Models\User;
use App\Notifications\InfoNotif;
use App\Services\Aktivitas;
use App\Services\AntreanArkasService;
use App\Services\EmailNotif;
use App\Services\JuknisBosp2026Service;
use App\Services\PraAjukanRkasService;
use App\Services\RkasExportService;
use Illuminate\Http\Request;

class RkasController extends Controller
{
    public function index(Request $r)
    {
        $tahun = (int) session('tahun', now()->year);
        $sekolah = auth()->user()->school;
        $sumberFilter = trim((string) $r->query('sumber', ''));

        $items = RkasHeader::with('details.rekening.komponen')
            ->where('tahun', $tahun)
            ->when($sumberFilter !== '', fn ($q) => $q->where('sumber_dana', $sumberFilter))
            ->orderByDesc('revisi_ke')
            ->get();

        $pagu = JuknisBosp2026Service::paguSumberDana($sekolah, $tahun, $sumberFilter ?: 'BOS Reguler');
        $adaTahunLalu = RkasHeader::where('school_id', $sekolah->id)
            ->where('tahun', $tahun - 1)
            ->exists();
        $aktifTerakhir = $items->first(fn ($x) => $x->status !== 'draft') ?? $items->first();

        // Rekap anggaran per sumber dana (revisi terbaru per sumber, tak
        // dijumlahkan ganda antar revisi).
        $rekapSumber = collect();
        foreach ($items as $h) {
            foreach ($h->details->groupBy('sumber_dana') as $sumber => $rows) {
                if ($rekapSumber->has($sumber)) {
                    continue;
                }
                $rekapSumber->put($sumber, [
                    'nilai' => (int) $rows->sum('jumlah'),
                    'baris' => $rows->count(),
                ]);
            }
        }

        return view('rkas.index', [
            'items' => $items,
            'tahun' => $tahun,
            'pagu' => $pagu,
            'sekolah' => $sekolah,
            'sumberFilter' => $sumberFilter,
            'sumberList' => RkasHeader::SUMBER_DANA_ARKAS,
            'adaTahunLalu' => $adaTahunLalu,
            'aktifTerakhir' => $aktifTerakhir,
            'rekapSumber' => $rekapSumber,
        ]);
    }

    public function create(Request $r)
    {
        $tahun = (int) session('tahun', now()->year);
        $sekolah = auth()->user()->school;
        $sid = $sekolah->id;

        $jenis = in_array($r->query('jenis'), ['murni', 'pergeseran', 'perubahan'], true)
            ? (string) $r->query('jenis')
            : 'murni';
        $sumber = (string) $r->query('sumber', 'BOS Reguler');

        $acuanHeader = null;
        if ($r->filled('acuan')) {
            $acuanHeader = RkasHeader::with('details.rekening')
                ->where('school_id', $sid)
                ->find((int) $r->query('acuan'));
            if ($acuanHeader && $acuanHeader->sumber_dana) {
                $sumber = $acuanHeader->sumber_dana;
            }
        } elseif ($jenis !== 'murni') {
            $acuanHeader = RkasHeader::with('details.rekening')
                ->where('school_id', $sid)
                ->where('tahun', $tahun)
                ->where('status', '!=', 'draft')
                ->orderByDesc('revisi_ke')
                ->first();
        }

        // Rekomendasi pergeseran/perubahan: rincian RKAS non-draft terakhir yang sudah ada realisasinya
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
                $rv = $realisasi[$det->rekening_id] ?? null;
                if ($rv && (int) $rv->j > 0) {
                    $rekomendasi[] = [
                        'rekening_id' => $det->rekening_id,
                        'kode_kegiatan' => $det->kode_kegiatan ?? '06.05',
                        'kode' => $det->rekening->kode ?? '',
                        'nama' => $det->rekening->nama ?? '',
                        'uraian' => $det->uraian,
                        'anggaran' => (int) $det->jumlah,
                        'realisasi' => (int) $rv->j,
                        'siplah' => (int) $rv->s === 1,
                    ];
                }
            }
        }

        $prefillRows = [];
        if ($acuanHeader) {
            foreach ($acuanHeader->details as $d) {
                $prefillRows[] = [
                    'kode_kegiatan' => $d->kode_kegiatan ?: '06.05',
                    'rekening_id' => $d->rekening_id,
                    'standar_harga_id' => $d->standar_harga_id,
                    'uraian' => $d->uraian,
                    'satuan' => $d->satuan,
                    'banyak' => $d->banyak,
                    'harga' => (int) $d->harga_satuan,
                    'sumber' => $d->sumber_dana ?: $sumber,
                    'bulan' => $d->bulanMap(),
                ];
            }
        }

        return view('rkas.create', [
            'rekenings' => Rekening::belanja()->with('komponen')->orderBy('kode')->get(),
            'standarHargas' => StandarHarga::untukSekolah($sid)->orderBy('nama_barang')->get(),
            'kodeKegiatanList' => ArkasKode::peta($sid, $tahun) + JuknisBosp2026Service::KODE_SNP_DEFAULT,
            'tahun' => $tahun,
            'sekolah' => $sekolah,
            'sumber' => $sumber,
            'sumberList' => RkasHeader::SUMBER_DANA_ARKAS,
            'jenis' => $jenis,
            'acuanHeader' => $acuanHeader,
            'prefillRows' => $prefillRows,
            'pagu' => JuknisBosp2026Service::paguSumberDana($sekolah, $tahun, $sumber),
            'aturanJuknis' => JuknisBosp2026Service::batasProporsi($sekolah, $sumber),
            'rekomendasi' => $rekomendasi,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'tahun' => 'required|integer',
            'no_rkas' => 'nullable|string|max:120',
            'sumber_dana' => 'nullable|string|max:60',
            'jenis_rkas' => 'nullable|in:murni,pergeseran,perubahan',
            'acuan_header_id' => 'nullable|integer',
            'catatan' => 'nullable|string|max:1000',
            'kode_kegiatan' => 'array',
            'kode_kegiatan.*' => 'nullable|string|max:20',
            'standar_harga_id' => 'array',
            'standar_harga_id.*' => 'nullable|integer',
            'rekening_id' => 'required|array|min:1',
            'rekening_id.*' => 'required|integer|exists:rekenings,id',
            'uraian' => 'required|array',
            'uraian.*' => 'required|string|max:500',
            'satuan' => 'array',
            'satuan.*' => 'nullable|string|max:40',
            'banyak' => 'array',
            'banyak.*' => 'nullable|numeric|min:0',
            'harga' => 'array',
            'harga.*' => 'nullable|numeric|min:0',
            'bulan' => 'array',
            'sumber' => 'array',
            'sumber.*' => 'nullable|string|max:60',
        ], [
            'uraian.*.max' => 'Uraian kegiatan maksimal 500 karakter sesuai ketentuan ARKAS 4.',
        ]);

        $sid = auth()->user()->school_id;
        $tahun = (int) $data['tahun'];
        $jenisRkas = $data['jenis_rkas'] ?? 'murni';
        $sumberHeader = $data['sumber_dana'] ?? ($data['sumber'][0] ?? 'BOS Reguler');

        $sshMap = StandarHarga::untukSekolah($sid)->get()->keyBy('id');
        $barisSiap = [];

        foreach ($data['rekening_id'] as $i => $rid) {
            $bulanInput = $data['bulan'][$i] ?? null;
            $volMap = array_fill(1, 12, 0);
            $adaBulan = false;
            if (is_array($bulanInput)) {
                for ($m = 1; $m <= 12; $m++) {
                    $v = max(0, (int) ($bulanInput[$m] ?? 0));
                    $volMap[$m] = $v;
                    if ($v > 0) {
                        $adaBulan = true;
                    }
                }
            }

            if ($adaBulan) {
                $banyak = max(1, array_sum($volMap));
            } else {
                $banyak = max(1, (int) ($data['banyak'][$i] ?? 1));
                $volMap[1] = $banyak;
            }

            $harga = max(0, (int) ($data['harga'][$i] ?? 0));
            $sshId = ! empty($data['standar_harga_id'][$i]) ? (int) $data['standar_harga_id'][$i] : null;
            if ($sshId && isset($sshMap[$sshId]) && $harga > (int) $sshMap[$sshId]->batas_harga) {
                $ssh = $sshMap[$sshId];

                return back()->with('error', "Harga satuan '{$data['uraian'][$i]}' (".rp($harga).
                    ') melebihi batas atas Standar Harga Dinas/SSH untuk '.$ssh->nama_barang.
                    ' (maks. '.rp($ssh->batas_harga).'/'.$ssh->satuan.').')->withInput();
            }

            $pv = [
                '1' => array_sum(array_slice($volMap, 0, 6)),
                '2' => array_sum(array_slice($volMap, 6, 6)),
            ];

            $barisSiap[] = [
                'rekening_id' => (int) $rid,
                'kode_kegiatan' => $data['kode_kegiatan'][$i] ?? null,
                'standar_harga_id' => $sshId,
                'uraian' => $data['uraian'][$i],
                'satuan' => ($data['satuan'][$i] ?? '') ?: 'paket',
                'banyak' => $banyak,
                'harga_satuan' => $harga,
                'jumlah' => $banyak * $harga,
                'sumber_dana' => ($data['sumber'][$i] ?? '') ?: $sumberHeader,
                'bulan_volumes' => json_encode($volMap),
                'periode_volume' => json_encode($pv),
            ];
        }

        // Validasi Pergeseran vs Perubahan sesuai ARKAS 4 v4.3.0 Bab IX & XI
        $acuan = null;
        if (! empty($data['acuan_header_id'])) {
            $acuan = RkasHeader::where('school_id', $sid)->find((int) $data['acuan_header_id']);
        } elseif ($jenisRkas !== 'murni') {
            $acuan = RkasHeader::where('school_id', $sid)->where('tahun', $tahun)
                ->where('status', '!=', 'draft')->orderByDesc('revisi_ke')->first();
        }

        if ($jenisRkas !== 'murni' && $acuan) {
            $galatRevisi = JuknisBosp2026Service::validasiRevisiArkas($jenisRkas, $acuan, $barisSiap, $sid, $tahun);
            if ($galatRevisi) {
                return back()->with('error', implode(' ', $galatRevisi))->withInput();
            }
        }

        $revisi = (int) RkasHeader::where('school_id', $sid)->where('tahun', $tahun)->max('revisi_ke') + 1;
        $h = RkasHeader::create([
            'tahun' => $tahun,
            'revisi_ke' => $revisi,
            'no_rkas' => $data['no_rkas'] ?? null,
            'sumber_dana' => $sumberHeader,
            'jenis_rkas' => $jenisRkas,
            'acuan_header_id' => $acuan?->id,
            'catatan' => $data['catatan'] ?? null,
            'status' => 'draft',
        ]);
        foreach ($barisSiap as $b) {
            $h->details()->create($b);
        }

        return redirect()->route('rkas.show', $h)
            ->with('ok', $h->labelJenis()." (revisi ke-{$revisi}) tersimpan. Periksa panel Review Kertas Kerja & Proporsi Juknis 2026 di bawah.");
    }

    /**
     * Aktivasi / Salin Kertas Kerja dari Tahun Lalu (T-1) atau duplikasi RKAS acuan (ala ARKAS 4 Bab VI).
     */
    public function salin(Request $r)
    {
        $tahunTarget = (int) session('tahun', now()->year);
        $sid = auth()->user()->school_id;
        $jenis = in_array($r->input('jenis_rkas'), ['murni', 'pergeseran', 'perubahan'], true)
            ? (string) $r->input('jenis_rkas')
            : 'murni';

        $sumberHeader = $r->filled('source_id')
            ? RkasHeader::with('details')->where('school_id', $sid)->find((int) $r->input('source_id'))
            : RkasHeader::with('details')->where('school_id', $sid)->where('tahun', $tahunTarget - 1)
                ->orderByDesc('revisi_ke')->first();

        if (! $sumberHeader || $sumberHeader->details->isEmpty()) {
            return back()->with('error', 'Belum ditemukan Kertas Kerja RKAS tahun sebelumnya yang dapat disalin.');
        }

        $revisi = (int) RkasHeader::where('school_id', $sid)->where('tahun', $tahunTarget)->max('revisi_ke') + 1;
        $baru = RkasHeader::create([
            'tahun' => $tahunTarget,
            'revisi_ke' => $revisi,
            'sumber_dana' => $sumberHeader->sumber_dana ?: 'BOS Reguler',
            'jenis_rkas' => $jenis,
            'acuan_header_id' => $sumberHeader->id,
            'status' => 'draft',
            'catatan' => "Salinan Kertas Kerja dari TA {$sumberHeader->tahun} revisi ke-{$sumberHeader->revisi_ke}",
        ]);

        foreach ($sumberHeader->details as $d) {
            $baru->details()->create([
                'rekening_id' => $d->rekening_id,
                'kode_kegiatan' => $d->kode_kegiatan,
                'standar_harga_id' => $d->standar_harga_id,
                'uraian' => $d->uraian,
                'satuan' => $d->satuan,
                'banyak' => $d->banyak,
                'harga_satuan' => $d->harga_satuan,
                'jumlah' => $d->jumlah,
                'sumber_dana' => $d->sumber_dana,
                'bulan_volumes' => $d->bulan_volumes,
                'periode_volume' => $d->periode_volume,
                'sudah_diinput_arkas' => false,
            ]);
        }

        return redirect()->route('rkas.show', $baru)
            ->with('ok', "Kertas Kerja berhasil disalin dari TA {$sumberHeader->tahun} ({$baru->details()->count()} rincian).");
    }

    /**
     * Sisip Uraian pada kegiatan/rekening yang sama (ala ARKAS 4 Bab VII.4):
     * hanya tersedia ketika Kertas Kerja berstatus aktif disunting (`draft` atau `revisi`),
     * ditolak bila sedang diajukan / sudah disahkan / dikunci.
     */
    public function sisipUraian(Request $r, RkasHeader $rkasHeader, RkasDetail $detail)
    {
        abort_unless((int) $detail->rkas_header_id === (int) $rkasHeader->id, 404);
        if (! in_array($rkasHeader->status, ['draft', 'revisi'], true)) {
            return back()->with('error', 'Sisip Uraian hanya dapat dilakukan pada Kertas Kerja berstatus Draft atau Perlu Revisi (sesuai ARKAS 4).');
        }

        $uraianBaru = trim((string) $r->input('uraian', $detail->uraian.' (sisipan)'));
        $banyakBaru = max(1, (int) $r->input('banyak', $detail->banyak));
        $hargaBaru = max(0, (int) $r->input('harga_satuan', $detail->harga_satuan));
        $bulanBaru = max(1, min(12, (int) $r->input('bulan', 1)));

        $volMap = array_fill(1, 12, 0);
        $volMap[$bulanBaru] = $banyakBaru;

        $rkasHeader->details()->create([
            'rekening_id' => $detail->rekening_id,
            'kode_kegiatan' => $detail->kode_kegiatan,
            'standar_harga_id' => $detail->standar_harga_id,
            'uraian' => mb_substr($uraianBaru, 0, 500),
            'satuan' => $detail->satuan,
            'banyak' => $banyakBaru,
            'harga_satuan' => $hargaBaru,
            'jumlah' => $banyakBaru * $hargaBaru,
            'sumber_dana' => $detail->sumber_dana,
            'bulan_volumes' => json_encode($volMap),
            'periode_volume' => json_encode([
                '1' => $bulanBaru <= 6 ? $banyakBaru : 0,
                '2' => $bulanBaru > 6 ? $banyakBaru : 0,
            ]),
        ]);

        return back()->with('ok', 'Uraian baru berhasil disisipkan pada kegiatan yang sama.');
    }

    /** Mode Pendamping "Salin Cepat ke Aplikasi ARKAS 4 Desktop" + Checklist Progres Input. */
    public function salinArkas(RkasHeader $rkasHeader)
    {
        $rkasHeader->load('details.rekening.komponen');
        $sekolah = auth()->user()->school;
        $kodeNama = ArkasKode::peta($sekolah->id, (int) $rkasHeader->tahun) + JuknisBosp2026Service::KODE_SNP_DEFAULT;

        $grouped = $rkasHeader->details->groupBy(fn ($d) => ($d->sumber_dana ?: 'BOS Reguler').' — '.($d->kode_kegiatan ?: '06.05'));
        $totalItem = $rkasHeader->details->count();
        $sudahInput = $rkasHeader->details->where('sudah_diinput_arkas', true)->count();

        return view('rkas.salin-arkas', [
            'h' => $rkasHeader,
            'grouped' => $grouped,
            'kodeNama' => $kodeNama,
            'namaBulan' => JuknisBosp2026Service::NAMA_BULAN,
            'totalItem' => $totalItem,
            'sudahInput' => $sudahInput,
            'persenInput' => $totalItem > 0 ? round($sudahInput / $totalItem * 100) : 0,
        ]);
    }

    /** Centang / batalkan centang status "Sudah Diinput ke ARKAS 4 Desktop" per baris. */
    public function toggleInputArkas(RkasHeader $rkasHeader, RkasDetail $detail)
    {
        abort_unless((int) $detail->rkas_header_id === (int) $rkasHeader->id, 404);
        $detail->update(['sudah_diinput_arkas' => ! $detail->sudah_diinput_arkas]);

        return back()->with('ok', 'Status input ARKAS diperbarui untuk: '.$detail->uraian);
    }

    public function impor(Request $request, AntreanArkasService $antrean)
    {
        $data = $request->validate([
            'berkas' => 'required|file|mimes:csv,txt|max:2048',
        ]);
        $tahun = (int) session('tahun', now()->year);

        try {
            $run = $antrean->daftarkan(
                $data['berkas'],
                $request->user(),
                ArkasImportRun::JENIS_RKAS_CSV,
                $tahun,
                'Impor CSV RKAS'
            );
        } catch (ArkasRunAktifException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'Berkas CSV belum dapat dimasukkan ke antrean. Coba lagi atau hubungi administrator.');
        }

        return redirect()->route('impor-arkas.status', $run);
    }

    /**
     * Review & Proporsi Anggaran — daftar kertas kerja tahun berjalan beserta
     * ringkasan proporsi komponen (Juknis 8/2026) dan tautan ke panel review
     * lengkap tiap kertas kerja.
     */
    public function review(Request $r)
    {
        $tahun = (int) session('tahun', now()->year);
        $sekolah = auth()->user()->school;
        $items = RkasHeader::with('details.rekening')
            ->where('tahun', $tahun)->orderByDesc('revisi_ke')->get();

        $baris = [];
        foreach ($items as $h) {
            $review = JuknisBosp2026Service::reviewKertasKerja($h, $h->school ?? $sekolah);
            $baris[] = [
                'header' => $h,
                'total' => (int) $h->details->sum('jumlah'),
                'proporsi' => $review['proporsi'] ?? [],
                'pagu' => $review['pagu'] ?? 0,
                'temuan' => $review['temuan'] ?? [],
            ];
        }

        return view('rkas.review', [
            'baris' => $baris,
            'tahun' => $tahun,
            'sekolah' => $sekolah,
        ]);
    }

    public function show(RkasHeader $rkasHeader)
    {
        $rkasHeader->load(['details.rekening.komponen', 'acuan']);
        $sekolah = auth()->user()->school;
        $reviewJuknis = JuknisBosp2026Service::reviewKertasKerja($rkasHeader, $sekolah);
        $kodeNama = ArkasKode::peta($sekolah->id, (int) $rkasHeader->tahun) + JuknisBosp2026Service::KODE_SNP_DEFAULT;

        return view('rkas.show', [
            'h' => $rkasHeader,
            'reviewJuknis' => $reviewJuknis,
            'kodeNama' => $kodeNama,
            'namaBulan' => JuknisBosp2026Service::NAMA_BULAN,
            'praAjukan' => PraAjukanRkasService::periksa($rkasHeader, $sekolah, null, $reviewJuknis),
        ]);
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

    /**
     * Hasil pemeriksaan kelayakan pengajuan (dipakai tombol "Periksa Kelayakan"
     * dan modal gerbang pra-pengajuan di halaman Kertas Kerja).
     */
    public function praAjukan(RkasHeader $rkasHeader)
    {
        return response()->json(
            PraAjukanRkasService::periksa($rkasHeader, auth()->user()->school)
        );
    }

    public function aksi(Request $r, RkasHeader $rkasHeader)
    {
        $aksi = $r->validate(['aksi' => 'required|in:ajukan,setujui,kunci,kirim_dinas'])['aksi'];
        $sekolah = auth()->user()->school;

        // Gerbang pra-pengajuan (paritas "Ajukan Pengesahan" ARKAS 4): tolak selama
        // masih ada blokir — Belum Dianggarkan belum Rp 0, rincian belum lengkap,
        // uraian > 500 karakter, atau tidak ada rincian sama sekali.
        $pra = null;
        if ($aksi === 'ajukan') {
            $pra = PraAjukanRkasService::periksa($rkasHeader, $sekolah);
            if (! $pra['boleh_ajukan']) {
                $rincian = collect($pra['cek'])
                    ->where('level', 'blokir')
                    ->map(fn ($c) => '• '.$c['pesan'])
                    ->implode(' ');

                return back()->with('error', 'Kertas Kerja belum dapat diajukan: '.$rincian);
            }
        }

        // Alur persetujuan Dinas: validasi batas kepatuhan Permendikdasmen No. 8 Tahun 2026 & ARKAS 4
        if ($aksi === 'kirim_dinas') {
            if (! in_array($rkasHeader->status, ['disetujui', 'revisi'])) {
                return back()->with('error', 'RKAS harus disetujui Kepala Sekolah dulu (atau sedang berstatus revisi) sebelum dikirim ke Dinas.');
            }
            $cek = JuknisBosp2026Service::reviewKertasKerja($rkasHeader, $sekolah);
            if ($cek['ada_blokir'] && ! $r->boolean('abaikan_peringatan')) {
                $pesanBlokir = collect($cek['temuan'])
                    ->where('level', 'blokir')
                    ->pluck('pesan')
                    ->implode(' ');

                return back()->with('error', 'Pengajuan ditahan karena melanggar batas Juknis BOSP 2026 / ARKAS 4: '.$pesanBlokir);
            }

            $rkasHeader->update([
                'status' => 'dikirim_dinas',
                'dikirim_dinas_at' => now(),
                'catatan_dinas' => null,
                'ditinjau_oleh' => null,
                'ditinjau_at' => null,
            ]);
            $namaSekolah = $sekolah->nama;
            $penerimaDinas = User::whereIn('role', ['dinas', 'superadmin'])->get();
            $penerimaDinas->each(fn ($u) => $u->notify(
                new InfoNotif('📋', "RKAS masuk: {$namaSekolah}",
                    "RKAS TA {$rkasHeader->tahun} revisi ke-{$rkasHeader->revisi_ke} menunggu tinjauan Anda.", route('dinas.rkas'))));
            EmailNotif::kirim($penerimaDinas, "RKAS masuk: {$namaSekolah}", [
                "RKAS TA {$rkasHeader->tahun} revisi ke-{$rkasHeader->revisi_ke} dari {$namaSekolah} menunggu tinjauan Anda.",
                'Silakan masuk ke akun Dinas untuk meninjau dan mengesahkannya.',
            ], route('dinas.rkas'));
            Aktivitas::catat('rkas', "RKAS TA {$rkasHeader->tahun} revisi ke-{$rkasHeader->revisi_ke} dikirim ke Dinas");

            $peringatan = collect($cek['temuan'])->where('level', 'peringatan')->pluck('pesan')->implode(' ');

            return back()
                ->with('ok', 'RKAS terkirim ke Dinas untuk disahkan. Pantau statusnya di halaman ini.')
                ->with('warning', $peringatan ?: null);
        }

        $map = ['ajukan' => 'diajukan', 'setujui' => 'disetujui', 'kunci' => 'dikunci'];
        if ($aksi === 'setujui' && auth()->user()->role !== 'kepsek') {
            return back()->with('error', 'Hanya Kepala Sekolah yang dapat menyetujui.');
        }
        if ($aksi === 'kunci' && $rkasHeader->status !== 'disahkan') {
            return back()->with('error', 'RKAS hanya bisa dikunci setelah DISAHKAN oleh Dinas.');
        }
        $rkasHeader->update(['status' => $map[$aksi]]);

        if ($aksi === 'ajukan') {
            $pra ??= PraAjukanRkasService::periksa($rkasHeader, $sekolah);
            $peringatan = collect($pra['cek'])
                ->where('level', 'peringatan')
                ->map(fn ($c) => '• '.$c['pesan'])
                ->implode(' ');

            return back()
                ->with('ok', 'Kertas Kerja diajukan ke Kepala Sekolah untuk disetujui.')
                ->with('warning', $peringatan ?: null);
        }

        return back()->with('ok', 'Status RKAS diperbarui: '.$map[$aksi]);
    }
}
