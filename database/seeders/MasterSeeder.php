<?php

namespace Database\Seeders;

use App\Models\BosType;
use App\Models\Komponen;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\Stage;
use App\Models\StandarHarga;
use Illuminate\Database\Seeder;

/**
 * Master referensi idempoten (aman dijalankan berulang):
 * jenis BOSP lengkap ARKAS 4 v4.3.0 (Reguler, Kinerja, SiLPA Kinerja, Afirmasi, BOSDA, Lainnya),
 * 12 komponen penggunaan BOSP, rekening ARKAS, jenis pajak nasional & daerah (SSPD),
 * standar harga referensi (SSH/SBU), dan tahap 2023–2028.
 *
 * Dipakai DatabaseSeeder (pusat/demo) DAN pemasangan lokal
 * (siapbos:pasang-lokal) — satu sumber data.
 */
class MasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'reguler' => 'BOS Reguler',
            'kinerja' => 'BOS Kinerja',
            'silpa_kinerja' => 'SiLPA BOSP Kinerja',
            'afirmasi' => 'BOSP Afirmasi',
            'bosda' => 'BOSP Daerah',
            'silpa' => 'SILPA Tahun Sebelumnya',
            'lainnya' => 'Sumber Dana Lainnya',
        ] as $k => $n) {
            BosType::updateOrCreate(['kode' => $k], ['nama' => $n]);
        }

        $kom = [];
        foreach ([
            '01' => 'Pelaksanaan Penerimaan Peserta Didik Baru',
            '02' => 'Pengembangan Perpustakaan',
            '03' => 'Kegiatan Pembelajaran dan Ekstrakurikuler',
            '04' => 'Kegiatan Asesmen dan Evaluasi Pembelajaran',
            '05' => 'Kegiatan Administrasi Sekolah',
            '06' => 'Pengembangan Profesi Guru dan Tenaga Kependidikan',
            '07' => 'Pembiayaan Langganan Daya dan Jasa',
            '08' => 'Pemeliharaan Sarana dan Prasarana Sekolah',
            '09' => 'Penyediaan Alat Multimedia Pembelajaran',
            '10' => 'Penyelenggaraan Kegiatan Peningkatan Kompetensi Keahlian',
            '11' => 'Penyelenggaraan Kegiatan dalam Mendukung Keterserapan Lulusan',
            '12' => 'Pembayaran Honor',
        ] as $k => $n) {
            $kom[$k] = Komponen::updateOrCreate(['kode' => $k], ['nama' => $n]);
        }

        Rekening::updateOrCreate(['kode' => '4.3.1.01.01.0001'], ['nama' => 'Pendapatan Hibah BOS — Pemerintah Pusat', 'tipe' => 'pendapatan']);
        Rekening::updateOrCreate(['kode' => '4.3.1.01.01.0002'], ['nama' => 'Pendapatan Hibah BOSP — Pemerintah Daerah', 'tipe' => 'pendapatan']);
        Rekening::updateOrCreate(['kode' => '8.1.03.01.01.0001'], ['nama' => 'Pendapatan Jasa Giro / Bunga Bank BOSP', 'tipe' => 'pendapatan']);

        foreach ([
            ['5.1.01.02.01.0001', 'Belanja Honorarium GTK bukan ASN', '12'],
            ['5.1.02.04.01.0001', 'Belanja Alat Tulis Kantor (ATK)', '05'],
            ['5.1.02.04.01.0006', 'Belanja Bahan Cetak (Perbanyakan Bahan Ajar)', '03'],
            ['5.1.02.01.02.0002', 'Belanja Jasa Kantor (fotokopi/bendahara dsb.)', '05'],
            ['5.1.02.05.01.0001', 'Belanja Langganan Listrik', '07'],
            ['5.1.02.05.03.0002', 'Belanja Langganan Telepon dan Data Internet', '07'],
            ['5.1.02.05.03.0004', 'Belanja Jasa Kebersihan dan Keamanan Sekolah', '07'],
            ['5.1.06.09.03.0001', 'Belanja Pelatihan/Perjadin Pengembangan Guru', '06'],
            ['5.2.01.05.01.0002', 'Belanja Modal Alat Multimedia Pembelajaran', '09'],
            ['5.1.02.04.01.0021', 'Belanja Bahan Bacaan Buku Perpustakaan', '02'],
            // Master ARKAS 4 (kode aktif standar nasional)
            ['5.1.02.01.01.0001', 'Belanja Bahan-Bahan Bangunan dan Konstruksi', '08'],
            ['5.1.02.01.01.0005', 'Belanja Bahan-Bahan Baku Praktek KBM', '03'],
            ['5.1.02.01.01.0008', 'Belanja Bahan-Bahan/Bibit Tanaman', '03'],
            ['5.1.02.01.01.0012', 'Belanja Bahan-Bahan Lainnya', '05'],
            ['5.1.02.01.01.0024', 'Belanja Alat/Bahan untuk Kegiatan Kantor-Alat Tulis Kantor', '05'],
            ['5.1.02.01.01.0026', 'Belanja Alat/Bahan untuk Kegiatan Kantor- Bahan Cetak dan Penggandaan', '03'],
            ['5.1.02.01.01.0029', 'Belanja Alat/Bahan untuk Kegiatan Kantor-Bahan Komputer', '05'],
            ['5.1.02.01.01.0030', 'Belanja Alat/Bahan untuk Kegiatan Kantor-Perabot Kantor', '05'],
            ['5.1.02.01.01.0036', 'Belanja Alat/Bahan untuk Kegiatan Kantor-Alat/Bahan untuk Kegiatan Kantor Lainnya', '05'],
            ['5.1.02.01.01.0037', 'Belanja Obat-Obat-Obatan', '05'],
            ['5.1.02.01.01.0052', 'Belanja Makanan dan Minuman Rapat', '05'],
            ['5.1.02.01.01.0053', 'Belanja Makanan dan Minuman Jamuan Tamu', '05'],
            ['5.1.02.02.01.0003', 'Honorarium Narasumber atau Pembahas, Moderator, Pembawa Acara, dan Panitia', '06'],
            ['5.1.02.02.01.0013', 'Belanja Jasa Tenaga Pendidikan / Honorarium Guru', '12'],
            ['5.1.02.02.01.0016', 'Belanja Jasa Tenaga Penanganan Prasarana dan Sarana Umum / Upah Tukang', '08'],
            ['5.1.02.02.01.0025', 'Belanja Jasa Tenaga Kesenian dan Kebudayaan', '03'],
            ['5.1.02.02.01.0026', 'Belanja Jasa Tenaga Administrasi', '05'],
            ['5.1.02.02.01.0027', 'Belanja Jasa Tenaga Operator Komputer', '05'],
            ['5.1.02.02.01.0029', 'Belanja Jasa Tenaga Ahli', '06'],
            ['5.1.02.02.01.0030', 'Belanja Jasa Tenaga Kebersihan', '07'],
            ['5.1.02.02.01.0031', 'Belanja Jasa Tenaga Keamanan', '07'],
            ['5.1.02.02.01.0035', 'Belanja Jasa Tenaga Teknisi Mekanik dan Listrik', '08'],
            ['5.1.02.02.01.0037', 'Belanja Jasa Juri Perlombaan/Pertandingan', '03'],
            ['5.1.02.02.01.0051', 'Belanja Jasa Pengolahan Sampah', '07'],
            ['5.1.02.02.01.0060', 'Belanja Tagihan Air', '07'],
            ['5.1.02.02.01.0061', 'Belanja Tagihan Listrik', '07'],
            ['5.1.02.02.01.0062', 'Belanja Langganan Jurnal/Surat Kabar/Majalah', '02'],
            ['5.1.02.02.01.0063', 'Belanja Kawat/Faksimili/Internet/TV Berlangganan', '07'],
            ['5.1.02.02.01.0066', 'Belanja Registrasi/Keanggotaan', '06'],
            ['5.1.02.02.04.0117', 'Belanja Sewa Alat Kantor Lainnya', '05'],
            ['5.1.02.03.02.0121', 'Belanja Pemeliharaan Alat Kantor dan Rumah Tangga-Alat Rumah Tangga-Alat Pendingin', '08'],
            ['5.1.02.03.02.0405', 'Belanja Pemeliharaan Komputer-Komputer Unit-Personal Computer', '08'],
            ['5.1.02.03.02.0411', 'Belanja Pemeliharaan Komputer-Peralatan Komputer-Peralatan Komputer Lainnya', '08'],
            ['5.1.02.03.03.0010', 'Belanja Pemeliharaan Bangunan Gedung-Bangunan Gedung Tempat Kerja-Bangunan Gedung Tempat Pendidikan', '08'],
            ['5.1.02.03.03.0030', 'Belanja Pemeliharaan Bangunan Gedung-Bangunan Gedung Tempat Kerja-Bangunan Gedung Tempat Kerja Lainnya', '08'],
            ['5.1.02.03.05.0001', 'Belanja Pemeliharaan Bahan Perpustakaan-Bahan Perpustakaan Tercetak-Buku Umum', '02'],
            ['5.1.02.04.01.0003', 'Belanja Perjalanan Dinas Dalam Kota / Dalam Daerah', '05'],
            ['5.1.02.04.01.0004', 'Belanja Perjalanan Dinas Paket Meeting Dalam Kota', '05'],
            ['5.2.02.05.01.0005', 'Belanja Modal Alat Kantor Lainnya', '08'],
            ['5.2.02.05.02.0004', 'Belanja Modal Alat Pendingin', '08'],
            ['5.2.02.05.02.0006', 'Belanja Modal Alat Rumah Tangga Lainnya (Home Use)', '08'],
            ['5.2.02.06.01.0001', 'Belanja Modal Peralatan Studio Audio', '09'],
            ['5.2.02.06.01.0002', 'Belanja Modal Peralatan Studio Video dan Film', '09'],
            ['5.2.02.10.01.0001', 'Belanja Modal Komputer Jaringan', '09'],
            ['5.2.05.01.01.0001', 'Belanja Modal Buku Umum / Koleksi Perpustakaan', '02'],
            ['5.2.05.01.01.0002', 'Belanja Modal Buku Pelajaran Kurikulum Nasional', '02'],
        ] as [$k, $n, $c]) {
            Rekening::updateOrCreate(['kode' => $k], ['nama' => $n, 'tipe' => 'belanja', 'komponen_id' => $kom[$c]->id]);
        }

        foreach ([
            'ppn' => 'PPN (Pajak Pertambahan Nilai)',
            'pph21' => 'PPh Pasal 21',
            'pph22' => 'PPh Pasal 22',
            'pph23' => 'PPh Pasal 23',
            'pph42' => 'PPh Pasal 4 Ayat 2',
            'sspd' => 'Pajak Daerah / SSPD (Restoran/Mamin 10%)',
        ] as $k => $n) {
            RekeningPajak::updateOrCreate(['kode' => $k], ['nama' => $n]);
        }

        // Referensi Standar Harga (SSH/SBU) bawaan Dinas sebagai contoh batas harga ARKAS 4
        foreach ([
            ['5.1.02.01.01.0024', 'SSH-ATK-001', 'Kertas HVS F4/A4 75 gsm', 'Rim 500 lembar SNI', 'rim', 65000, 'ssh'],
            ['5.2.05.01.01.0002', 'SSH-BUKU-001', 'Buku Teks Utama Kurikulum Nasional', 'HET Kemendikdasmen', 'eksemplar', 45000, 'modal'],
            ['5.2.05.01.01.0001', 'SSH-BUKU-002', 'Buku Pengayaan Literasi & Numerasi', 'Katalog SIBI terkurasi', 'eksemplar', 75000, 'modal'],
            ['5.1.02.01.01.0052', 'SBU-MAMIN-001', 'Konsumsi Rapat Dinas / KKG / Kombel', 'Nasi kotak + kudapan SBU Daerah', 'kotak', 45000, 'sbu'],
            ['5.1.02.03.03.0010', 'SSH-SAR-001', 'Cat Tembok Interior Ruang Kelas 25 kg', 'Pemeliharaan ringan sarpras', 'pail', 850000, 'ssh'],
            ['5.2.02.10.01.0001', 'SSH-TIK-001', 'Laptop/Chromebook Pembelajaran Asesmen', 'Minimal RAM 8GB SSD 256GB TKDN', 'unit', 9500000, 'modal'],
            ['5.1.02.02.01.0013', 'SBU-HON-001', 'Honorarium Guru Non-ASN (Ber-NUPTK)', 'Sesuai SK Penugasan & SBU Daerah', 'orang/bulan', 1500000, 'sbu'],
        ] as [$rekKode, $kodeBrg, $nama, $spek, $satuan, $batas, $kat]) {
            StandarHarga::updateOrCreate(
                ['school_id' => null, 'kode_barang' => $kodeBrg],
                [
                    'kode_rekening' => $rekKode,
                    'nama_barang' => $nama,
                    'spesifikasi' => $spek,
                    'satuan' => $satuan,
                    'batas_harga' => $batas,
                    'kategori' => $kat,
                ]
            );
        }

        foreach (range(2023, 2028) as $y) {
            for ($i = 1; $i <= 5; $i++) {
                Stage::firstOrCreate(['tahun' => $y, 'urutan' => $i], ['nama' => "Tahap $i", 'is_open' => true]);
            }
        }
    }
}
