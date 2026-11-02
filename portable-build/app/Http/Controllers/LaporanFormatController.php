<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pusat Format Laporan — satu halaman untuk memilih seluruh format cetak
 * yang tersedia, meniru daftar "Format Laporan" pada ARKAS 4.
 *
 * Halaman ini hanya menyusun tautan; seluruh perhitungan tetap dilakukan
 * controller laporan masing-masing agar tidak ada duplikasi angka.
 */
class LaporanFormatController extends Controller
{
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public function index(Request $r): View
    {
        $tahun = (int) session('tahun', now()->year);

        $bulan = (int) $r->query('bulan', now()->format('n'));
        $bulan = $bulan >= 1 && $bulan <= 12 ? $bulan : (int) now()->format('n');

        $semester = (int) $r->query('semester', $bulan <= 6 ? 1 : 2);
        $semester = in_array($semester, [1, 2], true) ? $semester : 1;

        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->orderBy('id')->get();
        $tahap = $stages->firstWhere('id', (int) $r->query('tahap')) ?? $stages->first();

        $bulanParam = sprintf('%d-%02d', $tahun, $bulan);
        $namaBulan = self::BULAN[$bulan];
        $namaSemester = $semester === 1 ? 'Semester 1 (Januari–Juni)' : 'Semester 2 (Juli–Desember)';

        $grup = [
            [
                'label' => 'Penganggaran (RKAS)',
                'ikon' => 'file',
                'desc' => 'Rencana kegiatan dan anggaran sekolah — rincian per periode serta review proporsi.',
                'items' => [
                    [
                        'nama' => 'Rincian RKAS (Bulanan)',
                        'desc' => 'Rincian anggaran dan realisasi satu bulan, dikelompokkan per program dan kegiatan.',
                        'link' => [$this->tautan('Bulan '.$namaBulan, route('laporan-arkas.rkas-bulanan', ['bulan' => $bulan]))],
                    ],
                    [
                        'nama' => 'Rincian RKAS (Tahapan)',
                        'desc' => 'Pembagian anggaran per tahap penyaluran dana.',
                        'link' => [$this->tautan('Lihat rincian', route('laporan-arkas.rkas-tahap'))],
                    ],
                    [
                        'nama' => 'Anggaran Bulanan (ARKAS)',
                        'desc' => 'Lembar anggaran bulanan format ARKAS untuk kebutuhan verifikasi dinas.',
                        'link' => [$this->tautan('Bulan '.$namaBulan, route('penganggaran.anggaran-bulanan', ['bulan' => $bulan]))],
                    ],
                    [
                        'nama' => 'Review & Proporsi Anggaran',
                        'desc' => 'Total per program kegiatan dan proporsi antar standar nasional pendidikan.',
                        'link' => [$this->tautan('Buka review', route('penganggaran.review'))],
                    ],
                ],
            ],
            [
                'label' => 'RKAS & Kertas Kerja (ARKAS)',
                'ikon' => 'file',
                'desc' => 'Lembar cetak RKAS/APBS, RKA-DPA unit kerja, RPD-K7, serta pemisahan kertas kerja draf dan RKAS yang sudah disahkan.',
                'items' => [
                    [
                        'nama' => 'Kertas Kerja RAPBS (Draf)',
                        'desc' => 'Dokumen kerja yang belum disahkan — dipakai saat pembahasan internal sekolah.',
                        'link' => [
                            $this->tautan('Per Tahap', route('laporan-arkas.rapbs-cetak', ['format' => 'tahap', 'draf' => 1])),
                            $this->tautan('Per Triwulan', route('laporan-arkas.rapbs-cetak', ['format' => 'triwulan', 'draf' => 1])),
                            $this->tautan('Bulan '.$namaBulan, route('laporan-arkas.rapbs-cetak', ['format' => 'bulan', 'bulan' => $bulan, 'draf' => 1])),
                        ],
                    ],
                    [
                        'nama' => 'RKAS Disahkan (RAPBS)',
                        'desc' => 'Lembar RKAS dari dokumen yang sudah disahkan — acuan resmi pelaksanaan anggaran.',
                        'link' => [
                            $this->tautan('Per Tahap', route('laporan-arkas.rapbs-cetak', ['format' => 'tahap', 'draf' => 0])),
                            $this->tautan('Per Triwulan', route('laporan-arkas.rapbs-cetak', ['format' => 'triwulan', 'draf' => 0])),
                            $this->tautan('Bulan '.$namaBulan, route('laporan-arkas.rapbs-cetak', ['format' => 'bulan', 'bulan' => $bulan, 'draf' => 0])),
                        ],
                    ],
                    [
                        'nama' => 'APBS — Semua Sumber Dana',
                        'desc' => 'Penerimaan dan pohon belanja enam kelompok sumber dana dalam satu lembar.',
                        'link' => [$this->tautan('Cetak APBS', route('laporan-arkas.apbs'))],
                    ],
                    [
                        'nama' => 'RKA / DPA Unit Kerja',
                        'desc' => 'Rincian anggaran belanja langsung per unit kerja, per triwulan atau per tahap.',
                        'link' => [
                            $this->tautan('RKA Triwulan', route('laporan-arkas.rka-unit', ['format' => 'triwulan'])),
                            $this->tautan('DPA Tahap', route('laporan-arkas.rka-unit', ['varian' => 'dpa', 'format' => 'tahap'])),
                        ],
                    ],
                    [
                        'nama' => 'RKA 2.2 — Program & Kegiatan',
                        'desc' => 'Rincian anggaran belanja langsung berdasarkan program dan kegiatan.',
                        'link' => [$this->tautan('Cetak RKA 2.2', route('laporan-arkas.rka-unit', ['varian' => 'rka22']))],
                    ],
                    [
                        'nama' => 'RKA 2.2.1 — Rekap Rekening',
                        'desc' => 'Rekap belanja langsung per rekening, bukan per program.',
                        'link' => [$this->tautan('Cetak RKA 2.2.1', route('laporan-arkas.rka-unit', ['varian' => 'rka221']))],
                    ],
                    [
                        'nama' => 'RPD-K7',
                        'desc' => 'Rekapitulasi realisasi penggunaan dana per standar nasional pendidikan dan sub program.',
                        'link' => [$this->tautan('Cetak RPD-K7', route('laporan-arkas.rpd-k7'))],
                    ],
                    [
                        'nama' => 'Laporan Penggunaan Hibah Dana',
                        'desc' => 'Pagu, realisasi, dan sisa dana hibah per semester atau satu tahun anggaran.',
                        'link' => [
                            $this->tautan('Semester 1', route('laporan-arkas.dana-hibah', ['periode' => 'semester', 'semester' => 1])),
                            $this->tautan('Semester 2', route('laporan-arkas.dana-hibah', ['periode' => 'semester', 'semester' => 2])),
                            $this->tautan('Tahunan', route('laporan-arkas.dana-hibah', ['periode' => 'tahunan'])),
                        ],
                    ],
                ],
            ],
            [
                'label' => 'Buku Kas Umum (BKU)',
                'ikon' => 'receipt',
                'desc' => 'Buku kas utama beserta rekap mutasi kas bulanan, tahunan, tahapan, dan akumulasi.',
                'items' => [
                    [
                        'nama' => 'Buku Kas Umum (Bulanan)',
                        'desc' => 'Seluruh penerimaan dan pengeluaran satu bulan dengan saldo berjalan.',
                        'link' => [$this->tautan('Bulan '.$namaBulan, route('bku.cetak', ['format' => 'bulanan', 'bulan' => $bulanParam]))],
                    ],
                    [
                        'nama' => 'Buku Kas Umum (Tahunan)',
                        'desc' => 'Seluruh transaksi satu tahun anggaran.',
                        'link' => [$this->tautan('Tahun '.$tahun, route('bku.cetak', ['format' => 'tahunan', 'bulan' => $bulanParam]))],
                    ],
                    [
                        'nama' => 'Buku Kas Umum (Tahapan)',
                        'desc' => 'Transaksi yang tercatat pada tahap penyaluran tertentu.',
                        'link' => [$this->tautan((string) ($tahap->nama ?? 'Tahap'),
                            route('bku.cetak', ['format' => 'tahapan', 'bulan' => $bulanParam, 'tahap' => $tahap?->id]))],
                    ],
                    [
                        'nama' => 'Buku Kas Umum (Akumulasi)',
                        'desc' => 'Transaksi sejak 1 Januari sampai akhir bulan yang dipilih.',
                        'link' => [$this->tautan('s.d. '.$namaBulan, route('bku.cetak', ['format' => 'akumulasi', 'bulan' => $bulanParam]))],
                    ],
                ],
            ],
            [
                'label' => 'Buku Pembantu',
                'ikon' => 'book',
                'desc' => 'Rincian kas bank, kas tunai, pajak, dan objek belanja — format ARKAS 4.',
                'items' => [
                    [
                        'nama' => 'Buku Kas Pembantu Bank',
                        'desc' => 'Mutasi kas bank: penerimaan transfer, belanja nontunai, setor tunai, dan setor pajak.',
                        'link' => [
                            $this->tautan('Bulanan', route('pembukuan.bukuPembantuBank', ['periode' => 'bulan', 'bulan' => $bulanParam])),
                            $this->tautan('Tahunan', route('pembukuan.bukuPembantuBank', ['periode' => 'tahun'])),
                        ],
                    ],
                    [
                        'nama' => 'Buku Kas Pembantu Tunai',
                        'desc' => 'Mutasi kas tunai: tarik tunai dari bank dan pembelanjaan tunai.',
                        'link' => [
                            $this->tautan('Bulanan', route('pembukuan.bukuPembantuTunai', ['periode' => 'bulan', 'bulan' => $bulanParam])),
                            $this->tautan('Tahunan', route('pembukuan.bukuPembantuTunai', ['periode' => 'tahun'])),
                        ],
                    ],
                    [
                        'nama' => 'Buku Pembantu Pajak',
                        'desc' => 'Potongan pajak per transaksi beserta status setor dan NTPN.',
                        'link' => [
                            $this->tautan('Bulanan', route('pembukuan.bukuPembantuPajak', ['periode' => 'bulan', 'bulan' => $bulanParam])),
                            $this->tautan('Tahunan', route('pembukuan.bukuPembantuPajak', ['periode' => 'tahun'])),
                        ],
                    ],
                    [
                        'nama' => 'Buku Pembantu Rincian Objek Belanja',
                        'desc' => 'Rincian belanja per objek/rekening dalam satu semester.',
                        'link' => [$this->tautan($namaSemester, route('pembukuan.bukuPembantu', ['semester' => $semester]))],
                    ],
                ],
            ],
            [
                'label' => 'Rekapitulasi & Realisasi',
                'ikon' => 'chart',
                'desc' => 'Rekap anggaran versus realisasi untuk kebutuhan monitoring dan pelaporan.',
                'items' => [
                    [
                        'nama' => 'Rekapitulasi Realisasi',
                        'desc' => 'Anggaran, realisasi, sisa pagu, dan persentase serapan per rekening.',
                        'link' => [
                            $this->tautan('Bulanan', route('pembukuan.rekapRealisasi', ['periode' => 'bulanan', 'bulan' => $bulanParam])),
                            $this->tautan('Semester', route('pembukuan.rekapRealisasi', ['periode' => 'semester', 'semester' => $semester])),
                            $this->tautan('Tahunan', route('pembukuan.rekapRealisasi', ['periode' => 'tahunan'])),
                            $this->tautan('Tahapan', route('pembukuan.rekapRealisasi', ['periode' => 'tahapan', 'tahap' => $tahap?->id])),
                        ],
                    ],
                    [
                        'nama' => 'Realisasi Anggaran',
                        'desc' => 'Realisasi per rekening: bulan lalu, bulan ini, s.d. bulan ini, dan persentase.',
                        'link' => [$this->tautan('Bulan '.$namaBulan, route('laporan-arkas.realisasi', ['bulan' => $bulan]))],
                    ],
                    [
                        'nama' => 'Realisasi Penerimaan & Belanja',
                        'desc' => 'Ringkasan realisasi penerimaan dan belanja per kategori belanja.',
                        'link' => [
                            $this->tautan('Bulanan', route('laporan-arkas.penerimaan-belanja', ['periode' => 'bulanan', 'bulan' => $bulan])),
                            $this->tautan('Semester', route('laporan-arkas.penerimaan-belanja', ['periode' => 'semester', 'semester' => $semester])),
                        ],
                    ],
                    [
                        'nama' => 'Realisasi Barang Habis Pakai',
                        'desc' => 'Rekapitulasi realisasi belanja barang habis pakai satu bulan.',
                        'link' => [$this->tautan('Bulan '.$namaBulan, route('laporan-arkas.habis-pakai', ['bulan' => $bulan]))],
                    ],
                    [
                        'nama' => 'Realisasi Barang Modal/Aset',
                        'desc' => 'Rekapitulasi realisasi belanja barang modal dan aset satu bulan.',
                        'link' => [$this->tautan('Bulan '.$namaBulan, route('laporan-arkas.modal', ['bulan' => $bulan]))],
                    ],
                ],
            ],
            [
                'label' => 'Pertanggungjawaban',
                'ikon' => 'edit',
                'desc' => 'Dokumen pernyataan tanggung jawab dan lampiran pemeriksaan.',
                'items' => [
                    [
                        'nama' => 'SPTJM Dana BOSP',
                        'desc' => 'Surat Pernyataan Tanggung Jawab Mutlak beserta rincian saldo dan realisasi.',
                        'link' => [
                            $this->tautan('Bulanan', route('pembukuan.sptjm', ['periode' => 'bulanan', 'bulan' => $bulanParam])),
                            $this->tautan('Semester', route('pembukuan.sptjm', ['periode' => 'semester', 'semester' => $semester])),
                        ],
                    ],
                    [
                        'nama' => 'Daftar Kas Umum',
                        'desc' => 'Rincian transaksi kas beserta penerima dan nomor bukti.',
                        'link' => [$this->tautan('Bulan '.$namaBulan, route('laporan-arkas.kas-umum', ['bulan' => $bulan]))],
                    ],
                    [
                        'nama' => 'Daftar Nota',
                        'desc' => 'Daftar nota/bukti belanja beserta toko dan NPWP.',
                        'link' => [$this->tautan('Bulan '.$namaBulan, route('laporan-arkas.nota', ['bulan' => $bulan]))],
                    ],
                    [
                        'nama' => 'Register Penutupan Kas',
                        'desc' => 'Berita acara penutupan kas dan rincian saldo akhir.',
                        'link' => [
                            $this->tautan('Bulan '.$namaBulan, route('laporan-arkas.penutupan-kas', ['bulan' => $bulan])),
                            $this->tautan('Cetak', route('laporan-arkas.penutupan-kas-cetak', ['bulan' => $bulan])),
                        ],
                    ],
                    [
                        'nama' => 'Arsip Dokumen Sah',
                        'desc' => 'RKAS yang sudah disahkan dan BKU yang sudah ditutup.',
                        'link' => [$this->tautan('Buka arsip', route('arsip.index'))],
                    ],
                ],
            ],
        ];

        return view('laporan.format', compact('grup', 'tahun', 'bulan', 'namaBulan', 'semester',
            'namaSemester', 'stages', 'tahap'));
    }

    /**
     * Satu tautan format laporan.
     *
     * @return array{label: string, url: string}
     */
    private function tautan(string $label, string $url): array
    {
        return ['label' => $label, 'url' => $url];
    }
}
