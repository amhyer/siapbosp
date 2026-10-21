<?php

namespace App\Http\Controllers;

use App\Models\ArkasKode;
use App\Models\PerencanaanPbd;
use App\Models\PerencanaanRkm;
use App\Models\PerencanaanRkt;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\StandarHarga;
use App\Services\Aktivitas;
use App\Services\JuknisBosp2026Service;
use Illuminate\Http\Request;

/**
 * Modul Hulu Pra-ARKAS (Alur Perencanaan Berbasis Data):
 * PBD / Rapor Pendidikan -> RKM (4 Tahun) -> RKT (Tahunan) -> Draft Kertas Kerja RKAS + Standar Harga (SSH/SBU)
 *
 * Memenuhi fungsi utama SiapBOSP: memudahkan satuan pendidikan merancang kegiatan & anggaran
 * secara matang dan patuh Juknis BOSP 2026 sebelum menyalin/menginput ke aplikasi ARKAS 4 Desktop.
 */
class PerencanaanController extends Controller
{
    public function index()
    {
        $tahun = (int) session('tahun', now()->year);
        $sid = auth()->user()->school_id;

        $pbds = PerencanaanPbd::where('tahun', $tahun)->orderBy('kode_indikator')->get();
        $rkms = PerencanaanRkm::with('pbd')
            ->where('tahun_awal', '<=', $tahun)
            ->where('tahun_akhir', '>=', $tahun)
            ->orderBy('id')
            ->get();
        $rkts = PerencanaanRkt::with(['rkm.pbd', 'rekening'])
            ->where('tahun', $tahun)
            ->orderBy('id')
            ->get();

        $rekenings = Rekening::belanja()->with('komponen')->orderBy('kode')->get();
        $kodeKegiatan = ArkasKode::peta($sid, $tahun) + JuknisBosp2026Service::KODE_SNP_DEFAULT;

        return view('perencanaan.index', [
            'tahun' => $tahun,
            'pbds' => $pbds,
            'rkms' => $rkms,
            'rkts' => $rkts,
            'rekenings' => $rekenings,
            'kodeKegiatan' => $kodeKegiatan,
            'indikatorRapor' => PerencanaanPbd::INDIKATOR_RAPOR,
            'sumberDanaList' => RkasHeader::SUMBER_DANA_ARKAS,
        ]);
    }

    /** Muat 4 paket contoh rekomendasi PBD Rapor Pendidikan (Literasi, Numerasi, Karakter, Iklim Keamanan). */
    public function pbdTemplate()
    {
        $tahun = (int) session('tahun', now()->year);
        $contoh = [
            ['A.1', 'A.1 Kemampuan Literasi Peserta Didik', 64.5, 'tinggi',
                'Proporsi peserta didik yang mencapai kompetensi minimum literasi teks informasi masih perlu ditingkatkan.',
                'Pengadaan buku pengayaan literasi & optimalisasi pemanfaatan perpustakaan/pojok baca.', '02.02'],
            ['A.2', 'A.2 Kemampuan Numerasi Peserta Didik', 61.0, 'tinggi',
                'Domain aljabar dan data-ketidakpastian pada asesmen nasional masih di bawah rata-rata.',
                'Penguatan pembelajaran numerasi kontekstual melalui komunitas belajar guru.', '07.05'],
            ['D.1', 'D.1 Kualitas Pembelajaran', 68.0, 'sedang',
                'Metode pembelajaran interaktif dan refleksi umpan balik konstruktif belum merata di semua kelas.',
                'Workshop IHT/komunitas belajar guru dalam penyusunan modul ajar terdiferensiasi.', '03.03'],
            ['D.4', 'D.4 Iklim Keamanan Satuan Pendidikan', 72.5, 'sedang',
                'Program pencegahan perundungan (TPPK) belum tersosialisasi penuh ke orang tua dan siswa.',
                'Sosialisasi Sekolah Ramah Anak dan penguatan Tim Pencegahan & Penanganan Kekerasan (TPPK).', '03.03'],
        ];

        $dibuat = 0;
        foreach ($contoh as [$kode, $nama, $skor, $prio, $akar, $benahi, $keg]) {
            $exists = PerencanaanPbd::where('tahun', $tahun)->where('kode_indikator', $kode)->exists();
            if (! $exists) {
                $pbd = PerencanaanPbd::create([
                    'tahun' => $tahun,
                    'kode_indikator' => $kode,
                    'nama_indikator' => $nama,
                    'skor_capaian' => $skor,
                    'prioritas' => $prio,
                    'akar_masalah' => $akar,
                    'benahi' => $benahi,
                    'kode_kegiatan' => $keg,
                ]);
                PerencanaanRkm::create([
                    'pbd_id' => $pbd->id,
                    'tahun_awal' => $tahun,
                    'tahun_akhir' => $tahun + 3,
                    'sasaran' => 'Peningkatan '.$nama,
                    'indikator_kinerja' => $benahi,
                    'target_capaian' => 'Naik minimal +10 poin dalam 4 tahun',
                ]);
                $dibuat++;
            }
        }

        return back()->with('ok', $dibuat > 0
            ? "{$dibuat} rekomendasi prioritas Rapor Pendidikan (PBD) & RKM 4 tahun berhasil dimuat."
            : 'Indikator prioritas Rapor Pendidikan tahun ini sudah tersedia.');
    }

    public function pbdStore(Request $r)
    {
        $data = $r->validate([
            'kode_indikator' => 'required|string|max:20',
            'nama_indikator' => 'nullable|string|max:255',
            'skor_capaian' => 'nullable|numeric|min:0|max:100',
            'prioritas' => 'required|in:tinggi,sedang,rendah',
            'akar_masalah' => 'required|string|max:1000',
            'benahi' => 'required|string|max:1000',
            'kode_kegiatan' => 'nullable|string|max:20',
        ]);
        $tahun = (int) session('tahun', now()->year);
        $nama = $data['nama_indikator'] ?: (PerencanaanPbd::INDIKATOR_RAPOR[$data['kode_indikator']] ?? $data['kode_indikator']);
        PerencanaanPbd::create([
            'tahun' => $tahun,
            'kode_indikator' => $data['kode_indikator'],
            'nama_indikator' => $nama,
            'skor_capaian' => $data['skor_capaian'] ?? null,
            'prioritas' => $data['prioritas'],
            'akar_masalah' => $data['akar_masalah'],
            'benahi' => $data['benahi'],
            'kode_kegiatan' => $data['kode_kegiatan'] ?? null,
        ]);

        return back()->with('ok', 'Rekomendasi PBD / Rapor Pendidikan tersimpan.');
    }

    public function pbdDestroy(PerencanaanPbd $pbd)
    {
        $pbd->delete();

        return back()->with('ok', 'Item PBD dihapus.');
    }

    public function rkmStore(Request $r)
    {
        $data = $r->validate([
            'pbd_id' => 'nullable|integer|exists:perencanaan_pbds,id',
            'tahun_awal' => 'required|integer|min:2023|max:2035',
            'sasaran' => 'required|string|max:255',
            'indikator_kinerja' => 'nullable|string|max:255',
            'target_capaian' => 'nullable|string|max:100',
        ]);
        PerencanaanRkm::create([
            'pbd_id' => $data['pbd_id'] ?? null,
            'tahun_awal' => (int) $data['tahun_awal'],
            'tahun_akhir' => (int) $data['tahun_awal'] + 3,
            'sasaran' => $data['sasaran'],
            'indikator_kinerja' => $data['indikator_kinerja'] ?? null,
            'target_capaian' => $data['target_capaian'] ?? null,
        ]);

        return back()->with('ok', 'Sasaran RKM 4 Tahun tersimpan.');
    }

    public function rkmDestroy(PerencanaanRkm $rkm)
    {
        $rkm->delete();

        return back()->with('ok', 'Item RKM dihapus.');
    }

    public function rktStore(Request $r)
    {
        $data = $r->validate([
            'rkm_id' => 'nullable|integer|exists:perencanaan_rkms,id',
            'kode_kegiatan' => 'nullable|string|max:20',
            'nama_kegiatan' => 'required|string|max:500',
            'butuh_anggaran' => 'required|boolean',
            'rekening_id' => 'nullable|integer|exists:rekenings,id',
            'sumber_dana' => 'nullable|string|max:60',
            'volume' => 'nullable|integer|min:1',
            'satuan' => 'nullable|string|max:40',
            'harga_satuan' => 'nullable|numeric|min:0',
            'bulan_target' => 'nullable|integer|min:1|max:12',
        ]);
        $tahun = (int) session('tahun', now()->year);
        $butuh = (bool) $data['butuh_anggaran'];
        if ($butuh && empty($data['rekening_id'])) {
            return back()->with('error', 'Kegiatan RKT yang membutuhkan anggaran wajib memilih Kode Rekening Belanja.')->withInput();
        }
        $vol = $butuh ? max(1, (int) ($data['volume'] ?? 1)) : 1;
        $harga = $butuh ? max(0, (int) ($data['harga_satuan'] ?? 0)) : 0;

        PerencanaanRkt::create([
            'rkm_id' => $data['rkm_id'] ?? null,
            'tahun' => $tahun,
            'kode_kegiatan' => $data['kode_kegiatan'] ?? '03.03',
            'nama_kegiatan' => $data['nama_kegiatan'],
            'rekening_id' => $butuh ? $data['rekening_id'] : null,
            'butuh_anggaran' => $butuh,
            'sumber_dana' => $data['sumber_dana'] ?: 'BOS Reguler',
            'volume' => $vol,
            'satuan' => $data['satuan'] ?: 'paket',
            'harga_satuan' => $harga,
            'estimasi_biaya' => $butuh ? ($vol * $harga) : 0,
            'bulan_target' => (int) ($data['bulan_target'] ?? 1),
            'status_rkas' => 'belum',
        ]);

        return back()->with('ok', 'Kegiatan RKT Tahunan berhasil ditambahkan.');
    }

    public function rktDestroy(PerencanaanRkt $rkt)
    {
        $rkt->delete();

        return back()->with('ok', 'Kegiatan RKT dihapus.');
    }

    /**
     * Dorong seluruh item RKT ber-anggaran yang belum masuk RKAS ke Draft Kertas Kerja RKAS
     * lengkap dengan alokasi bulan target (1..12) dan `periode_volume` tahap 1/2.
     */
    public function dorongKeRkas()
    {
        $tahun = (int) session('tahun', now()->year);
        $sid = auth()->user()->school_id;

        $items = PerencanaanRkt::where('tahun', $tahun)
            ->where('butuh_anggaran', true)
            ->where('status_rkas', 'belum')
            ->whereNotNull('rekening_id')
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'Tidak ada item RKT ber-anggaran yang belum masuk ke Kertas Kerja RKAS.');
        }

        // Gunakan draft murni tahun ini bila sudah ada, atau buat draft baru
        $draft = RkasHeader::where('school_id', $sid)
            ->where('tahun', $tahun)
            ->where('status', 'draft')
            ->orderByDesc('revisi_ke')
            ->first();

        if (! $draft) {
            $revisi = (int) RkasHeader::where('school_id', $sid)->where('tahun', $tahun)->max('revisi_ke') + 1;
            $draft = RkasHeader::create([
                'tahun' => $tahun,
                'revisi_ke' => $revisi,
                'sumber_dana' => $items->first()->sumber_dana ?: 'BOS Reguler',
                'jenis_rkas' => 'murni',
                'status' => 'draft',
                'catatan' => "Diturunkan dari RKT Pra-ARKAS TA {$tahun}",
            ]);
        }

        foreach ($items as $rkt) {
            $bulan = max(1, min(12, (int) ($rkt->bulan_target ?: 1)));
            $volMap = array_fill(1, 12, 0);
            $volMap[$bulan] = (int) $rkt->volume;
            $pv = [
                '1' => $bulan <= 6 ? (int) $rkt->volume : 0,
                '2' => $bulan > 6 ? (int) $rkt->volume : 0,
            ];

            $draft->details()->create([
                'rekening_id' => $rkt->rekening_id,
                'kode_kegiatan' => $rkt->kode_kegiatan,
                'rkt_id' => $rkt->id,
                'uraian' => $rkt->nama_kegiatan,
                'satuan' => $rkt->satuan ?: 'paket',
                'banyak' => (int) $rkt->volume,
                'harga_satuan' => (int) $rkt->harga_satuan,
                'jumlah' => (int) $rkt->estimasi_biaya,
                'sumber_dana' => $rkt->sumber_dana ?: 'BOS Reguler',
                'bulan_volumes' => json_encode($volMap),
                'periode_volume' => json_encode($pv),
            ]);
            $rkt->update(['status_rkas' => 'masuk_draft']);
        }

        Aktivitas::catat('perencanaan', count($items)." kegiatan RKT didorong ke Kertas Kerja RKAS #{$draft->id}");

        return redirect()->route('rkas.show', $draft)
            ->with('ok', count($items).' kegiatan RKT berhasil dituangkan ke Draft Kertas Kerja RKAS! Silakan cek Review Proporsi Juknis 2026 di bawah.');
    }

    // ==================== STANDAR HARGA (SSH / SBU) ====================

    public function ssh(Request $r)
    {
        $sid = auth()->user()->school_id;
        $q = trim((string) $r->query('q', ''));
        $items = StandarHarga::untukSekolah($sid)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('nama_barang', 'like', "%{$q}%")
                ->orWhere('kode_barang', 'like', "%{$q}%")
                ->orWhere('kode_rekening', 'like', "%{$q}%")))
            ->orderByRaw('school_id is null desc')
            ->orderBy('nama_barang')
            ->paginate(25)
            ->withQueryString();

        return view('perencanaan.ssh', [
            'items' => $items,
            'q' => $q,
            'rekenings' => Rekening::belanja()->orderBy('kode')->get(),
        ]);
    }

    public function sshStore(Request $r)
    {
        $data = $r->validate([
            'kode_rekening' => 'nullable|string|max:30',
            'kode_barang' => 'nullable|string|max:40',
            'nama_barang' => 'required|string|max:255',
            'spesifikasi' => 'nullable|string|max:255',
            'satuan' => 'required|string|max:40',
            'batas_harga' => 'required|numeric|min:1',
            'kategori' => 'required|in:ssh,sbu,modal',
        ]);
        StandarHarga::create([
            'school_id' => auth()->user()->school_id,
            'kode_rekening' => $data['kode_rekening'] ?? null,
            'kode_barang' => $data['kode_barang'] ?? null,
            'nama_barang' => $data['nama_barang'],
            'spesifikasi' => $data['spesifikasi'] ?? null,
            'satuan' => $data['satuan'],
            'batas_harga' => (int) $data['batas_harga'],
            'kategori' => $data['kategori'],
        ]);

        return back()->with('ok', 'Referensi Standar Harga (SSH/SBU) berhasil ditambahkan.');
    }

    public function sshImpor(Request $r)
    {
        $r->validate(['berkas' => 'required|file|max:2048']);
        $isi = file($r->file('berkas')->getRealPath());
        if (! $isi) {
            return back()->with('error', 'Berkas CSV kosong.');
        }
        $sep = substr_count($isi[0], ';') >= substr_count($isi[0], ',') ? ';' : ',';
        $n = 0;
        $sid = auth()->user()->school_id;
        foreach ($isi as $b) {
            $kol = array_map('trim', str_getcsv($b, $sep));
            if (count($kol) < 4 || empty($kol[0]) || str_contains(strtolower($kol[0]), 'nama')) {
                continue;
            }
            [$nama, $satuan, $batas, $kodeRek, $spek] = array_pad($kol, 5, null);
            $harga = (int) preg_replace('/\D+/', '', (string) $batas);
            if ($harga <= 0) {
                continue;
            }
            StandarHarga::create([
                'school_id' => $sid,
                'nama_barang' => $nama,
                'satuan' => $satuan ?: 'unit',
                'batas_harga' => $harga,
                'kode_rekening' => $kodeRek ?: null,
                'spesifikasi' => $spek ?: null,
                'kategori' => 'ssh',
            ]);
            $n++;
        }

        return back()->with($n > 0 ? 'ok' : 'error', $n > 0
            ? "{$n} item Standar Harga berhasil diimpor dari CSV."
            : 'Tidak ada baris Standar Harga valid yang ditemukan.');
    }

    public function sshDestroy(StandarHarga $ssh)
    {
        if ($ssh->school_id !== auth()->user()->school_id) {
            return back()->with('error', 'Standar Harga resmi Dinas bersifat global dan tidak dapat dihapus sekolah.');
        }
        $ssh->delete();

        return back()->with('ok', 'Item Standar Harga dihapus.');
    }
}
