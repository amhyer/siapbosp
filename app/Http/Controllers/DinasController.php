<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\Announcement;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Komponen;
use App\Models\LpjSubmission;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Models\User;
use App\Notifications\InfoNotif;
use App\Services\Aktivitas;
use App\Services\EmailNotif;
use App\Services\KasService;
use App\Services\KonsolidasiExportService;
use App\Services\LpjExportService;
use App\Services\LpjService;
use App\Services\RingkasanService;
use App\Services\RkasExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DinasController extends Controller
{
    private function tahun(): int
    {
        return (int) session('tahun', now()->year);
    }

    private function ringkas(School $s): School
    {
        return RingkasanService::sekolah($s, $this->tahun());
    }

    public function dashboard()
    {
        $schools = School::orderBy('nama')->get()->map(fn ($s) => $this->ringkas($s));
        $belumAktif = $schools->where('notAktif', true)->count();
        $pajakBelumTotal = $schools->sum('pajakBelum');
        $peringkat = $schools->sortByDesc('serapan')->take(8)->values();
        $tahun = $this->tahun();
        $perTahapWilayah = Stage::where('tahun', $tahun)->orderBy('urutan')->get()->map(function ($s) use ($tahun) {
            $b = (int) KasTransaction::withoutGlobalScope('school')->where('tahun', $tahun)
                ->where('type', 'belanja')->where('stage_id', $s->id)->sum('total');

            return ['nama' => $s->nama, 'belanja' => $b];
        })->filter(fn ($x) => $x['belanja'] > 0)->values();
        // Data grafik interaktif: tren belanja wilayah per bulan + komposisi per komponen
        $trenBulanan = array_fill(1, 12, 0);
        // agregasi di PHP agar portable antar driver (pgsql, sqlite, mysql)
        foreach (DB::table('kas_transactions')->where('tahun', $tahun)->where('type', 'belanja')
            ->select('tanggal', 'total')->get() as $trx) {
            $trenBulanan[(int) substr((string) $trx->tanggal, 5, 2)] += (int) $trx->total;
        }
        $komposisiKomponen = DB::table('kas_details as d')
            ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
            ->leftJoin('rekenings as r', 'd.rekening_id', '=', 'r.id')
            ->leftJoin('komponens as k', 'r.komponen_id', '=', 'k.id')
            ->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->selectRaw("coalesce(k.nama, '(tanpa komponen)') nama, sum(d.nilai) j")
            ->groupBy(DB::raw("coalesce(k.nama, '(tanpa komponen)')"))->orderByDesc('j')->get()
            ->map(fn ($x) => ['nama' => $x->nama, 'jumlah' => (int) $x->j])->values();

        return view('dinas.dashboard', ['schools' => $schools, 'tahun' => $tahun, 'belumAktif' => $belumAktif,
            'pajakBelumTotal' => $pajakBelumTotal, 'peringkat' => $peringkat, 'perTahapWilayah' => $perTahapWilayah,
            'trenBulanan' => $trenBulanan, 'komposisiKomponen' => $komposisiKomponen]);

        return view('dinas.dashboard', ['schools' => $schools, 'tahun' => $tahun, 'belumAktif' => $belumAktif,
            'pajakBelumTotal' => $pajakBelumTotal, 'peringkat' => $peringkat, 'perTahapWilayah' => $perTahapWilayah]);
    }

    public function sekolah()
    {
        return view('dinas.sekolah', ['schools' => School::orderBy('nama')->get()]);
    }

    public function sekolahStore(Request $r)
    {
        $data = $r->validate([
            'npsn' => 'required|unique:schools,npsn', 'nama' => 'required', 'jenjang' => 'required',
            'desa' => 'nullable', 'kecamatan' => 'nullable', 'kab_kota' => 'nullable',
            'provinsi' => 'nullable', 'alamat' => 'nullable',
        ]);
        School::create($data);

        return back()->with('ok', 'Sekolah ditambahkan.');
    }

    public function sekolahToggle(School $school)
    {
        $school->update(['is_active' => ! $school->is_active]);

        return back()->with('ok', 'Status sekolah diperbarui.');
    }

    public function imporSekolah(Request $r)
    {
        $r->validate(['berkas' => 'required|file|max:2048']);
        $isi = file($r->file('berkas')->getRealPath());
        if (! $isi) {
            return back()->with('error', 'Berkas kosong.');
        }
        $sep = substr_count($isi[0], ';') >= substr_count($isi[0], ',') ? ';' : ',';
        $buat = 0;
        $lewati = 0;
        $gagal = [];
        foreach ($isi as $no => $baris) {
            $kol = array_map('trim', str_getcsv($baris, $sep));
            if (count($kol) < 2 || empty($kol[0]) || str_contains(strtolower($kol[0]), 'npsn')) {
                continue;
            }
            [$npsn, $nama, $jenjang, $kecamatan] = array_pad($kol, 4, null);
            if (School::where('npsn', $npsn)->exists()) {
                $lewati++;

                continue;
            }
            try {
                School::create([
                    'npsn' => $npsn, 'nama' => $nama, 'jenjang' => in_array($jenjang, ['SD', 'SMP', 'SMA', 'SMK', 'SLB']) ? $jenjang : 'SD',
                    'kecamatan' => $kecamatan, 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
                ]);
                $buat++;
            } catch (\Throwable $e) {
                $gagal[] = ($no + 1).": $nama";
            }
        }
        $msg = "Impor selesai: $buat sekolah dibuat, $lewati dilewati (NPSN sudah ada).";
        if ($gagal) {
            $msg .= ' Gagal baris: '.implode(', ', $gagal);
        }

        return back()->with('ok', $msg);
    }

    // ===== MONITORING DETAIL SEKOLAH (read-only) =====
    public function sekolahShow(School $school)
    {
        $tahun = $this->tahun();
        $s = $this->ringkas($school);
        $saldo = KasService::saldo($school->id, $tahun);
        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->get()->map(function ($st) use ($school, $tahun) {
            $st->pendapatan = (int) KasTransaction::withoutGlobalScope('school')->where('school_id', $school->id)->where('tahun', $tahun)->where('type', 'pendapatan')->where('stage_id', $st->id)->sum('total');
            $st->belanja = (int) KasTransaction::withoutGlobalScope('school')->where('school_id', $school->id)->where('tahun', $tahun)->where('type', 'belanja')->where('stage_id', $st->id)->sum('total');
            $st->terkunci = TutupBuku::terkunci($school->id, $tahun, $st->id);

            return $st;
        });
        $terbaru = KasTransaction::withoutGlobalScope('school')->where('school_id', $school->id)->where('tahun', $tahun)
            ->orderByDesc('tanggal')->orderByDesc('id')->take(10)->get();

        return view('dinas.sekolah-show', ['s' => $s, 'saldo' => $saldo, 'stages' => $stages, 'terbaru' => $terbaru, 'tahun' => $tahun]);
    }

    // ===== KONSOLIDASI LINTAS SEKOLAH =====
    public function konsolidasi()
    {
        $schools = School::where('is_active', true)->orderBy('nama')->get()->map(fn ($s) => $this->ringkas($s));

        return view('dinas.konsolidasi', ['schools' => $schools, 'tahun' => $this->tahun()]);
    }

    /** Ekspor konsolidasi wilayah ke Excel multi-sheet (Rekap Sekolah + Matriks Komponen). */
    public function konsolidasiXlsx()
    {
        $tahun = $this->tahun();
        $schools = School::orderBy('nama')->get()->map(fn ($s) => $this->ringkas($s));
        $path = KonsolidasiExportService::xlsx($schools, $tahun);

        return response()->download($path, "Konsolidasi_Wilayah_{$tahun}.xlsx")->deleteFileAfterSend(true);
    }

    public function konsolidasiCsv()
    {
        $tahun = $this->tahun();
        $schools = School::where('is_active', true)->orderBy('nama')->get()->map(fn ($s) => $this->ringkas($s));

        return response()->streamDownload(function () use ($schools) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['NPSN', 'Sekolah', 'Jenjang', 'Kecamatan', 'Pagu', 'Dana Diterima', 'Belanja', 'Serapan (%)', 'Pajak Belum Disetor'], ';');
            foreach ($schools as $s) {
                fputcsv($out, [$s->npsn, $s->nama, $s->jenjang, $s->kecamatan, $s->pagu, $s->pendapatan, $s->belanja, $s->serapan, $s->pajakBelum], ';');
            }
            fclose($out);
        }, "konsolidasi_{$tahun}.csv", ['Content-Type' => 'text/csv']);
    }

    // ===== KONTROL TAHAP GLOBAL =====
    public function tahap()
    {
        $tahun = $this->tahun();
        $tahap1 = Stage::where('tahun', $tahun)->orderBy('urutan')->first();
        $cekJuknis = collect();
        if ($tahap1) {
            $sekolahs = School::orderBy('nama')->get();
            foreach ($sekolahs as $s) {
                $ring = RingkasanService::sekolah($s, $tahun);
                $pagu = (int) $ring->pagu;
                $belanja1 = (int) KasTransaction::withoutGlobalScope('school')
                    ->where('school_id', $s->id)->where('tahun', $tahun)
                    ->where('stage_id', $tahap1->id)->where('type', 'belanja')->sum('total');
                $cekJuknis[] = (object) [
                    'sekolah' => $s, 'pagu' => $pagu, 'belanja1' => $belanja1,
                    'persen' => $pagu > 0 ? (int) round($belanja1 / $pagu * 100) : null,
                ];
            }
        }

        return view('dinas.tahap', ['stages' => Stage::where('tahun', $tahun)->orderBy('urutan')->get(),
            'tahun' => $tahun, 'tahap1' => $tahap1, 'cekJuknis' => $cekJuknis]);
    }

    public function tahapToggle(Stage $stage)
    {
        $stage->update(['is_open' => ! $stage->is_open]);
        $status = $stage->is_open ? 'dibuka' : 'ditutup';
        Aktivitas::catat('tahap', "{$stage->nama} {$stage->tahun} {$status} oleh Dinas");
        $ikon = $stage->is_open ? '🟢' : '🔴';
        $penerima = User::whereNotNull('school_id')->get();
        $pesan = $stage->is_open ? 'Input transaksi pada tahap ini kembali dibuka.' : 'Input transaksi pada tahap ini diblokir sementara.';
        $penerima->each(fn ($usr) => $usr->notify(new InfoNotif(
            $ikon, "{$stage->nama} {$stage->tahun} {$status} oleh Dinas", $pesan)));
        EmailNotif::kirim($penerima, "{$stage->nama} {$stage->tahun} {$status} oleh Dinas", [
            $pesan,
            'Informasi ini berlaku untuk seluruh sekolah. Rincian dapat dilihat di aplikasi SiapBOSP.',
        ]);

        return back()->with('ok', "{$stage->nama} tahun {$stage->tahun} kini {$status} untuk semua sekolah.");
    }

    public function pengguna()
    {
        return view('dinas.pengguna', [
            'users' => User::with('school')->orderBy('school_id')->orderBy('role')->paginate(20),
            'schools' => School::where('is_active', true)->orderBy('nama')->get(),
        ]);
    }

    public function penggunaStore(Request $r)
    {
        $data = $r->validate([
            'name' => 'required', 'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6', 'role' => 'required|in:operator,kepsek,dinas',
            'school_id' => 'nullable|integer|exists:schools,id',
        ]);
        if (in_array($data['role'], ['operator', 'kepsek']) && empty($data['school_id'])) {
            return back()->with('error', 'Operator/Kepsek wajib dipilihkan sekolahnya.');
        }
        User::create($data);

        return back()->with('ok', 'Pengguna dibuat.');
    }

    /** Impor massal pengguna dari CSV: nama;email;npsn_sekolah;role;password(?) — password & role opsional. */
    public function penggunaImpor(Request $r)
    {
        $r->validate(['file' => 'required|file|mimes:csv,txt']);
        $fh = fopen($r->file('file')->getRealPath(), 'r');
        $baris1 = fgets($fh);
        $delim = substr_count($baris1, ';') >= substr_count($baris1, ',') ? ';' : ',';
        rewind($fh);
        $schoolsByNpsn = School::pluck('id', 'npsn');
        $dibuat = 0;
        $dilewati = [];
        $n = 0;
        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            $n++;
            $row = array_map('trim', $row);
            if ($n === 1 && isset($row[1]) && stripos($row[1], 'email') !== false) {
                continue;
            } // header
            $nama = $row[0] ?? '';
            $email = strtolower($row[1] ?? '');
            $npsn = $row[2] ?? '';
            $role = strtolower($row[3] ?? 'operator');
            $pass = $row[4] ?? 'password';
            if (! $nama || ! $email) {
                $dilewati[] = "baris {$n}: nama/email kosong";

                continue;
            }
            if (User::where('email', $email)->exists()) {
                $dilewati[] = "baris {$n}: {$email} sudah terdaftar";

                continue;
            }
            $schoolId = $npsn ? ($schoolsByNpsn[$npsn] ?? null) : null;
            if (in_array($role, ['operator', 'kepsek']) && ! $schoolId) {
                $dilewati[] = "baris {$n}: NPSN '{$npsn}' tidak dikenal";

                continue;
            }
            if (! in_array($role, User::ROLES)) {
                $role = 'operator';
            }
            if (in_array($role, ['superadmin', 'dinas'])) {
                $schoolId = null;
            }
            User::create(['name' => $nama, 'email' => $email, 'password' => $pass, 'role' => $role, 'school_id' => $schoolId]);
            $dibuat++;
        }
        fclose($fh);
        $msg = "Impor selesai: {$dibuat} akun dibuat.";
        if ($dilewati) {
            $msg .= ' Dilewati: '.implode(' · ', array_slice($dilewati, 0, 8));
        }

        return back()->with($dibuat ? 'ok' : 'error', $msg);
    }

    /** Antrean LPJ yang dikirim sekolah. */
    public function lpj()
    {
        $subs = LpjSubmission::with('school', 'stage')
            ->orderByRaw("case status when 'dikirim' then 0 when 'ditolak' then 1 else 2 end")
            ->orderByDesc('created_at')->paginate(20);
        $pending = LpjSubmission::where('status', 'dikirim')->count();

        return view('dinas.lpj', compact('subs', 'pending'));
    }

    /** Dinas membuka dokumen LPJ sekolah (mode tinjau). */
    public function lpjShow(LpjSubmission $sub)
    {
        [$stages, $stage, $items, $bkuRows, $saldoAwal, $totalMasuk, $totalKeluar,
            $totalBelanja, $rekapRekening, $rekapPajak, $tahun, $kumulatif, $cakupan] =
            LpjService::data($sub->school_id, $sub->tahun, $sub->stage_id);
        $sekolah = $sub->school;
        $penandatangan = Penandatangan::withoutGlobalScope('school')
            ->where('school_id', $sub->school_id)->orderBy('id')->get();
        $review = $sub;

        return view('laporan.lpj-cetak', compact('stages', 'stage', 'items', 'bkuRows', 'saldoAwal',
            'totalMasuk', 'totalKeluar', 'totalBelanja', 'rekapRekening', 'rekapPajak',
            'tahun', 'sekolah', 'penandatangan', 'review', 'kumulatif', 'cakupan'));
    }

    /** Dinas mengunduh LPJ sekolah sebagai PDF arsip. */
    public function lpjPdf(LpjSubmission $sub)
    {
        $vars = LpjService::data($sub->school_id, $sub->tahun, $sub->stage_id);
        $sekolah = $sub->school;
        $penandatangan = Penandatangan::withoutGlobalScope('school')
            ->where('school_id', $sub->school_id)->orderBy('id')->get();

        return response(LpjExportService::pdf($vars, $sekolah, $penandatangan), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.LpjExportService::namaBerkas($vars, $sekolah, 'pdf').'"',
        ]);
    }

    /** Dinas mengunduh LPJ sekolah sebagai Excel multi-sheet. */
    public function lpjXlsx(LpjSubmission $sub)
    {
        $vars = LpjService::data($sub->school_id, $sub->tahun, $sub->stage_id);
        $sekolah = $sub->school;
        $path = LpjExportService::xlsx($vars, $sekolah);

        return response()->download($path, LpjExportService::namaBerkas($vars, $sekolah, 'xlsx'))
            ->deleteFileAfterSend(true);
    }

    // ===== RKAS MASUK: persetujuan anggaran oleh Dinas =====
    public function rkas()
    {
        $items = RkasHeader::withoutGlobalScope('school')->with('school', 'details')
            ->whereIn('status', ['dikirim_dinas', 'revisi', 'disahkan', 'dikunci'])
            ->orderByRaw("case status when 'dikirim_dinas' then 0 when 'revisi' then 1 else 2 end")
            ->orderByDesc('dikirim_dinas_at')->paginate(20);
        $pending = RkasHeader::withoutGlobalScope('school')->where('status', 'dikirim_dinas')->count();

        return view('dinas.rkas', compact('items', 'pending'));
    }

    private function rkasById(int $id): RkasHeader
    {
        return RkasHeader::withoutGlobalScope('school')->with('school')->findOrFail($id);
    }

    /** Dinas membuka dokumen RKAS sekolah (mode tinjau). */
    public function rkasShow(int $rkas)
    {
        $h = $this->rkasById($rkas)->load('details.rekening');

        return view('rkas.show', ['h' => $h, 'review' => $h]);
    }

    /** Dinas mengesahkan RKAS. */
    public function rkasSahkan(int $rkas)
    {
        $h = $this->rkasById($rkas);
        if ($h->status !== 'dikirim_dinas') {
            return back()->with('error', 'RKAS ini tidak sedang menunggu tinjauan.');
        }
        $h->update(['status' => 'disahkan', 'ditinjau_oleh' => auth()->user()->name, 'ditinjau_at' => now()]);
        $penerima = User::where('school_id', $h->school_id)->get();
        $penerima->each(fn ($u) => $u->notify(
            new InfoNotif('✅', 'RKAS DISAHKAN Dinas',
                "RKAS TA {$h->tahun} revisi ke-{$h->revisi_ke} sekolah Anda disahkan oleh ".auth()->user()->name.'.',
                route('rkas.show', $h->id))));
        EmailNotif::kirim($penerima, 'RKAS DISAHKAN oleh Dinas', [
            "RKAS TA {$h->tahun} revisi ke-{$h->revisi_ke} sekolah Anda resmi DISAHKAN oleh Dinas (peninjau: ".auth()->user()->name.').',
            'RKAS yang disahkan menjadi dasar pelaksanaan belanja tahun anggaran berjalan.',
        ], route('rkas.show', $h->id));
        Aktivitas::catat('rkas', "RKAS TA {$h->tahun} revisi ke-{$h->revisi_ke} DISAHKAN Dinas oleh ".auth()->user()->name, $h->school_id);

        return back()->with('ok', 'RKAS disahkan. Sekolah sudah dinotifikasi.');
    }

    /** Dinas meminta revisi dengan catatan wajib. */ /** Dinas mengunduh RKAS sekolah: PDF resmi. */
    public function rkasPdf(int $rkas)
    {
        $h = $this->rkasById($rkas)->load('details.rekening.komponen');
        $sekolah = $h->school;
        $ttd = Penandatangan::withoutGlobalScope('school')->where('school_id', $h->school_id)->orderBy('id')->get();

        return response(RkasExportService::pdf($h, $sekolah, $ttd), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.
                RkasExportService::namaBerkas($h, $sekolah, 'pdf').'"',
        ]);
    }

    /** Dinas mengunduh RKAS sekolah: Excel. */
    public function rkasXlsx(int $rkas)
    {
        $h = $this->rkasById($rkas)->load('details.rekening.komponen');
        $sekolah = $h->school;
        $path = RkasExportService::xlsx($h, $sekolah);

        return response()->download($path, RkasExportService::namaBerkas($h, $sekolah, 'xlsx'))
            ->deleteFileAfterSend(true);
    }

    public function rkasRevisi(Request $r, int $rkas)
    {
        $data = $r->validate(['catatan_dinas' => 'required|string|min:5']);
        $h = $this->rkasById($rkas);
        if ($h->status !== 'dikirim_dinas') {
            return back()->with('error', 'RKAS ini tidak sedang menunggu tinjauan.');
        }
        $h->update(['status' => 'revisi', 'catatan_dinas' => $data['catatan_dinas'],
            'ditinjau_oleh' => auth()->user()->name, 'ditinjau_at' => now()]);
        $penerima = User::where('school_id', $h->school_id)->get();
        $penerima->each(fn ($u) => $u->notify(
            new InfoNotif('🟠', 'RKAS perlu REVISI',
                "RKAS TA {$h->tahun} revisi ke-{$h->revisi_ke}: {$data['catatan_dinas']}", route('rkas.show', $h->id))));
        EmailNotif::kirim($penerima, 'RKAS perlu REVISI', [
            "RKAS TA {$h->tahun} revisi ke-{$h->revisi_ke} sekolah Anda dikembalikan Dinas untuk diperbaiki.",
            "Catatan Dinas: {$data['catatan_dinas']}",
            'Setelah diperbaiki, ajukan ulang persetujuan Kepala Sekolah lalu kirim kembali ke Dinas.',
        ], route('rkas.show', $h->id));
        Aktivitas::catat('rkas', "RKAS TA {$h->tahun} revisi ke-{$h->revisi_ke} MINTA REVISI: {$data['catatan_dinas']}", $h->school_id);

        return back()->with('ok', 'Permintaan revisi terkirim ke sekolah.');
    }

    public function lpjTerima(LpjSubmission $sub)
    {
        $sub->update(['status' => 'diterima', 'ditinjau_oleh' => auth()->user()->name, 'ditinjau_at' => now()]);
        $penerima = User::where('school_id', $sub->school_id)->get();
        $penerima->each(fn ($u) => $u->notify(new InfoNotif(
            '✅', "LPJ {$sub->stage->nama} DITERIMA Dinas",
            "Pertanggungjawaban {$sub->tahun} disetujui. Selamat!", route('laporan.tahap', ['stage' => $sub->stage_id]))));
        EmailNotif::kirim($penerima, "LPJ {$sub->stage->nama} DITERIMA oleh Dinas", [
            "LPJ {$sub->stage->nama} tahun {$sub->tahun} sekolah Anda telah diperiksa dan DITERIMA oleh Dinas.",
            'Dokumen pertanggungjawaban tahap ini dinyatakan lengkap dan sah.',
        ], route('laporan.tahap', ['stage' => $sub->stage_id]));
        Aktivitas::catat('lpj', "LPJ {$sub->stage->nama} DITERIMA oleh ".auth()->user()->name, $sub->school_id);

        return back()->with('ok', "LPJ {$sub->school->nama} ({$sub->stage->nama}) DITERIMA. Sekolah sudah dinotifikasi.");
    }

    public function lpjTolak(Request $r, LpjSubmission $sub)
    {
        $data = $r->validate(['catatan_dinas' => 'required|min:5'], ['catatan_dinas.required' => 'Alasan penolakan wajib diisi.']);
        $sub->update(['status' => 'ditolak', 'catatan_dinas' => $data['catatan_dinas'],
            'ditinjau_oleh' => auth()->user()->name, 'ditinjau_at' => now()]);
        $penerima = User::where('school_id', $sub->school_id)->get();
        $penerima->each(fn ($u) => $u->notify(new InfoNotif(
            '❌', "LPJ {$sub->stage->nama} DITOLAK Dinas",
            $data['catatan_dinas'], route('laporan.tahap', ['stage' => $sub->stage_id]))));
        EmailNotif::kirim($penerima, "LPJ {$sub->stage->nama} DITOLAK oleh Dinas", [
            "LPJ {$sub->stage->nama} tahun {$sub->tahun} sekolah Anda dikembalikan oleh Dinas.",
            "Catatan Dinas: {$data['catatan_dinas']}",
            'Silakan perbaiki catatan/dokumen sesuai catatan, lalu kirim ulang LPJ tahap ini.',
        ], route('laporan.tahap', ['stage' => $sub->stage_id]));
        Aktivitas::catat('lpj', "LPJ {$sub->stage->nama} DITOLAK: {$data['catatan_dinas']}", $sub->school_id);

        return back()->with('ok', 'LPJ ditolak dengan catatan. Sekolah bisa memperbaiki & mengirim ulang.');
    }

    /** Unduh backup database penuh (SQL) — sekali klik. */
    public function backup()
    {
        $c = config('database.connections.pgsql');
        $bin = trim((string) shell_exec('command -v pg_dump')) ?: '/usr/lib/postgresql/17/bin/pg_dump';
        $file = tempnam(sys_get_temp_dir(), 'backup_').'.sql';
        $cmd = sprintf('PGPASSWORD=%s %s -h %s -p %s -U %s -d %s -f %s 2>&1',
            escapeshellarg((string) ($c['password'] ?? '')), escapeshellarg($bin),
            escapeshellarg((string) $c['host']), escapeshellarg((string) $c['port']),
            escapeshellarg((string) $c['username']), escapeshellarg((string) $c['database']),
            escapeshellarg($file));
        exec($cmd, $out, $code);
        if ($code !== 0 || ! is_file($file) || filesize($file) < 100) {
            return back()->with('error', 'Backup gagal ('.$code.'): '.implode(' | ', array_slice($out, 0, 3)));
        }
        Aktivitas::catat('backup', 'Backup database diunduh ('.round(filesize($file) / 1024).' KB)');

        return response()->download($file, 'siapbosp_backup_'.now()->format('Ymd_His').'.sql',
            ['Content-Type' => 'application/sql'])->deleteFileAfterSend(true);
    }

    /** Jejak audit seluruh sekolah (filterable). */
    public function aktivitas(Request $r)
    {
        $q = ActivityLog::with('school')->orderByDesc('id');
        if ($r->filled('school')) {
            $q->where('school_id', (int) $r->school);
        }

        return view('dinas.aktivitas', [
            'logs' => $q->paginate(50)->withQueryString(),
            'schools' => School::orderBy('nama')->get(),
        ]);
    }

    public function alokasi()
    {
        $tahun = $this->tahun();

        return view('dinas.alokasi', [
            'items' => Allocation::withoutGlobalScope('school')->with(['school', 'stage', 'bosType'])
                ->where('tahun', $tahun)->orderBy('school_id')->paginate(20),
            'schools' => School::where('is_active', true)->orderBy('nama')->get(),
            'bosTypes' => BosType::orderBy('id')->get(),
            'stages' => Stage::where('tahun', $tahun)->orderBy('urutan')->get(),
            'tahun' => $tahun,
        ]);
    }

    public function alokasiStore(Request $r)
    {
        $data = $r->validate([
            'school_id' => 'required|integer|exists:schools,id', 'tahun' => 'required|integer',
            'bos_type_id' => 'required|integer', 'stage_id' => 'nullable|integer',
            'nilai' => 'required|numeric|min:0',
        ]);
        Allocation::withoutGlobalScope('school')->create($data);

        return back()->with('ok', 'Pagu/alokasi tersimpan.');
    }

    public function pengumuman()
    {
        return view('dinas.pengumuman', ['items' => Announcement::with('creator')->latest()->paginate(15)]);
    }

    public function pengumumanStore(Request $r)
    {
        $data = $r->validate(['judul' => 'required', 'isi' => 'required']);
        $data['created_by'] = auth()->id();
        $baru = Announcement::create($data);
        // Kirim notifikasi lonceng ke seluruh pengguna sekolah
        $penerima = User::whereNotNull('school_id')->get();
        $penerima->each(fn ($usr) => $usr->notify(new InfoNotif(
            '📣', 'Pengumuman Dinas: '.$baru->judul,
            Str::limit($baru->isi, 140), route('dashboard'))));
        EmailNotif::kirim($penerima, 'Pengumuman Dinas: '.$baru->judul, [
            $baru->isi,
        ], route('dashboard'));

        return back()->with('ok', 'Pengumuman terbit + notifikasi dikirim ke semua sekolah.');
    }

    public function pengumumanDestroy(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('ok', 'Pengumuman dihapus.');
    }

    /** Buat paket tahap untuk tahun yang dipilih — jumlah fleksibel 2–5 (default 5; TA 2026 juknis memakai dua tahap salur), idempoten — tahap yang sudah ada tidak digandakan. */
    public function tahapBuat(Request $r)
    {
        $data = $r->validate([
            'tahun' => 'required|integer|min:2020|max:'.(now()->year + 3),
            'jumlah' => 'nullable|integer|min:2|max:5',
        ]);
        $th = (int) $data['tahun'];
        $jumlah = max(2, min(5, (int) ($data['jumlah'] ?? 5)));
        $dibuat = 0;
        for ($i = 1; $i <= $jumlah; $i++) {
            $ada = Stage::where('tahun', $th)->where('nama', "Tahap $i")->exists();
            if ($ada) {
                continue;
            }
            Stage::create(['tahun' => $th, 'nama' => "Tahap $i", 'urutan' => $i, 'is_open' => $i === 1]);
            $dibuat++;
        }
        if ($dibuat === 0) {
            return back()->with('error', "Tidak ada tahap baru yang dibuat — tahun {$th} sudah memiliki paket Tahap 1 s/d {$jumlah}.");
        }
        session(['tahun' => $th]);
        Aktivitas::catat('tahap', "Paket tahap tahun {$th} dibuat ({$dibuat} tahap)");
        $penerima = User::whereNotNull('school_id')->get();
        $penerima->each(fn ($usr) => $usr->notify(new InfoNotif(
            '🗓️', "Tahun anggaran {$th} dibuka", "Dinas membuat paket tahap untuk TA {$th}. Tahap 1 langsung terbuka.")));
        EmailNotif::kirim($penerima, "Tahun anggaran {$th} dibuka", [
            "Dinas membuat paket tahap untuk TA {$th}. Tahap 1 langsung terbuka.",
            'Sekolah dapat mulai menyusun RKAS pada tahap yang terbuka.',
        ]);

        return back()->with('ok', "{$dibuat} tahap untuk tahun {$th} berhasil dibuat. Tahap 1 langsung terbuka — sisanya bisa dibuka bertahap.");
    }

    /** Pusat audit keamanan: riwayat & statistik percobaan masuk. */
    public function keamanan(Request $r)
    {
        $q = ActivityLog::where('aksi', 'like', 'auth%');
        if ($email = $r->query('email')) {
            $q->where('user_name', 'ilike', "%{$email}%");
        }
        $riwayat = $q->orderByDesc('created_at')->paginate(50)->withQueryString();
        $h24 = ActivityLog::where('aksi', 'like', 'auth%')->where('created_at', '>=', now()->subDay());

        return view('dinas.keamanan', [
            'riwayat' => $riwayat,
            'ok24' => (clone $h24)->where('aksi', 'auth-ok')->count(),
            'gagal24' => (clone $h24)->whereIn('aksi', ['auth-gagal', 'auth-block'])->count(),
            'unik24' => (clone $h24)->distinct()->count('user_name'),
            'email' => $email,
        ]);
    }

    /** Perbarui identitas/peran/sekolah akun pengguna. */
    public function penggunaUpdate(Request $r, User $user)
    {
        $data = $r->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|in:operator,kepsek,dinas',
            'school_id' => 'nullable|integer|exists:schools,id',
        ]);
        if (in_array($data['role'], ['operator', 'kepsek']) && empty($data['school_id'])) {
            return back()->with('error', 'Operator/Kepsek wajib dipilihkan sekolahnya.');
        }
        if ($user->id === auth()->id() && $data['role'] !== auth()->user()->role) {
            return back()->with('error', 'Tidak bisa mengubah peran akun diri sendiri.');
        }
        if ($data['role'] === 'dinas') {
            $data['school_id'] = null;
        }
        $user->update(collect($data)->only(['name', 'email', 'role', 'school_id'])->toArray());
        Aktivitas::catat('pengguna', "Akun {$user->email} diperbarui oleh Dinas");

        return back()->with('ok', "Akun {$user->email} diperbarui.");
    }

    /** Reset sandi akun (manual atau acak) + pemberitahuan email. */
    public function penggunaReset(Request $r, User $user)
    {
        $data = $r->validate(['password' => 'nullable|string|min:6']);
        $acak = empty($data['password']);
        $sandi = $data['password'] ?: ucfirst(Str::camel(Str::random(6))).random_int(100, 999).'!';
        $user->update(['password' => $sandi]);
        Aktivitas::catat('pengguna', "Sandi akun {$user->email} direset Dinas");
        EmailNotif::kirim(collect([$user]), 'Kata sandi akun Anda di-reset', [
            'Kata sandi akun SiapBOSP Anda baru saja di-reset oleh Admin Dinas.',
            "Kata sandi baru Anda: {$sandi}",
            'Segera masuk, lalu ganti sandi ini melalui menu Ganti Kata Sandi.',
        ], '/login');

        $ket = $acak ? "dibuat acak: {$sandi}" : 'sesuai yang Anda isi';

        return back()->with('ok', "Sandi {$user->email} direset ({$ket}). Pemberitahuan email juga dikirim ke akun tersebut.");
    }

    /** Aktifkan / nonaktifkan akun (blokir login). */
    public function penggunaToggle(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun diri sendiri.');
        }
        $user->update(['is_active' => ! $user->is_active]);
        Aktivitas::catat('pengguna',
            "Akun {$user->email} ".($user->is_active ? 'diaktifkan' : 'dinonaktifkan').' oleh Dinas');

        return back()->with('ok', "Akun {$user->email} kini ".($user->is_active ? 'AKTIF' : 'NONAKTIF').'.');
    }

    /** Hapus akun pengguna. */
    public function penggunaHapus(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun diri sendiri.');
        }
        if ($user->isDinas() && User::whereIn('role', ['dinas', 'superadmin'])->count() <= 1) {
            return back()->with('error', 'Ini satu-satunya akun Dinas/admin — tidak boleh dihapus demi keamanan.');
        }
        $email = $user->email;
        $user->delete();
        Aktivitas::catat('pengguna', "Akun {$email} dihapus oleh Dinas");

        return back()->with('ok', "Akun {$email} dihapus.");
    }

    /** ===== MASTER REKENING & KOMPONEN ===== */
    public function master()
    {
        $komponens = Komponen::withCount('rekenings')->with('rekenings')->orderBy('kode')->get();
        $rekeningBelanja = Rekening::where('tipe', 'belanja')->count();
        $rekeningPendapatan = Rekening::where('tipe', 'pendapatan')->count();

        return view('dinas.master', compact('komponens', 'rekeningBelanja', 'rekeningPendapatan'));
    }

    /** Impor CSV: kode_rek;nama_rek;kode_komponen;nama_komponen;tipe (upsert aman). */
    public function masterImpor(Request $r)
    {
        $r->validate(['berkas' => 'required|file|max:2048']);
        $isi = file($r->file('berkas')->getRealPath());
        if (! $isi) {
            return back()->with('error', 'Berkas kosong.');
        }
        $sep = substr_count($isi[0], ';') >= substr_count($isi[0], ',') ? ';' : ',';
        $baru = 0;
        $diperbarui = 0;
        $lewati = [];
        foreach ($isi as $no => $b) {
            $kol = array_map('trim', str_getcsv($b, $sep));
            if (count($kol) < 2 || empty($kol[0]) || str_contains(strtolower($kol[0]), 'kode')) {
                continue;
            }
            [$kode, $nama, $kodeKom, $namaKom, $tipe] = array_pad($kol, 5, null);
            $tipe = strtolower((string) $tipe) === 'pendapatan' ? 'pendapatan' : 'belanja';
            $komponenId = null;
            if ($kodeKom) {
                $kom = Komponen::firstOrCreate(['kode' => $kodeKom], ['nama' => $namaKom ?: $kodeKom]);
                if ($namaKom && $kom->nama !== $namaKom) {
                    $kom->update(['nama' => $namaKom]);
                }
                $komponenId = $kom->id;
            }
            $ada = Rekening::where('kode', $kode)->first();
            if ($ada) {
                $ubah = ['tipe' => $tipe];
                if ($nama) {
                    $ubah['nama'] = $nama;
                }
                if ($komponenId) {
                    $ubah['komponen_id'] = $komponenId;
                }
                $ada->update($ubah);
                $diperbarui++;
            } else {
                if (! $nama) {
                    $lewati[] = ($no + 1)." ({$kode}: nama kosong)";

                    continue;
                }
                Rekening::create(['kode' => $kode, 'nama' => $nama, 'tipe' => $tipe, 'komponen_id' => $komponenId]);
                $baru++;
            }
        }
        Aktivitas::catat('master', "Impor master rekening: {$baru} baru, {$diperbarui} diperbarui");
        $msg = "Impor selesai: {$baru} rekening baru, {$diperbarui} diperbarui.";
        if ($lewati) {
            $msg .= ' Dilewati: '.implode(', ', $lewati);
        }

        return back()->with('ok', $msg);
    }

    /** Contoh berkas CSV untuk impor master. */
    public function masterTemplate()
    {
        $csv = "kode_rekening;nama_rekening;kode_komponen;nama_komponen;tipe\n"
            ."5.1.02.04.01.0005;Pengadaan buku teks pendamping;02;Pengembangan Perpustakaan;belanja\n"
            ."4.3.1.01.01.0003;Dana BOSP Tahap 3;;;pendapatan\n";

        return response($csv, 200, ['Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contoh_master_rekening.csv"']);
    }

    /** Kirim email uji ke alamat Dinas sendiri — memeriksa konfigurasi mail. */
    public function emailUji()
    {
        $saya = auth()->user();
        $mailer = (string) config('mail.default');
        $terkirim = EmailNotif::kirim([$saya], 'Uji Kirim Email', [
            'Ini surat uji dari SiapBOSP.',
            'Driver mail aktif: '.$mailer.'. Alamat pengirim: '.config('mail.from.address'),
            'Bila surat ini sampai ke kotak masuk Anda, konfigurasi SMTP sudah benar.',
        ], route('dashboard'));
        if ($mailer === 'log') {
            return back()->with('ok', "Email uji diproses ({$terkirim} terkirim). Driver aktif: LOG — surat tercatat utuh di storage/logs/laravel.log, bukan dikirim ke kotak masuk sungguhan. Untuk email riil, isi MAIL_MAILER=smtp + kredensial SMTP di file .env.");
        }

        return $terkirim
            ? back()->with('ok', "Email uji terkirim ke {$saya->email} via {$mailer}. Silakan periksa kotak masuk (dan folder Spam).")
            : back()->with('error', 'Email uji GAGAL — periksa kredensial SMTP di .env (detail kegagalan di storage/logs/laravel.log).');
    }
}
