<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{BosType, Komponen, Rekening, RekeningPajak, Stage};

/**
 * Master referensi idempoten (aman dijalankan berulang):
 * jenis BOS, komponen, rekening (termasuk 44 kode ARKAS 2025),
 * jenis pajak, dan tahap 2023–2028.
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
            'bosda' => 'BOSP Daerah',
            'silpa' => 'SILPA Tahun Sebelumnya',
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
            // Master ARKAS 2025 (44 kode aktif; 2 menimpa kode lama ke nama standar ARKAS)
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
        ] as [$k, $n, $c]) {
            Rekening::updateOrCreate(['kode' => $k], ['nama' => $n, 'tipe' => 'belanja', 'komponen_id' => $kom[$c]->id]);
        }

        foreach ([
            'ppn' => 'PPN (Pajak Pertambahan Nilai)',
            'pph21' => 'PPh Pasal 21',
            'pph22' => 'PPh Pasal 22',
            'pph23' => 'PPh Pasal 23',
            'pph42' => 'PPh Pasal 4 Ayat 2',
        ] as $k => $n) {
            RekeningPajak::updateOrCreate(['kode' => $k], ['nama' => $n]);
        }

        foreach (range(2023, 2028) as $y) {
            for ($i = 1; $i <= 5; $i++) {
                Stage::firstOrCreate(['tahun' => $y, 'urutan' => $i], ['nama' => "Tahap $i", 'is_open' => true]);
            }
        }
    }
}
