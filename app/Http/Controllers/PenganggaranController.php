<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\RkasHeader;
use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman perantara Penganggaran (pola ARKAS 4).
 *
 * ARKAS tidak menaruh seluruh menu penganggaran di sidebar — sidebar-nya hanya
 * tiga area kerja (Penganggaran, Penatausahaan, Arsip), lalu navigasi detail
 * ada di dalam halaman. SiapBOSP mengikuti pola itu: sidebar cukup satu tautan
 * "Penganggaran", dan halaman ini menampilkan ringkasan pagu, status dokumen
 * RKAS, serta jalan masuk ke setiap fitur.
 *
 * Halaman ini hanya membaca data; tidak ada tombol yang mengubah apa pun selain
 * tautan biasa ke form yang sudah ada.
 */
class PenganggaranController extends Controller
{
    public function index(Request $r): View
    {
        $u = $r->user();
        $sid = (int) $u->school_id;
        $tahun = (int) session('tahun', now()->year);

        // Satu baris per sumber dana: revisi terbaru yang berlaku pada tahun aktif.
        $headers = RkasHeader::with('details')
            ->where('school_id', $sid)
            ->where('tahun', $tahun)
            ->orderByDesc('revisi_ke')
            ->orderByDesc('id')
            ->get();

        $perSumber = $headers->groupBy(fn (RkasHeader $h) => (string) ($h->sumber_dana ?: 'BOSP Reguler'))
            ->map(function ($grup) {
                /** @var RkasHeader $header */
                $header = $grup->sortByDesc('revisi_ke')->first();

                return [
                    'header' => $header,
                    'total' => (int) $header->details->sum('jumlah'),
                    'revisi' => (int) $header->revisi_ke,
                ];
            })
            ->sortByDesc('total')
            ->values();

        $dokumenBerlaku = $perSumber->pluck('header');
        $totalPagu = (int) $perSumber->sum('total');
        $jumlahDraf = $dokumenBerlaku->where('status', 'draft')->count();
        $jumlahDisahkan = $dokumenBerlaku->filter(
            fn (RkasHeader $h) => in_array($h->status, ['disahkan', 'aktif', 'final'], true)
        )->count();

        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->orderBy('id')->get();
        $alokasi = (int) Allocation::where('school_id', $sid)->where('tahun', $tahun)->sum('nilai');

        $menu = [
            ['label' => 'Kertas Kerja (RKAS)', 'ket' => 'Susun, revisi, dan sahkan dokumen RKAS',
                'href' => route('rkas.index'), 'ikon' => '📝'],
            ['label' => 'Review & Proporsi Anggaran', 'ket' => 'Total per program dan proporsi 8 SNP',
                'href' => route('penganggaran.review'), 'ikon' => '📊'],
            ['label' => 'Daftar Kegiatan', 'ket' => 'Referensi kegiatan dan kode rekening',
                'href' => route('penganggaran.kegiatan'), 'ikon' => '🗂️'],
            ['label' => 'Anggaran Bulanan (ARKAS)', 'ket' => 'Lembar anggaran bulanan format ARKAS',
                'href' => route('penganggaran.anggaran-bulanan'), 'ikon' => '📅'],
            ['label' => 'PBD → RKM → RKT', 'ket' => 'Perencanaan berbasis data sebelum RKAS',
                'href' => route('perencanaan.index'), 'ikon' => '🧭'],
            ['label' => 'Pusat Format Laporan', 'ket' => 'Seluruh format cetak RKAS dan laporan',
                'href' => route('laporan.format'), 'ikon' => '🖨️'],
        ];

        return view('penganggaran.index', [
            'tahun' => $tahun,
            'sekolah' => $u->school,
            'perSumber' => $perSumber,
            'totalPagu' => $totalPagu,
            'alokasi' => $alokasi,
            'jumlahDokumen' => $perSumber->count(),
            'jumlahDraf' => $jumlahDraf,
            'jumlahDisahkan' => $jumlahDisahkan,
            'stages' => $stages,
            'menu' => $menu,
        ]);
    }
}
