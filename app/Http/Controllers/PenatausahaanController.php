<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\RkasHeader;
use App\Services\BkuBulan;
use App\Services\KasService;
use Illuminate\Http\Request;

/**
 * Halaman perantara Penatausahaan (pola ARKAS 4).
 *
 * ARKAS tidak menaruh seluruh menu penatausahaan di sidebar — sidebar-nya hanya
 * tiga area kerja (Penganggaran, Penatausahaan, Arsip), lalu navigasi detail ada
 * di dalam halaman. SiapBOSP mengikuti pola itu: sidebar cukup satu tautan
 * "Penatausahaan", dan halaman ini menampilkan status BKU 12 bulan beserta jalan
 * masuk ke setiap fitur, sehingga tidak ada tautan yang hilang.
 *
 * Halaman ini hanya membaca data; tidak ada tombol yang mengubah apa pun selain
 * tautan biasa ke form yang sudah ada.
 */
class PenatausahaanController extends Controller
{
    public function index(Request $r)
    {
        $sid = (int) auth()->user()->school_id;
        $tahun = (int) session('tahun', now()->year);

        // Status 12 bulan memakai logika yang sama dengan halaman BKU (BkuBulan),
        // supaya kedua halaman tidak pernah berbeda kesimpulan.
        $bulanGrid = BkuBulan::grid($sid, $tahun);

        $bulan = array_map(fn (array $b) => $b + [
            'label' => BkuBulan::LABEL[$b['status']],
            'tanda' => BkuBulan::TANDA[$b['status']],
        ], $bulanGrid);

        // Ringkasan di kepala halaman hanya menghitung bulan yang benar-benar
        // sudah ditutup; bulan tanpa dana tidak boleh membuat angka terlihat 12/12.
        $jumlahTutup = count(array_filter($bulanGrid,
            fn (array $b) => in_array($b['status'], ['tertutup', 'belum-pajak'], true)));

        // --- Transaksi setahun, dibaca sekali lalu diagregasi di PHP ---
        $tabInput = (string) $r->query('tab', '');
        $bosFilter = $tabInput !== '' ? RkasHeader::bosTypeIds($tabInput) : [];
        $tabAktif = $bosFilter !== [] ? $tabInput : '';

        $trx = KasTransaction::with('stage')->where('school_id', $sid)->where('tahun', $tahun)
            ->when($bosFilter !== [], fn ($q) => $q->whereIn('bos_type_id', $bosFilter))
            ->orderBy('tanggal')
            ->get(['id', 'type', 'tanggal', 'stage_id', 'keterangan', 'total', 'bos_type_id']);

        $penerimaanList = $trx->where('type', 'pendapatan')->values();

        $saldo = KasService::saldo($sid, $tahun);
        $pendapatan = (int) $trx->where('type', 'pendapatan')->sum('total');
        $belanja = (int) $trx->where('type', 'belanja')->sum('total');

        // --- Pagu & realisasi per sumber dana ---
        $pagu = Allocation::where('school_id', $sid)->where('tahun', $tahun)
            ->when($bosFilter !== [], fn ($q) => $q->whereIn('bos_type_id', $bosFilter))
            ->selectRaw('bos_type_id, sum(nilai) as j')
            ->groupBy('bos_type_id')->pluck('j', 'bos_type_id');

        $belanjaPer = $trx->where('type', 'belanja')->groupBy('bos_type_id')
            ->map(fn ($g) => (int) $g->sum('total'));
        $terimaPer = $trx->where('type', 'pendapatan')->groupBy('bos_type_id')
            ->map(fn ($g) => (int) $g->sum('total'));

        $namaBos = BosType::pluck('nama', 'id');
        $idSumber = $pagu->keys()->merge($belanjaPer->keys())->merge($terimaPer->keys())
            ->filter()->unique()->values();

        $sumberDana = $idSumber->map(function ($id) use ($namaBos, $pagu, $belanjaPer, $terimaPer) {
            $p = (int) ($pagu[$id] ?? 0);
            $b = (int) ($belanjaPer[$id] ?? 0);

            return [
                'nama' => $namaBos[$id] ?? 'Tanpa sumber dana',
                'pagu' => $p,
                'penerimaan' => (int) ($terimaPer[$id] ?? 0),
                'belanja' => $b,
                'sisa' => $p - $b,
            ];
        })->sortByDesc('pagu')->values();

        // --- Jalan masuk ke setiap fitur penatausahaan ---
        $menu = [
            ['label' => 'Buku Kas Umum', 'ket' => 'Catatan harian penerimaan & pengeluaran',
                'href' => route('bku'), 'aktif' => $trx->isNotEmpty()],
            ['label' => 'Kas dan Bank', 'ket' => 'Mutasi kas tunai dan saldo bank',
                'href' => route('kas'), 'aktif' => true],
            ['label' => 'Pendapatan', 'ket' => 'Terima dana BOSP & pendapatan lain',
                'href' => route('pendapatan.index'), 'aktif' => true],
            ['label' => 'Pembelanjaan', 'ket' => 'Belanja barang/jasa beserta pajaknya',
                'href' => route('belanja.index'), 'aktif' => true],
            ['label' => 'Pajak (Setor & Utang)', 'ket' => 'PPN, PPh, dan bukti setor',
                'href' => route('setorpajak.index'), 'aktif' => true],
            ['label' => 'Tarik Tunai', 'ket' => 'Bank → tunai', 'href' => route('ambil.index'), 'aktif' => true],
            ['label' => 'Setor Tunai', 'ket' => 'Tunai → bank', 'href' => route('mutasi.index'), 'aktif' => true],
            ['label' => 'Buku Pembantu', 'ket' => 'Rincian per objek belanja',
                'href' => route('pembukuan.bukuPembantu'), 'aktif' => $belanja > 0],
            ['label' => 'Rekap Realisasi', 'ket' => 'Anggaran vs realisasi per rekening',
                'href' => route('pembukuan.rekapRealisasi'), 'aktif' => $belanja > 0],
            ['label' => 'Register Dokumen', 'ket' => 'Register kas tunai & bank',
                'href' => route('pembukuan.register', ['jenis' => 'tunai']), 'aktif' => true],
            ['label' => 'SPTJM BOSP', 'ket' => 'Surat pertanggungjawaban semester',
                'href' => route('pembukuan.sptjm'), 'aktif' => true],
            ['label' => 'Penutupan BKU', 'ket' => 'Tutup bulan & register penutupan kas',
                'href' => route('penatausahaan.penutupan-bku'), 'aktif' => true],
        ];

        return view('penatausahaan.index', [
            'tahun' => $tahun,
            'tabAktif' => $tabAktif,
            'bulan' => $bulan,
            'jumlahTutup' => $jumlahTutup,
            'saldo' => $saldo,
            'pendapatan' => $pendapatan,
            'belanja' => $belanja,
            'sumberDana' => $sumberDana,
            'menu' => $menu,
            'adaTransaksi' => $trx->isNotEmpty(),
            'penerimaanList' => $penerimaanList,
        ]);
    }
}
